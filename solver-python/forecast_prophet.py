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
    get_worked_days_from_db,
    load_historical_data,
    train_prophet_model,
)

app = FastAPI(title="Prophet Forecast Service", version="1.0.0")


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
    
    # Jours fériés
    use_french_holidays: bool = True
    custom_holidays: Optional[List[str]] = None
    
    # Plage de données historiques (dates exactes)
    # Si NULL → tout l'historique disponible
    history_start_date: Optional[str] = None  # Format: YYYY-MM-DD
    history_end_date: Optional[str] = None    # Format: YYYY-MM-DD


class WfmSettings(BaseModel):
    """Paramètres WFM pour formater les prévisions"""
    day_start_time: str = "09:00:00"
    day_end_time: str = "17:00:00"


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


# ================================
# Fonctions Helper (load/train → prophet_common)
# ================================


def is_working_day(date: datetime, worked_days: Optional[List[int]] = None) -> bool:
    """
    Vérifie si une date est un jour ouvré selon worked_days_json, hors fériés
    
    Args:
        date: Date à vérifier
        worked_days: Liste des jours travaillés (1=Lundi à 7=Dimanche)
                     Si None, charge depuis wfm_settings
    
    Returns:
        True si c'est un jour travaillé et non férié
    """
    from holidays import France
    
    # Charger les jours travaillés si non fournis
    if worked_days is None:
        worked_days = get_worked_days_from_db()
    
    # Python weekday(): 0=Lundi, ..., 6=Dimanche
    # worked_days: 1=Lundi, ..., 7=Dimanche
    day_of_week = date.weekday() + 1  # Convertir 0-6 en 1-7
    
    if day_of_week not in worked_days:
        return False
    
    # Vérifier les jours fériés français
    fr_holidays = France(years=date.year)
    if date.date() in fr_holidays:
        return False
    
    return True


def generate_forecast_for_date(
    model: Prophet,
    target_date: datetime,
    wfm_settings: WfmSettings,
    avg_dmt: int = 300,
    growth_cap: Optional[float] = None,
    worked_days: Optional[List[int]] = None
) -> Dict[str, ForecastPoint]:
    """
    Génère une prévision pour une date au pas de 15 minutes
    
    Args:
        model: Modèle Prophet entraîné
        target_date: Date cible
        wfm_settings: Paramètres WFM  
        avg_dmt: DMT moyenne
        growth_cap: Capacité maximale pour logistic growth
        worked_days: Liste des jours travaillés (1=Lundi à 7=Dimanche)
    
    Returns:
        Dict avec clés HH:MM:SS et valeurs ForecastPoint
    """
    # Charger les jours travaillés si non fournis
    if worked_days is None:
        worked_days = get_worked_days_from_db()
    
    # Vérifier si c'est un jour ouvré
    if not is_working_day(target_date, worked_days):
        print(f"[Prophet] {target_date.date()} est un jour non travaillé/férié → prévisions = 0")
        
        start_time = datetime.strptime(wfm_settings.day_start_time, "%H:%M:%S").time()
        end_time = datetime.strptime(wfm_settings.day_end_time, "%H:%M:%S").time()
        start_dt = datetime.combine(target_date.date(), start_time)
        end_dt = datetime.combine(target_date.date(), end_time)
        
        result = {}
        current = start_dt
        while current < end_dt:
            time_slot = current.strftime('%H:%M:%S')
            result[time_slot] = ForecastPoint(
                time_slot=time_slot,
                volume=0,
                volume_lower=0,
                volume_upper=0,
                dmt=avg_dmt
            )
            current += timedelta(minutes=15)
        
        return result
    
    # Générer les tranches de 15 minutes
    start_time = datetime.strptime(wfm_settings.day_start_time, "%H:%M:%S").time()
    end_time = datetime.strptime(wfm_settings.day_end_time, "%H:%M:%S").time()
    
    start_dt = datetime.combine(target_date.date(), start_time)
    end_dt = datetime.combine(target_date.date(), end_time)
    
    # Créer la liste des timestamps 15 minutes
    timestamps = []
    current = start_dt
    while current < end_dt:
        timestamps.append(current)
        current += timedelta(minutes=15)
    
    # Créer le DataFrame futur pour Prophet
    future = pd.DataFrame({'ds': timestamps})
    
    # Mode linear uniquement
    
    # Prédire avec Prophet
    forecast = model.predict(future)
    
    # Construire le résultat
    result = {}
    for idx, row in forecast.iterrows():
        time_slot = row['ds'].strftime('%H:%M:%S')
        
        # Arrondir et forcer >= 0 (volumes négatifs n'ont pas de sens)
        volume = max(0, int(round(row['yhat'])))
        
        result[time_slot] = ForecastPoint(
            time_slot=time_slot,
            volume=volume,
            volume_lower=max(0, int(round(row['yhat_lower']))),
            volume_upper=max(0, int(round(row['yhat_upper']))),
            dmt=avg_dmt
        )
    
    # Log
    total_volume = sum(p.volume for p in result.values())
    print(f"[Prophet] {target_date.date()}: {total_volume} appels prévus")
    
    return result


def calculate_metrics(model: Prophet, df: pd.DataFrame) -> Dict[str, float]:
    """
    Calcule les métriques de performance (MAPE, MAE, RMSE)
    
    Args:
        model: Modèle entraîné
        df: Données utilisées pour l'entraînement (doit contenir 'cap' si logistic)
    
    Returns:
        Dict avec métriques
    """
    # Préparer le DataFrame pour la prédiction
    df_for_predict = df[['ds']].copy()
    
    # Mode linear uniquement (pas de cap)
    
    # Prédire sur les données d'entraînement
    forecast = model.predict(df_for_predict)
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
            end_date=request.prophet_settings.history_end_date
        )
        
        # Calculer la DMT moyenne
        avg_dmt = int(df['dmt'].mean()) if 'dmt' in df.columns else 300
        
        # Préparer les données pour Prophet
        df_volume = df[['ds', 'y']].copy()
        
        # Entraîner le modèle
        model, df_used = train_prophet_model(df_volume, request.prophet_settings)
        
        # Générer la prévision
        target_date = datetime.strptime(request.date, '%Y-%m-%d')
        forecast = generate_forecast_for_date(
            model,
            target_date,
            request.wfm_settings,
            avg_dmt,
            None  # growth_cap retiré (mode linear uniquement)
        )
        
        # Calculer les métriques
        metrics = calculate_metrics(model, df_used)
        
        return ForecastResponse(
            success=True,
            offer_id=request.offer_id,
            date=request.date,
            forecast=forecast,
            metrics=metrics
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
            end_date=request.prophet_settings.history_end_date
        )
        
        # Calculer la DMT moyenne
        avg_dmt = int(df['dmt'].mean()) if 'dmt' in df.columns else 300
        
        # Préparer les données
        df_volume = df[['ds', 'y']].copy()
        
        # Entraîner le modèle UNE SEULE FOIS
        model, df_used = train_prophet_model(df_volume, request.prophet_settings)
        
        # Calculer les métriques
        metrics = calculate_metrics(model, df_used)
        
        # Générer les prévisions pour chaque jour
        start_date = datetime.strptime(request.start_date, '%Y-%m-%d')
        end_date = datetime.strptime(request.end_date, '%Y-%m-%d')
        
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
                avg_dmt,
                None  # Mode linear seulement
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
            'metrics': metrics
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
    print("Mode: Auto-reload activé")
    print("=" * 60)
    uvicorn.run(
        "forecast_prophet:app",
        host="127.0.0.1",
        port=8001,
        reload=True,
        log_level="info"
    )
