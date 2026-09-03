# -*- coding: utf-8 -*-
from __future__ import annotations
from typing import List, Dict, Optional, Any, Tuple
from fastapi import APIRouter
from pydantic import BaseModel, Field
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
    # V2 multi-lignes
    skills: Optional[List[int]] = None
    target_slots_by_line: Optional[Dict[str, int]] = None
    history_slots_by_line: Optional[Dict[str, int]] = None
    history_worked_days_by_offer: Optional[Dict[str, List[int]]] = None


class RotationLineSlot(BaseModel):
    start: str
    end: str


class RotationLine(BaseModel):
    id: int
    line_type: str  # quota | coverage
    offer_id: Optional[int] = None
    sort_order: int = 1
    target_count: Optional[int] = None
    shift_duration: Optional[int] = None
    window_start: Optional[str] = None
    window_end: Optional[str] = None
    fit_need_curve: bool = True
    quantity: int = 1
    equity_enabled: bool = True
    same_person_day_slots: bool = False
    days_of_week: Optional[List[int]] = None
    slots: List[RotationLineSlot] = Field(default_factory=list)


class RotationRequest(BaseModel):
    date: str  # Date du Lundi de la semaine à planifier "YYYY-MM-DD"
    agents: List[RotationAgent]
    slot_minutes: int = 15
    timeout_seconds: float = 10.0
    # Nouvelle courbe de besoin : ID Offre -> Liste de floats (besoin par slot)
    need_curve: Optional[Dict[int, List[float]]] = None
    lines: Optional[List[RotationLine]] = None
    exclusive_day: bool = True


class RotationBlock(BaseModel):
    user_id: int
    offer_id: Optional[int]
    start: str
    end: str
    day_index: int # 0=Lundi


class RotationShortfall(BaseModel):
    line_id: Optional[int] = None
    line_type: str
    offer_id: Optional[int] = None
    kind: str
    agent_id: Optional[int] = None
    day_index: Optional[int] = None
    slot_start: Optional[str] = None
    slot_end: Optional[str] = None
    expected: Optional[int] = None
    actual: Optional[int] = None


class RotationResponse(BaseModel):
    status: str
    blocks: List[RotationBlock]
    debug_info: Optional[str] = None
    shortfalls: List[RotationShortfall] = Field(default_factory=list)

# --- Helpers ---

def parse_time_to_slot(time_str: str, slot_step: int) -> int:
    """Convertit '09:30:00' en index de slot."""
    try:
        t = datetime.strptime(time_str, '%H:%M:%S')
        total_minutes = t.hour * 60 + t.minute
        return total_minutes // slot_step
    except ValueError:
        return 0


def _dict_int_keys(d: Optional[Dict]) -> Dict[int, Any]:
    if not d:
        return {}
    out: Dict[int, Any] = {}
    for k, v in d.items():
        try:
            out[int(k)] = v
        except (TypeError, ValueError):
            continue
    return out


def _curve_for(need_curve: Optional[Dict], offer_id: Optional[int]) -> List[float]:
    if not need_curve or offer_id is None:
        return []
    if offer_id in need_curve:
        return need_curve[offer_id] or []
    key = str(offer_id)
    if key in need_curve:
        return need_curve[key] or []
    return []


def line_weight(sort_order: int) -> int:
    exp = 8 - 2 * max(1, int(sort_order))
    if exp < 0:
        return 1
    return 10 ** exp


def _intervals_overlap(a0: int, a1: int, b0: int, b1: int) -> bool:
    return max(a0, b0) < min(a1, b1)


def _lunch_ok_for_interval(
    shift_start: int,
    shift_end: int,
    lunch_start: int,
    lunch_end: int,
    lunch_duration_slots: int,
    day_unavailables: List[Tuple[int, int]],
) -> bool:
    lunch_window_size = lunch_end - lunch_start
    if lunch_window_size <= 0 or lunch_duration_slots <= 0:
        return True
    lunch_slots_status = [True] * lunch_window_size
    s_rel_start = max(0, shift_start - lunch_start)
    s_rel_end = min(lunch_window_size, shift_end - lunch_start)
    if s_rel_start < s_rel_end:
        for i in range(s_rel_start, s_rel_end):
            lunch_slots_status[i] = False
    for (u_start, u_end) in day_unavailables:
        u_rel_start = max(0, u_start - lunch_start)
        u_rel_end = min(lunch_window_size, u_end - lunch_start)
        if u_rel_start < u_rel_end:
            for i in range(u_rel_start, u_rel_end):
                lunch_slots_status[i] = False
    consecutive_free = 0
    for is_free in lunch_slots_status:
        if is_free:
            consecutive_free += 1
            if consecutive_free >= lunch_duration_slots:
                return True
        else:
            consecutive_free = 0
    return False


def _day_unavailables(agent: RotationAgent, day: int, slot_step: int) -> List[Tuple[int, int]]:
    out: List[Tuple[int, int]] = []
    if not agent.unavailable_by_day:
        return out
    day_key = str(day)
    if day_key not in agent.unavailable_by_day:
        return out
    for unavail in agent.unavailable_by_day[day_key]:
        out.append((
            parse_time_to_slot(unavail.start, slot_step),
            parse_time_to_slot(unavail.end, slot_step),
        ))
    return out


def _agent_has_skill(agent: RotationAgent, offer_id: Optional[int]) -> bool:
    if offer_id is None:
        return True
    if agent.skills is None:
        return agent.offer_id == offer_id
    return offer_id in agent.skills


def _coverage_days(line: RotationLine, days: int) -> List[int]:
    if not line.days_of_week:
        return list(range(days))
    allowed = set()
    for n in line.days_of_week:
        # PHP format('N'): 1=Lundi → day_index 0
        idx = int(n) - 1
        if 0 <= idx < days:
            allowed.add(idx)
    return sorted(allowed) if allowed else list(range(days))


# --- Logique du Solveur ---

def solve_rotation_legacy(request: RotationRequest) -> RotationResponse:
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
    solver.parameters.max_time_in_seconds = max(1.0, float(request.timeout_seconds))
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
        debug_info=f"Solved in {solver.WallTime()}s",
        shortfalls=[],
    )


def _fmt_slot(slot_index: int, slot_step: int) -> str:
    minutes = slot_index * slot_step
    return f"{(minutes // 60) % 24:02d}:{minutes % 60:02d}:00"


def solve_rotation_lines(request: RotationRequest) -> RotationResponse:
    model = cp_model.CpModel()
    try:
        monday_date = datetime.strptime(request.date, '%Y-%m-%d')
    except ValueError:
        monday_date = datetime.now()

    days = 5
    slot_step = request.slot_minutes
    exclusive_day = bool(request.exclusive_day)
    agents_map = {a.id: a for a in request.agents}
    lines = list(request.lines or [])
    quota_lines = [ln for ln in lines if ln.line_type == 'quota']
    coverage_lines = [ln for ln in lines if ln.line_type == 'coverage']

    # intervals: dicts with var, agent_id, day, start, end, offer_id, kind, line_id
    intervals: List[Dict[str, Any]] = []
    duties: Dict[Tuple[int, int], List[Any]] = {}  # (agent, day) -> duty bools
    cover_vars: Dict[Tuple[int, int, int, int], Any] = {}  # agent, line, day, slot_idx
    quota_vars: Dict[Tuple[int, int, int, int], Any] = {}  # agent, line, day, start

    def add_duty(agent_id: int, day: int, var) -> None:
        duties.setdefault((agent_id, day), []).append(var)

    def interval_blocked(agent: RotationAgent, d: int, start: int, end: int) -> bool:
        unavails = _day_unavailables(agent, d, slot_step)
        for (u0, u1) in unavails:
            if _intervals_overlap(start, end, u0, u1):
                return True
        has_lunch = bool(agent.lunch_window_start and agent.lunch_window_end and agent.lunch_duration > 0)
        if has_lunch:
            ls = parse_time_to_slot(agent.lunch_window_start, slot_step)
            le = parse_time_to_slot(agent.lunch_window_end, slot_step)
            ld = agent.lunch_duration // slot_step
            if not _lunch_ok_for_interval(start, end, ls, le, ld, unavails):
                return True
        return False

    # --- Quota variables ---
    for line in quota_lines:
        duration = int(line.shift_duration or 0)
        if duration <= 0 or line.window_start is None or line.window_end is None:
            continue
        duration_slots = duration // slot_step
        w_start = parse_time_to_slot(line.window_start, slot_step)
        w_end = parse_time_to_slot(line.window_end, slot_step)
        latest_start = w_end - duration_slots
        if latest_start < w_start:
            continue
        for agent in request.agents:
            if not _agent_has_skill(agent, line.offer_id):
                continue
            targets = _dict_int_keys(agent.target_slots_by_line)
            target = int(targets.get(line.id, agent.target_slots if len(quota_lines) == 1 else 0))
            if target <= 0:
                continue
            for d in range(days):
                for s in range(w_start, latest_start + 1):
                    end = s + duration_slots
                    if interval_blocked(agent, d, s, end):
                        continue
                    var = model.NewBoolVar(f"q_a{agent.id}_l{line.id}_d{d}_s{s}")
                    quota_vars[(agent.id, line.id, d, s)] = var
                    intervals.append({
                        'var': var,
                        'agent_id': agent.id,
                        'day': d,
                        'start': s,
                        'end': end,
                        'offer_id': line.offer_id,
                        'kind': 'quota',
                        'line_id': line.id,
                    })
                    add_duty(agent.id, d, var)

    # --- Coverage variables ---
    for line in coverage_lines:
        slot_defs = []
        for idx, sl in enumerate(line.slots):
            st = parse_time_to_slot(sl.start, slot_step)
            en = parse_time_to_slot(sl.end, slot_step)
            if en <= st:
                continue
            slot_defs.append((idx, st, en, sl.start, sl.end))
        if not slot_defs:
            continue
        allowed_days = _coverage_days(line, days)
        for agent in request.agents:
            if not _agent_has_skill(agent, line.offer_id):
                continue
            for d in allowed_days:
                valid_slots = []
                for idx, st, en, _, _ in slot_defs:
                    if interval_blocked(agent, d, st, en):
                        continue
                    valid_slots.append((idx, st, en))
                if line.same_person_day_slots:
                    if len(valid_slots) != len(slot_defs):
                        continue
                    duty = model.NewBoolVar(f"covduty_a{agent.id}_l{line.id}_d{d}")
                    add_duty(agent.id, d, duty)
                    for idx, st, en in valid_slots:
                        var = model.NewBoolVar(f"c_a{agent.id}_l{line.id}_d{d}_i{idx}")
                        model.Add(var == duty)
                        cover_vars[(agent.id, line.id, d, idx)] = var
                        intervals.append({
                            'var': var,
                            'agent_id': agent.id,
                            'day': d,
                            'start': st,
                            'end': en,
                            'offer_id': line.offer_id,
                            'kind': 'coverage',
                            'line_id': line.id,
                            'slot_idx': idx,
                        })
                else:
                    for idx, st, en in valid_slots:
                        var = model.NewBoolVar(f"c_a{agent.id}_l{line.id}_d{d}_i{idx}")
                        cover_vars[(agent.id, line.id, d, idx)] = var
                        intervals.append({
                            'var': var,
                            'agent_id': agent.id,
                            'day': d,
                            'start': st,
                            'end': en,
                            'offer_id': line.offer_id,
                            'kind': 'coverage',
                            'line_id': line.id,
                            'slot_idx': idx,
                        })
                        add_duty(agent.id, d, var)

    # --- Non-chevauchement ---
    by_agent_day: Dict[Tuple[int, int], List[Dict[str, Any]]] = {}
    for iv in intervals:
        by_agent_day.setdefault((iv['agent_id'], iv['day']), []).append(iv)
    for (_aid, _d), ivs in by_agent_day.items():
        n = len(ivs)
        for i in range(n):
            for j in range(i + 1, n):
                a, b = ivs[i], ivs[j]
                if a['var'] is b['var']:
                    continue
                if _intervals_overlap(a['start'], a['end'], b['start'], b['end']):
                    model.Add(a['var'] + b['var'] <= 1)

    # --- Exclusive day (duties) ---
    if exclusive_day:
        for (aid, d), duty_vars in duties.items():
            uniq = []
            seen = set()
            for v in duty_vars:
                vid = id(v)
                if vid in seen:
                    continue
                seen.add(vid)
                uniq.append(v)
            if uniq:
                model.Add(sum(uniq) <= 1)

    # --- Repas sur l'union ---
    for agent in request.agents:
        if not (agent.lunch_window_start and agent.lunch_window_end and agent.lunch_duration > 0):
            continue
        lunch_start = parse_time_to_slot(agent.lunch_window_start, slot_step)
        lunch_end = parse_time_to_slot(agent.lunch_window_end, slot_step)
        lunch_dur = agent.lunch_duration // slot_step
        if lunch_end <= lunch_start or lunch_dur <= 0:
            continue
        for d in range(days):
            ivs = by_agent_day.get((agent.id, d), [])
            if not ivs:
                continue
            unavails = _day_unavailables(agent, d, slot_step)
            unavail_slots = set()
            for t in range(lunch_start, lunch_end):
                for (u0, u1) in unavails:
                    if u0 <= t < u1:
                        unavail_slots.add(t)
                        break
            interval_vars = [iv['var'] for iv in ivs]
            any_work = model.NewBoolVar(f"anywork_a{agent.id}_d{d}")
            model.Add(sum(interval_vars) >= 1).OnlyEnforceIf(any_work)
            model.Add(sum(interval_vars) == 0).OnlyEnforceIf(any_work.Not())

            lunch_ok_opts = []
            always_ok = False
            latest_lunch_start = lunch_end - lunch_dur
            for s in range(lunch_start, latest_lunch_start + 1):
                if any(t in unavail_slots for t in range(s, s + lunch_dur)):
                    continue
                occ = []
                for t in range(s, s + lunch_dur):
                    for iv in ivs:
                        if iv['start'] <= t < iv['end']:
                            occ.append(iv['var'])
                if not occ:
                    always_ok = True
                    break
                free_s = model.NewBoolVar(f"lunchfree_a{agent.id}_d{d}_s{s}")
                model.Add(sum(occ) == 0).OnlyEnforceIf(free_s)
                model.Add(sum(occ) >= 1).OnlyEnforceIf(free_s.Not())
                lunch_ok_opts.append(free_s)
            if always_ok:
                pass
            elif not lunch_ok_opts:
                model.Add(sum(interval_vars) == 0)
            else:
                model.AddBoolOr(lunch_ok_opts).OnlyEnforceIf(any_work)

    # --- Objectifs ---
    objective_terms = []
    shortfalls_meta: List[Dict[str, Any]] = []

    # Quota shortfalls
    for line in quota_lines:
        w = line_weight(line.sort_order)
        for agent in request.agents:
            if not _agent_has_skill(agent, line.offer_id):
                continue
            targets = _dict_int_keys(agent.target_slots_by_line)
            target = int(targets.get(line.id, 0))
            if target <= 0:
                continue
            vars_a = [v for (aid, lid, _d, _s), v in quota_vars.items() if aid == agent.id and lid == line.id]
            if not vars_a:
                shortfalls_meta.append({
                    'line_id': line.id, 'line_type': 'quota', 'offer_id': line.offer_id,
                    'kind': 'quota_agent', 'agent_id': agent.id, 'expected': target, 'actual': 0,
                    'var': None, 'forced': target,
                })
                objective_terms.append(target * w)
                continue
            assigned = model.NewIntVar(0, target, f"qass_a{agent.id}_l{line.id}")
            model.Add(assigned == sum(vars_a))
            sf = model.NewIntVar(0, target, f"qsf_a{agent.id}_l{line.id}")
            model.Add(assigned + sf == target)
            objective_terms.append(sf * w)
            shortfalls_meta.append({
                'line_id': line.id, 'line_type': 'quota', 'offer_id': line.offer_id,
                'kind': 'quota_agent', 'agent_id': agent.id, 'expected': target,
                'var': sf,
            })

    # Coverage understaffing
    for line in coverage_lines:
        w = line_weight(line.sort_order)
        qty = max(1, int(line.quantity or 1))
        allowed_days = _coverage_days(line, days)
        for d in allowed_days:
            for idx, sl in enumerate(line.slots):
                staff_vars = [
                    v for (aid, lid, dy, si), v in cover_vars.items()
                    if lid == line.id and dy == d and si == idx
                ]
                max_staff = max(len(staff_vars), qty)
                staffing = model.NewIntVar(0, max_staff, f"cstaff_l{line.id}_d{d}_i{idx}")
                if staff_vars:
                    model.Add(staffing == sum(staff_vars))
                else:
                    model.Add(staffing == 0)
                sf = model.NewIntVar(0, qty, f"csf_l{line.id}_d{d}_i{idx}")
                model.Add(staffing + sf >= qty)
                objective_terms.append(sf * w)
                shortfalls_meta.append({
                    'line_id': line.id, 'line_type': 'coverage', 'offer_id': line.offer_id,
                    'kind': 'coverage_slot', 'day_index': d,
                    'slot_start': sl.start, 'slot_end': sl.end,
                    'expected': qty, 'var': sf,
                })

        if line.equity_enabled:
            eq_w = max(1, w // 100)
            eligible = [a for a in request.agents if _agent_has_skill(a, line.offer_id)]
            if eligible:
                hist_max = 0
                loads = []
                for agent in eligible:
                    hist_map = _dict_int_keys(agent.history_slots_by_line)
                    hist = int(hist_map.get(line.id, 0))
                    hist_max = max(hist_max, hist)
                    assigned_vars = [
                        v for (aid, lid, _d, _i), v in cover_vars.items()
                        if aid == agent.id and lid == line.id
                    ]
                    cap = hist + len(assigned_vars)
                    load = model.NewIntVar(0, max(cap, hist), f"cload_a{agent.id}_l{line.id}")
                    if assigned_vars:
                        model.Add(load == sum(assigned_vars) + hist)
                    else:
                        model.Add(load == hist)
                    loads.append(load)
                    k_max = max(cap, 1)
                    for k in range(2, k_max + 1):
                        over = model.NewBoolVar(f"coverk_a{agent.id}_l{line.id}_k{k}")
                        model.Add(load >= k).OnlyEnforceIf(over)
                        model.Add(load < k).OnlyEnforceIf(over.Not())
                        objective_terms.append(over * k)
                if loads:
                    max_load = model.NewIntVar(0, max(hist_max + 10, 20), f"cmaxload_l{line.id}")
                    model.AddMaxEquality(max_load, loads)
                    objective_terms.append(max_load * eq_w)

    # Need curve (quota lines)
    delta_squared_vars = []
    max_agents = len(request.agents) + 1
    for line in quota_lines:
        if not line.fit_need_curve or line.offer_id is None:
            continue
        curve = _curve_for(request.need_curve, line.offer_id)
        w_start = parse_time_to_slot(line.window_start or '09:00:00', slot_step)
        w_end = parse_time_to_slot(line.window_end or '17:00:00', slot_step)
        duration = int(line.shift_duration or 0)
        duration_slots = duration // slot_step if duration else 0
        for day in range(days):
            for t in range(w_start, w_end):
                active = []
                for iv in intervals:
                    if iv['kind'] == 'quota' and iv['line_id'] == line.id and iv['day'] == day:
                        if iv['start'] <= t < iv['end']:
                            active.append(iv['var'])
                if not active:
                    continue
                staffing_at_t = model.NewIntVar(0, max_agents, f"staff_l{line.id}_d{day}_t{t}")
                model.Add(staffing_at_t == sum(active))
                target_need = 0
                if t < len(curve):
                    target_need = int(round(curve[t]))
                delta = model.NewIntVar(-max_agents, max_agents, f"delta_l{line.id}_d{day}_t{t}")
                model.Add(delta == staffing_at_t - target_need)
                delta_sq = model.NewIntVar(0, max_agents ** 2, f"dsq_l{line.id}_d{day}_t{t}")
                model.AddMultiplicationEquality(delta_sq, [delta, delta])
                delta_squared_vars.append(delta_sq)
    objective_terms.extend(delta_squared_vars)

    # Préférences jours (duties)
    for agent in request.agents:
        hist_days = set(agent.history_worked_days or [])
        work_by_day = {}
        for d in range(days):
            dvars = duties.get((agent.id, d), [])
            if not dvars:
                continue
            uniq = []
            seen = set()
            for v in dvars:
                if id(v) in seen:
                    continue
                seen.add(id(v))
                uniq.append(v)
            is_working = model.NewBoolVar(f"work_a{agent.id}_d{d}")
            model.Add(sum(uniq) >= 1).OnlyEnforceIf(is_working)
            model.Add(sum(uniq) == 0).OnlyEnforceIf(is_working.Not())
            work_by_day[d] = is_working
            if d in hist_days:
                objective_terms.append(is_working * 100)
        ordered = sorted(work_by_day.keys())
        for i in range(len(ordered) - 1):
            d1, d2 = ordered[i], ordered[i + 1]
            if d2 == d1 + 1:
                consecutive = model.NewBoolVar(f"consec_a{agent.id}_d{d1}")
                model.AddBoolAnd([work_by_day[d1], work_by_day[d2]]).OnlyEnforceIf(consecutive)
                model.AddBoolOr([work_by_day[d1].Not(), work_by_day[d2].Not()]).OnlyEnforceIf(consecutive.Not())
                objective_terms.append(consecutive * 10)

    if objective_terms:
        model.Minimize(sum(objective_terms))

    solver = cp_model.CpSolver()
    solver.parameters.max_time_in_seconds = max(1.0, float(request.timeout_seconds))
    status = solver.Solve(model)

    response_blocks: List[RotationBlock] = []
    shortfalls: List[RotationShortfall] = []
    status_str = "INFEASIBLE"
    if status == cp_model.OPTIMAL or status == cp_model.FEASIBLE:
        status_str = "FEASIBLE"
        for iv in intervals:
            if solver.Value(iv['var']) == 1:
                start_minutes = iv['start'] * slot_step
                end_minutes = iv['end'] * slot_step
                start_time_obj = datetime.combine(monday_date.date(), datetime.min.time()) + timedelta(days=iv['day'], minutes=start_minutes)
                end_time_obj = datetime.combine(monday_date.date(), datetime.min.time()) + timedelta(days=iv['day'], minutes=end_minutes)
                response_blocks.append(RotationBlock(
                    user_id=iv['agent_id'],
                    offer_id=iv['offer_id'],
                    start=start_time_obj.strftime('%H:%M:%S'),
                    end=end_time_obj.strftime('%H:%M:%S'),
                    day_index=iv['day'],
                ))
        for meta in shortfalls_meta:
            actual = 0
            expected = int(meta.get('expected') or 0)
            if meta.get('forced') is not None:
                actual = 0
                sf_val = int(meta['forced'])
            else:
                sf_val = int(solver.Value(meta['var'])) if meta.get('var') is not None else 0
                actual = expected - sf_val
            if sf_val <= 0:
                continue
            shortfalls.append(RotationShortfall(
                line_id=meta.get('line_id'),
                line_type=meta['line_type'],
                offer_id=meta.get('offer_id'),
                kind=meta['kind'],
                agent_id=meta.get('agent_id'),
                day_index=meta.get('day_index'),
                slot_start=meta.get('slot_start'),
                slot_end=meta.get('slot_end'),
                expected=expected,
                actual=max(0, actual),
            ))

    return RotationResponse(
        status=status_str,
        blocks=response_blocks,
        debug_info=f"Solved in {solver.WallTime()}s",
        shortfalls=shortfalls,
    )


@router.post("/solve-rotation", response_model=RotationResponse)
def solve_rotation(request: RotationRequest):
    """
    Solveur CP-SAT pour la Passe 1.5.
    Payload sans lines = comportement historique.
    """
    if request.lines:
        return solve_rotation_lines(request)
    return solve_rotation_legacy(request)
