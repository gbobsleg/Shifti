# -*- coding: utf-8 -*-
"""
A/B Fourier journalier hors Optuna.

Compare le daily natif Prophet (ordre 4) à une saisonnalité custom
period=1, fourier_order=10 et 15, sur le même walk-forward que le tuning
(3 cutoffs × 14 j, params offre figés).

Usage (depuis solver-python, venv activé) :

    python ab_daily_fourier.py --offer-id 1 --offer-id 2
"""

from __future__ import annotations

import argparse
import gc
import sys
import time
from datetime import datetime, time as dt_time, timedelta
from typing import Any, Dict, List, Optional, Tuple

from prophet_common import get_db_connection
from prophet_tuning_core import (
    assert_min_history,
    build_prophet_params_from_offer,
    evaluate_params_walk_forward,
    history_span_days,
    load_offer_history_df,
    merge_optuna_settings,
    parse_json_field,
)

# None = daily natif Prophet (fourier 4). Int = add_seasonality custom.
VARIANTS: Tuple[Tuple[str, Optional[int]], ...] = (
    ("4-natif", None),
    ("10-custom", 10),
    ("15-custom", 15),
)


def _as_time(value: Any, default: str) -> dt_time:
    if value is None:
        h, m, s = (int(p) for p in default.split(":"))
        return dt_time(h, m, s)
    if isinstance(value, dt_time):
        return value.replace(microsecond=0)
    if isinstance(value, timedelta):
        total = int(value.total_seconds()) % (24 * 3600)
        h, rem = divmod(total, 3600)
        m, s = divmod(rem, 60)
        return dt_time(h, m, s)
    if isinstance(value, datetime):
        return value.time().replace(microsecond=0)
    text = str(value).strip()
    parts = text.split(":")
    h = int(parts[0])
    m = int(parts[1]) if len(parts) > 1 else 0
    s = int(float(parts[2])) if len(parts) > 2 else 0
    return dt_time(h, m, s)


def _load_wfm_open_hours(conn) -> Tuple[dt_time, dt_time]:
    cursor = conn.cursor()
    cursor.execute("SELECT day_start_time, day_end_time FROM wfm_settings LIMIT 1")
    row = cursor.fetchone()
    cursor.close()
    start_raw, end_raw = (row[0], row[1]) if row else (None, None)
    return _as_time(start_raw, "09:00:00"), _as_time(end_raw, "17:00:00")


def _load_offer(conn, offer_id: int) -> Dict[str, Any]:
    cursor = conn.cursor(dictionary=True)
    cursor.execute(
        """
        SELECT id, name, prophet_default_settings_json
        FROM offers
        WHERE id = %s
        """,
        (offer_id,),
    )
    row = cursor.fetchone()
    cursor.close()
    if not row:
        raise ValueError(f"Offre id={offer_id} introuvable")
    raw = parse_json_field(row.get("prophet_default_settings_json"))
    return {
        "id": int(row["id"]),
        "name": row.get("name") or f"offre {offer_id}",
        "profile": raw if isinstance(raw, dict) else {},
    }


def _fmt_scores(scores: Dict[str, float]) -> str:
    wape_open = scores.get("wape_open_hours")
    open_txt = f"  WAPE ouv.={wape_open:.2f}%" if wape_open is not None else ""
    return (
        f"WAPE={scores['wape_volume']:.2f}%  "
        f"MAE={scores['mae_volume']:.4f}  "
        f"MAPE={scores['mape_volume']:.2f}%"
        f"{open_txt}"
    )


def run_offer(
    offer: Dict[str, Any],
    *,
    horizon: int,
    n_cutoffs: int,
    min_history_days: int,
    open_start: Optional[dt_time],
    open_end: Optional[dt_time],
) -> List[Dict[str, Any]]:
    offer_id = int(offer["id"])
    profile = offer["profile"]
    print("")
    print("=" * 72)
    print(f"Offre #{offer_id} — {offer['name']}")
    print("=" * 72)

    df = load_offer_history_df(offer_id, profile)
    assert_min_history(df, min_history_days)
    span_days = history_span_days(df)
    print(f"Historique utile : {span_days} j  ({df['ds'].min()} → {df['ds'].max()})")
    print(f"Walk-forward : {n_cutoffs} cutoffs × {horizon} j")
    if open_start and open_end:
        print(f"Plage WFM (WAPE ouvert) : {open_start} → {open_end}")

    base_params = build_prophet_params_from_offer(
        profile, history_span_days=span_days
    )
    rows: List[Dict[str, Any]] = []

    for label, order in VARIANTS:
        params = dict(base_params)
        if order is None:
            params.pop("daily_fourier_order", None)
        else:
            params["daily_fourier_order"] = order

        print(f"\n--- Variante {label} ---")
        t0 = time.perf_counter()
        scores = evaluate_params_walk_forward(
            df,
            params,
            horizon,
            n_cutoffs,
            open_start=open_start,
            open_end=open_end,
        )
        elapsed = time.perf_counter() - t0
        print(f"  {_fmt_scores(scores)}  ({elapsed:.0f} s)")
        rows.append(
            {
                "offer_id": offer_id,
                "offer_name": offer["name"],
                "span_days": span_days,
                "variant": label,
                "fourier": 4 if order is None else order,
                "elapsed_s": round(elapsed, 1),
                **scores,
            }
        )
        gc.collect()

    return rows


def _print_summary(rows: List[Dict[str, Any]]) -> None:
    if not rows:
        return
    print("")
    print("=" * 72)
    print("Synthèse (WAPE plus bas = mieux ; décider sur le walk-forward)")
    print("=" * 72)
    header = (
        f"{'offre':<6} {'variante':<12} {'WAPE%':>8} {'WAPE ouv.%':>11} "
        f"{'MAE':>8} {'MAPE%':>8} {'s':>7}"
    )
    print(header)
    print("-" * len(header))
    for row in rows:
        wopen = row.get("wape_open_hours")
        wopen_txt = f"{wopen:.2f}" if wopen is not None else "—"
        print(
            f"{row['offer_id']:<6} {row['variant']:<12} "
            f"{row['wape_volume']:>8.2f} {wopen_txt:>11} "
            f"{row['mae_volume']:>8.4f} {row['mape_volume']:>8.2f} "
            f"{row['elapsed_s']:>7.0f}"
        )

    print("")
    print(
        "Si 15-custom gagne seulement sur un historique court et perd sur le long "
        "→ risque d'overfit, plutôt 10-custom ou 4-natif."
    )


def parse_args(argv: Optional[List[str]] = None) -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="A/B Fourier journalier Prophet (4 natif vs 10 vs 15), hors Optuna."
    )
    parser.add_argument(
        "--offer-id",
        type=int,
        action="append",
        required=True,
        dest="offer_ids",
        help="ID d'offre (répétable). Idéal : 1 historique long + 1 court.",
    )
    parser.add_argument("--horizon-days", type=int, default=None)
    parser.add_argument("--n-cutoffs", type=int, default=None)
    parser.add_argument(
        "--no-open-hours",
        action="store_true",
        help="Ne pas calculer le WAPE restreint aux heures WFM.",
    )
    return parser.parse_args(argv)


def main(argv: Optional[List[str]] = None) -> int:
    args = parse_args(argv)
    offer_ids = list(dict.fromkeys(args.offer_ids))

    conn = get_db_connection()
    try:
        optuna_cfg = merge_optuna_settings(None)
        cursor = conn.cursor()
        cursor.execute("SELECT optuna_settings_json FROM wfm_settings LIMIT 1")
        wfm_row = cursor.fetchone()
        cursor.close()
        raw_optuna = parse_json_field(wfm_row[0]) if wfm_row else None
        if isinstance(raw_optuna, dict):
            optuna_cfg = merge_optuna_settings(raw_optuna)

        horizon = int(args.horizon_days or optuna_cfg["test_horizon_days"])
        n_cutoffs = int(args.n_cutoffs or optuna_cfg.get("n_cutoffs", 3))
        min_history = int(optuna_cfg["min_history_days"])

        open_start: Optional[dt_time] = None
        open_end: Optional[dt_time] = None
        if not args.no_open_hours:
            open_start, open_end = _load_wfm_open_hours(conn)

        offers = [_load_offer(conn, oid) for oid in offer_ids]
    finally:
        conn.close()

    print("A/B Fourier journalier — params offre figés, pas d'Optuna")
    print(f"Offres : {', '.join(str(o['id']) for o in offers)}")

    all_rows: List[Dict[str, Any]] = []
    for offer in offers:
        try:
            all_rows.extend(
                run_offer(
                    offer,
                    horizon=horizon,
                    n_cutoffs=n_cutoffs,
                    min_history_days=min_history,
                    open_start=open_start,
                    open_end=open_end,
                )
            )
        except Exception as exc:
            print(f"\n[ERREUR] offre #{offer['id']}: {exc}", file=sys.stderr)
            return 1

    _print_summary(all_rows)
    return 0


if __name__ == "__main__":
    sys.exit(main())
