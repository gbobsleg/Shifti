# -*- coding: utf-8 -*-
"""
Worker asynchrone Optuna pour le tuning Prophet par offre.

Boucle: claim job queued → backtest + Optuna → brouillon / auto-apply → gc.
Pas de serveur HTTP (évite les timeouts sur runs longs).

Usage:
  python prophet_tuning_worker.py
  python prophet_tuning_worker.py --once
  python prophet_tuning_worker.py --sleep 10
"""

from __future__ import annotations

import argparse
import gc
import json
import sys
import time
import traceback
from typing import Any, Dict, Optional

from prophet_common import get_db_connection
from prophet_tuning_core import (
    JobCancelled,
    assert_min_history,
    build_prophet_params_from_offer,
    describe_seasonality_adaptation,
    evaluate_params_walk_forward,
    history_span_days,
    improvement_pct,
    load_offer_history_df,
    merge_optuna_settings,
    parse_json_field,
    run_optuna_search,
    scores_for_storage,
    should_auto_apply,
)

# Sentinel : injecte UTC_TIMESTAMP() en SQL (jamais datetime.now() / NOW() local).
_UTC_TIMESTAMP = object()


def _json_dumps(value: Any) -> str:
    return json.dumps(value, ensure_ascii=False, default=str)


def reclaim_orphaned_running() -> int:
    """
    Au démarrage worker : marque les running orphelins en failed.
    Ne reprend / ne relance pas le travail.
    """
    conn = get_db_connection()
    try:
        cursor = conn.cursor()
        cursor.execute(
            """
            UPDATE prophet_tuning_jobs
            SET status = 'failed',
                finished_at = UTC_TIMESTAMP(),
                modified = UTC_TIMESTAMP(),
                error_message = %s
            WHERE status = 'running'
            """,
            (
                "Worker redémarré — job interrompu (orphelin). "
                "Relancer manuellement ou via le cron si besoin.",
            ),
        )
        conn.commit()
        n = int(cursor.rowcount or 0)
        cursor.close()
        return n
    finally:
        conn.close()


def _fetch_job_status(conn, job_id: int) -> Optional[str]:
    cursor = conn.cursor()
    cursor.execute(
        "SELECT status FROM prophet_tuning_jobs WHERE id = %s",
        (job_id,),
    )
    row = cursor.fetchone()
    cursor.close()
    if not row:
        return None
    return str(row[0])


def _is_cancelled(conn, job_id: int) -> bool:
    return _fetch_job_status(conn, job_id) == "cancelled"


def claim_next_job() -> Optional[int]:
    """
    Claim atomique en deux temps (évite MySQL 1093).
    1) SELECT id WHERE status=queued ORDER BY id LIMIT 1
    2) UPDATE … WHERE id=X AND status=queued
    Refuse de claim s'il existe déjà un job running (1 job à la fois).
    """
    conn = get_db_connection()
    try:
        cursor = conn.cursor()

        cursor.execute(
            "SELECT COUNT(*) FROM prophet_tuning_jobs WHERE status = 'running'"
        )
        running_count = int(cursor.fetchone()[0])
        if running_count > 0:
            cursor.close()
            return None

        cursor.execute(
            """
            SELECT id
            FROM prophet_tuning_jobs
            WHERE status = 'queued'
            ORDER BY id ASC
            LIMIT 1
            """
        )
        row = cursor.fetchone()
        if not row:
            cursor.close()
            return None

        job_id = int(row[0])
        cursor.execute(
            """
            UPDATE prophet_tuning_jobs
            SET status = 'running',
                started_at = UTC_TIMESTAMP(),
                modified = UTC_TIMESTAMP(),
                error_message = NULL
            WHERE id = %s AND status = 'queued'
            """,
            (job_id,),
        )
        conn.commit()
        claimed = cursor.rowcount == 1
        cursor.close()
        return job_id if claimed else None
    finally:
        conn.close()


def _fetch_job(conn, job_id: int) -> Optional[Dict[str, Any]]:
    cursor = conn.cursor(dictionary=True)
    cursor.execute(
        """
        SELECT j.*,
               o.prophet_default_settings_json AS offer_prophet_json
        FROM prophet_tuning_jobs j
        INNER JOIN offers o ON o.id = j.offer_id
        WHERE j.id = %s
        """,
        (job_id,),
    )
    row = cursor.fetchone()
    cursor.close()
    return row


def _fetch_wfm_optuna_settings(conn) -> Dict[str, Any]:
    cursor = conn.cursor()
    cursor.execute("SELECT optuna_settings_json FROM wfm_settings LIMIT 1")
    row = cursor.fetchone()
    cursor.close()
    raw = parse_json_field(row[0]) if row else None
    return merge_optuna_settings(raw if isinstance(raw, dict) else None)


def _update_job(
    conn,
    job_id: int,
    fields: Dict[str, Any],
    *,
    only_if_status: Optional[str] = None,
) -> int:
    if not fields:
        return 0
    cols = []
    vals = []
    for key, value in fields.items():
        if value is _UTC_TIMESTAMP:
            cols.append(f"`{key}` = UTC_TIMESTAMP()")
        else:
            cols.append(f"`{key}` = %s")
            vals.append(value)
    cols.append("`modified` = UTC_TIMESTAMP()")
    vals.append(job_id)
    sql = f"UPDATE prophet_tuning_jobs SET {', '.join(cols)} WHERE id = %s"
    if only_if_status is not None:
        sql += " AND status = %s"
        vals.append(only_if_status)
    cursor = conn.cursor()
    cursor.execute(sql, tuple(vals))
    conn.commit()
    n = int(cursor.rowcount or 0)
    cursor.close()
    return n


def _fail_job(conn, job_id: int, message: str) -> None:
    if _is_cancelled(conn, job_id):
        print(f"[OptunaWorker] Job #{job_id} déjà cancelled — skip fail")
        return
    _update_job(
        conn,
        job_id,
        {
            "status": "failed",
            "error_message": message[:65000],
            "finished_at": _UTC_TIMESTAMP,
        },
    )
    _touch_offer_last_run(conn, job_id)


def _touch_offer_last_run(conn, job_id: int) -> None:
    cursor = conn.cursor()
    cursor.execute("SELECT offer_id FROM prophet_tuning_jobs WHERE id = %s", (job_id,))
    row = cursor.fetchone()
    if not row:
        cursor.close()
        return
    offer_id = int(row[0])
    cursor.execute(
        """
        UPDATE offers
        SET prophet_tuning_last_run_at = UTC_TIMESTAMP(),
            prophet_tuning_last_job_id = %s
        WHERE id = %s
        """,
        (job_id, offer_id),
    )
    conn.commit()
    cursor.close()


def _write_draft(conn, offer_id: int, draft_params: dict, draft_scores: dict) -> None:
    cursor = conn.cursor()
    cursor.execute(
        """
        UPDATE offers
        SET prophet_tuning_draft_json = %s,
            prophet_tuning_draft_scores_json = %s
        WHERE id = %s
        """,
        (_json_dumps(draft_params), _json_dumps(draft_scores), offer_id),
    )
    conn.commit()
    cursor.close()


def _auto_apply_to_offer(
    conn,
    offer_id: int,
    current_official: Optional[dict],
    new_params: dict,
) -> None:
    """previous ← official ; official ← new ; clear draft."""
    cursor = conn.cursor()
    previous = _json_dumps(current_official) if current_official else None
    cursor.execute(
        """
        UPDATE offers
        SET prophet_tuning_previous_json = %s,
            prophet_default_settings_json = %s,
            prophet_tuning_draft_json = NULL,
            prophet_tuning_draft_scores_json = NULL
        WHERE id = %s
        """,
        (previous, _json_dumps(new_params), offer_id),
    )
    conn.commit()
    cursor.close()


def process_job(job_id: int) -> None:
    """Exécute un job de tuning de bout en bout."""
    conn = get_db_connection()
    study = None
    df = None

    try:
        job = _fetch_job(conn, job_id)
        if not job:
            print(f"[OptunaWorker] Job #{job_id} introuvable")
            return

        offer_id = int(job["offer_id"])
        print(f"[OptunaWorker] Job #{job_id} offer_id={offer_id} — démarrage")

        snapshot = parse_json_field(job.get("config_snapshot_json"))
        if isinstance(snapshot, dict) and snapshot:
            optuna_cfg = merge_optuna_settings(snapshot)
        else:
            optuna_cfg = _fetch_wfm_optuna_settings(conn)
            _update_job(
                conn,
                job_id,
                {"config_snapshot_json": _json_dumps(optuna_cfg)},
            )

        offer_raw = parse_json_field(job.get("offer_prophet_json"))
        offer_profile = offer_raw if isinstance(offer_raw, dict) else {}

        try:
            df = load_offer_history_df(offer_id, offer_profile)
            assert_min_history(df, int(optuna_cfg["min_history_days"]))
        except Exception as e:
            _fail_job(conn, job_id, str(e))
            print(f"[OptunaWorker] Job #{job_id} failed (historique): {e}")
            return

        span_days = history_span_days(df)
        seasonality_adapt = describe_seasonality_adaptation(span_days)
        baseline_params = build_prophet_params_from_offer(
            offer_profile, history_span_days=span_days
        )
        print(
            f"[OptunaWorker] Historique {span_days} j — "
            f"yearly={'ON' if seasonality_adapt['yearly_seasonality'] else 'OFF'}, "
            f"monthly={'ON' if seasonality_adapt['monthly_seasonality'] else 'OFF'}"
        )

        horizon = int(optuna_cfg["test_horizon_days"])
        n_cutoffs = int(optuna_cfg.get("n_cutoffs", 3))
        n_trials = int(optuna_cfg["n_trials"])

        _update_job(
            conn,
            job_id,
            {
                "progress_trials_done": 0,
                "progress_trials_total": n_trials,
                "baseline_params_json": _json_dumps(baseline_params),
            },
        )

        try:
            baseline_scores_raw = evaluate_params_walk_forward(
                df, baseline_params, horizon, n_cutoffs
            )
        except Exception as e:
            _fail_job(conn, job_id, f"Échec évaluation baseline: {e}")
            print(f"[OptunaWorker] Job #{job_id} failed (baseline): {e}")
            return

        baseline_scores = scores_for_storage(baseline_scores_raw, horizon, n_cutoffs)
        _update_job(
            conn,
            job_id,
            {"baseline_scores_json": _json_dumps(baseline_scores)},
        )
        print(
            f"[OptunaWorker] Baseline WAPE={baseline_scores['wape_volume']}% "
            f"MAE={baseline_scores['mae_volume']} "
            f"MAPE={baseline_scores['mape_volume']}%"
        )

        def on_progress(done: int, total: int, best_wape: Optional[float]) -> None:
            fields: Dict[str, Any] = {
                "progress_trials_done": done,
                "progress_trials_total": total,
            }
            if best_wape is not None:
                fields["best_mae_so_far"] = float(best_wape)
            best_txt = f"{best_wape:.2f}%" if best_wape is not None else "—"
            print(f"[OptunaWorker] trial {done}/{total} (best WAPE={best_txt})")
            # Reconnexion courte pour ne pas tenir la conn pendant des heures
            progress_conn = get_db_connection()
            try:
                if _is_cancelled(progress_conn, job_id):
                    return
                _update_job(progress_conn, job_id, fields)
            finally:
                progress_conn.close()

        def cancel_check() -> bool:
            check_conn = get_db_connection()
            try:
                return _is_cancelled(check_conn, job_id)
            finally:
                check_conn.close()

        try:
            best_params, best_scores_raw, study = run_optuna_search(
                df,
                offer_profile,
                optuna_cfg,
                progress_callback=on_progress,
                cancel_check=cancel_check,
            )
        except JobCancelled as e:
            print(f"[OptunaWorker] Job #{job_id} annulé — arrêt Optuna ({e})")
            return
        except Exception as e:
            _fail_job(conn, job_id, f"Échec Optuna: {e}")
            print(f"[OptunaWorker] Job #{job_id} failed (optuna): {e}")
            traceback.print_exc()
            return

        if _is_cancelled(conn, job_id):
            print(f"[OptunaWorker] Job #{job_id} cancelled — skip completed")
            return

        best_scores = scores_for_storage(best_scores_raw, horizon, n_cutoffs)
        wape_imp = improvement_pct(
            float(baseline_scores["wape_volume"]),
            float(best_scores["wape_volume"]),
        )
        mae_imp = improvement_pct(
            float(baseline_scores["mae_volume"]),
            float(best_scores["mae_volume"]),
        )
        draft_scores = {
            "baseline": baseline_scores,
            "proposed": best_scores,
            "wape_improvement_pct": wape_imp,
            "mae_improvement_pct": mae_imp,
            "seasonality_adaptation": seasonality_adapt,
        }

        auto_applied = should_auto_apply(
            float(baseline_scores["wape_volume"]),
            float(best_scores["wape_volume"]),
            optuna_cfg,
        )

        if auto_applied:
            _auto_apply_to_offer(conn, offer_id, offer_profile or None, best_params)
            print(f"[OptunaWorker] Auto-apply effectué sur offer_id={offer_id}")
        else:
            _write_draft(conn, offer_id, best_params, draft_scores)
            print(f"[OptunaWorker] Brouillon écrit pour offer_id={offer_id}")

        if _is_cancelled(conn, job_id):
            print(f"[OptunaWorker] Job #{job_id} cancelled avant write completed — skip")
            return

        updated = _update_job(
            conn,
            job_id,
            {
                "status": "completed",
                "best_params_json": _json_dumps(best_params),
                "best_scores_json": _json_dumps(best_scores),
                "best_mae_so_far": float(best_scores["wape_volume"]),
                "progress_trials_done": n_trials,
                "progress_trials_total": n_trials,
                "auto_applied": 1 if auto_applied else 0,
                "finished_at": _UTC_TIMESTAMP,
                "error_message": None,
            },
            only_if_status="running",
        )
        if updated < 1:
            print(
                f"[OptunaWorker] Job #{job_id} non marqué completed "
                "(statut déjà changé, ex. cancelled)"
            )
            return
        _touch_offer_last_run(conn, job_id)
        print(
            f"[OptunaWorker] Job #{job_id} completed — "
            f"WAPE {baseline_scores['wape_volume']}% → {best_scores['wape_volume']}%"
        )

    except Exception as e:
        traceback.print_exc()
        try:
            _fail_job(conn, job_id, str(e))
        except Exception:
            print(f"[OptunaWorker] Impossible de marquer failed job #{job_id}: {e}")
    finally:
        # Libération mémoire Prophet / Optuna
        if study is not None:
            try:
                study_name = study.study_name
                del study
                print(f"[OptunaWorker] Study '{study_name}' libérée")
            except Exception:
                pass
        if df is not None:
            try:
                del df
            except Exception:
                pass
        try:
            conn.close()
        except Exception:
            pass
        gc.collect()


def run_loop(sleep_seconds: float = 5.0, once: bool = False) -> None:
    print("=" * 60)
    print("Prophet Optuna Tuning Worker")
    print(f"Sleep={sleep_seconds}s  once={once}")
    print("=" * 60)

    try:
        reclaimed = reclaim_orphaned_running()
        if reclaimed:
            print(
                f"[OptunaWorker] {reclaimed} job(s) running orphelin(s) "
                "marqué(s) failed au démarrage"
            )
    except Exception as e:
        print(f"[OptunaWorker] reclaim orphelins échoué: {e}")
        traceback.print_exc()

    while True:
        job_id = None
        try:
            job_id = claim_next_job()
            if job_id is not None:
                process_job(job_id)
            elif once:
                print("[OptunaWorker] Aucun job queued — fin (--once)")
                break
            else:
                time.sleep(sleep_seconds)
        except KeyboardInterrupt:
            print("\n[OptunaWorker] Arrêt demandé")
            break
        except Exception as e:
            print(f"[OptunaWorker] Erreur boucle: {e}")
            traceback.print_exc()
            time.sleep(sleep_seconds)
        finally:
            # Nettoyage explicite après chaque itération (fuite Stan/Optuna)
            if job_id is not None:
                gc.collect()

        if once and job_id is not None:
            break
        if once and job_id is None:
            break


def main(argv: Optional[list] = None) -> int:
    parser = argparse.ArgumentParser(description="Worker Optuna Prophet tuning")
    parser.add_argument(
        "--once",
        action="store_true",
        help="Traite au plus un job puis quitte",
    )
    parser.add_argument(
        "--sleep",
        type=float,
        default=5.0,
        help="Pause entre polls si file vide (secondes)",
    )
    args = parser.parse_args(argv)
    run_loop(sleep_seconds=args.sleep, once=args.once)
    return 0


if __name__ == "__main__":
    sys.exit(main())
