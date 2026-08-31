# -*- coding: utf-8 -*-
"""
Cœur Optuna : backtest walk-forward, baseline, optimisation, persistance résultats.
"""

from __future__ import annotations

import json
from datetime import datetime, time as dt_time
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


def wape_percent(abs_err_sum: float, y_sum: float) -> float:
    """WAPE en % : 100 * sum(|e|) / sum(y). Refus si dénominateur nul."""
    if y_sum <= 0:
        raise ValueError(
            "Somme des volumes réels nulle sur les fenêtres de test — WAPE indéfini"
        )
    return round(100.0 * float(abs_err_sum) / float(y_sum), 2)


def _open_hours_mask(
    ds: pd.Series,
    open_start: dt_time,
    open_end: dt_time,
) -> pd.Series:
    slot_times = ds.dt.time
    return (slot_times >= open_start) & (slot_times < open_end)


def _window_scores(
    df: pd.DataFrame,
    params: Dict[str, Any],
    cutoff: pd.Timestamp,
    horizon_days: int,
    *,
    open_start: Optional[dt_time] = None,
    open_end: Optional[dt_time] = None,
) -> Optional[Dict[str, float]]:
    """
    Train avant cutoff, score sur [cutoff, cutoff+horizon).
    Returns dict mae/mape/abs_err_sum/y_sum (+ open si plage fournie), ou None.
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
        abs_err = (y_true - y_pred).abs()
        mae = float(abs_err.mean())

        mask = y_true >= 3
        if mask.sum() > 0:
            mape = float((abs((y_true[mask] - y_pred[mask]) / y_true[mask])).mean() * 100)
        else:
            mape = 0.0

        out: Dict[str, float] = {
            "mae": mae,
            "mape": mape,
            "abs_err_sum": float(abs_err.sum()),
            "y_sum": float(y_true.sum()),
        }

        if open_start is not None and open_end is not None:
            open_mask = _open_hours_mask(comparison["ds"], open_start, open_end)
            out["abs_err_sum_open"] = float(abs_err[open_mask].sum())
            out["y_sum_open"] = float(y_true[open_mask].sum())

        return out
    finally:
        del model


def evaluate_params_walk_forward(
    df: pd.DataFrame,
    params: Dict[str, Any],
    horizon_days: int,
    n_cutoffs: int = 3,
    *,
    open_start: Optional[dt_time] = None,
    open_end: Optional[dt_time] = None,
) -> Dict[str, float]:
    """
    Évalue des params sur N cutoffs.
    Objectif Optuna = WAPE global (somme des |e| / somme des y, en %).
    MAE / MAPE restent en diagnostic.
    """
    cutoffs = build_cutoffs(df, horizon_days, n_cutoffs)
    maes: List[float] = []
    mapes: List[float] = []
    abs_err_total = 0.0
    y_total = 0.0
    abs_err_open = 0.0
    y_open = 0.0
    has_open = open_start is not None and open_end is not None

    for cutoff in cutoffs:
        result = _window_scores(
            df,
            params,
            cutoff,
            horizon_days,
            open_start=open_start,
            open_end=open_end,
        )
        if result is None:
            continue
        maes.append(result["mae"])
        mapes.append(result["mape"])
        abs_err_total += result["abs_err_sum"]
        y_total += result["y_sum"]
        if has_open:
            abs_err_open += result.get("abs_err_sum_open", 0.0)
            y_open += result.get("y_sum_open", 0.0)

    if not maes:
        raise ValueError(
            "Aucune fenêtre de backtest valide (historique insuffisant pour "
            f"horizon={horizon_days}j × {n_cutoffs} cutoffs)"
        )

    scores: Dict[str, float] = {
        "mae_volume": round(float(sum(maes) / len(maes)), 4),
        "mape_volume": round(float(sum(mapes) / len(mapes)), 2),
        "wape_volume": wape_percent(abs_err_total, y_total),
        "n_cutoffs": float(len(maes)),
        "horizon_days": float(horizon_days),
    }
    if has_open and y_open > 0:
        scores["wape_open_hours"] = wape_percent(abs_err_open, y_open)
    return scores


def scores_for_storage(scores: Dict[str, float], horizon_days: int, n_cutoffs: int) -> Dict[str, Any]:
    """Shape JSON scores du plan."""
    out: Dict[str, Any] = {
        "wape_volume": round(float(scores["wape_volume"]), 2),
        "mae_volume": round(float(scores["mae_volume"]), 4),
        "mape_volume": round(float(scores["mape_volume"]), 2),
        "n_cutoffs": int(scores.get("n_cutoffs", n_cutoffs)),
        "horizon_days": int(horizon_days),
    }
    if "wape_open_hours" in scores:
        out["wape_open_hours"] = round(float(scores["wape_open_hours"]), 2)
    return out


def improvement_pct(baseline: float, proposed: float) -> Optional[float]:
    """Amélioration relative en % (plus bas = mieux). None si baseline <= 0."""
    if baseline <= 0:
        return None
    return round((1.0 - float(proposed) / float(baseline)) * 100.0, 2)


ProgressCallback = Callable[[int, int, Optional[float]], None]
CancelCheck = Callable[[], bool]


class JobCancelled(Exception):
    """Levée quand le job a été annulé côté BDD pendant Optuna."""


def clamp_tunable_for_search_space(
    params: Dict[str, Any],
    optuna_cfg: Dict[str, Any],
) -> Optional[Dict[str, Any]]:
    """
    Tunables officiels bornés à l'espace Optuna, pour enqueue_trial.
    None si une clé manque ou n'est pas numérique.
    """
    try:
        cps_min = float(optuna_cfg["changepoint_prior_scale_min"])
        cps_max = float(optuna_cfg["changepoint_prior_scale_max"])
        sps_min = float(optuna_cfg["seasonality_prior_scale_min"])
        sps_max = float(optuna_cfg["seasonality_prior_scale_max"])
        ncp_min = int(optuna_cfg["n_changepoints_min"])
        ncp_max = int(optuna_cfg["n_changepoints_max"])
        mfo_min = int(optuna_cfg["monthly_fourier_order_min"])
        mfo_max = int(optuna_cfg["monthly_fourier_order_max"])
        cps = float(params["changepoint_prior_scale"])
        sps = float(params["seasonality_prior_scale"])
        ncp = int(params["n_changepoints"])
        mfo = int(params["monthly_fourier_order"])
    except (KeyError, TypeError, ValueError):
        return None
    if cps_min > cps_max or sps_min > sps_max or ncp_min > ncp_max or mfo_min > mfo_max:
        return None
    return {
        "changepoint_prior_scale": min(max(cps, cps_min), cps_max),
        "seasonality_prior_scale": min(max(sps, sps_min), sps_max),
        "n_changepoints": min(max(ncp, ncp_min), ncp_max),
        "monthly_fourier_order": min(max(mfo, mfo_min), mfo_max),
    }


def run_optuna_search(
    df: pd.DataFrame,
    offer_profile: Dict[str, Any],
    optuna_cfg: Dict[str, Any],
    progress_callback: Optional[ProgressCallback] = None,
    cancel_check: Optional[CancelCheck] = None,
) -> Tuple[Dict[str, Any], Dict[str, Any], optuna.Study]:
    """
    Lance Optuna (TPE). Retourne (best_params, best_scores, study).
    L'appelant DOIT supprimer la study et appeler gc.collect().
    Si cancel_check() devient True, study.stop() après le trial courant puis JobCancelled.

    1er essai = tunables du profil officiel (enqueue sans values : réévalués
    sur les cutoffs du job). optimize(n_trials=N) compte cet essai : N fits
    au total (pas N+1).
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
        if cancel_check is not None and cancel_check():
            # TrialPruned + study.stop() dans le callback : arrête la vague proprement
            raise optuna.TrialPruned()
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
        trial.set_user_attr("wape_volume", scores["wape_volume"])
        trial.set_user_attr("mape_volume", scores["mape_volume"])
        trial.set_user_attr("mae_volume", scores["mae_volume"])
        return float(scores["wape_volume"])

    study = optuna.create_study(
        direction="minimize",
        sampler=optuna.samplers.TPESampler(seed=42),
        study_name=f"prophet_tune_{datetime.utcnow().strftime('%Y%m%d%H%M%S')}",
    )

    official = build_prophet_params_from_offer(offer_profile, history_span_days=span_days)
    seed = clamp_tunable_for_search_space(official, optuna_cfg)
    if seed is not None:
        try:
            study.enqueue_trial(seed)
            print(
                "[Optuna] Warm-start : profil officiel en 1er essai "
                "(réévaluation sur les cutoffs du job, sans ancien score)"
            )
        except Exception as exc:
            print(f"[Optuna] Warm-start ignoré ({exc})")
    else:
        print("[Optuna] Warm-start ignoré (tunables officiels incomplets)")

    def _callback(study: optuna.Study, trial: optuna.trial.FrozenTrial) -> None:
        if progress_callback is not None:
            best_wape = None
            if study.best_trial is not None:
                best_wape = float(study.best_value)
            progress_callback(len(study.trials), n_trials, best_wape)
        if cancel_check is not None and cancel_check():
            study.stop()

    if progress_callback:
        progress_callback(0, n_trials, None)

    if cancel_check is not None and cancel_check():
        raise JobCancelled("Job annulé avant Optuna")

    study.optimize(objective, n_trials=n_trials, callbacks=[_callback], show_progress_bar=False)

    if cancel_check is not None and cancel_check():
        raise JobCancelled("Job annulé pendant Optuna")

    if study.best_trial is None:
        raise JobCancelled("Job annulé — aucun trial abouti")

    best_tunable = dict(study.best_params)
    best_params = build_prophet_params_from_offer(
        offer_profile, best_tunable, history_span_days=span_days
    )
    best_scores = {
        "wape_volume": float(study.best_value),
        "mae_volume": float(study.best_trial.user_attrs.get("mae_volume", 0.0)),
        "mape_volume": float(study.best_trial.user_attrs.get("mape_volume", 0.0)),
        "n_cutoffs": float(n_cutoffs),
        "horizon_days": float(horizon),
    }

    return best_params, best_scores, study


def should_auto_apply(
    baseline_score: float,
    proposed_score: float,
    optuna_cfg: Dict[str, Any],
) -> bool:
    """Auto-apply si activé et amélioration (WAPE) >= seuil %.

    Clé JSON inchangée : auto_apply_min_mae_improvement_pct (compat wfm_settings).
    """
    if not optuna_cfg.get("auto_apply"):
        return False
    if baseline_score <= 0:
        return proposed_score < baseline_score
    threshold_pct = float(optuna_cfg.get("auto_apply_min_mae_improvement_pct", 5))
    max_allowed = baseline_score * (1.0 - threshold_pct / 100.0)
    return proposed_score <= max_allowed


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
