# -*- coding: utf-8 -*-
"""
Série journalière Prophet : agrégation, fériés, winsor, profils appris, ancrage.

L'entraînement ne voit que des jours ouvrés à volume > 0. La ventilation 15 min
et les zéros calendaires (week-end, férié) sont faits à l'émission.
"""

from __future__ import annotations

from datetime import datetime
from typing import Any, Dict, Iterable, List, Optional, Sequence

import pandas as pd
from holidays import France

# Bornes d'émission : un JSON hors de cette boîte est ramené au fit,
# même si l'espace Optuna stocké est encore l'ancien.
CPS_MIN = 0.005
CPS_MAX = 0.10
SPS_MIN = 1.0
SPS_MAX = 50.0
N_CHANGEPOINTS = 25

DOW_PROFILE_COL = "dow_profile"
MONTH_PROFILE_COL = "month_profile"
PROFILE_PRIOR = 1.0
PROFILE_MIN_OBS = 3
REGRESSOR_STD_MIN = 1e-6

WINSOR_FACTOR = 1.35
WINSOR_MIN_SAMPLE = 15

ANCHOR_PHI = 0.98
ANCHOR_LOOKBACK_DAYS = 90
DAMP_HORIZON_DAYS = 90
DAMP_TREND_LOW = 0.6
DAMP_TREND_HIGH = 1.5
DAMP_ALERT_GAP = 0.02

DEFAULT_CUTOFF_LAGS_DAYS = [90, 180, 270, 360]


def clamp_prior(value: float, low: float, high: float) -> float:
    return float(min(high, max(low, float(value))))


def stored_profile_violates_bounds(profile: Optional[dict]) -> bool:
    """
    True si le JSON d'offre est hors bornes d'émission ou viole un flag figé
    (n_changepoints, fériés FR encore actifs).
    """
    data = profile or {}
    try:
        cps = float(data.get("changepoint_prior_scale", 0.05))
        sps = float(data.get("seasonality_prior_scale", 10.0))
        ncp = int(data.get("n_changepoints", N_CHANGEPOINTS))
    except (TypeError, ValueError):
        return True
    if cps < CPS_MIN - 1e-12 or cps > CPS_MAX + 1e-12:
        return True
    if sps < SPS_MIN - 1e-12 or sps > SPS_MAX + 1e-12:
        return True
    if ncp != N_CHANGEPOINTS:
        return True
    holidays = data.get("use_french_holidays", True)
    if holidays in (True, 1, "1", "true", "True"):
        return True
    return False


def is_subdaily(df: pd.DataFrame) -> bool:
    if df.empty:
        return False
    return bool(df["ds"].dt.normalize().duplicated().any())


def aggregate_daily(df: pd.DataFrame) -> pd.DataFrame:
    """Somme du volume par jour calendaire. Une série déjà journalière est recopiée."""
    if df.empty:
        return pd.DataFrame(columns=["ds", "y"])
    work = df[["ds", "y"]].copy()
    work["ds"] = pd.to_datetime(work["ds"])
    if not is_subdaily(work):
        out = work.copy()
        out["ds"] = out["ds"].dt.normalize()
        return out.sort_values("ds").reset_index(drop=True)
    out = (
        work.groupby(work["ds"].dt.normalize(), as_index=False)["y"]
        .sum()
        .rename(columns={"ds": "ds"})
    )
    out["ds"] = pd.to_datetime(out["ds"])
    return out.sort_values("ds").reset_index(drop=True)


def french_holiday_dates(dates: Iterable[pd.Timestamp]) -> set:
    years = set()
    for value in dates:
        ts = pd.Timestamp(value)
        if pd.isna(ts):
            continue
        years.add(int(ts.year))
    if not years:
        return set()
    return set(France(years=sorted(years)).keys())


def drop_closed_days(df: pd.DataFrame) -> pd.DataFrame:
    """Retire y <= 0 et les jours fériés français."""
    if df.empty:
        return df.copy()
    holidays = french_holiday_dates(df["ds"])
    day = df["ds"].dt.date
    keep = (df["y"] > 0) & ~day.isin(holidays)
    return df.loc[keep].reset_index(drop=True)


def _circular_day(day: int) -> int:
    return ((int(day) - 1) % 31) + 1


def _month_mean_ratio(df: pd.DataFrame, value_col: str = "y") -> pd.Series:
    month_key = df["ds"].dt.to_period("M")
    month_mean = df.groupby(month_key)[value_col].transform("mean")
    return df[value_col] / month_mean.replace(0, pd.NA)


def _fill_sparse_days(direct: Dict[int, float]) -> Dict[int, float]:
    """Voisins sur le cercle 1..31. Le 31 touche le 30 et le 1."""
    filled = dict(direct)
    for day in range(1, 32):
        if day in direct:
            continue
        neighbors = []
        for delta in (-1, 1):
            other = _circular_day(day + delta)
            if other in direct:
                neighbors.append(direct[other])
        filled[day] = float(sum(neighbors) / len(neighbors)) if neighbors else 1.0
    return filled


def day_of_month_ratios(df: pd.DataFrame, value_col: str = "y") -> Dict[int, float]:
    """
    Médiane du ratio au mois, par numéro de jour.
    Moins de 3 observations : moyenne des voisins déjà estimés.
    """
    if df.empty:
        return {day: 1.0 for day in range(1, 32)}
    work = df[["ds", value_col]].copy()
    work["ratio"] = _month_mean_ratio(work, value_col)
    work["dom"] = work["ds"].dt.day
    direct: Dict[int, float] = {}
    for dom, sub in work.groupby("dom"):
        vals = sub["ratio"].dropna()
        if len(vals) >= PROFILE_MIN_OBS:
            direct[int(dom)] = float(vals.median())
    return _fill_sparse_days(direct)


def winsorize_daily(df: pd.DataFrame) -> pd.DataFrame:
    """
    Plafond 1,35 × médiane du même mois (autres années) × max(1, ratio du numéro de jour).
    Le ratio est lu avant écrêtage. Moins de 15 jours : toutes les années.
    """
    if df.empty:
        return df.copy()
    ratios = day_of_month_ratios(df)
    out = df.copy()
    months = out["ds"].dt.month.to_numpy()
    years = out["ds"].dt.year.to_numpy()
    doms = out["ds"].dt.day.to_numpy()
    original = out["y"].to_numpy(dtype=float)
    values = original.copy()
    for i in range(len(out)):
        same = months == months[i]
        others = same & (years != years[i])
        sample = original[others]
        if sample.size < WINSOR_MIN_SAMPLE:
            sample = original[same]
        if sample.size == 0:
            continue
        ratio = max(1.0, float(ratios.get(int(doms[i]), 1.0)))
        cap = WINSOR_FACTOR * float(pd.Series(sample).median()) * ratio
        if values[i] > cap:
            values[i] = cap
    out["y"] = values
    return out


def weekday_profile(df: pd.DataFrame) -> Dict[int, float]:
    """Médiane du volume / moyenne du mois, par jour ouvré présent. Pas de week-end."""
    if df.empty:
        return {}
    work = df[["ds", "y"]].copy()
    work["ratio"] = _month_mean_ratio(work)
    work["dow"] = work["ds"].dt.dayofweek + 1
    factors: Dict[int, float] = {}
    for dow, sub in work.groupby("dow"):
        vals = sub["ratio"].dropna()
        if len(vals) == 0:
            continue
        factors[int(dow)] = float(vals.median())
    return factors


def month_profile(df: pd.DataFrame, dow_factors: Dict[int, float]) -> Dict[int, float]:
    """Médiane du ratio mensuel après retrait du facteur de jour de semaine."""
    if df.empty:
        return {day: 1.0 for day in range(1, 32)}
    work = df[["ds", "y"]].copy()
    work["dow"] = work["ds"].dt.dayofweek + 1
    factor = work["dow"].map(lambda dow: float(dow_factors.get(int(dow), 1.0)))
    factor = factor.mask(factor.abs() < 1e-12)
    work["adj"] = work["y"] / factor
    return day_of_month_ratios(work[["ds", "adj"]].rename(columns={"adj": "y"}))


def _column_std(values: pd.Series) -> float:
    clean = pd.to_numeric(values, errors="coerce").dropna()
    if len(clean) < 2:
        return 0.0
    return float(clean.std(ddof=0))


def build_offer_profiles(df: pd.DataFrame) -> Dict[str, Any]:
    """Profils du fit. use_* est faux si la colonne est constante."""
    dow = weekday_profile(df)
    month = month_profile(df, dow)
    attached = apply_offer_profiles(
        df,
        {"dow": dow, "month": month, "use_dow": True, "use_month": True},
    )
    use_dow = DOW_PROFILE_COL in attached and _column_std(attached[DOW_PROFILE_COL]) > REGRESSOR_STD_MIN
    use_month = (
        MONTH_PROFILE_COL in attached
        and _column_std(attached[MONTH_PROFILE_COL]) > REGRESSOR_STD_MIN
    )
    return {"dow": dow, "month": month, "use_dow": use_dow, "use_month": use_month}


def apply_offer_profiles(df: pd.DataFrame, profiles: Optional[dict]) -> pd.DataFrame:
    out = df.copy()
    if not profiles:
        return out
    if profiles.get("use_dow"):
        dow = profiles.get("dow") or {}
        out[DOW_PROFILE_COL] = out["ds"].dt.dayofweek.add(1).map(
            lambda day: float(dow.get(int(day), 1.0))
        )
    if profiles.get("use_month"):
        month = profiles.get("month") or {}
        out[MONTH_PROFILE_COL] = out["ds"].dt.day.map(
            lambda day: float(month.get(int(day), 1.0))
        )
    return out


def prepare_training_frame(df: pd.DataFrame, use_peak: bool = False) -> tuple:
    """
    Historique prêt pour Prophet et profils du fit.
    use_peak est ignoré : les anciens JSON ne déclenchent plus de règle par offre.
    """
    del use_peak
    daily = drop_closed_days(aggregate_daily(df))
    if len(daily) < 30:
        raise ValueError(
            f"Historique journalier trop court après retrait des fériés et des zéros "
            f"({len(daily)} jour(s), minimum 30)"
        )
    prepared = winsorize_daily(daily)
    profiles = build_offer_profiles(prepared)
    prepared = apply_offer_profiles(prepared, profiles)
    return prepared.reset_index(drop=True), profiles


def future_frame(ds: Sequence, profiles: Optional[dict] = None) -> pd.DataFrame:
    frame = pd.DataFrame({"ds": pd.to_datetime(list(ds))})
    return apply_offer_profiles(frame, profiles)


def predict_frame(model: Any, ds: Sequence) -> pd.DataFrame:
    return future_frame(ds, getattr(model, "offer_profiles", None))


def anchor_target(trend_t0: float, trend_ref: float) -> Dict[str, Any]:
    """Rappel vers la moyenne des 90 derniers jours, sans seuil."""
    origin = float(trend_t0)
    ref = float(trend_ref)
    if abs(ref) < 1e-9:
        return {"target": origin, "branch": "rappel", "gap_ref": 0.0}
    gap = abs(origin - ref) / abs(ref)
    return {"target": ref, "branch": "rappel", "gap_ref": float(gap)}


def anchored_trend(
    trend_t0: float,
    target: float,
    delta_days: float,
    *,
    phi: float = ANCHOR_PHI,
) -> float:
    """Retour vers la cible dès t0, puis tunnel autour de trend(t0)."""
    if delta_days <= 0:
        value = float(trend_t0)
    else:
        weight = phi ** float(delta_days)
        value = float(trend_t0) * weight + float(target) * (1.0 - weight)
    lo = DAMP_TREND_LOW * float(trend_t0)
    hi = DAMP_TREND_HIGH * float(trend_t0)
    if hi < lo:
        lo, hi = hi, lo
    return float(min(hi, max(lo, value)))


def trend_relative_gap(trend_raw: float, trend_damped: float) -> float:
    if abs(trend_raw) < 1e-9:
        return 0.0 if abs(trend_damped - trend_raw) < 1e-9 else 1.0
    return abs(trend_raw - trend_damped) / abs(trend_raw)


def apply_anchored_yhat(
    trend: float,
    multiplicative_terms: float,
    additive_terms: float,
    when: datetime,
    *,
    t0: pd.Timestamp,
    trend_t0: float,
    target: float,
) -> Dict[str, float]:
    """
    Reconstruit yhat. Avant t0, la tendance Prophet est inchangée.
    Retourne yhat, trend utilisé, et l'écart relatif contre la tendance brute.
    """
    ts = pd.Timestamp(when).normalize()
    origin = pd.Timestamp(t0).normalize()
    raw_trend = float(trend)
    delta = (ts - origin).total_seconds() / 86400.0
    if delta < 0:
        used = raw_trend
        gap = 0.0
    else:
        used = anchored_trend(float(trend_t0), float(target), delta)
        gap = trend_relative_gap(raw_trend, used)
    yhat = used * (1.0 + float(multiplicative_terms)) + float(additive_terms)
    return {"yhat": float(yhat), "trend": float(used), "gap": float(gap)}


def build_volume_shares(
    df: pd.DataFrame,
    slots: List[str],
    *,
    lookback_days: int = 365,
) -> Dict[int, Dict[str, float]]:
    """
    Part du volume de chaque créneau HH:MM dans la journée, par jour ISO.
    Fenêtre = lookback_days avant la fin d'historique. Somme 1 par jour.
    """
    equal = 1.0 / len(slots) if slots else 0.0
    fallback = {slot: equal for slot in slots}
    shares: Dict[int, Dict[str, float]] = {dow: dict(fallback) for dow in range(1, 8)}
    if df.empty or not slots:
        return shares

    work = df[["ds", "y"]].copy()
    work["ds"] = pd.to_datetime(work["ds"])
    end = work["ds"].max()
    start = end - pd.Timedelta(days=lookback_days)
    work = work[work["ds"] >= start]
    if work.empty:
        return shares
    work["dow"] = work["ds"].dt.dayofweek + 1
    work["hhmm"] = work["ds"].dt.strftime("%H:%M")
    slot_set = set(slots)
    work = work[work["hhmm"].isin(slot_set)]
    for dow in range(1, 8):
        sub = work[work["dow"] == dow]
        if sub.empty or float(sub["y"].sum()) <= 0:
            continue
        grouped = sub.groupby("hhmm")["y"].sum()
        raw = {slot: float(grouped.get(slot, 0.0)) for slot in slots}
        total = sum(raw.values())
        if total <= 0:
            continue
        shares[dow] = {slot: value / total for slot, value in raw.items()}
    return shares


def distribute_daily_volume(daily_volume: float, shares: Dict[str, float], slots: List[str]) -> Dict[str, int]:
    """Ventile un volume journalier sur les créneaux. Clé HH:MM:SS."""
    total = max(0.0, float(daily_volume))
    out: Dict[str, int] = {}
    for slot in slots:
        hhmm = slot[:5]
        share = float(shares.get(hhmm, 0.0))
        out[f"{hhmm}:00" if len(slot) == 5 else slot] = max(0, int(round(total * share)))
    return out
