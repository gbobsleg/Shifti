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
from datetime import datetime
from typing import Any, List, Optional, Tuple

import mysql.connector
import pandas as pd
from prophet import Prophet

# Variables d'environnement (Docker) avec repli local WAMP.
DB_CONFIG = {
    "host": os.environ.get("DB_HOST", "localhost"),
    "user": os.environ.get("DB_USER", "root"),
    "password": os.environ.get("DB_PASSWORD", ""),
    "database": os.environ.get("DB_NAME", "cake_planning"),
}

_worked_days_cache = {
    "value": None,
    "timestamp": None,
    "ttl_seconds": 300,
}


def get_db_connection():
    """Crée une connexion MySQL."""
    return mysql.connector.connect(**DB_CONFIG)


def get_worked_days_from_db() -> List[int]:
    """
    Récupère les jours travaillés depuis wfm_settings.worked_days_json.
    Cache 5 minutes. Défaut: [1, 2, 3, 4, 5].
    """
    global _worked_days_cache

    now = datetime.now()
    if (
        _worked_days_cache["value"] is not None
        and _worked_days_cache["timestamp"] is not None
        and (now - _worked_days_cache["timestamp"]).total_seconds()
        < _worked_days_cache["ttl_seconds"]
    ):
        return _worked_days_cache["value"]

    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("SELECT worked_days_json FROM wfm_settings LIMIT 1")
        row = cursor.fetchone()
        cursor.close()
        conn.close()

        worked_days = [1, 2, 3, 4, 5]

        if row and row[0]:
            raw_value = row[0]
            if isinstance(raw_value, str):
                parsed = json.loads(raw_value)
            elif isinstance(raw_value, (list, tuple)):
                parsed = list(raw_value)
            else:
                parsed = None

            if parsed and isinstance(parsed, list):
                worked_days = [int(d) for d in parsed]

        _worked_days_cache["value"] = worked_days
        _worked_days_cache["timestamp"] = now

        print(f"[Prophet] Jours travaillés chargés depuis BDD: {worked_days}")
        return worked_days

    except Exception as e:
        print(f"[Prophet] ⚠️ Erreur lecture worked_days_json: {e} - utilisation défaut [1-5]")
        return [1, 2, 3, 4, 5]


def load_historical_data(
    offer_id: int,
    start_date: Optional[str] = None,
    end_date: Optional[str] = None,
    worked_days: Optional[List[int]] = None,
) -> pd.DataFrame:
    """
    Charge les données historiques (intervalles 15 minutes).

    Returns:
        DataFrame colonnes: ds, y (volume), dmt
    """
    if worked_days is None:
        worked_days = get_worked_days_from_db()

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


def _parse_daily_fourier_order(settings: Any) -> Optional[int]:
    """
    Si settings.daily_fourier_order est un entier > 0, on remplace le daily
    natif Prophet par add_seasonality(period=1). Sinon None = comportement actuel.
    """
    raw = getattr(settings, "daily_fourier_order", None)
    if raw is None:
        return None
    try:
        order = int(raw)
    except (TypeError, ValueError):
        return None
    if order <= 0:
        return None
    return order


def train_prophet_model(
    df: pd.DataFrame,
    settings: Any,
    *,
    verbose: bool = True,
) -> Tuple[Prophet, pd.DataFrame]:
    """
    Entraîne un modèle Prophet.

    Args:
        df: DataFrame avec colonnes ds, y
        settings: objet duck-typed (ProphetSettings Pydantic ou SimpleNamespace).
            Si `daily_fourier_order` > 0 : daily natif OFF + add_seasonality(period=1).
            Absent / None : comportement actuel (`settings.daily_seasonality`).
        verbose: logs détaillés (désactiver pendant Optuna)
    """
    df_to_use = df.copy()

    daily_fourier_order = _parse_daily_fourier_order(settings)
    use_custom_daily = daily_fourier_order is not None

    if verbose:
        print(f"[Prophet] Entraînement: {len(df_to_use)} points de 15min")
        print(
            f"[Prophet] Stats: min={df_to_use['y'].min()}, max={df_to_use['y'].max()}, "
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

    model = Prophet(
        seasonality_mode=settings.seasonality_mode,
        yearly_seasonality=settings.yearly_seasonality,
        weekly_seasonality=settings.weekly_seasonality,
        daily_seasonality=False if use_custom_daily else settings.daily_seasonality,
        changepoint_prior_scale=settings.changepoint_prior_scale,
        seasonality_prior_scale=settings.seasonality_prior_scale,
        growth=settings.growth,
        n_changepoints=settings.n_changepoints,
        changepoint_range=settings.changepoint_range,
        interval_width=0.80,
    )

    logging.getLogger("prophet").setLevel(logging.WARNING)
    logging.getLogger("cmdstanpy").setLevel(logging.WARNING)

    if settings.use_french_holidays:
        model.add_country_holidays(country_name="FR")
        if verbose:
            print("[Prophet] Jours fériés français ajoutés")

    if use_custom_daily:
        model.add_seasonality(
            name="daily",
            period=1,
            fourier_order=daily_fourier_order,
            prior_scale=settings.seasonality_prior_scale,
        )
        if verbose:
            print(
                f"[Prophet] Saisonnalité journalière custom "
                f"(period=1, fourier_order={daily_fourier_order})"
            )

    if settings.monthly_seasonality:
        model.add_seasonality(
            name="monthly",
            period=30.5,
            fourier_order=settings.monthly_fourier_order,
            prior_scale=settings.seasonality_prior_scale,
        )
        if verbose:
            print(
                f"[Prophet] Saisonnalité mensuelle ajoutée "
                f"(period=30.5, fourier_order={settings.monthly_fourier_order})"
            )

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
