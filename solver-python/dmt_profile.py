# -*- coding: utf-8 -*-
"""
Profil DMT Prophet (proposition B) : niveau hebdomadaire figé + forme intra-journalière.

Construit à partir de l'historique brut au pas de 15 minutes (colonnes ds, y, dmt),
avant toute agrégation pour l'entraînement Prophet.
"""

from __future__ import annotations

from datetime import datetime, time, timedelta
from typing import Any, Dict, List, Optional, Tuple

import pandas as pd

DMT_ABS_MIN = 90
DMT_ABS_MAX = 1200
MIN_SLOT_VOLUME = 5
SHAPE_DAYS = 365
LEVEL_COMPLETE_WEEKS = 4
FALLBACK_LEVEL = 300.0


def clip_abs(value: float) -> float:
    return float(min(DMT_ABS_MAX, max(DMT_ABS_MIN, value)))


def emit_dmt(level_seconds: float, coeff: float) -> int:
    """DMT écrite sur un ForecastPoint : filet métier 90–1200 s uniquement."""
    return int(round(clip_abs(float(level_seconds) * float(coeff))))


def slot_hhmm(time_slot: str) -> str:
    """HH:MM:SS ou HH:MM → HH:MM."""
    text = str(time_slot).strip()
    if len(text) >= 5:
        return text[:5]
    return text


def lookup_coeff(profile: Dict[str, Any], iso_dow: int, time_slot: str) -> float:
    coefficients = profile.get("coefficients") or {}
    day_map = coefficients.get(str(iso_dow)) or {}
    if not isinstance(day_map, dict):
        return 1.0
    value = day_map.get(slot_hhmm(time_slot))
    if value is None:
        return 1.0
    return float(value)


def dmt_for_datetime(profile: Dict[str, Any], when: datetime, time_slot: str) -> int:
    level = float(profile.get("level_seconds") or FALLBACK_LEVEL)
    coeff = lookup_coeff(profile, when.isoweekday(), time_slot)
    return emit_dmt(level, coeff)


def _parse_clock(value: str) -> time:
    raw = str(value).strip()
    if len(raw) == 5:
        raw += ":00"
    return datetime.strptime(raw[:8], "%H:%M:%S").time()


def iter_day_slots(day_start: str, day_end: str) -> List[str]:
    start_t = _parse_clock(day_start)
    end_t = _parse_clock(day_end)
    cur = datetime.combine(datetime(2000, 1, 1).date(), start_t)
    end = datetime.combine(datetime(2000, 1, 1).date(), end_t)
    slots: List[str] = []
    while cur < end:
        slots.append(cur.strftime("%H:%M"))
        cur += timedelta(minutes=15)
    return slots


def _to_history_end(history_end: Optional[Any], df: pd.DataFrame) -> pd.Timestamp:
    fallback = None
    if not df.empty:
        fallback = pd.to_datetime(df["ds"]).max()
        if pd.isna(fallback):
            fallback = None
    if fallback is None:
        fallback = pd.Timestamp.now()

    if history_end is None or (isinstance(history_end, str) and not history_end.strip()):
        return pd.Timestamp(fallback).normalize()

    ts = pd.Timestamp(history_end)
    if pd.isna(ts):
        return pd.Timestamp(fallback).normalize()
    if ts.tzinfo is not None:
        ts = ts.tz_localize(None)
    return ts.normalize()


def _complete_iso_weeks_end(history_end: pd.Timestamp) -> Tuple[pd.Timestamp, pd.Timestamp]:
    """Dernier dimanche <= history_end, et lundi de la 4e semaine complète en reculant."""
    days_since_sunday = (history_end.weekday() + 1) % 7
    last_sunday = history_end - pd.Timedelta(days=int(days_since_sunday))
    last_monday = last_sunday - pd.Timedelta(days=6)
    first_monday = last_monday - pd.Timedelta(weeks=LEVEL_COMPLETE_WEEKS - 1)
    return first_monday.normalize(), last_sunday.normalize()


def _weighted_dmt(sub: pd.DataFrame, min_volume: int = MIN_SLOT_VOLUME) -> Optional[float]:
    if sub.empty:
        return None
    vol = float(sub["y"].sum())
    if vol < min_volume:
        return None
    return float((sub["y"] * sub["dmt"]).sum() / vol)


def _smooth_day_values(values: List[float]) -> List[float]:
    n = len(values)
    if n == 0:
        return []
    if n == 1:
        return [values[0]]
    out: List[float] = []
    for i, current in enumerate(values):
        if i == 0:
            out.append(0.5 * current + 0.5 * values[1])
        elif i == n - 1:
            out.append(0.5 * values[n - 2] + 0.5 * current)
        else:
            out.append(0.25 * values[i - 1] + 0.5 * current + 0.25 * values[i + 1])
    return out


def _empty_coefficients() -> Dict[str, Dict[str, float]]:
    return {str(d): {} for d in range(1, 8)}


def fallback_profile(
    *,
    window_start: Optional[str] = None,
    window_end: Optional[str] = None,
    reason: str = "insufficient_data",
) -> Dict[str, Any]:
    return {
        "level_seconds": FALLBACK_LEVEL,
        "coefficients": _empty_coefficients(),
        "window_start": window_start,
        "window_end": window_end,
        "min_slot_volume": MIN_SLOT_VOLUME,
        "p5": None,
        "p95": None,
        "k": 1.0,
        "computed_at": datetime.utcnow().strftime("%Y-%m-%dT%H:%M:%SZ"),
        "reason": reason,
    }


def build_dmt_profile(
    df: pd.DataFrame,
    *,
    history_end: Optional[Any] = None,
    day_start: str = "09:00:00",
    day_end: str = "17:00:00",
) -> Dict[str, Any]:
    """
    Construit le profil DMT à partir d'un DataFrame brut 15 min (ds, y, dmt).
    """
    if df is None or df.empty or "ds" not in df.columns:
        return fallback_profile(reason="empty_history")

    work = df.copy()
    work["ds"] = pd.to_datetime(work["ds"])
    work["y"] = pd.to_numeric(work["y"], errors="coerce").fillna(0.0)
    work["dmt"] = pd.to_numeric(work["dmt"], errors="coerce")

    end_day = _to_history_end(history_end, work)
    # Inclure toute la journée history_end, rien après.
    work = work[work["ds"] <= (end_day + pd.Timedelta(days=1) - pd.Timedelta(seconds=1))]
    window_start = end_day - pd.Timedelta(days=SHAPE_DAYS - 1)
    work = work[work["ds"] >= window_start]

    if work.empty:
        return fallback_profile(
            window_start=window_start.strftime("%Y-%m-%d"),
            window_end=end_day.strftime("%Y-%m-%d"),
            reason="empty_window",
        )

    positive = work[work["y"] > 0]["dmt"].dropna()
    p5 = float(positive.quantile(0.05)) if len(positive) >= 20 else None
    p95 = float(positive.quantile(0.95)) if len(positive) >= 20 else None
    if p5 is not None and p95 is not None and p95 > p5:
        work["dmt"] = work["dmt"].clip(lower=p5, upper=p95)
    work["dmt"] = work["dmt"].clip(lower=DMT_ABS_MIN, upper=DMT_ABS_MAX)

    week_start, week_end = _complete_iso_weeks_end(end_day)
    level_df = work[
        (work["ds"] >= week_start)
        & (work["ds"] < (week_end + pd.Timedelta(days=1)))
        & (work["y"] > 0)
    ]
    level = _weighted_dmt(level_df, min_volume=1)
    if level is None:
        level = _weighted_dmt(work[work["y"] > 0], min_volume=1)
    if level is None:
        level = FALLBACK_LEVEL
    level = clip_abs(level)

    work["iso_dow"] = work["ds"].dt.dayofweek + 1
    work["slot"] = work["ds"].dt.strftime("%H:%M")
    half_min = ((work["ds"].dt.minute // 30) * 30).astype(int).astype(str).str.zfill(2)
    work["half"] = work["ds"].dt.strftime("%H") + ":" + half_min
    work["hour"] = work["ds"].dt.strftime("%H") + ":00"

    slots = iter_day_slots(day_start, day_end)
    brute: Dict[str, Dict[str, float]] = {str(d): {} for d in range(1, 8)}

    for dow in range(1, 8):
        day_df = work[work["iso_dow"] == dow]
        for slot in slots:
            slot_ts = datetime.strptime(slot, "%H:%M")
            half_key = slot_ts.replace(minute=(slot_ts.minute // 30) * 30).strftime("%H:%M")
            hour_key = slot_ts.replace(minute=0).strftime("%H:%M")
            dmt_slot = _weighted_dmt(day_df[day_df["slot"] == slot])
            if dmt_slot is None:
                dmt_slot = _weighted_dmt(day_df[day_df["half"] == half_key])
            if dmt_slot is None:
                dmt_slot = _weighted_dmt(day_df[day_df["hour"] == hour_key])
            if dmt_slot is None:
                dmt_slot = level
            brute[str(dow)][slot] = float(dmt_slot)

    smoothed: Dict[str, Dict[str, float]] = {str(d): {} for d in range(1, 8)}
    for dow in range(1, 8):
        raw_vals = [brute[str(dow)][s] for s in slots]
        sm_vals = _smooth_day_values(raw_vals)
        for slot, value in zip(slots, sm_vals):
            smoothed[str(dow)][slot] = float(value)

    brute_charge = 0.0
    smooth_charge = 0.0
    for row in work.itertuples(index=False):
        vol = float(row.y)
        if vol <= 0:
            continue
        dow_k = str(int(row.iso_dow))
        slot_k = str(row.slot)
        if slot_k not in brute.get(dow_k, {}):
            continue
        brute_charge += vol * brute[dow_k][slot_k]
        smooth_charge += vol * smoothed[dow_k][slot_k]

    k = 1.0
    if smooth_charge > 0:
        k = brute_charge / smooth_charge

    coefficients: Dict[str, Dict[str, float]] = {str(d): {} for d in range(1, 8)}
    for dow in range(1, 8):
        for slot in slots:
            dmt_final = smoothed[str(dow)][slot] * k
            coefficients[str(dow)][slot] = float(dmt_final / level) if level else 1.0

    return {
        "level_seconds": float(level),
        "coefficients": coefficients,
        "window_start": window_start.strftime("%Y-%m-%d"),
        "window_end": end_day.strftime("%Y-%m-%d"),
        "min_slot_volume": MIN_SLOT_VOLUME,
        "p5": p5,
        "p95": p95,
        "k": float(k),
        "level_weeks_start": week_start.strftime("%Y-%m-%d"),
        "level_weeks_end": week_end.strftime("%Y-%m-%d"),
        "computed_at": datetime.utcnow().strftime("%Y-%m-%dT%H:%M:%SZ"),
    }
