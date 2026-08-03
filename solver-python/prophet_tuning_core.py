# -*- coding: utf-8 -*-
"""
Cœur Optuna : backtest walk-forward, baseline, optimisation, persistance résultats.
"""

from __future__ import annotations

import json
from datetime import datetime
from types import SimpleNamespace
from typing import Any, Callable, Dict, List, Optional, Tuple

import optuna
import pandas as pd

from prophet_common import load_historical_data, train_prophet_model

# Optuna est bruyant par défaut
optuna.logging.set_verbosity(optuna.logging.WARNING)

DEFAULT_OPTUNA_SETTINGS: Dict[str, Any] = {
    "cron_enabled": False,
    "cron_period_days": 7,
    "test_horizon_days": 14,
    "n_cutoffs": 3,
    "n_trials": 50,
    "min_history_days": 90,
    "auto_apply": False,
    "auto_apply_min_mae_improvement_pct": 5,
    "changepoint_prior_scale_min": 0.001,
    "changepoint_prior_scale_max": 0.5,
    "seasonality_prior_scale_min": 0.01,
    "seasonality_prior_scale_max": 100.0,
    "n_changepoints_min": 10,
    "n_changepoints_max": 50,
    "monthly_fourier_order_min": 3,
    "monthly_fourier_order_max": 10,
}

# Params Prophet toujours imposés V1 (non tunés, indépendants de l'historique)
FIXED_PROPHET_FLAGS: Dict[str, Any] = {
    "seasonality_mode": "multiplicative",
    "weekly_seasonality": True,
    "daily_seasonality": True,
    "growth": "linear",
    "changepoint_range": 0.8,
    "use_french_holidays": True,
}

# Saisonnalités adaptées automatiquement selon la longueur d'historique utile
YEARLY_MIN_HISTORY_DAYS = 365
MONTHLY_MIN_HISTORY_DAYS = 90

TUNABLE_KEYS = (
    "changepoint_prior_scale",
    "seasonality_prior_scale",
    "n_changepoints",
    "monthly_fourier_order",
)


def parse_json_field(value: Any) -> Optional[Any]:
    """Décode un champ JSON MySQL (str / dict / list / None)."""
    if value is None:
        return None
    if isinstance(value, (dict, list)):
        return value
    if isinstance(value, (bytes, bytearray)):
        value = value.decode("utf-8")
    if isinstance(value, str):
        if value.strip() == "":
            return None
        return json.loads(value)
    return value


def merge_optuna_settings(raw: Optional[dict]) -> Dict[str, Any]:
    """Fusionne defaults + snapshot job / WFM."""
    merged = dict(DEFAULT_OPTUNA_SETTINGS)
    if isinstance(raw, dict):
        merged.update({k: v for k, v in raw.items() if v is not None})
    # V1 : n_cutoffs toujours 3
    merged["n_cutoffs"] = 3
    return merged


def history_span_days(df: pd.DataFrame) -> int:
    """Nombre de jours calendaires couverts par l'historique chargé."""
    if df is None or df.empty:
        return 0
    return int((df["ds"].max() - df["ds"].min()).days)


def seasonality_flags_for_history(history_span_days: int) -> Dict[str, bool]:
    """
    Active yearly / monthly seulement si l'historique est assez long.
    Weekly / daily restent toujours ON (via FIXED_PROPHET_FLAGS).
    """
    span = int(history_span_days or 0)
    return {
        "yearly_seasonality": span >= YEARLY_MIN_HISTORY_DAYS,
        "monthly_seasonality": span >= MONTHLY_MIN_HISTORY_DAYS,
    }


def describe_seasonality_adaptation(history_span_days: int) -> Dict[str, Any]:
    """Métadonnées UI / scores : quelles saisonnalités ont été adaptées."""
    flags = seasonality_flags_for_history(history_span_days)
    span = int(history_span_days or 0)
    notes: List[str] = []
    if not flags["yearly_seasonality"]:
        notes.append(
            f"saisonnalité annuelle désactivée (historique {span} j < {YEARLY_MIN_HISTORY_DAYS} j)"
        )
    else:
        notes.append(
            f"saisonnalité annuelle activée (historique {span} j ≥ {YEARLY_MIN_HISTORY_DAYS} j)"
        )
    if not flags["monthly_seasonality"]:
        notes.append(
            f"saisonnalité mensuelle désactivée (historique {span} j < {MONTHLY_MIN_HISTORY_DAYS} j)"
        )
    else:
        notes.append(
            f"saisonnalité mensuelle activée (historique {span} j ≥ {MONTHLY_MIN_HISTORY_DAYS} j)"
        )
    notes.append("saisonnalités hebdomadaire et journalière toujours activées")
    return {
        "history_span_days": span,
        "yearly_min_days": YEARLY_MIN_HISTORY_DAYS,
        "monthly_min_days": MONTHLY_MIN_HISTORY_DAYS,
        "yearly_seasonality": flags["yearly_seasonality"],
        "monthly_seasonality": flags["monthly_seasonality"],
        "weekly_seasonality": True,
        "daily_seasonality": True,
        "notes": notes,
    }


def apply_fixed_prophet_flags(
    params: Dict[str, Any],
    history_span_days: Optional[int] = None,
) -> Dict[str, Any]:
    """
    Impose les flags figés V1 + yearly/monthly selon l'historique.
    Si history_span_days est None, yearly/monthly restent à True (comportement
    legacy / apply PHP ne doit pas passer par ce chemin sans draft).
    """
    out = dict(params) if params else {}
    out.update(FIXED_PROPHET_FLAGS)
    if history_span_days is None:
        out.setdefault("yearly_seasonality", True)
        out.setdefault("monthly_seasonality", True)
    else:
        out.update(seasonality_flags_for_history(history_span_days))
    return out


def build_prophet_params_from_offer(
    offer_profile: Optional[dict],
    tunable: Optional[dict] = None,
    history_span_days: Optional[int] = None,
) -> Dict[str, Any]:
    """
    Construit un dict params Prophet complet :
    profil offre + overrides tunables + flags figés (+ yearly/monthly selon historique).
    """
    base = dict(offer_profile) if offer_profile else {}
    if tunable:
        for key in TUNABLE_KEYS:
            if key in tunable and tunable[key] is not None:
                base[key] = tunable[key]

    # Defaults sensés si absents du profil
    base.setdefault("changepoint_prior_scale", 0.05)
    base.setdefault("seasonality_prior_scale", 10.0)
    base.setdefault("n_changepoints", 25)
    base.setdefault("monthly_fourier_order", 5)
    base.setdefault("history_start_date", None)
    base.setdefault("history_end_date", None)
    base.setdefault("custom_holidays", None)

    return apply_fixed_prophet_flags(base, history_span_days=history_span_days)


def params_to_settings(params: Dict[str, Any]) -> SimpleNamespace:
    """Duck-typing pour train_prophet_model."""
    return SimpleNamespace(**params)


def build_cutoffs(
    df: pd.DataFrame,
    horizon_days: int,
    n_cutoffs: int = 3,
) -> List[pd.Timestamp]:
    """
    Cutoffs walk-forward espacés de horizon_days depuis la fin d'historique.
    Pour i=1..n : cutoff_i = end - i*horizon + 1 jour
    Train: ds < cutoff ; Test: cutoff <= ds < cutoff + horizon
    """
    if df.empty:
        raise ValueError("DataFrame historique vide")

    end = pd.Timestamp(df["ds"].max()).normalize()
    cutoffs: List[pd.Timestamp] = []
    for i in range(1, n_cutoffs + 1):
        cutoff = end - pd.Timedelta(days=horizon_days * i) + pd.Timedelta(days=1)
        cutoffs.append(cutoff.normalize())

    return sorted(cutoffs)


def _window_scores(
    df: pd.DataFrame,
    params: Dict[str, Any],
    cutoff: pd.Timestamp,
    horizon_days: int,
) -> Optional[Tuple[float, float]]:
    """
    Train avant cutoff, score sur [cutoff, cutoff+horizon).
    Returns (mae, mape) ou None si fenêtre invalide.
    """
    cutoff_ts = pd.Timestamp(cutoff)
    horizon_end = cutoff_ts + pd.Timedelta(days=horizon_days)

    train = df[df["ds"] < cutoff_ts][["ds", "y"]].copy()
    test = df[(df["ds"] >= cutoff_ts) & (df["ds"] < horizon_end)][["ds", "y"]].copy()

    if len(train) < 96:  # < 1 jour de slots 15min
        return None
    if len(test) == 0:
        return None

    model = None
    try:
        model, _ = train_prophet_model(
            train,
            params_to_settings(params),
            verbose=False,
        )
        forecast = model.predict(test[["ds"]].copy())
        comparison = pd.merge(test, forecast[["ds", "yhat"]], on="ds").dropna()
        if comparison.empty:
            return None

        y_true = comparison["y"]
        y_pred = comparison["yhat"].clip(lower=0)
        mae = float(abs(y_true - y_pred).mean())

        mask = y_true >= 3
        if mask.sum() > 0:
            mape = float((abs((y_true[mask] - y_pred[mask]) / y_true[mask])).mean() * 100)
        else:
            mape = 0.0

        return mae, mape
    finally:
        del model


def evaluate_params_walk_forward(
    df: pd.DataFrame,
    params: Dict[str, Any],
    horizon_days: int,
    n_cutoffs: int = 3,
) -> Dict[str, float]:
    """
    Évalue des params sur N cutoffs. Objectif = MAE volume moyen.
    """
    cutoffs = build_cutoffs(df, horizon_days, n_cutoffs)
    maes: List[float] = []
    mapes: List[float] = []

    for cutoff in cutoffs:
        result = _window_scores(df, params, cutoff, horizon_days)
        if result is None:
            continue
        mae, mape = result
        maes.append(mae)
        mapes.append(mape)

    if not maes:
        raise ValueError(
            "Aucune fenêtre de backtest valide (historique insuffisant pour "
            f"horizon={horizon_days}j × {n_cutoffs} cutoffs)"
        )

    return {
        "mae_volume": round(float(sum(maes) / len(maes)), 4),
        "mape_volume": round(float(sum(mapes) / len(mapes)), 2),
        "n_cutoffs": float(len(maes)),
        "horizon_days": float(horizon_days),
    }


def scores_for_storage(scores: Dict[str, float], horizon_days: int, n_cutoffs: int) -> Dict[str, Any]:
    """Shape JSON scores du plan."""
    return {
        "mae_volume": round(float(scores["mae_volume"]), 4),
        "mape_volume": round(float(scores["mape_volume"]), 2),
        "n_cutoffs": int(scores.get("n_cutoffs", n_cutoffs)),
        "horizon_days": int(horizon_days),
    }


ProgressCallback = Callable[[int, int, Optional[float]], None]


def run_optuna_search(
    df: pd.DataFrame,
    offer_profile: Dict[str, Any],
    optuna_cfg: Dict[str, Any],
    progress_callback: Optional[ProgressCallback] = None,
) -> Tuple[Dict[str, Any], Dict[str, Any], optuna.Study]:
    """
    Lance Optuna (TPE). Retourne (best_params, best_scores, study).
    L'appelant DOIT supprimer la study et appeler gc.collect().
    """
    horizon = int(optuna_cfg["test_horizon_days"])
    n_cutoffs = int(optuna_cfg.get("n_cutoffs", 3))
    n_trials = int(optuna_cfg["n_trials"])
    span_days = history_span_days(df)

    cps_min = float(optuna_cfg["changepoint_prior_scale_min"])
    cps_max = float(optuna_cfg["changepoint_prior_scale_max"])
    sps_min = float(optuna_cfg["seasonality_prior_scale_min"])
    sps_max = float(optuna_cfg["seasonality_prior_scale_max"])
    ncp_min = int(optuna_cfg["n_changepoints_min"])
    ncp_max = int(optuna_cfg["n_changepoints_max"])
    mfo_min = int(optuna_cfg["monthly_fourier_order_min"])
    mfo_max = int(optuna_cfg["monthly_fourier_order_max"])

    def objective(trial: optuna.Trial) -> float:
        tunable = {
            "changepoint_prior_scale": trial.suggest_float(
                "changepoint_prior_scale", cps_min, cps_max, log=True
            ),
            "seasonality_prior_scale": trial.suggest_float(
                "seasonality_prior_scale", sps_min, sps_max, log=True
            ),
            "n_changepoints": trial.suggest_int("n_changepoints", ncp_min, ncp_max),
            "monthly_fourier_order": trial.suggest_int(
                "monthly_fourier_order", mfo_min, mfo_max
            ),
        }
        params = build_prophet_params_from_offer(
            offer_profile, tunable, history_span_days=span_days
        )
        scores = evaluate_params_walk_forward(df, params, horizon, n_cutoffs)
        trial.set_user_attr("mape_volume", scores["mape_volume"])
        trial.set_user_attr("mae_volume", scores["mae_volume"])
        return float(scores["mae_volume"])

    study = optuna.create_study(
        direction="minimize",
        sampler=optuna.samplers.TPESampler(seed=42),
        study_name=f"prophet_tune_{datetime.utcnow().strftime('%Y%m%d%H%M%S')}",
    )

    def _callback(study: optuna.Study, trial: optuna.trial.FrozenTrial) -> None:
        if progress_callback is None:
            return
        best_mae = None
        if study.best_trial is not None:
            best_mae = float(study.best_value)
        progress_callback(len(study.trials), n_trials, best_mae)

    if progress_callback:
        progress_callback(0, n_trials, None)

    study.optimize(objective, n_trials=n_trials, callbacks=[_callback], show_progress_bar=False)

    best_tunable = dict(study.best_params)
    best_params = build_prophet_params_from_offer(
        offer_profile, best_tunable, history_span_days=span_days
    )
    best_scores = {
        "mae_volume": float(study.best_value),
        "mape_volume": float(study.best_trial.user_attrs.get("mape_volume", 0.0)),
        "n_cutoffs": float(n_cutoffs),
        "horizon_days": float(horizon),
    }

    return best_params, best_scores, study


def should_auto_apply(
    baseline_mae: float,
    proposed_mae: float,
    optuna_cfg: Dict[str, Any],
) -> bool:
    """Auto-apply si activé et amélioration MAE >= seuil %."""
    if not optuna_cfg.get("auto_apply"):
        return False
    if baseline_mae <= 0:
        return proposed_mae < baseline_mae
    threshold_pct = float(optuna_cfg.get("auto_apply_min_mae_improvement_pct", 5))
    max_allowed = baseline_mae * (1.0 - threshold_pct / 100.0)
    return proposed_mae <= max_allowed


def load_offer_history_df(offer_id: int, offer_profile: Dict[str, Any]) -> pd.DataFrame:
    """Charge l'historique selon history_* du profil offre."""
    return load_historical_data(
        offer_id,
        start_date=offer_profile.get("history_start_date") or None,
        end_date=offer_profile.get("history_end_date") or None,
    )


def assert_min_history(df: pd.DataFrame, min_history_days: int) -> None:
    span_days = history_span_days(df)
    if span_days < min_history_days:
        raise ValueError(
            f"Historique trop court: {span_days} jour(s) "
            f"(minimum requis: {min_history_days})"
        )
