# -*- coding: utf-8 -*-
from __future__ import annotations
from typing import List, Dict, Optional, Any
from fastapi import APIRouter
from pydantic import BaseModel
from datetime import datetime, timedelta
from ortools.sat.python import cp_model

router = APIRouter()

# --- Modèles Pydantic ---

class UnavailableInterval(BaseModel):
    """Intervalle d'indisponibilité (réunion, absence, activité fixe)."""
    start: str  # "HH:MM:SS"
    end: str    # "HH:MM:SS"


class RotationAgent(BaseModel):
    id: int
    offer_id: Optional[int] = None
    target_slots: int  # Nombre de shifts à placer cette semaine
    duration: int      # Durée en minutes (ex: 180)
    window_start: str  # "09:00:00"
    window_end: str    # "18:00:00"
    # Historique simplifié pour l'équité (jours travaillés semaine précédente)
    history_worked_days: List[int] = []
    # Contrainte de pause repas
    lunch_window_start: Optional[str] = None  # "12:00:00"
    lunch_window_end: Optional[str] = None    # "14:00:00"
    lunch_duration: int = 0  # Durée requise en minutes (ex: 60)
    # Indisponibilités par jour de la semaine (0=Lundi à 6=Dimanche)
    # Format: { "0": [{start: "10:00:00", end: "11:00:00"}], ... }
    # Note: Les clés sont des strings car JSON ne supporte pas les clés int
    unavailable_by_day: Optional[Dict[str, List[UnavailableInterval]]] = None 

class RotationRequest(BaseModel):
    date: str  # Date du Lundi de la semaine à planifier "YYYY-MM-DD"
    agents: List[RotationAgent]
    slot_minutes: int = 15
    # Nouvelle courbe de besoin : ID Offre -> Liste de floats (besoin par slot)
    need_curve: Optional[Dict[int, List[float]]] = None

class RotationBlock(BaseModel):
    user_id: int
    offer_id: Optional[int]
    start: str
    end: str
    day_index: int # 0=Lundi

class RotationResponse(BaseModel):
    status: str
    blocks: List[RotationBlock]
    debug_info: Optional[str] = None

# --- Helpers ---

def parse_time_to_slot(time_str: str, slot_step: int) -> int:
    """Convertit '09:30:00' en index de slot."""
    try:
        t = datetime.strptime(time_str, '%H:%M:%S')
        total_minutes = t.hour * 60 + t.minute
        return total_minutes // slot_step
    except ValueError:
        return 0

# --- Logique du Solveur ---

@router.post("/solve-rotation", response_model=RotationResponse)
def solve_rotation(request: RotationRequest):
    """
    Solveur CP-SAT pour la Passe 1.5.
    Gère les cibles de shifts, les fenêtres horaires, la pause repas, 
    et optimise la couverture du besoin (Need Curve).
    """
    model = cp_model.CpModel()
    
    # Configuration temporelle
    try:
        monday_date = datetime.strptime(request.date, '%Y-%m-%d')
    except ValueError:
        monday_date = datetime.now()

    days = 5 # Lundi au Vendredi
    slot_step = request.slot_minutes
    
    # --- 1. Création des Variables ---
    
    shifts = {} 
    agents_map = {a.id: a for a in request.agents}
    
    # Pré-calcul des ID d'offres présents pour l'optimisation
    present_offer_ids = set()

    for agent in request.agents:
        if agent.offer_id is not None:
            present_offer_ids.add(agent.offer_id)

        # Parsing des horaires de travail
        w_start = parse_time_to_slot(agent.window_start, slot_step)
        w_end = parse_time_to_slot(agent.window_end, slot_step)
        duration_slots = agent.duration // slot_step
        
        # Parsing de la fenêtre repas
        has_lunch_constraint = False
        lunch_start_slot = 0
        lunch_end_slot = 0
        lunch_duration_slots = 0
        
        if agent.lunch_window_start and agent.lunch_window_end and agent.lunch_duration > 0:
            has_lunch_constraint = True
            lunch_start_slot = parse_time_to_slot(agent.lunch_window_start, slot_step)
            lunch_end_slot = parse_time_to_slot(agent.lunch_window_end, slot_step)
            lunch_duration_slots = agent.lunch_duration // slot_step
        
        # Le dernier slot de départ possible
        latest_start = w_end - duration_slots
        
        if latest_start < w_start:
            # Fenêtre trop courte, on ignore cet agent silencieusement ou via log
            continue

        for d in range(days):
            # Optimisation : on ne boucle que sur les créneaux possibles
            for s in range(w_start, latest_start + 1):
                
                # Définition du shift potentiel [s, s + duration]
                shift_start = s
                shift_end = s + duration_slots
                
                # --- FILTRAGE INDISPONIBILITÉS (Réunions, Absences, Passe 1) ---
                # Si le shift chevauche une indisponibilité, on l'interdit
                is_blocked = False
                day_key = str(d)  # Les clés JSON sont des strings
                day_unavailables = []  # Liste des (u_start, u_end) en slots pour ce jour
                
                if agent.unavailable_by_day and day_key in agent.unavailable_by_day:
                    for unavail in agent.unavailable_by_day[day_key]:
                        u_start = parse_time_to_slot(unavail.start, slot_step)
                        u_end = parse_time_to_slot(unavail.end, slot_step)
                        day_unavailables.append((u_start, u_end))
                        
                        # Chevauchement Shift/Indispo : max(shift_start, u_start) < min(shift_end, u_end)
                        if max(shift_start, u_start) < min(shift_end, u_end):
                            is_blocked = True
                            break
                
                if is_blocked:
                    continue
                
                # --- FILTRAGE LUNCH (Logique Stricte avec Indisponibilités) ---
                # Le shift doit laisser assez de temps pour manger dans la fenêtre repas,
                # EN TENANT COMPTE des réunions qui occupent aussi cette fenêtre.
                if has_lunch_constraint:
                    # Construire un masque de disponibilité pour la fenêtre repas
                    # True = Libre pour manger, False = Occupé (par Shift OU Réunion)
                    lunch_window_size = lunch_end_slot - lunch_start_slot
                    lunch_slots_status = [True] * lunch_window_size
                    
                    # 1. Marquer occupé par le SHIFT potentiel
                    s_rel_start = max(0, shift_start - lunch_start_slot)
                    s_rel_end = min(lunch_window_size, shift_end - lunch_start_slot)
                    if s_rel_start < s_rel_end:
                        for i in range(s_rel_start, s_rel_end):
                            lunch_slots_status[i] = False
                    
                    # 2. Marquer occupé par les INDISPONIBILITÉS (Réunions)
                    for (u_start, u_end) in day_unavailables:
                        u_rel_start = max(0, u_start - lunch_start_slot)
                        u_rel_end = min(lunch_window_size, u_end - lunch_start_slot)
                        if u_rel_start < u_rel_end:
                            for i in range(u_rel_start, u_rel_end):
                                lunch_slots_status[i] = False
                    
                    # 3. Vérification : Trouve-t-on une séquence libre >= lunch_duration_slots ?
                    consecutive_free = 0
                    found_lunch_spot = False
                    for is_free in lunch_slots_status:
                        if is_free:
                            consecutive_free += 1
                            if consecutive_free >= lunch_duration_slots:
                                found_lunch_spot = True
                                break
                        else:
                            consecutive_free = 0
                    
                    if not found_lunch_spot:
                        # Ce shift est invalide : Shift + Réunions ne laissent pas de place pour manger
                        continue

                # Si valide, on crée la variable
                shifts[(agent.id, d, s)] = model.NewBoolVar(f"shift_a{agent.id}_d{d}_s{s}")

    # --- 2. Contraintes Structurelles ---
    
    # Collecteur de pénalités pour l'objectif global
    shortfall_penalties = []      # Pénalités de non-atteinte de cible (poids TRÈS élevé)
    preference_penalties = []     # Pénalités de préférence (rotation, étalement)

    for agent in request.agents:
        agent_daily_shifts = [] # Liste de (jour, variable_working_indicator)
        
        # A. Cible Hebdo - CONTRAINTE SOUPLE (Soft)
        all_agent_vars = [shifts[(agent.id, d, s)] for (a, d, s) in shifts if a == agent.id]
        if all_agent_vars:
            # Nombre de shifts réellement assignés
            shifts_assigned = model.NewIntVar(0, agent.target_slots, f"assigned_{agent.id}")
            model.Add(shifts_assigned == sum(all_agent_vars))
            
            # Variable d'écart : combien de shifts manquent pour atteindre la cible ?
            shortfall = model.NewIntVar(0, agent.target_slots, f"shortfall_{agent.id}")
            
            # Relation : assignés + manquants = cible
            model.Add(shifts_assigned + shortfall == agent.target_slots)
            
            # PÉNALITÉ CRITIQUE : Poids 1 000 000 par shift manquant
            # Le solveur ne réduira la cible QUE s'il n'a pas le choix (indisponibilités)
            shortfall_penalties.append(shortfall * 1_000_000)
        
        # B. Unicité Journalière (max 1 shift par jour par agent)
        for d in range(days):
            daily_vars = [shifts[(agent.id, d, s)] for (a, dy, s) in shifts if a == agent.id and dy == d]
            if daily_vars:
                model.Add(sum(daily_vars) <= 1)
                
                is_working_day = model.NewBoolVar(f"working_a{agent.id}_d{d}")
                model.Add(sum(daily_vars) == 1).OnlyEnforceIf(is_working_day)
                model.Add(sum(daily_vars) == 0).OnlyEnforceIf(is_working_day.Not())
                agent_daily_shifts.append((d, is_working_day))

        # C. Objectifs Soft (Rotation Jours & Étalement) - Transformés en pénalités Minimize
        for d, is_working_var in agent_daily_shifts:
            # Rotation : Pénalité si on retravaille le même jour que la semaine passée
            if d in agent.history_worked_days:
                # Pénalité pour éviter la répétition (poids 100)
                preference_penalties.append(is_working_var * 100)
            else:
                # Bonus léger pour encourager le travail sur d'autres jours
                # (Transformé en pénalité négative, mais on utilise une approche différente)
                # On ne pénalise pas, ce qui revient à un bonus implicite
                pass
        
        # Étalement : Pénalité pour jours consécutifs
        for i in range(len(agent_daily_shifts) - 1):
            d1, work1 = agent_daily_shifts[i]
            d2, work2 = agent_daily_shifts[i+1]
            if d2 == d1 + 1:
                consecutive = model.NewBoolVar(f"consec_a{agent.id}_d{d1}")
                model.AddBoolAnd([work1, work2]).OnlyEnforceIf(consecutive)
                model.AddBoolOr([work1.Not(), work2.Not()]).OnlyEnforceIf(consecutive.Not())
                # Pénalité pour jours consécutifs (poids 10)
                preference_penalties.append(consecutive * 10)

    # --- 3. Objectif de Couverture (Fit to Need Curve) ---
    
    # Calcul de la fenêtre globale pour scanner la charge
    if request.agents:
        global_w_start = min(parse_time_to_slot(a.window_start, slot_step) for a in request.agents)
        global_w_end = max(parse_time_to_slot(a.window_end, slot_step) for a in request.agents)
    else:
        global_w_start, global_w_end = 0, 0

    delta_squared_vars = []
    max_agents = len(request.agents) + 1 # +1 pour marge de sécurité

    # On traite chaque offre séparément
    # Si need_curve est présent, on utilise les IDs qu'il contient.
    # Sinon, on itère sur les IDs présents chez les agents.
    relevant_offer_ids = present_offer_ids
    if request.need_curve:
        relevant_offer_ids = relevant_offer_ids.union(request.need_curve.keys())

    for offer_id in relevant_offer_ids:
        # Récupération de la courbe pour cette offre (ou liste vide)
        curve = []
        if request.need_curve and offer_id in request.need_curve:
            curve = request.need_curve[offer_id]
        
        for day in range(days):
            for t in range(global_w_start, global_w_end):
                # 1. Calcul du Staffing à l'instant t pour cette offre
                active_shifts_at_t = []
                for (agent_id, shift_day, start_slot), var in shifts.items():
                    if shift_day == day:
                        ag = agents_map[agent_id]
                        if ag.offer_id == offer_id: # On ne compte que les agents de l'offre
                            duration_slots = ag.duration // slot_step
                            if start_slot <= t < start_slot + duration_slots:
                                active_shifts_at_t.append(var)
                
                # S'il y a des agents potentiels sur ce slot/offre
                if active_shifts_at_t:
                    staffing_at_t = model.NewIntVar(0, max_agents, f"staff_o{offer_id}_d{day}_t{t}")
                    model.Add(staffing_at_t == sum(active_shifts_at_t))
                    
                    # 2. Récupération du Besoin Cible (Target)
                    # Si la courbe est définie et assez longue, on prend la valeur, sinon 0
                    target_need = 0
                    if t < len(curve):
                        target_need = int(round(curve[t])) # On arrondit le float
                    
                    # 3. Calcul de l'écart au carré : (Staffing - Target)^2
                    # C'est ce que nous voulons minimiser pour coller à la courbe
                    delta = model.NewIntVar(-max_agents, max_agents, f"delta_o{offer_id}_d{day}_t{t}")
                    model.Add(delta == staffing_at_t - target_need)
                    
                    delta_sq = model.NewIntVar(0, max_agents**2, f"delta_sq_o{offer_id}_d{day}_t{t}")
                    model.AddMultiplicationEquality(delta_sq, [delta, delta])
                    
                    delta_squared_vars.append(delta_sq)

    # --- OBJECTIF GLOBAL CONSOLIDÉ ---
    # Priorités (par poids décroissant) :
    # 1. Shortfall (1 000 000) : Respecter la cible contractuelle autant que possible
    # 2. Couverture Need Curve (1) : Coller au besoin prévisionnel
    # 3. Préférences (10-100) : Rotation des jours, étalement
    
    objective_terms = []
    
    # 1. Pénalités de shortfall (PRIORITÉ ABSOLUE)
    objective_terms.extend(shortfall_penalties)
    
    # 2. Écarts de couverture Need Curve
    objective_terms.extend(delta_squared_vars)
    
    # 3. Pénalités de préférence (rotation, étalement)
    objective_terms.extend(preference_penalties)
    
    if objective_terms:
        model.Minimize(sum(objective_terms))

    # --- 4. Résolution ---
    
    solver = cp_model.CpSolver()
    # Augmenter un peu le temps si nécessaire, mais 5s est souvent suffisant pour la rotation
    solver.parameters.max_time_in_seconds = 5.0 
    status = solver.Solve(model)
    
    response_blocks = []
    status_str = "INFEASIBLE"
    
    if status == cp_model.OPTIMAL or status == cp_model.FEASIBLE:
        status_str = "FEASIBLE"
        for (agent_id, d, s), var in shifts.items():
            if solver.Value(var) == 1:
                agent = agents_map[agent_id]
                start_minutes = s * slot_step
                end_minutes = start_minutes + agent.duration
                
                # Reconstruction Date/Heure
                start_time_obj = datetime.combine(monday_date.date(), datetime.min.time()) + timedelta(days=d, minutes=start_minutes)
                end_time_obj = datetime.combine(monday_date.date(), datetime.min.time()) + timedelta(days=d, minutes=end_minutes)
                
                response_blocks.append(RotationBlock(
                    user_id=agent_id,
                    offer_id=agent.offer_id,
                    start=start_time_obj.strftime('%H:%M:%S'),
                    end=end_time_obj.strftime('%H:%M:%S'),
                    day_index=d
                ))
    else:
        # En cas d'échec, on renvoie une liste vide mais avec un statut explicite
        pass

    return RotationResponse(
        status=status_str,
        blocks=response_blocks,
        debug_info=f"Solved in {solver.WallTime()}s"
    )