# -*- coding: utf-8 -*-
"""Grain journalier, bornes et tendance amortie. Pas d'appel Prophet."""

from __future__ import annotations

import unittest

import pandas as pd

from prophet_series import (
    ANCHOR_PHI,
    aggregate_daily,
    anchor_target,
    anchored_trend,
    build_offer_profiles,
    build_volume_shares,
    day_of_month_ratios,
    distribute_daily_volume,
    drop_closed_days,
    stored_profile_violates_bounds,
    trend_relative_gap,
    winsorize_daily,
)
from prophet_tuning_core import build_cutoffs, merge_optuna_settings


class TestCutoffLags(unittest.TestCase):
    def test_lags_remplacent_le_pas_horizon(self) -> None:
        end = pd.Timestamp("2026-11-12")
        df = pd.DataFrame({"ds": [end], "y": [1.0]})
        cutoffs = build_cutoffs(df, 90, lags_days=[90, 180, 270, 360])
        expected = [
            (end - pd.Timedelta(days=lag) + pd.Timedelta(days=1)).normalize()
            for lag in (90, 180, 270, 360)
        ]
        self.assertEqual(cutoffs, sorted(expected))

    def test_merge_ecrase_un_vieux_json(self) -> None:
        merged = merge_optuna_settings(
            {
                "seasonality_prior_scale_min": 0.01,
                "seasonality_prior_scale_max": 100.0,
                "changepoint_prior_scale_min": 0.001,
                "changepoint_prior_scale_max": 0.5,
                "n_cutoffs": 3,
                "n_trials": 50,
                "auto_apply_min_mae_improvement_pct": 1,
                "cron_period_days": 1,
            }
        )
        self.assertEqual(merged["seasonality_prior_scale_min"], 1.0)
        self.assertEqual(merged["seasonality_prior_scale_max"], 50.0)
        self.assertEqual(merged["changepoint_prior_scale_min"], 0.005)
        self.assertEqual(merged["changepoint_prior_scale_max"], 0.10)
        self.assertEqual(merged["cutoff_lags_days"], [90, 180, 270, 360])
        self.assertEqual(merged["test_horizon_days"], 90)
        self.assertEqual(merged["n_cutoffs"], 4)
        self.assertEqual(merged["n_trials"], 50)
        self.assertEqual(merged["cron_period_days"], 1)


class TestStoredBounds(unittest.TestCase):
    def test_profil_mort_hors_bornes(self) -> None:
        self.assertTrue(
            stored_profile_violates_bounds(
                {
                    "changepoint_prior_scale": 0.00183,
                    "seasonality_prior_scale": 0.0239,
                    "n_changepoints": 47,
                    "use_french_holidays": True,
                }
            )
        )

    def test_profil_deja_legal(self) -> None:
        self.assertFalse(
            stored_profile_violates_bounds(
                {
                    "changepoint_prior_scale": 0.05,
                    "seasonality_prior_scale": 6.8,
                    "n_changepoints": 25,
                    "use_french_holidays": False,
                }
            )
        )

    def test_feries_absents_comptent_comme_actifs(self) -> None:
        self.assertTrue(
            stored_profile_violates_bounds(
                {
                    "changepoint_prior_scale": 0.05,
                    "seasonality_prior_scale": 6.8,
                    "n_changepoints": 25,
                }
            )
        )


class TestDailySeries(unittest.TestCase):
    def test_agregat_15_min(self) -> None:
        rows = []
        for hour in (9, 10):
            rows.append({"ds": pd.Timestamp(f"2026-01-05 {hour:02d}:00:00"), "y": 10.0})
        rows.append({"ds": pd.Timestamp("2026-01-06 09:00:00"), "y": 4.0})
        out = aggregate_daily(pd.DataFrame(rows))
        self.assertEqual(len(out), 2)
        self.assertEqual(float(out.iloc[0]["y"]), 20.0)

    def test_drop_zero_et_ferie(self) -> None:
        df = pd.DataFrame(
            {
                "ds": pd.to_datetime(["2026-01-01", "2026-01-05", "2026-01-06"]),
                "y": [12.0, 0.0, 8.0],
            }
        )
        kept = drop_closed_days(df)
        self.assertEqual(list(kept["ds"].dt.strftime("%Y-%m-%d")), ["2026-01-06"])

    def test_winsor_autres_annees_puis_repli(self) -> None:
        rows = []
        for year in (2022, 2023, 2024, 2025):
            rows.append({"ds": pd.Timestamp(f"{year}-03-10"), "y": 100.0})
        rows.append({"ds": pd.Timestamp("2026-03-10"), "y": 1000.0})
        out = winsorize_daily(pd.DataFrame(rows))
        capped = float(out.loc[out["ds"] == pd.Timestamp("2026-03-10"), "y"].iloc[0])
        self.assertAlmostEqual(capped, 135.0)

        tiny = pd.DataFrame(
            {
                "ds": pd.to_datetime(["2024-03-10", "2025-03-10", "2026-03-10"]),
                "y": [100.0, 100.0, 1000.0],
            }
        )
        fallback = winsorize_daily(tiny)
        # Moins de 15 points hors année : médiane de toute la strate, puis 1,35×.
        self.assertLess(float(fallback.iloc[-1]["y"]), 1000.0)

    def test_winsor_garde_un_pic_habituel(self) -> None:
        rows = []
        for year in (2022, 2023, 2024, 2025):
            rows.append({"ds": pd.Timestamp(f"{year}-03-10"), "y": 100.0})
            rows.append({"ds": pd.Timestamp(f"{year}-03-28"), "y": 300.0})
        rows.append({"ds": pd.Timestamp("2026-03-10"), "y": 100.0})
        rows.append({"ds": pd.Timestamp("2026-03-28"), "y": 2000.0})
        out = winsorize_daily(pd.DataFrame(rows))
        kept = float(out.loc[out["ds"] == pd.Timestamp("2024-03-28"), "y"].iloc[0])
        spiked = float(out.loc[out["ds"] == pd.Timestamp("2026-03-28"), "y"].iloc[0])
        self.assertAlmostEqual(kept, 300.0)
        self.assertLess(spiked, 2000.0)
        self.assertGreater(spiked, 300.0)

    def test_repli_jour_31_sur_le_cercle(self) -> None:
        rows = []
        for year in (2024, 2025, 2026):
            for day in (1, 15, 30):
                rows.append({"ds": pd.Timestamp(f"{year}-01-{day:02d}"), "y": 100.0 if day == 15 else 400.0})
            rows.append({"ds": pd.Timestamp(f"{year}-03-15"), "y": 100.0})
            rows.append({"ds": pd.Timestamp(f"{year}-03-30"), "y": 400.0})
            rows.append({"ds": pd.Timestamp(f"{year}-03-01"), "y": 400.0})
        rows.append({"ds": pd.Timestamp("2024-01-31"), "y": 400.0})
        rows.append({"ds": pd.Timestamp("2025-01-31"), "y": 400.0})
        ratios = day_of_month_ratios(pd.DataFrame(rows))
        self.assertAlmostEqual(ratios[31], (ratios[30] + ratios[1]) / 2.0)

    def test_profil_plat_non_active(self) -> None:
        rows = []
        day = pd.Timestamp("2024-01-02")
        while len(rows) < 40:
            if day.dayofweek < 5 and day.day != 1:
                rows.append({"ds": day, "y": 100.0})
            day += pd.Timedelta(days=1)
        profiles = build_offer_profiles(pd.DataFrame(rows))
        self.assertFalse(profiles["use_dow"])
        self.assertFalse(profiles["use_month"])

    def test_ventilation_somme(self) -> None:
        slots = ["09:00", "09:15"]
        shares = build_volume_shares(
            pd.DataFrame(
                {
                    "ds": pd.to_datetime(["2026-11-10 09:00:00", "2026-11-10 09:15:00"]),
                    "y": [3.0, 1.0],
                }
            ),
            slots,
        )
        volumes = distribute_daily_volume(100.0, shares[2], slots)
        self.assertEqual(sum(volumes.values()), 100)
        self.assertEqual(volumes["09:00:00"], 75)


class TestDamping(unittest.TestCase):
    def test_continu_a_t0(self) -> None:
        self.assertAlmostEqual(anchored_trend(35.7, 38.5, 0.0), 35.7)

    def test_formule_un_jour(self) -> None:
        expected = 35.7 * ANCHOR_PHI + 38.5 * (1.0 - ANCHOR_PHI)
        self.assertAlmostEqual(anchored_trend(35.7, 38.5, 1.0), expected)

    def test_rappel_sans_seuil(self) -> None:
        loin = anchor_target(35.69, 38.53)
        proche = anchor_target(145.95, 149.47)
        self.assertEqual(loin["branch"], "rappel")
        self.assertAlmostEqual(loin["target"], 38.53)
        self.assertEqual(proche["branch"], "rappel")
        self.assertAlmostEqual(proche["target"], 149.47)

    def test_tunnel(self) -> None:
        crushed = anchored_trend(100.0, 1000.0, 400.0)
        self.assertGreaterEqual(crushed, 60.0)
        self.assertLessEqual(crushed, 150.0)

    def test_ecart_relatif(self) -> None:
        self.assertAlmostEqual(trend_relative_gap(100.0, 97.0), 0.03)
        self.assertEqual(trend_relative_gap(0.0, 0.0), 0.0)


if __name__ == "__main__":
    unittest.main()
