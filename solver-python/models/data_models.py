# -*- coding: utf-8 -*-
# Modèles Pydantic partagés pour les solveurs

from __future__ import annotations

from typing import List, Dict, Optional, Any, Union
from pydantic import BaseModel, Field, validator


class _StrictConfig:
    extra = "forbid"


class FixedWork(BaseModel):
    """
    Représente une contrainte de travail imposé (ex: issu de la Rotation Passe 1.5).
    L'agent DOIT travailler sur ce créneau, et optionnellement sur une offre précise.
    """
    class Config(_StrictConfig):
        pass

    user_id: int
    start: str      # "HH:MM:SS"
    end: str        # "HH:MM:SS"
    offer_id: Optional[int] = None    # ID de l'offre (si disponible)
    offer_name: Optional[str] = None  # Nom de l'offre (pour mapping direct dans le solveur)
    type: str = "ROTATION"


class Agent(BaseModel):
    class Config(_StrictConfig):
        pass

    id: int
    name: Optional[str] = None
    site: Optional[str] = None
    skills: List[str] = Field(default_factory=list)
    availability_start_time: str = "09:00:00"
    availability_end_time: str = "17:00:00"
    earliest_end_time: Optional[str] = None
    # Intervalles d'indisponibilité (ex: activités fixes de la Passe 1)
    unavailable_intervals: Optional[List[Dict[str, Any]]] = None
    # Intervalles de télétravail
    remote_work_intervals: Optional[List[Dict[str, Any]]] = None
    # Heures de départ de repas "préférées"
    preferred_lunch_starts: Optional[List[str]] = None
    # Scores d'équité période (multi-jours)
    equity_scores: Optional[Dict[str, int]] = None
    # V2: capacité en heures (temps dispo hors absences / non-fixes)
    capacity_hours: Optional[float] = None
    # V2: prorata de présence (part de l'agent dans le total)
    target_ratio: Optional[float] = None
    # V2: réalisé cumulé par groupe d'équité (equity_group -> minutes) pour la boucle de rétroaction
    current_realization: Optional[Dict[str, float]] = None
    # V2: quota cible en minutes par groupe d'équité (equity_group -> minutes)
    target_quota_minutes: Optional[Dict[str, float]] = None


class Window(BaseModel):
    class Config(_StrictConfig):
        pass

    start: str
    end: str


class MinMaxOffer(BaseModel):
    class Config(_StrictConfig):
        pass

    offer: str
    min: int = 0
    max: Optional[int] = None

    @validator("offer")
    def _offer_non_empty(cls, v):
        if not isinstance(v, str) or v.strip() == "":
            raise ValueError("offer doit être une chaîne non vide")
        return v

    @validator("min")
    def _min_ge0(cls, v):
        if v < 0:
            raise ValueError("min doit être >= 0")
        return v

    @validator("max")
    def _max_ge_min(cls, v, values):
        if v is not None and "min" in values and v < values["min"]:
            raise ValueError("max doit être >= min")
        return v


class Problem(BaseModel):
    class Config(_StrictConfig):
        pass

    # Required
    offers: List[str]
    need_curve: Dict[str, Dict[str, int]]
    agents: List[Agent]

    # --- AJOUT : Liste des travaux imposés (Rotation) ---
    fixed_work: Optional[List[FixedWork]] = None

    # Optional params
    workday_start_time: str = "09:00:00"
    workday_end_time: str = "17:00:00"
    slot_minutes: int = 15
    strict_work_hours: bool = True

    am_break_window: Optional[Window] = None
    pm_break_window: Optional[Window] = None
    lunch_window: Optional[Window] = None

    break_duration_minutes: int = 15
    lunch_duration_minutes: int = 60

    min_max_offers: Optional[List[MinMaxOffer]] = None

    # Contrôle: activer/désactiver pauses AM/PM
    enable_am_pm_breaks: bool = True
    # Priorité d'offres
    priority_offers: Optional[List[str]] = None
    priority_shortage_multiplier: int = 5
    # Restreindre au besoin
    restrict_to_need_offers: Optional[List[str]] = None
    # Plafonner à need
    cap_to_need_offers: Optional[List[str]] = None
    # Équité intra-jour
    equity_offers: Optional[List[str]] = None
    weight_equity: int = 40

    # Équité "période" (multi-jours)
    period_equity_offers: Optional[List[str]] = None
    period_equity_scores: Optional[Dict[str, Dict[int, int]]] = None
    weight_period_equity: int = 0

    # Poids de l'objectif
    weight_shortage: int = 1000
    weight_surplus: int = 1
    weight_miss_am: int = 0
    weight_miss_pm: int = 0
    weight_miss_lunch: int = 0
    weight_hours_slack: int = 0
    weight_early_end: int = 0
    weight_late_work: int = 0
    weight_same_offer_windows: int = 300
    weight_break_alignment: int = 2
    weight_break_dispersion: int = 3
    
    debug_logging: bool = False
    forbid_midday_singletons: bool = False
    
    lunch_activity_name: str = "LUNCH"
    break_activity_name: str = "Pause"
    
    relative_gap_limit: float = 0.01

    @validator("offers")
    def _offers_strings(cls, v):
        if not all(isinstance(x, str) and x.strip() for x in v):
            raise ValueError("offers doit être une liste de chaînes non vides")
        return v

    @validator("slot_minutes")
    def _slot_divides_hour(cls, v):
        if v <= 0 or 60 % v != 0:
            raise ValueError("slot_minutes doit diviser 60 (ex: 5, 10, 15, 20, 30)")
        return v

    @validator("need_curve")
    def _need_curve_shape(cls, v, values):
        offers = values.get("offers", [])
        if not isinstance(v, dict):
            raise ValueError("need_curve doit être un objet {offre: {timekey: int}}")
        missing = [o for o in offers if o not in v]
        if missing:
            raise ValueError(f"need_curve manque des clés pour ces offres: {missing}")
        unknown = [o for o in v.keys() if o not in offers]
        if unknown:
            raise ValueError(f"need_curve contient des offres non déclarées dans offers: {unknown}")
        for off, curve in v.items():
            if not isinstance(curve, dict):
                raise ValueError(f"need_curve['{off}'] doit être un objet {{timekey:int}}")
            for tk, val in curve.items():
                if not isinstance(tk, str):
                    raise ValueError(f"Clé horaire invalide dans '{off}': attendu chaîne, reçu {type(tk).__name__}")
                if not isinstance(val, int) or val < 0:
                    raise ValueError(f"Valeur de besoin invalide pour '{off}' @ {tk}: attendu int >= 0")
        return v


class FixedActivity(BaseModel):
    class Config(_StrictConfig):
        pass

    offer_name: str
    start_time: str
    end_time: str
    quantity: int
    is_splittable: Optional[bool] = True
    equity_enabled: Optional[bool] = False
    period_equity_weight: Optional[int] = None
    equity_strength: Optional[int] = 0
    priority: int = 0
    active: bool = True
    blocks: Optional[List[Window]] = None
    lunch_overlap_allowed: bool = True
    lunch_attach_mode: str = "none"
    is_remote_work_compatible: bool = True
    base_offer_name: Optional[str] = None
    incompatible_base_offers: List[str] = Field(default_factory=list)
    # V2: meta-groupe d'équité (plusieurs activités partagent le même compteur)
    equity_group: Optional[str] = None


class FixedActivityProblem(BaseModel):
    class Config(_StrictConfig):
        pass

    agents: List[Agent]
    fixed_activities: List[FixedActivity]
    equity_scores: Union[Dict[str, Dict[int, int]], Dict[int, int]] = {}
    workday_start_time: str
    workday_end_time: str
    slot_minutes: int = 15
    lunch_window: Optional[Window] = None
    lunch_duration_minutes: int = 60
    am_break_window: Optional[Window] = None
    pm_break_window: Optional[Window] = None
    break_duration_minutes: int = 15
    enable_am_pm_breaks: bool = True
    lunch_activity_name: str = "LUNCH"
    break_activity_name: str = "Pause"
    enforce_remote_work_incompatibilities: bool = False
    debug_logging: bool = False
    relative_gap_limit: float = 0.01
    generation_date: Optional[str] = None  # Date du jour généré (Y-m-d), pour les logs


# =========================
# Modèles pour la Passe 1.5 (Rotation/Équité)
# =========================

class RotationAgent(BaseModel):
    class Config(_StrictConfig):
        pass

    id: int
    target_slots: int
    duration: int
    window_start: str
    window_end: str
    offer_id: int


class RotationRequest(BaseModel):
    class Config(_StrictConfig):
        pass

    agents: List[RotationAgent]
    date: str
    workday_start_time: str
    workday_end_time: str
    slot_minutes: int = 15


class RotationBlock(BaseModel):
    class Config(_StrictConfig):
        pass

    user_id: int
    offer_id: int
    start: str
    end: str


class RotationResponse(BaseModel):
    class Config(_StrictConfig):
        pass

    status: str
    blocks: List[RotationBlock]