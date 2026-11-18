# -*- coding: utf-8 -*-
"""
Service de prévisions Prophet pour le système WFM
Génère des prévisions de volume d'appels et DMT en utilisant Prophet (Meta/Facebook)
"""

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field
from typing import Optional, List, Dict
from datetime import datetime, timedelta
import os
import pandas as pd
from prophet import Prophet
import json

from prophet_common import (
    get_db_connection,
    load_historical_data,
    train_prophet_model,
)
from dmt_profile import build_dmt_profile, dmt_for_datetime, fallback_profile, iter_day_slots
from prophet_series import (
    ANCHOR_LOOKBACK_DAYS,
    DAMP_ALERT_GAP,
    DAMP_HORIZON_DAYS,
    anchor_target,
    anchored_trend,
    apply_anchored_yhat,
    build_volume_shares,
    distribute_daily_volume,
    predict_frame,
    trend_relative_gap,
)

from service_auth import install_token_guard

app = FastAPI(title="Prophet Forecast Service", version="1.0.0")
install_token_guard(app)


# ================================
# Configuration BDD
# ================================
# DB_CONFIG / get_db_connection : prophet_common (réexport implicite via import)


# ================================
# Models Pydantic
# ================================

class ProphetSettings(BaseModel):
    """Paramètres de configuration Prophet - TOUS configurables par l'utilisateur"""
    
    # Saisonnalités
    yearly_seasonality: bool = True
    weekly_seasonality: bool = True
    monthly_seasonality: bool = False
    monthly_fourier_order: int = Field(default=5, ge=1, le=15)
    daily_seasonality: bool = True
    
    # Mode de saisonnalité (additive ou multiplicative)
    seasonality_mode: str = Field(default='additive', pattern='^(additive|multiplicative)$')
    
    # Sensibilités
    changepoint_prior_scale: float = Field(default=0.05, ge=0.001, le=0.5)
    seasonality_prior_scale: float = Field(default=10.0, ge=0.01, le=100.0)
    
    # Croissance (logistic retiré car instable sous Windows)
    growth: str = Field(default='linear', pattern='^(linear)$')
    n_changepoints: int = Field(default=25, ge=0, le=100)
    changepoint_range: float = Field(default=0.8, ge=0.5, le=0.95)
    
    # Ancien drapeau 25-5. Ignoré : le profil du mois est appris sur l'offre.
    use_calendar_peak_25_5: bool = False
    
    # Plage de données historiques (dates exactes)
    # Si NULL → tout l'historique disponible
    history_start_date: Optional[str] = None  # Format: YYYY-MM-DD
    history_end_date: Optional[str] = None    # Format: YYYY-MM-DD


class WfmSettings(BaseModel):
    """Paramètres WFM pour formater les prévisions. worked_days vient de PHP."""
    day_start_time: str = "09:00:00"
    day_end_time: str = "17:00:00"
    worked_days: List[int]
    closed_dates: List[str] = []


class ForecastRequest(BaseModel):
    """Requête pour générer une prévision"""
    offer_id: int
    date: str  # Format: YYYY-MM-DD
    prophet_settings: ProphetSettings
    wfm_settings: WfmSettings


class BatchForecastRequest(BaseModel):
    """Requête pour générer des prévisions batch"""
    offer_id: int
    start_date: str  # Format: YYYY-MM-DD
    end_date: str    # Format: YYYY-MM-DD
    prophet_settings: ProphetSettings
    wfm_settings: WfmSettings


class ForecastPoint(BaseModel):
    """Un point de prévision"""
    time_slot: str  # HH:MM:SS
    volume: int
    volume_lower: int
    volume_upper: int
    dmt: int


class ForecastResponse(BaseModel):
    """Réponse d'une prévision"""
    success: bool
    offer_id: int
    date: str
    forecast: Dict[str, ForecastPoint]
    metrics: Optional[Dict[str, float]] = None
    message: Optional[str] = None
    trend_damping: Optional[Dict] = None


# ================================
# Fonctions Helper (load/train → prophet_common)
# ================================


def is_working_day(
    date: datetime,
    worked_days: Optional[List[int]] = None,
    closed_dates: Optional[List[str]] = None,
) -> bool:
    """Jour de la trame PHP, hors dates fermées envoyées par PHP."""
    if not worked_days:
        raise ValueError("worked_days requis")

    day_of_week = date.weekday() + 1
    if day_of_week not in worked_days:
        return False

    closed = set(closed_dates or [])
    if date.strftime("%Y-%m-%d") in closed:
        return False

    return True


def _day_slots(wfm_settings: WfmSettings) -> List[str]:
    return iter_day_slots(wfm_settings.day_start_time, wfm_settings.day_end_time)


def _zero_forecast(
    target_date: datetime,
    wfm_settings: WfmSettings,
    dmt_profile: Dict,
) -> Dict[str, ForecastPoint]:
    result = {}
    for slot in _day_slots(wfm_settings):
        time_slot = f"{slot}:00" if len(slot) == 5 else slot
        result[time_slot] = ForecastPoint(
            time_slot=time_slot,
            volume=0,
            volume_lower=0,
            volume_upper=0,
            dmt=dmt_for_datetime(dmt_profile, target_date, time_slot),
        )
    return result


def damping_context(
    model: Prophet,
    t0: pd.Timestamp,
    settings: ProphetSettings,
    horizon_end: datetime,
) -> Dict:
    """
    trend(t0) et moyenne des jours d'apprentissage sur les 90 jours précédents.
    Relu après chaque fit : aucune de ces valeurs n'est figée dans le code.
    """
    t0 = pd.Timestamp(t0).normalize()
    end = pd.Timestamp(horizon_end).normalize()
    window_start = t0 - pd.Timedelta(days=ANCHOR_LOOKBACK_DAYS)
    history = pd.to_datetime(model.history["ds"]).dt.normalize()
    ref_days = sorted(set(history[(history >= window_start) & (history <= t0)].tolist()))
    days = list(ref_days)
    for extra in (t0, end):
        if extra not in days:
            days.append(extra)
    pred = model.predict(predict_frame(model, days))
    pred["ds"] = pd.to_datetime(pred["ds"])
    by_day = {pd.Timestamp(row.ds).normalize(): row for row in pred.itertuples(index=False)}
    trend_t0 = float(by_day[t0].trend)
    ref_values = [float(by_day[day].trend) for day in ref_days if day in by_day]
    trend_ref = float(sum(ref_values) / len(ref_values)) if ref_values else trend_t0
    decision = anchor_target(trend_t0, trend_ref)
    delta_end = max(0, (end - t0).days)
    used_end = anchored_trend(trend_t0, decision["target"], delta_end)
    end_gap = trend_relative_gap(float(by_day[end].trend), used_end)
    print(
        f"[Prophet] Ancrage {decision['branch']} "
        f"trend_t0={trend_t0:.3f} trend_ref={trend_ref:.3f} "
        f"écart={decision['gap_ref'] * 100:.2f}% cible={decision['target']:.3f}"
    )
    return {
        "t0": t0,
        "trend_t0": trend_t0,
        "trend_ref": trend_ref,
        "target": decision["target"],
        "branch": decision["branch"],
        "gap_ref": decision["gap_ref"],
        "end_gap": end_gap,
    }


def maybe_damping(
    model: Prophet,
    t0: datetime,
    settings: ProphetSettings,
    horizon_end: datetime,
) -> Optional[Dict]:
    """Ancre seulement si la fin demandée est à plus de 90 jours du dernier ds historique."""
    origin = pd.Timestamp(t0).normalize()
    if pd.isna(origin):
        return None
    if (pd.Timestamp(horizon_end).normalize() - origin).days <= DAMP_HORIZON_DAYS:
        return None
    return damping_context(model, origin, settings, horizon_end)


def damping_report(damping: Optional[Dict], horizon_end: datetime) -> Dict:
    end = pd.Timestamp(horizon_end).normalize()
    end_gap = 0.0 if damping is None else float(damping.get("end_gap", 0.0))
    branch = None if damping is None else damping.get("branch")
    alert = (
        damping is not None
        and end > pd.Timestamp(damping["t0"]).normalize()
        and end_gap > DAMP_ALERT_GAP
    )
    message = None
    if alert:
        message = (
            "Tendance longue ramenée vers la moyenne des 90 derniers jours "
            f"(écart de tendance en fin d'horizon {end_gap * 100:.1f} %)."
        )
    return {
        "enabled": damping is not None,
        "alert": alert,
        "branch": branch,
        "t0": None if damping is None else pd.Timestamp(damping["t0"]).date().isoformat(),
        "trend_t0": None if damping is None else round(float(damping["trend_t0"]), 4),
        "trend_ref": None if damping is None else round(float(damping["trend_ref"]), 4),
        "max_relative_gap": round(float(end_gap), 4),
        "message": message,
    }


def generate_forecast_for_date(
    model: Prophet,
    target_date: datetime,
    wfm_settings: WfmSettings,
    dmt_profile: Optional[Dict] = None,
    growth_cap: Optional[float] = None,
    worked_days: Optional[List[int]] = None,
    volume_shares: Optional[Dict] = None,
    damping: Optional[Dict] = None,
    prophet_settings: Optional[ProphetSettings] = None,
    damping_gaps: Optional[List[float]] = None,
    closed_dates: Optional[List[str]] = None,
) -> Dict[str, ForecastPoint]:
    """
    Génère une prévision pour une date au pas de 15 minutes
    
    Args:
        model: Modèle Prophet entraîné
        target_date: Date cible
        wfm_settings: Paramètres WFM  
        dmt_profile: Profil DMT (niveau + coefficients), figé pour le batch
        growth_cap: Capacité maximale pour logistic growth
        worked_days: Liste des jours travaillés (1=Lundi à 7=Dimanche)
    
    Returns:
        Dict avec clés HH:MM:SS et valeurs ForecastPoint
    """
    if dmt_profile is None:
        dmt_profile = fallback_profile()

    if not worked_days:
        raise ValueError("worked_days requis")

    if not is_working_day(target_date, worked_days, closed_dates):
        print(f"[Prophet] {target_date.date()} est un jour non travaillé/férié → prévisions = 0")
        return _zero_forecast(target_date, wfm_settings, dmt_profile)

    slots = _day_slots(wfm_settings)
    day = pd.Timestamp(target_date).normalize()
    row = model.predict(predict_frame(model, [day])).iloc[0]
    trend = float(row["trend"])
    multiplicative = float(row.get("multiplicative_terms", 0.0) or 0.0)
    additive = float(row.get("additive_terms", 0.0) or 0.0)
    if damping is not None:
        applied = apply_anchored_yhat(
            trend,
            multiplicative,
            additive,
            day.to_pydatetime(),
            t0=damping["t0"],
            trend_t0=float(damping["trend_t0"]),
            target=float(damping["target"]),
        )
        y_daily = applied["yhat"]
        if damping_gaps is not None:
            damping_gaps.append(applied["gap"])
    else:
        y_daily = float(row["yhat"])

    volumes = distribute_daily_volume(y_daily, (volume_shares or {}).get(day.isoweekday(), {}), slots)
    lower_ratio = 1.0
    upper_ratio = 1.0
    raw_yhat = float(row["yhat"])
    if raw_yhat > 1e-9:
        lower_ratio = max(0.0, float(row["yhat_lower"])) / raw_yhat
        upper_ratio = max(0.0, float(row["yhat_upper"])) / raw_yhat

    result = {}
    for slot in slots:
        time_slot = f"{slot}:00" if len(slot) == 5 else slot
        volume = int(volumes.get(time_slot, 0))
        result[time_slot] = ForecastPoint(
            time_slot=time_slot,
            volume=volume,
            volume_lower=max(0, int(round(volume * lower_ratio))),
            volume_upper=max(0, int(round(volume * upper_ratio))),
            dmt=dmt_for_datetime(dmt_profile, target_date, time_slot),
        )

    total_volume = sum(p.volume for p in result.values())
    print(f"[Prophet] {target_date.date()}: {total_volume} appels prévus")
    return result


def calculate_metrics(model: Prophet, df: pd.DataFrame, use_peak: bool = False) -> Dict[str, float]:
    """
    Calcule les métriques de performance (MAPE, MAE, RMSE)
    
    Args:
        model: Modèle entraîné
        df: Données utilisées pour l'entraînement (doit contenir 'cap' si logistic)
    
    Returns:
        Dict avec métriques
    """
    forecast = model.predict(predict_frame(model, df["ds"]))
    comparison = pd.merge(df[['ds', 'y']], forecast[['ds', 'yhat']], on='ds')
    comparison = comparison.dropna()
    
    if len(comparison) == 0:
        return {'mape': 0.0, 'mae': 0.0, 'rmse': 0.0}
    
    y_true = comparison['y']
    y_pred = comparison['yhat'].clip(lower=0)  # Pas de prédictions négatives
    
    # MAPE : filtrer heures creuses (< 3 appels/15min)
    mask = y_true >= 3
    if mask.sum() > 0:
        mape = (abs((y_true[mask] - y_pred[mask]) / y_true[mask])).mean() * 100
    else:
        mape = 0.0
    
    mae = abs(y_true - y_pred).mean()
    rmse = ((y_true - y_pred) ** 2).mean() ** 0.5
    
    print(f"[Prophet] Métriques: MAPE={mape:.2f}%, MAE={mae:.2f}, RMSE={rmse:.2f}")
    
    return {
        'mape': round(float(mape), 2),
        'mae': round(float(mae), 2),
        'rmse': round(float(rmse), 2)
    }


# ================================
# Endpoints FastAPI
# ================================

@app.get("/health")
def health():
    """Endpoint de santé du service"""
    return {
        "status": "ok",
        "service": "Prophet Forecast Service",
        "version": "1.0.0",
        "library": "prophet"
    }


@app.post("/forecast/generate", response_model=ForecastResponse)
def generate_forecast(request: ForecastRequest):
    """
    Génère une prévision Prophet pour une offre et une date (intervalles 15 min)
    """
    try:
        # Charger les données historiques avec la plage définie
        df = load_historical_data(
            request.offer_id,
            start_date=request.prophet_settings.history_start_date,
            end_date=request.prophet_settings.history_end_date,
            worked_days=request.wfm_settings.worked_days,
            closed_dates=request.wfm_settings.closed_dates,
        )

        # Profil DMT sur le DataFrame brut 15 min, avant tout agrégat Prophet
        dmt_profile = build_dmt_profile(
            df,
            history_end=request.prophet_settings.history_end_date,
            day_start=request.wfm_settings.day_start_time,
            day_end=request.wfm_settings.day_end_time,
        )
        
        # Préparer les données pour Prophet
        df_volume = df[['ds', 'y']].copy()
        
        model, df_used = train_prophet_model(
            df_volume,
            request.prophet_settings,
            closed_dates=request.wfm_settings.closed_dates,
        )

        target_date = datetime.strptime(request.date, '%Y-%m-%d')
        shares = build_volume_shares(
            df_volume,
            iter_day_slots(request.wfm_settings.day_start_time, request.wfm_settings.day_end_time),
        )
        damping = maybe_damping(
            model, df_volume["ds"].max(), request.prophet_settings, target_date
        )
        forecast = generate_forecast_for_date(
            model,
            target_date,
            request.wfm_settings,
            dmt_profile,
            None,
            worked_days=request.wfm_settings.worked_days,
            volume_shares=shares,
            damping=damping,
            prophet_settings=request.prophet_settings,
            closed_dates=request.wfm_settings.closed_dates,
        )

        metrics = calculate_metrics(
            model, df_used, use_peak=bool(request.prophet_settings.use_calendar_peak_25_5)
        )
        report = damping_report(damping, target_date)

        return ForecastResponse(
            success=True,
            offer_id=request.offer_id,
            date=request.date,
            forecast=forecast,
            metrics=metrics,
            trend_damping=report,
            message=report.get("message"),
        )
        
    except Exception as e:
        print(f"[Prophet] ERREUR: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/forecast/batch")
def generate_batch_forecast(request: BatchForecastRequest):
    """
    Génère des prévisions Prophet pour une offre sur une période
    """
    try:
        # DEBUG: Logger les paramètres reçus
        print(f"[Prophet API] === Batch request received ===")
        print(f"[Prophet API] Offer ID: {request.offer_id}")
        print(f"[Prophet API] Period: {request.start_date} → {request.end_date}")
        print(f"[Prophet API] Growth type: {request.prophet_settings.growth}")
        
        # Mode linear uniquement
        # Charger les données historiques avec la plage définie
        df = load_historical_data(
            request.offer_id,
            start_date=request.prophet_settings.history_start_date,
            end_date=request.prophet_settings.history_end_date,
            worked_days=request.wfm_settings.worked_days,
            closed_dates=request.wfm_settings.closed_dates,
        )

        # Profil DMT sur le DataFrame brut 15 min, avant tout agrégat Prophet
        dmt_profile = build_dmt_profile(
            df,
            history_end=request.prophet_settings.history_end_date,
            day_start=request.wfm_settings.day_start_time,
            day_end=request.wfm_settings.day_end_time,
        )
        
        # Préparer les données
        df_volume = df[['ds', 'y']].copy()
        
        model, df_used = train_prophet_model(
            df_volume,
            request.prophet_settings,
            closed_dates=request.wfm_settings.closed_dates,
        )

        metrics = calculate_metrics(
            model, df_used, use_peak=bool(request.prophet_settings.use_calendar_peak_25_5)
        )

        start_date = datetime.strptime(request.start_date, '%Y-%m-%d')
        end_date = datetime.strptime(request.end_date, '%Y-%m-%d')
        shares = build_volume_shares(
            df_volume,
            iter_day_slots(request.wfm_settings.day_start_time, request.wfm_settings.day_end_time),
        )
        damping = maybe_damping(
            model, df_volume["ds"].max(), request.prophet_settings, end_date
        )

        print(f"[Prophet] Génération batch de {start_date.date()} à {end_date.date()}")
        print(f"[Prophet] Modèle: growth={model.growth}")
        
        # Mode linear uniquement (logistic retiré)
        results = []
        current_date = start_date
        day_count = 0
        total_days = (end_date - start_date).days + 1
        
        while current_date <= end_date:
            day_count += 1
            print(f"[Prophet] === Jour {day_count}/{total_days}: {current_date.date()} ===")
            
            forecast = generate_forecast_for_date(
                model,
                current_date,
                request.wfm_settings,
                dmt_profile,
                None,
                worked_days=request.wfm_settings.worked_days,
                volume_shares=shares,
                damping=damping,
                prophet_settings=request.prophet_settings,
                closed_dates=request.wfm_settings.closed_dates,
            )
            
            results.append({
                'date': current_date.strftime('%Y-%m-%d'),
                'forecast': forecast
            })
            
            current_date += timedelta(days=1)
        
        print(f"[Prophet] ✓ Batch généré avec succès: {len(results)} jours")
        
        return {
            'success': True,
            'offer_id': request.offer_id,
            'start_date': request.start_date,
            'end_date': request.end_date,
            'results': results,
            'metrics': metrics,
            'dmt_profile': dmt_profile,
            'trend_damping': damping_report(damping, end_date),
        }
        
    except Exception as e:
        import traceback
        print(f"[Prophet] ❌ ERREUR batch: {str(e)}")
        print(f"[Prophet] Traceback complet:")
        traceback.print_exc()
        raise HTTPException(status_code=500, detail=str(e))


@app.get("/forecast/test")
def test_connection():
    """Endpoint de test pour vérifier la connexion BDD et Prophet"""
    try:
        # Test connexion BDD
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("SELECT COUNT(*) FROM historical_data")
        count = cursor.fetchone()[0]
        cursor.close()
        conn.close()
        
        # Test Prophet
        import prophet
        
        return {
            "status": "ok",
            "database": {
                "connected": True,
                "historical_data_count": count
            },
            "prophet": {
                "version": prophet.__version__,
                "available": True
            }
        }
    except Exception as e:
        return {
            "status": "error",
            "error": str(e)
        }


# ================================
# Main
# ================================

if __name__ == "__main__":
    import uvicorn
    print("=" * 60)
    print("Prophet Forecast Service")
    print("=" * 60)
    print("Démarrage sur http://localhost:8001")
    print("Documentation: http://localhost:8001/docs")
    print("=" * 60)
    uvicorn.run(
        "forecast_prophet:app",
        host=os.environ.get("FORECAST_HOST", "127.0.0.1"),
        port=8001,
        reload=os.environ.get("FORECAST_RELOAD") == "1",
        log_level="info"
    )
