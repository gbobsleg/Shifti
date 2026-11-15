# -*- coding: utf-8 -*-
"""
Logique Prophet partagée (chargement historique + entraînement).
Utilisée par forecast_prophet.py et le worker Optuna.
"""

from __future__ import annotations

import json
import logging
import os
import sys
from typing import Any, List, Optional, Tuple

import mysql.connector
import pandas as pd
from prophet import Prophet

from prophet_series import (
    CPS_MAX,
    CPS_MIN,
    DOW_PROFILE_COL,
    MONTH_PROFILE_COL,
    N_CHANGEPOINTS,
    PROFILE_PRIOR,
    SPS_MAX,
    SPS_MIN,
    clamp_prior,
    prepare_training_frame,
)

# Variables d'environnement (Docker) avec repli local WAMP.
DB_CONFIG = {
    "host": os.environ.get("DB_HOST", "localhost"),
    "user": os.environ.get("DB_USER", "root"),
    "password": os.environ.get("DB_PASSWORD", ""),
    "database": os.environ.get("DB_NAME", "cake_planning"),
}

def get_db_connection():
    """Crée une connexion MySQL."""
    return mysql.connector.connect(**DB_CONFIG)


def read_calendar_from_db() -> Tuple[List[int], List[str]]:
    """
    Repli explicite (worker sans snapshot, scripts). Pas un défaut de load_historical_data.
    worked_days du premier profil WFM, dates calendar_exceptions is_open = 0.
    """
    conn = get_db_connection()
    try:
        cursor = conn.cursor()
        cursor.execute(
            "SELECT worked_days_json FROM wfm_settings ORDER BY id ASC LIMIT 1"
        )
        row = cursor.fetchone()
        worked_days = [1, 2, 3, 4, 5]
        if row and row[0]:
            raw_value = row[0]
            parsed = json.loads(raw_value) if isinstance(raw_value, str) else raw_value
            if isinstance(parsed, list) and parsed:
                worked_days = [int(d) for d in parsed]
        cursor.execute(
            "SELECT DATE_FORMAT(`date`, '%Y-%m-%d') FROM calendar_exceptions WHERE is_open = 0"
        )
        closed = [str(item[0]) for item in cursor.fetchall()]
        cursor.close()
        return worked_days, closed
    finally:
        conn.close()


def load_historical_data(
    offer_id: int,
    start_date: Optional[str] = None,
    end_date: Optional[str] = None,
    worked_days: Optional[List[int]] = None,
    closed_dates: Optional[List[str]] = None,
) -> pd.DataFrame:
    """
    Charge les données historiques (intervalles 15 minutes).

    worked_days est obligatoire : PHP (ou read_calendar_from_db) doit le fournir.

    Returns:
        DataFrame colonnes: ds, y (volume), dmt
    """
    if not worked_days:
        raise ValueError("worked_days requis")

    conn = get_db_connection()

    query = """
        SELECT
            datetime_interval as ds,
            call_volume as y,
            avg_handle_time_seconds as dmt
        FROM historical_data
        WHERE offer_id = %s
    """
    params: list = [offer_id]

    if start_date:
        query += " AND datetime_interval >= %s"
        params.append(start_date + " 00:00:00")

    if end_date:
        query += " AND datetime_interval <= %s"
        params.append(end_date + " 23:59:59")

    # MySQL WEEKDAY: 0=Lundi … 6=Dimanche ; worked_days: 1=Lundi … 7=Dimanche
    if len(worked_days) < 7:
        mysql_worked_days = [d - 1 for d in worked_days]
        query += f" AND WEEKDAY(datetime_interval) IN ({','.join(map(str, mysql_worked_days))})"

    query += " ORDER BY datetime_interval"

    df = pd.read_sql(query, conn, params=params)
    conn.close()

    if df.empty:
        raise ValueError(f"Aucune donnée historique trouvée pour offer_id={offer_id}")

    df["ds"] = pd.to_datetime(df["ds"])
    if closed_dates:
        closed = {pd.Timestamp(value).normalize() for value in closed_dates}
        df = df.loc[~df["ds"].dt.normalize().isin(closed)].reset_index(drop=True)
        if df.empty:
            raise ValueError(f"Aucune donnée historique hors jours fermés pour offer_id={offer_id}")

    total_points = len(df)
    zero_points = (df["y"] == 0).sum()
    non_zero_points = total_points - zero_points
    pct_zero = (zero_points / total_points * 100) if total_points > 0 else 0

    period_info = "tout l'historique"
    if start_date or end_date:
        period_info = f"plage {start_date or 'début'} → {end_date or 'fin'}"

    day_names = {1: "Lun", 2: "Mar", 3: "Mer", 4: "Jeu", 5: "Ven", 6: "Sam", 7: "Dim"}
    worked_days_str = ",".join([day_names.get(d, str(d)) for d in sorted(worked_days)])

    print(f"[Prophet] {total_points} points 15min chargés ({period_info}, jours: {worked_days_str})")
    print(f"[Prophet] Période réelle: {df['ds'].min()} → {df['ds'].max()}")
    print(
        f"[Prophet] Distribution: {non_zero_points} points > 0 ({100 - pct_zero:.1f}%), "
        f"{zero_points} points = 0 ({pct_zero:.1f}%)"
    )
    print(
        f"[Prophet] Volume 15min: min={df['y'].min()}, max={df['y'].max()}, "
        f"médiane={df['y'].median():.1f}"
    )

    if pct_zero > 50:
        print(f"[Prophet] ⚠️ ATTENTION : {pct_zero:.1f}% de zéros - prévisions risquent d'être imprécises")

    return df


def train_prophet_model(
    df: pd.DataFrame,
    settings: Any,
    *,
    verbose: bool = True,
    closed_dates: Optional[List[str]] = None,
) -> Tuple[Prophet, pd.DataFrame]:
    """
    Entraîne un modèle Prophet.

    Args:
        df: DataFrame avec colonnes ds, y
        settings: objet duck-typed (ProphetSettings Pydantic ou SimpleNamespace).
            Les priors et n_changepoints sont clampés ici, les fériés sont exclus.
        verbose: logs détaillés (désactiver pendant Optuna)
    """
    # Grain journalier : la forme intra-jour est une ventilation, pas une saisonnalité Prophet.
    settings.changepoint_prior_scale = clamp_prior(
        getattr(settings, "changepoint_prior_scale", 0.05), CPS_MIN, CPS_MAX
    )
    settings.seasonality_prior_scale = clamp_prior(
        getattr(settings, "seasonality_prior_scale", 10.0), SPS_MIN, SPS_MAX
    )
    settings.n_changepoints = N_CHANGEPOINTS
    settings.monthly_seasonality = False
    settings.weekly_seasonality = False

    dates = closed_dates if closed_dates is not None else getattr(settings, "closed_dates", None)
    df_to_use, profiles = prepare_training_frame(df, closed_dates=dates)

    if verbose:
        print(f"[Prophet] Entraînement: {len(df_to_use)} jours ouvrés (hors fériés, y>0)")
        print(
            f"[Prophet] Stats journalières: min={df_to_use['y'].min()}, max={df_to_use['y'].max()}, "
            f"moyenne={df_to_use['y'].mean():.2f}"
        )
        print(
            f"[Prophet] Paramètres: seasonality_mode={settings.seasonality_mode}, "
            f"growth={settings.growth}"
        )
        print(
            f"[Prophet] Changepoints: n={settings.n_changepoints}, "
            f"prior_scale={settings.changepoint_prior_scale}"
        )
        print(f"[Prophet] Seasonality prior scale: {settings.seasonality_prior_scale}")
        if profiles["use_dow"] or profiles["use_month"]:
            print(
                "[Prophet] Profils appris: "
                f"semaine={'oui' if profiles['use_dow'] else 'non'}, "
                f"mois={'oui' if profiles['use_month'] else 'non'}"
            )

    model = Prophet(
        seasonality_mode=settings.seasonality_mode,
        yearly_seasonality=settings.yearly_seasonality,
        weekly_seasonality=False,
        daily_seasonality=False,
        changepoint_prior_scale=settings.changepoint_prior_scale,
        seasonality_prior_scale=settings.seasonality_prior_scale,
        growth=settings.growth,
        n_changepoints=settings.n_changepoints,
        changepoint_range=settings.changepoint_range,
        interval_width=0.80,
    )

    logging.getLogger("prophet").setLevel(logging.WARNING)
    logging.getLogger("cmdstanpy").setLevel(logging.WARNING)

    if profiles["use_dow"]:
        model.add_regressor(
            DOW_PROFILE_COL,
            mode="multiplicative",
            prior_scale=PROFILE_PRIOR,
            standardize=True,
        )
    if profiles["use_month"]:
        model.add_regressor(
            MONTH_PROFILE_COL,
            mode="multiplicative",
            prior_scale=PROFILE_PRIOR,
            standardize=True,
        )
    model.offer_profiles = profiles

    if verbose:
        print("[Prophet] Fériés retirés de l'entraînement (la sortie WFM les force à 0)")

    old_stderr = sys.stderr
    sys.stderr = open(os.devnull, "w")
    try:
        model.fit(df_to_use)
    finally:
        sys.stderr.close()
        sys.stderr = old_stderr

    if verbose:
        print("[Prophet] ✓ Modèle entraîné avec succès")

    return model, df_to_use
