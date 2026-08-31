# -*- coding: utf-8 -*-
"""
Rétro-analyse du seuil d'auto-apply WAPE.

Rejoue le walk-forward actuel (3×14 j) sur baseline vs best_params
des jobs completed. Les JSON historiques n'ont pas de WAPE : il faut
réentraîner (2 × 3 fits par job).

Les fenêtres de test sont celles d'AUJOURD'HUI, pas celles du soir du job.

Usage (depuis solver-python) :

    python recalibrate_auto_apply_wape.py
    python recalibrate_auto_apply_wape.py --limit 8
"""

from __future__ import annotations

import argparse
import gc
import sys
import time
from typing import Any, Dict, List, Optional

from prophet_common import get_db_connection
from prophet_tuning_core import (
    assert_min_history,
    evaluate_params_walk_forward,
    improvement_pct,
    load_offer_history_df,
    merge_optuna_settings,
    parse_json_field,
    should_auto_apply,
)


def _load_jobs(conn, limit: int) -> List[Dict[str, Any]]:
    cursor = conn.cursor(dictionary=True)
    sql = """
        SELECT j.id, j.offer_id, o.name AS offer_name,
               o.prophet_default_settings_json,
               j.baseline_params_json, j.best_params_json,
               j.baseline_scores_json, j.best_scores_json,
               j.finished_at
        FROM prophet_tuning_jobs j
        INNER JOIN offers o ON o.id = j.offer_id
        WHERE j.status = 'completed'
          AND j.baseline_params_json IS NOT NULL
          AND j.best_params_json IS NOT NULL
        ORDER BY j.id DESC
    """
    if limit > 0:
        sql += " LIMIT %s"
        cursor.execute(sql, (limit,))
    else:
        cursor.execute(sql)
    rows = cursor.fetchall()
    cursor.close()
    return rows


def _mae_improvement_from_json(baseline_scores: Any, best_scores: Any) -> Optional[float]:
    if not isinstance(baseline_scores, dict) or not isinstance(best_scores, dict):
        return None
    b = baseline_scores.get("mae_volume")
    p = best_scores.get("mae_volume")
    if b is None or p is None:
        return None
    return improvement_pct(float(b), float(p))


def parse_args(argv: Optional[List[str]] = None) -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Recalibre le seuil auto-apply WAPE à partir des jobs completed."
    )
    parser.add_argument(
        "--limit",
        type=int,
        default=8,
        help="Nombre max de jobs (0 = tous). Défaut 8.",
    )
    return parser.parse_args(argv)


def main(argv: Optional[List[str]] = None) -> int:
    args = parse_args(argv)
    conn = get_db_connection()
    try:
        jobs = _load_jobs(conn, args.limit)
        cursor = conn.cursor()
        cursor.execute("SELECT optuna_settings_json FROM wfm_settings LIMIT 1")
        wfm_row = cursor.fetchone()
        cursor.close()
    finally:
        conn.close()

    raw_optuna = parse_json_field(wfm_row[0]) if wfm_row else None
    optuna_cfg = merge_optuna_settings(raw_optuna if isinstance(raw_optuna, dict) else None)
    horizon = int(optuna_cfg["test_horizon_days"])
    n_cutoffs = int(optuna_cfg.get("n_cutoffs", 3))
    min_history = int(optuna_cfg["min_history_days"])
    current_threshold = float(optuna_cfg.get("auto_apply_min_mae_improvement_pct", 5))

    print("Rétro-analyse auto-apply WAPE (walk-forward actuel, pas les fenêtres du job)")
    print(f"Jobs : {len(jobs)}  horizon={horizon}j  cutoffs={n_cutoffs}")
    print(f"Seuil JSON actuel : {current_threshold} % (clé auto_apply_min_mae_improvement_pct)")
    if not jobs:
        print("Aucun job completed avec baseline + best params. Rien à recalibrer.")
        return 0

    rows: List[Dict[str, Any]] = []
    for job in jobs:
        job_id = int(job["id"])
        offer_id = int(job["offer_id"])
        name = job.get("offer_name") or f"offre {offer_id}"
        baseline_params = parse_json_field(job.get("baseline_params_json"))
        best_params = parse_json_field(job.get("best_params_json"))
        if not isinstance(baseline_params, dict) or not isinstance(best_params, dict):
            print(f"\n[SKIP] job #{job_id} params JSON invalides")
            continue

        profile_raw = parse_json_field(job.get("prophet_default_settings_json"))
        profile = profile_raw if isinstance(profile_raw, dict) else {}
        stored_mae_imp = _mae_improvement_from_json(
            parse_json_field(job.get("baseline_scores_json")),
            parse_json_field(job.get("best_scores_json")),
        )

        print(f"\n=== Job #{job_id} offre #{offer_id} {name} ===")
        try:
            df = load_offer_history_df(offer_id, profile)
            assert_min_history(df, min_history)
        except Exception as exc:
            print(f"  [SKIP] historique : {exc}")
            continue

        t0 = time.perf_counter()
        try:
            base_scores = evaluate_params_walk_forward(df, baseline_params, horizon, n_cutoffs)
            best_scores = evaluate_params_walk_forward(df, best_params, horizon, n_cutoffs)
        except Exception as exc:
            print(f"  [SKIP] walk-forward : {exc}")
            continue
        elapsed = time.perf_counter() - t0

        wape_imp = improvement_pct(base_scores["wape_volume"], best_scores["wape_volume"])
        would_5 = should_auto_apply(
            base_scores["wape_volume"], best_scores["wape_volume"],
            {**optuna_cfg, "auto_apply": True, "auto_apply_min_mae_improvement_pct": 5},
        )
        would_3 = should_auto_apply(
            base_scores["wape_volume"], best_scores["wape_volume"],
            {**optuna_cfg, "auto_apply": True, "auto_apply_min_mae_improvement_pct": 3},
        )
        would_cur = should_auto_apply(
            base_scores["wape_volume"], best_scores["wape_volume"],
            {**optuna_cfg, "auto_apply": True},
        )
        print(
            f"  WAPE {base_scores['wape_volume']}% → {best_scores['wape_volume']}% "
            f"(Δ {wape_imp if wape_imp is not None else '—'} %)  {elapsed:.0f}s"
        )
        mae_txt = f"{stored_mae_imp:.2f}" if stored_mae_imp is not None else "—"
        print(f"  MAE% stocké (job d'origine) : {mae_txt}")
        print(f"  Passerait seuil 3%={would_3}  5%={would_5}  actuel={would_cur}")
        rows.append(
            {
                "job_id": job_id,
                "offer_id": offer_id,
                "wape_imp": wape_imp,
                "mae_imp_stored": stored_mae_imp,
                "wape_base": base_scores["wape_volume"],
                "wape_best": best_scores["wape_volume"],
            }
        )
        gc.collect()

    if not rows:
        print("\nAucune ligne scorée.")
        return 0

    imps = [r["wape_imp"] for r in rows if r["wape_imp"] is not None]
    print("\n======== Synthèse ========")
    header = f"{'job':<6} {'offre':<6} {'WAPE% Δ':>9} {'MAE% stocké':>12} {'WAPE base':>10} {'WAPE best':>10}"
    print(header)
    print("-" * len(header))
    for r in rows:
        wi = f"{r['wape_imp']:.2f}" if r["wape_imp"] is not None else "—"
        mi = f"{r['mae_imp_stored']:.2f}" if r["mae_imp_stored"] is not None else "—"
        print(
            f"{r['job_id']:<6} {r['offer_id']:<6} {wi:>9} {mi:>12} "
            f"{r['wape_base']:>10.2f} {r['wape_best']:>10.2f}"
        )
    if imps:
        imps_sorted = sorted(imps)
        mid = imps_sorted[len(imps_sorted) // 2]
        print(f"\nWAPE Δ : n={len(imps)}  min={min(imps):.2f}  médiane={mid:.2f}  max={max(imps):.2f}")
        for thr in (3.0, 5.0, 7.0, 10.0):
            n_pass = sum(1 for x in imps if x >= thr)
            print(f"  seuil {thr:.0f} % → {n_pass}/{len(imps)} jobs passeraient")
        print(
            "\nSi presque aucun job ne passe 5 %, baisser le seuil (ex. 3 %) "
            "ou laisser l'auto-apply OFF. Échantillon petit = incertitude élevée."
        )
    return 0


if __name__ == "__main__":
    sys.exit(main())
