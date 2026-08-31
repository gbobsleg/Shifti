# -*- coding: utf-8 -*-
"""Tests unitaires WAPE (pas d'appel Prophet)."""

from __future__ import annotations

import unittest

from prophet_tuning_core import (
    clamp_tunable_for_search_space,
    improvement_pct,
    should_auto_apply,
    wape_percent,
)


class TestWapePercent(unittest.TestCase):
    def test_ratio_en_pourcent(self) -> None:
        self.assertEqual(wape_percent(20.0, 100.0), 20.0)

    def test_arrondi(self) -> None:
        self.assertEqual(wape_percent(1.0, 3.0), 33.33)

    def test_refus_denominateur_nul(self) -> None:
        with self.assertRaises(ValueError):
            wape_percent(1.0, 0.0)


class TestImprovementAndAutoApply(unittest.TestCase):
    def test_improvement_pct(self) -> None:
        self.assertEqual(improvement_pct(40.0, 36.0), 10.0)
        self.assertIsNone(improvement_pct(0.0, 1.0))

    def test_auto_apply_off(self) -> None:
        self.assertFalse(
            should_auto_apply(40.0, 30.0, {"auto_apply": False, "auto_apply_min_mae_improvement_pct": 5})
        )

    def test_auto_apply_wape_seuil_5(self) -> None:
        cfg = {"auto_apply": True, "auto_apply_min_mae_improvement_pct": 5}
        self.assertTrue(should_auto_apply(40.0, 37.9, cfg))
        self.assertFalse(should_auto_apply(40.0, 38.1, cfg))


class TestClampTunable(unittest.TestCase):
    def test_clamp_dans_les_bornes(self) -> None:
        cfg = {
            "changepoint_prior_scale_min": 0.001,
            "changepoint_prior_scale_max": 0.5,
            "seasonality_prior_scale_min": 0.01,
            "seasonality_prior_scale_max": 100.0,
            "n_changepoints_min": 10,
            "n_changepoints_max": 50,
            "monthly_fourier_order_min": 3,
            "monthly_fourier_order_max": 10,
        }
        out = clamp_tunable_for_search_space(
            {
                "changepoint_prior_scale": 0.05,
                "seasonality_prior_scale": 10.0,
                "n_changepoints": 25,
                "monthly_fourier_order": 5,
            },
            cfg,
        )
        self.assertEqual(out["n_changepoints"], 25)
        self.assertEqual(out["monthly_fourier_order"], 5)

    def test_clamp_hors_bornes(self) -> None:
        cfg = {
            "changepoint_prior_scale_min": 0.001,
            "changepoint_prior_scale_max": 0.5,
            "seasonality_prior_scale_min": 0.01,
            "seasonality_prior_scale_max": 100.0,
            "n_changepoints_min": 10,
            "n_changepoints_max": 50,
            "monthly_fourier_order_min": 3,
            "monthly_fourier_order_max": 10,
        }
        out = clamp_tunable_for_search_space(
            {
                "changepoint_prior_scale": 9.0,
                "seasonality_prior_scale": 0.001,
                "n_changepoints": 3,
                "monthly_fourier_order": 99,
            },
            cfg,
        )
        self.assertEqual(out["changepoint_prior_scale"], 0.5)
        self.assertEqual(out["seasonality_prior_scale"], 0.01)
        self.assertEqual(out["n_changepoints"], 10)
        self.assertEqual(out["monthly_fourier_order"], 10)

    def test_clamp_incomplet(self) -> None:
        self.assertIsNone(clamp_tunable_for_search_space({}, {}))


class TestEnqueueCountsInNTrials(unittest.TestCase):
    def test_un_enqueue_plus_n_trials_2_egale_2(self) -> None:
        import optuna

        def objective(trial: optuna.Trial) -> float:
            return float(trial.suggest_float("x", 0.0, 1.0))

        study = optuna.create_study()
        study.enqueue_trial({"x": 0.5})
        study.optimize(objective, n_trials=2, show_progress_bar=False)
        self.assertEqual(len(study.trials), 2)
        self.assertAlmostEqual(study.trials[0].params["x"], 0.5)


if __name__ == "__main__":
    unittest.main()
