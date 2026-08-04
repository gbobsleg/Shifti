# -*- coding: utf-8 -*-
"""Tests passe 2 — groupes d'offres / profils mixtes.

Couvre :
- allocation mixte sans double comptage + format coverage PHP
- non-régression sans offer_groups (chemin legacy)
- prefer_mixed (pénalité sur sélection membre)
"""

from __future__ import annotations

import unittest
from typing import Any, Dict, List, Optional

from models.data_models import Agent, OfferGroupSpec, Problem
from routers.solver_coverage import solve_schedule


TIMES_4 = ["09:00:00", "09:15:00", "09:30:00", "09:45:00"]
TIMES_2 = ["09:00:00", "09:15:00"]


def _curve(offers: List[str], times: List[str], values: Dict[str, int]) -> Dict[str, Dict[str, int]]:
    out: Dict[str, Dict[str, int]] = {}
    for off in offers:
        v = int(values.get(off, 0))
        out[off] = {t: v for t in times}
    return out


def _coverage_by_offer(result: Dict[str, Any]) -> Dict[str, List[Dict[str, Any]]]:
    return {row["offer"]: row["times"] for row in result["coverage"]}


def _assert_coverage_slot_shape(slot: Dict[str, Any], *, msg: str = "") -> None:
    """Format attendu par le parseur PHP (SchedulesController / reports)."""
    required = {"time", "need", "covered", "shortage", "surplus"}
    missing = required - set(slot.keys())
    assert not missing, f"{msg} clés manquantes: {missing} (slot={slot})"
    assert isinstance(slot["time"], str) and len(slot["time"]) == 8, msg
    for key in ("need", "covered", "shortage", "surplus"):
        assert isinstance(slot[key], int), f"{msg} {key} doit être int, got {type(slot[key])}"


class OfferGroupsAllocationTests(unittest.TestCase):
    """1 mixte + 2 membres, agents mixte-only → alloc sans double comptage."""

    def test_mixed_allocation_no_double_counting_and_coverage_format(self) -> None:
        problem = Problem(
            offers=["TI", "AE", "TI-AE"],
            need_curve=_curve(
                ["TI", "AE", "TI-AE"],
                TIMES_2,
                {"TI": 1, "AE": 1, "TI-AE": 99},  # need mixte ignoré (forcé 0)
            ),
            agents=[
                Agent(
                    id=1,
                    skills=["TI-AE"],
                    availability_start_time="09:00:00",
                    availability_end_time="09:30:00",
                ),
                Agent(
                    id=2,
                    skills=["TI-AE"],
                    availability_start_time="09:00:00",
                    availability_end_time="09:30:00",
                ),
            ],
            offer_groups=[
                OfferGroupSpec(
                    name="G_TI_AE",
                    mixed="TI-AE",
                    members=["TI", "AE"],
                    prefer_mixed=True,
                )
            ],
            workday_start_time="09:00:00",
            workday_end_time="09:30:00",
            enable_am_pm_breaks=False,
            weight_same_offer_windows=0,
            weight_early_end=0,
            weight_equity=0,
            weight_period_equity=0,
        )

        result = solve_schedule(problem)
        self.assertIn(result["status"], ("OPTIMAL", "FEASIBLE"))

        by_offer = _coverage_by_offer(result)
        self.assertEqual(set(by_offer.keys()), {"TI", "AE", "TI-AE"})

        for off, times in by_offer.items():
            self.assertEqual(len(times), 2)
            for slot in times:
                _assert_coverage_slot_shape(slot, msg=f"offer={off}")

        for i, t in enumerate(TIMES_2):
            ti = by_offer["TI"][i]
            ae = by_offer["AE"][i]
            mixed = by_offer["TI-AE"][i]

            self.assertEqual(ti["time"], t)
            self.assertEqual(ae["time"], t)
            self.assertEqual(mixed["time"], t)

            # Mixte : need forcé 0, pas de shortage/surplus
            self.assertEqual(mixed["need"], 0)
            self.assertEqual(mixed["shortage"], 0)
            self.assertEqual(mixed["surplus"], 0)

            # Membres : couverture effective (mono+alloc), ici mono=0
            self.assertEqual(ti["need"], 1)
            self.assertEqual(ae["need"], 1)
            self.assertEqual(ti["shortage"], 0)
            self.assertEqual(ae["shortage"], 0)

            # Pas de double comptage : somme des couvertures membres == capacité mixte
            mixed_capacity = mixed["covered"]
            self.assertEqual(mixed_capacity, 2, "2 agents connectés en TI-AE")
            self.assertEqual(
                ti["covered"] + ae["covered"],
                mixed_capacity,
                "Σ covered membres doit égaler la capacité mixte (pas de +1/+1 magique)",
            )
            self.assertLessEqual(ti["covered"] + ae["covered"], mixed_capacity)

        # Planning : uniquement du mixte (seule skill)
        work_offers = {s["offer"] for s in result["schedule"] if s["label"] == "WORK"}
        self.assertEqual(work_offers, {"TI-AE"})


class OfferGroupsLegacyRegressionTests(unittest.TestCase):
    """Payload sans offer_groups → comportement legacy figé."""

    LEGACY_PAYLOAD: Dict[str, Any] = {
        "offers": ["A"],
        "need_curve": {t: 1 for t in TIMES_4},  # placeholder, rebuilt below
        "agents": [
            {
                "id": 1,
                "skills": ["A"],
                "availability_start_time": "09:00:00",
                "availability_end_time": "10:00:00",
            }
        ],
        "workday_start_time": "09:00:00",
        "workday_end_time": "10:00:00",
        "enable_am_pm_breaks": False,
        "weight_same_offer_windows": 0,
        "weight_early_end": 0,
        "weight_equity": 0,
        "weight_period_equity": 0,
        "weight_shortage": 1000,
        "weight_surplus": 1,
    }

    # Valeurs golden du chemin legacy (obj phase 2 avec shortage/surplus figés à 0)
    EXPECTED_OBJECTIVE = 0.0
    EXPECTED_COVERAGE_A = [
        {"time": t, "need": 1, "covered": 1, "shortage": 0, "surplus": 0}
        for t in TIMES_4
    ]

    def _build_legacy_problem(self) -> Problem:
        payload = dict(self.LEGACY_PAYLOAD)
        payload["need_curve"] = {"A": {t: 1 for t in TIMES_4}}
        # Aucune clé offer_groups dans le dict brut
        self.assertNotIn("offer_groups", payload)
        problem = Problem(**payload)
        self.assertIsNone(problem.offer_groups)
        return problem

    def test_legacy_without_offer_groups_bit_identical(self) -> None:
        problem = self._build_legacy_problem()
        result = solve_schedule(problem)

        self.assertIn(result["status"], ("OPTIMAL", "FEASIBLE"))
        self.assertEqual(result["solver"]["objective_value"], self.EXPECTED_OBJECTIVE)

        by_offer = _coverage_by_offer(result)
        self.assertEqual(list(by_offer.keys()), ["A"])
        self.assertEqual(by_offer["A"], self.EXPECTED_COVERAGE_A)

        # Re-run : déterminisme bit à bit (même obj + même shortage/surplus)
        result2 = solve_schedule(problem)
        self.assertEqual(result2["solver"]["objective_value"], result["solver"]["objective_value"])
        self.assertEqual(result2["coverage"], result["coverage"])

    def test_legacy_understaffing_shortage_exact(self) -> None:
        """Besoin 2, un seul agent → shortage=1, surplus=0 chaque créneau (legacy)."""
        payload = {
            "offers": ["A"],
            "need_curve": {"A": {t: 2 for t in TIMES_2}},
            "agents": [
                {
                    "id": 1,
                    "skills": ["A"],
                    "availability_start_time": "09:00:00",
                    "availability_end_time": "09:30:00",
                }
            ],
            "workday_start_time": "09:00:00",
            "workday_end_time": "09:30:00",
            "enable_am_pm_breaks": False,
            "weight_same_offer_windows": 0,
            "weight_early_end": 0,
            "weight_equity": 0,
            "weight_period_equity": 0,
            "weight_shortage": 1000,
            "weight_surplus": 1,
        }
        self.assertNotIn("offer_groups", payload)
        problem = Problem(**payload)
        result = solve_schedule(problem)
        self.assertIn(result["status"], ("OPTIMAL", "FEASIBLE"))

        # Phase 1 minimise shortage (=2 slots * 1) puis surplus (0) → obj phase2 = 2000
        self.assertEqual(result["solver"]["objective_value"], 2000.0)

        expected = [
            {"time": t, "need": 2, "covered": 1, "shortage": 1, "surplus": 0}
            for t in TIMES_2
        ]
        self.assertEqual(_coverage_by_offer(result)["A"], expected)


class OfferGroupsPreferMixedTests(unittest.TestCase):
    """Agent multi-profil → mixte prioritaire si prefer_mixed + poids élevé."""

    def test_prefer_mixed_selects_mixed_over_member(self) -> None:
        problem = Problem(
            offers=["TI", "AE", "TI-AE"],
            need_curve=_curve(
                ["TI", "AE", "TI-AE"],
                TIMES_4,
                {"TI": 1, "AE": 1, "TI-AE": 0},
            ),
            agents=[
                Agent(
                    id=1,
                    skills=["TI", "AE", "TI-AE"],
                    availability_start_time="09:00:00",
                    availability_end_time="10:00:00",
                )
            ],
            offer_groups=[
                OfferGroupSpec(
                    name="G_TI_AE",
                    mixed="TI-AE",
                    members=["TI", "AE"],
                    prefer_mixed=True,
                )
            ],
            workday_start_time="09:00:00",
            workday_end_time="10:00:00",
            enable_am_pm_breaks=False,
            weight_prefer_mixed=500,
            weight_same_offer_windows=0,
            weight_early_end=0,
            weight_equity=0,
            weight_period_equity=0,
        )

        result = solve_schedule(problem)
        self.assertIn(result["status"], ("OPTIMAL", "FEASIBLE"))

        work_segments = [s for s in result["schedule"] if s["label"] == "WORK"]
        self.assertTrue(work_segments, "attendu au moins un segment WORK")
        work_offers = {s["offer"] for s in work_segments}
        self.assertEqual(
            work_offers,
            {"TI-AE"},
            "prefer_mixed doit pousser l'agent vers le profil mixte plutôt qu'un membre",
        )

        # Un seul agent mixte ne peut couvrir qu'une capacité totale de 1
        # répartie entre TI et AE (pas 1+1).
        by_offer = _coverage_by_offer(result)
        for i, _t in enumerate(TIMES_4):
            ti_c = by_offer["TI"][i]["covered"]
            ae_c = by_offer["AE"][i]["covered"]
            mixed_c = by_offer["TI-AE"][i]["covered"]
            self.assertEqual(mixed_c, 1)
            self.assertEqual(ti_c + ae_c, 1)


if __name__ == "__main__":
    unittest.main()
