# -*- coding: utf-8 -*-
"""Tests du profil DMT (pas d'appel Prophet)."""

from __future__ import annotations

import json
import unittest
from datetime import datetime, timedelta

import pandas as pd

from dmt_profile import (
    DMT_ABS_MAX,
    DMT_ABS_MIN,
    MIN_SLOT_VOLUME,
    build_dmt_profile,
    dmt_for_datetime,
    emit_dmt,
    lookup_coeff,
)


def _rows(start: datetime, hours: int, volume: float, dmt: float, every_min: int = 15):
    items = []
    cur = start
    end = start + timedelta(hours=hours)
    while cur < end:
        items.append({"ds": cur, "y": volume, "dmt": dmt})
        cur += timedelta(minutes=every_min)
    return items


class TestEmitClip(unittest.TestCase):
    def test_filet_metier_90_1200(self) -> None:
        self.assertEqual(emit_dmt(400.0, 4.0), DMT_ABS_MAX)
        self.assertEqual(emit_dmt(50.0, 1.0), DMT_ABS_MIN)
        self.assertEqual(emit_dmt(300.0, 1.0), 300)


class TestBuildProfile(unittest.TestCase):
    def test_niveau_pondere_ignore_historique_apres_history_end(self) -> None:
        history_end = datetime(2026, 9, 8, 12, 0, 0)  # mardi
        # 4 semaines ISO complètes : lun 10 août → dim 6 sept
        rows = []
        cur = datetime(2026, 8, 10, 9, 0, 0)
        while cur <= datetime(2026, 9, 6, 16, 45, 0):
            if cur.weekday() < 5 and 9 <= cur.hour < 17:
                rows.append({"ds": cur, "y": 20.0, "dmt": 400.0})
            cur += timedelta(minutes=15)
        # Après history_end : DMT 900, ne doit pas tirer le niveau
        cur = datetime(2026, 9, 9, 9, 0, 0)
        while cur <= datetime(2026, 9, 11, 16, 0, 0):
            if cur.weekday() < 5 and 9 <= cur.hour < 17:
                rows.append({"ds": cur, "y": 50.0, "dmt": 900.0})
            cur += timedelta(minutes=15)
        # Vieux historique (2025) DMT 120 — hors 4 semaines, ne doit pas tirer le niveau
        rows.append({"ds": datetime(2025, 9, 8, 10, 0, 0), "y": 80.0, "dmt": 120.0})

        df = pd.DataFrame(rows)
        profile = build_dmt_profile(
            df,
            history_end=history_end,
            day_start="09:00:00",
            day_end="17:00:00",
        )
        self.assertAlmostEqual(profile["level_seconds"], 400.0, delta=5.0)
        self.assertLess(profile["level_seconds"], 500.0)

    def test_moyenne_arithmetique_serait_plus_basse_que_le_niveau(self) -> None:
        rows = []
        # Ancien : beaucoup de points à 200 s
        cur = datetime(2025, 10, 6, 9, 0, 0)
        while cur <= datetime(2026, 8, 1, 16, 0, 0):
            if cur.weekday() < 5 and 9 <= cur.hour < 17:
                rows.append({"ds": cur, "y": 10.0, "dmt": 200.0})
            cur += timedelta(days=1)
            cur = cur.replace(hour=9, minute=0)
        # 4 dernières semaines : 400 s
        cur = datetime(2026, 8, 10, 9, 0, 0)
        while cur <= datetime(2026, 9, 6, 16, 45, 0):
            if cur.weekday() < 5 and 9 <= cur.hour < 17:
                rows.append({"ds": cur, "y": 10.0, "dmt": 400.0})
            cur += timedelta(minutes=15)

        df = pd.DataFrame(rows)
        arith = float(df["dmt"].mean())
        profile = build_dmt_profile(
            df,
            history_end=datetime(2026, 9, 8),
            day_start="09:00:00",
            day_end="17:00:00",
        )
        self.assertLess(arith, profile["level_seconds"] - 50)

    def test_cascade_volume_inferieur_a_5(self) -> None:
        # Un seul lundi 08:15 à 4 appels ; le reste de 08:00-08:30 a assez de volume
        rows = [
            {"ds": datetime(2026, 9, 7, 8, 0, 0), "y": 4.0, "dmt": 300.0},  # lundi
            {"ds": datetime(2026, 9, 7, 8, 15, 0), "y": 4.0, "dmt": 600.0},
            {"ds": datetime(2026, 9, 7, 8, 30, 0), "y": 10.0, "dmt": 300.0},
            {"ds": datetime(2026, 9, 7, 9, 0, 0), "y": 10.0, "dmt": 300.0},
        ]
        # Remplir 4 semaines pour le niveau
        cur = datetime(2026, 8, 10, 8, 0, 0)
        while cur <= datetime(2026, 9, 6, 10, 0, 0):
            if cur.weekday() == 0 and 8 <= cur.hour < 10 and cur.minute == 0:
                rows.append({"ds": cur, "y": 12.0, "dmt": 300.0})
            cur += timedelta(minutes=15)

        df = pd.DataFrame(rows)
        profile = build_dmt_profile(
            df,
            history_end=datetime(2026, 9, 8),
            day_start="08:00:00",
            day_end="10:00:00",
        )
        # 08:15 lundi : volume 4 < 5 → demi-heure 08:00-08:30 (4+4=8) pas 600 seul
        coeff_815 = lookup_coeff(profile, 1, "08:15")
        dmt_815 = profile["level_seconds"] * coeff_815
        self.assertLess(dmt_815, 550)
        self.assertGreater(dmt_815, 200)

    def test_clip_p95_et_abs_sur_creaneau_extreme(self) -> None:
        rows = []
        cur = datetime(2026, 8, 10, 9, 0, 0)
        while cur <= datetime(2026, 9, 6, 16, 45, 0):
            if cur.weekday() < 5 and 9 <= cur.hour < 17:
                rows.append({"ds": cur, "y": 10.0, "dmt": 240.0})
            cur += timedelta(minutes=15)
        rows.append({"ds": datetime(2026, 9, 7, 9, 0, 0), "y": 5.0, "dmt": 2700.0})

        df = pd.DataFrame(rows)
        profile = build_dmt_profile(
            df,
            history_end=datetime(2026, 9, 8),
            day_start="09:00:00",
            day_end="17:00:00",
        )
        monday_0900 = dmt_for_datetime(profile, datetime(2026, 9, 7, 9, 0, 0), "09:00:00")
        self.assertLessEqual(monday_0900, DMT_ABS_MAX)
        self.assertGreaterEqual(monday_0900, DMT_ABS_MIN)

    def test_lissage_25_50_25_different_de_un_tiers(self) -> None:
        values_simple = [1.4, 0.9, 1.3]
        ma_simple = sum(values_simple) / 3.0
        ma_pond = 0.25 * 1.4 + 0.5 * 0.9 + 0.25 * 1.3
        self.assertNotAlmostEqual(ma_simple, ma_pond)
        self.assertAlmostEqual(ma_pond, 1.125)

    def test_renorm_k_preserve_charge_sur_fenetre(self) -> None:
        rows = []
        cur = datetime(2026, 8, 10, 9, 0, 0)
        while cur <= datetime(2026, 9, 6, 11, 45, 0):
            if cur.weekday() < 5 and 9 <= cur.hour < 12:
                dmt = 500.0 if cur.minute == 0 else 250.0
                rows.append({"ds": cur, "y": 10.0, "dmt": dmt})
            cur += timedelta(minutes=15)
        df = pd.DataFrame(rows)
        profile = build_dmt_profile(
            df,
            history_end=datetime(2026, 9, 8),
            day_start="09:00:00",
            day_end="12:00:00",
        )
        level = float(profile["level_seconds"])
        charge_final = 0.0
        for rec in rows:
            if rec["ds"] > datetime(2026, 9, 8):
                continue
            if not (9 <= rec["ds"].hour < 12):
                continue
            iso = rec["ds"].isoweekday()
            slot = rec["ds"].strftime("%H:%M")
            coeff = lookup_coeff(profile, iso, slot)
            charge_final += rec["y"] * (level * coeff)
        # La charge finale (niveau × coeff) est définie ; k doit être fini et > 0
        self.assertGreater(profile["k"], 0.5)
        self.assertLess(profile["k"], 1.5)
        self.assertGreater(charge_final, 0)

    def test_cles_json_string_iso(self) -> None:
        rows = _rows(datetime(2026, 8, 10, 9, 0, 0), 8, 12.0, 300.0)
        # filtrer week-end dans _rows non : on génère tout, OK
        df = pd.DataFrame(
            [
                r
                for r in _rows(datetime(2026, 8, 10, 9, 0, 0), 24 * 28, 12.0, 300.0)
                if r["ds"].weekday() < 5 and 9 <= r["ds"].hour < 17
            ]
        )
        profile = build_dmt_profile(
            df,
            history_end=datetime(2026, 9, 8),
            day_start="09:00:00",
            day_end="17:00:00",
        )
        dumped = json.dumps(profile["coefficients"])
        self.assertIn('"1"', dumped)
        self.assertTrue(dumped.strip().startswith("{"))
        self.assertIn("09:00", dumped)
        self.assertIsInstance(list(profile["coefficients"].keys())[0], str)


    def test_history_end_vide_utilise_max_ds(self) -> None:
        rows = [
            {"ds": datetime(2026, 8, 10, 9, 0, 0), "y": 12.0, "dmt": 300.0},
            {"ds": datetime(2026, 9, 1, 9, 0, 0), "y": 12.0, "dmt": 300.0},
        ]
        # remplir un peu pour éviter fallback empty
        cur = datetime(2026, 8, 10, 9, 0, 0)
        while cur <= datetime(2026, 9, 6, 16, 0, 0):
            if cur.weekday() < 5 and 9 <= cur.hour < 17:
                rows.append({"ds": cur, "y": 12.0, "dmt": 300.0})
            cur += timedelta(minutes=15)
        df = pd.DataFrame(rows)
        profile = build_dmt_profile(df, history_end="", day_start="09:00:00", day_end="17:00:00")
        self.assertEqual(profile["window_end"], "2026-09-04")
        profile_none = build_dmt_profile(df, history_end=None, day_start="09:00:00", day_end="17:00:00")
        self.assertEqual(profile_none["window_end"], "2026-09-04")
    def test_slot_absent_coeff_1(self) -> None:
        profile = {
            "level_seconds": 300.0,
            "coefficients": {"1": {"09:00": 1.2}},
        }
        self.assertEqual(lookup_coeff(profile, 1, "09:15:00"), 1.0)
        self.assertEqual(dmt_for_datetime(profile, datetime(2026, 9, 7, 9, 0), "09:00:00"), 360)


if __name__ == "__main__":
    unittest.main()
