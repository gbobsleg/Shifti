# -*- coding: utf-8 -*-
# Solveur V25.0 - VERSION FINALE GOLD
# - P1 + P2 COMPLET
# - STAGGERING PAUSES AM/PM
# - EQUITÉ HYBRIDE (GLOBAL/POOLED vs PER_SITE)
# - COLLAGE REPAS P2
# - TT INCOMPATIBLE = HARD CONSTRAINT (INTERDICTION STRICTE)

from __future__ import annotations
from fastapi import APIRouter, Request
from ortools.sat.python import cp_model
import collections
from typing import List, Optional, Dict, Any, Tuple
from pydantic import BaseModel

# --- MODELES PYDANTIC ---
class TimeWindow(BaseModel):
    start: str
    end: str

class FixedActivityBlock(BaseModel):
    start: str
    end: str

class FixedActivity(BaseModel):
    offer_name: str
    base_offer_name: Optional[str] = None
    start_time: str
    end_time: str
    quantity: int
    allow_shortfall: bool = False
    equity_enabled: bool = True
    active: bool = True
    is_splittable: bool = True
    blocks: Optional[List[FixedActivityBlock]] = []
    incompatible_base_offers: Optional[List[str]] = []
    equity_group: Optional[str] = None
    pool_key: Optional[str] = None
    lunch_attach_mode: Optional[str] = 'none'
    lunch_overlap_allowed: bool = True
    is_remote_work_compatible: bool = True
    site_mode: Optional[str] = 'per_site'

class Agent(BaseModel):
    id: int
    name: str
    availability_start_time: str
    availability_end_time: str
    site: Optional[str] = None
    skills: List[str]
    remote_work_intervals: Optional[List[Dict[str, str]]] = []
    current_realization: Optional[Dict[str, float]] = {}
    # V2 équité : cible cumulative par equity_group (minutes)
    target_quota_minutes: Optional[Dict[str, float]] = None
    # V2 diversité inter-jours : historique cumulé en minutes par base_offer_name (ex: "RDV" -> 120)
    current_offer_minutes: Optional[Dict[str, int]] = None

class FixedActivityProblem(BaseModel):
    workday_start_time: str
    workday_end_time: str
    slot_minutes: int
    agents: List[Agent]
    fixed_activities: List[FixedActivity]
    timeout_seconds: float = 60.0
    enable_am_pm_breaks: bool = False
    break_duration_minutes: int = 15
    am_break_window: Optional[TimeWindow] = None
    pm_break_window: Optional[TimeWindow] = None
    lunch_duration_minutes: int = 60
    lunch_window: TimeWindow
    break_activity_name: str = "PAUSE"
    enforce_remote_work_incompatibilities: bool = False
    min_block_minutes: int = 60
    max_block_minutes: int = 240

router = APIRouter()

def parse_t(s: str) -> int:
    if not s: return 0
    p = s.split(":")
    return int(p[0]) * 60 + int(p[1])

def fmt_t(m: int) -> str:
    return f"{(m // 60) % 24:02d}:{m % 60:02d}:00"

@router.post("/solve-fixed-activities")
async def solve_fixed_activities(problem: FixedActivityProblem, request: Request):
    print("\n" + "="*60)
    print("--- SOLVEUR ACTIVITES FIXES : TT HARD + EQUITE HYBRIDE ---")
    print("="*60)
    
    model = cp_model.CpModel()
    slot_min = int(problem.slot_minutes)
    
    MIN_SLOTS = problem.min_block_minutes // slot_min
    MAX_SLOTS = problem.max_block_minutes // slot_min
    
    # 1. GRILLE
    grid = list(range(parse_t(problem.workday_start_time), parse_t(problem.workday_end_time), slot_min))
    num_slots = len(grid)
    agents = problem.agents
    
    # MAPPER LES ACTIVITÉS
    prio_acts = [] 
    sec_acts = []  
    activity_to_group = {}
    all_stagger_groups = set()
    equity_group_modes = {} 

    for a in problem.fixed_activities:
        if not a.active: continue
        grp = a.offer_name
        if a.base_offer_name and a.base_offer_name.strip():
            grp = a.base_offer_name.strip()
        activity_to_group[a.offer_name] = grp
        all_stagger_groups.add(grp)

        if a.equity_group:
            current_mode = getattr(a, 'site_mode', 'per_site')
            if a.equity_group not in equity_group_modes:
                equity_group_modes[a.equity_group] = current_mode
            else:
                if current_mode in ['global', 'pooled']:
                    equity_group_modes[a.equity_group] = current_mode

        if a.is_splittable:
            sec_acts.append(a)
        else:
            prio_acts.append(a)
            
    enforce_remote = getattr(problem, 'enforce_remote_work_incompatibilities', False)

    all_acts_combined = prio_acts + sec_acts

    # NOUVEAU : Mapping pool -> [base_offer_names] distincts pour la diversité inter-jours
    # Le pool scope par site (per_site = equity_group::site) ; base_offer_name ignore les suffixes de site.
    equity_group_offers: Dict[str, List[str]] = {}
    for a in all_acts_combined:
        if a.equity_group and a.equity_enabled:
            eqg = a.pool_key or a.equity_group
            base = (a.base_offer_name or a.offer_name).strip()
            if eqg not in equity_group_offers:
                equity_group_offers[eqg] = []
            if base not in equity_group_offers[eqg]:
                equity_group_offers[eqg].append(base)

    # V2 diversité : besoin total en minutes par pool et par sous-activité
    offer_need_minutes: Dict[str, Dict[str, int]] = {}
    for a in all_acts_combined:
        if a.equity_group and a.equity_enabled:
            base = (a.base_offer_name or a.offer_name).strip()
            dur = (parse_t(a.end_time) - parse_t(a.start_time)) % 1440  # modulo 24h (vacations de nuit)
            need = a.quantity * dur
            eqg = a.pool_key or a.equity_group
            if eqg not in offer_need_minutes:
                offer_need_minutes[eqg] = {}
            offer_need_minutes[eqg][base] = offer_need_minutes[eqg].get(base, 0) + need

    # V2 diversité : cibles par sous-activité (proportionnel pur au besoin + redistribution du delta)
    target_offer_minutes: Dict[Tuple[int, str], int] = {}
    for eq_group, base_names in equity_group_offers.items():
        if len(base_names) < 2:
            continue
        for a_idx in range(len(agents)):
            agent_obj = agents[a_idx]
            skills = set(agent_obj.skills)
            target_quota = int((agent_obj.target_quota_minutes or {}).get(eq_group, 0))
            if target_quota <= 0:
                continue

            feasible_names = [bn for bn in base_names if bn in skills]
            k = len(feasible_names)
            if k == 0:
                continue

            feasible_need = sum(offer_need_minutes[eq_group].get(bn, 0) for bn in feasible_names)
            if feasible_need <= 0:
                continue

            targets = []
            for base_name in feasible_names:
                offer_need = offer_need_minutes[eq_group].get(base_name, 0)
                share = offer_need / feasible_need
                raw = round(target_quota * share)
                targets.append((base_name, raw))

            current_sum = sum(v for _, v in targets)
            delta = target_quota - current_sum
            if delta != 0:
                targets.sort(key=lambda x: -x[1])
                step = 1 if delta > 0 else -1
                while delta != 0:
                    valid_indices = [i for i, (_, val) in enumerate(targets) if step == 1 or val > 0]
                    if not valid_indices:
                        break
                    for idx in valid_indices:
                        if delta == 0:
                            break
                        bn, val = targets[idx]
                        targets[idx] = (bn, val + step)
                        delta -= step

            for base_name, val in targets:
                target_offer_minutes[(a_idx, base_name)] = val

    # FENÊTRES
    def get_valid_starts(window, duration):
        if not window or duration <= 0: return []
        w_s, w_e = parse_t(window.start), parse_t(window.end)
        valid = []
        for t, tm in enumerate(grid):
            if tm >= w_s and (tm + duration) <= w_e:
                valid.append(t)
        return valid

    break_dur_slots = problem.break_duration_minutes // slot_min
    lunch_dur_slots = problem.lunch_duration_minutes // slot_min
    
    am_indices = get_valid_starts(problem.am_break_window, problem.break_duration_minutes) if problem.enable_am_pm_breaks else []
    pm_indices = get_valid_starts(problem.pm_break_window, problem.break_duration_minutes) if problem.enable_am_pm_breaks else []
    lunch_indices = get_valid_starts(problem.lunch_window, problem.lunch_duration_minutes)

    PIVOT_NOON = 13 * 60 

    # --- 2. VARIABLES ---
    x_prio = {} 
    x_sec = {}  
    
    start_am = {}
    start_pm = {}
    start_lunch = {}

    agent_active_on_group = {} 
    agent_conflict_offers = {a_idx: [] for a_idx in range(len(agents))}
    agent_equity_minutes = {a_idx: {} for a_idx in range(len(agents))}
    
    p2_diversification_penalties = []
    
    pools = {}

    # NOUVEAU : Accumulation des slots par base_offer_name pour la diversité inter-jours
    agent_offer_slots: Dict[Tuple[int, str], List] = {}

    # --- 3. BOUCLE PRINCIPALE ---
    for a_idx, agent in enumerate(agents):
        site_name = agent.site or "Sans_Site"
        if site_name not in pools: pools[site_name] = []
        pools[site_name].append(a_idx)

        av_s = parse_t(agent.availability_start_time)
        av_e = parse_t(agent.availability_end_time)

        # Init Lunch Vars
        for t in lunch_indices:
            t_time = grid[t]
            if t_time >= av_s and (t_time + lunch_dur_slots * slot_min) <= av_e:
                start_lunch[a_idx, t] = model.NewBoolVar(f'start_lunch_a{a_idx}_t{t}')

        # TT Slots
        tt_slots = set()
        for interval in (agent.remote_work_intervals or []):
            ri_s, ri_e = parse_t(interval.get('start')), parse_t(interval.get('end'))
            for t_idx, tm in enumerate(grid):
                if ri_s <= tm < ri_e: tt_slots.add(t_idx)

        # Init variables de groupe
        for grp in all_stagger_groups:
            agent_active_on_group[a_idx, grp] = model.NewBoolVar(f'active_on_{grp}_a{a_idx}')
        
        skills = set(s.strip() for s in (agent.skills or []))
        
        acts_in_am = []
        acts_in_pm = []

        # =================================================================
        # PHASE 1 : ACTIVITÉS PRIORITAIRES
        # =================================================================
        for f_idx, act in enumerate(prio_acts):
            if act.offer_name.strip() in skills:
                if not act.blocks:
                    b_list = [(parse_t(act.start_time), parse_t(act.end_time))]
                else:
                    b_list = [(parse_t(b.start), parse_t(b.end)) for b in act.blocks]
                
                agent_does_this_act = model.NewBoolVar(f'do_prio_{a_idx}_{f_idx}')
                agent_act_vars = []

                grp_key = activity_to_group[act.offer_name]

                for bs, be in b_list:
                    b_vars = []
                    for t_idx, tm in enumerate(grid):
                        if (parse_t(agent.availability_start_time) <= tm < parse_t(agent.availability_end_time)) and (bs <= tm < be):
                            v = model.NewBoolVar(f'x_prio_a{a_idx}_f{f_idx}_t{t_idx}')
                            x_prio[a_idx, f_idx, t_idx] = v
                            b_vars.append(v)
                            agent_act_vars.append(v)
                            
                            if tm < PIVOT_NOON: acts_in_am.append(v)
                            else: acts_in_pm.append(v)

                            model.Add(agent_active_on_group[a_idx, grp_key] >= v)

                            if act.equity_group and act.equity_enabled:
                                if (act.pool_key or act.equity_group) not in agent_equity_minutes[a_idx]: agent_equity_minutes[a_idx][act.pool_key or act.equity_group] = []
                                agent_equity_minutes[a_idx][act.pool_key or act.equity_group].append(v)
                                # Diversité inter-jours : accumulation par base_offer_name
                                base = (act.base_offer_name or act.offer_name).strip()
                                key = (a_idx, base)
                                if key not in agent_offer_slots:
                                    agent_offer_slots[key] = []
                                agent_offer_slots[key].append(v)
                            
                            # HARD CONSTRAINT TT
                            if enforce_remote and t_idx in tt_slots and not act.is_remote_work_compatible:
                                model.Add(v == 0)
                    
                    if not getattr(act, 'is_splittable', True) and len(b_vars) > 1:
                        for i in range(len(b_vars) - 1): model.Add(b_vars[i] == b_vars[i+1])

                if agent_act_vars:
                    model.AddMaxEquality(agent_does_this_act, agent_act_vars)
                else:
                    model.Add(agent_does_this_act == 0)

                # Attach Lunch
                mode = getattr(act, 'lunch_attach_mode', 'none')
                if mode in ['before', 'after']:
                    t_target = -1
                    try:
                        if mode == 'before':
                            target_time = parse_t(act.start_time) - problem.lunch_duration_minutes
                            t_target = grid.index(target_time)
                        elif mode == 'after':
                            target_time = parse_t(act.end_time)
                            t_target = grid.index(target_time)
                    except ValueError: pass

                    if t_target != -1 and (a_idx, t_target) in start_lunch:
                        model.Add(start_lunch[a_idx, t_target] == 1).OnlyEnforceIf(agent_does_this_act)
                    else:
                        if mode != 'none': model.Add(agent_does_this_act == 0)

        # =================================================================
        # PHASE 2 : ACTIVITÉS SECONDAIRES
        # =================================================================
        for s_idx, act in enumerate(sec_acts):
            if act.offer_name.strip() in skills:
                
                is_splittable = getattr(act, 'is_splittable', True)
                grp_key = activity_to_group[act.offer_name]
                
                p2_vars_for_agent = []

                # CAS 1 : BLOCS
                if act.blocks:
                    block_masters = []
                    for b in act.blocks:
                        bs, be = parse_t(b.start), parse_t(b.end)
                        block_vars = []
                        for t_idx, tm in enumerate(grid):
                            if (parse_t(agent.availability_start_time) <= tm < parse_t(agent.availability_end_time)) and (bs <= tm < be):
                                v = model.NewBoolVar(f'x_sec_a{a_idx}_s{s_idx}_t{t_idx}')
                                x_sec[a_idx, s_idx, t_idx] = v
                                block_vars.append(v)
                                p2_vars_for_agent.append(v)

                                if tm < PIVOT_NOON: acts_in_am.append(v)
                                else: acts_in_pm.append(v)
                                
                                model.Add(agent_active_on_group[a_idx, grp_key] >= v)

                                # Equité P2
                                if act.equity_group and act.equity_enabled:
                                    if (act.pool_key or act.equity_group) not in agent_equity_minutes[a_idx]:
                                        agent_equity_minutes[a_idx][act.pool_key or act.equity_group] = []
                                    agent_equity_minutes[a_idx][act.pool_key or act.equity_group].append(v)
                                    # Diversité inter-jours : accumulation par base_offer_name
                                    base = (act.base_offer_name or act.offer_name).strip()
                                    key = (a_idx, base)
                                    if key not in agent_offer_slots:
                                        agent_offer_slots[key] = []
                                    agent_offer_slots[key].append(v)

                                # HARD CONSTRAINT TT
                                if enforce_remote and t_idx in tt_slots and not act.is_remote_work_compatible:
                                    model.Add(v == 0)

                        if block_vars:
                            for i in range(len(block_vars) - 1): model.Add(block_vars[i] == block_vars[i+1])
                            block_masters.append(block_vars[0])
                    
                    if not is_splittable and len(block_masters) > 1:
                        for i in range(len(block_masters) - 1): model.Add(block_masters[i] == block_masters[i+1])
                    
                    if is_splittable and len(block_masters) > 1:
                        num_assigned = model.NewIntVar(0, len(block_masters), f'n_blks_a{a_idx}_s{s_idx}')
                        model.Add(num_assigned == sum(block_masters))
                        excess = model.NewIntVar(0, len(block_masters), f'ex_a{a_idx}_s{s_idx}')
                        model.Add(excess >= num_assigned - 1)
                        p2_diversification_penalties.append(excess)

                # CAS 2 : NON-SCINDABLE
                elif not is_splittable:
                    start_min = parse_t(act.start_time)
                    end_min = parse_t(act.end_time)
                    block_vars = []
                    for t_idx, tm in enumerate(grid):
                        if (start_min <= tm < end_min) and (parse_t(agent.availability_start_time) <= tm < parse_t(agent.availability_end_time)):
                            v = model.NewBoolVar(f'x_sec_a{a_idx}_s{s_idx}_t{t_idx}')
                            x_sec[a_idx, s_idx, t_idx] = v
                            block_vars.append(v)
                            p2_vars_for_agent.append(v)
                            
                            if tm < PIVOT_NOON: acts_in_am.append(v)
                            else: acts_in_pm.append(v)
                            
                            model.Add(agent_active_on_group[a_idx, grp_key] >= v)

                            if act.equity_group and act.equity_enabled:
                                if (act.pool_key or act.equity_group) not in agent_equity_minutes[a_idx]:
                                    agent_equity_minutes[a_idx][act.pool_key or act.equity_group] = []
                                agent_equity_minutes[a_idx][act.pool_key or act.equity_group].append(v)
                                # Diversité inter-jours : accumulation par base_offer_name
                                base = (act.base_offer_name or act.offer_name).strip()
                                key = (a_idx, base)
                                if key not in agent_offer_slots:
                                    agent_offer_slots[key] = []
                                agent_offer_slots[key].append(v)

                            # HARD CONSTRAINT TT (P2 non-scindable)
                            if enforce_remote and t_idx in tt_slots and not act.is_remote_work_compatible:
                                model.Add(v == 0)

                    if len(block_vars) > 1:
                        for i in range(len(block_vars) - 1): model.Add(block_vars[i] == block_vars[i+1])

                # CAS 3 : SCINDABLE
                else:
                    start_min = parse_t(act.start_time)
                    end_min = parse_t(act.end_time)
                    vars_sequence = []
                    for t_idx, tm in enumerate(grid):
                        if (start_min <= tm < end_min) and (parse_t(agent.availability_start_time) <= tm < parse_t(agent.availability_end_time)):
                            v = model.NewBoolVar(f'x_sec_a{a_idx}_s{s_idx}_t{t_idx}')
                            x_sec[a_idx, s_idx, t_idx] = v
                            vars_sequence.append(v)
                            p2_vars_for_agent.append(v)
                            
                            if tm < PIVOT_NOON: acts_in_am.append(v)
                            else: acts_in_pm.append(v)
                            
                            model.Add(agent_active_on_group[a_idx, grp_key] >= v)

                            if act.equity_group and act.equity_enabled:
                                if (act.pool_key or act.equity_group) not in agent_equity_minutes[a_idx]:
                                    agent_equity_minutes[a_idx][act.pool_key or act.equity_group] = []
                                agent_equity_minutes[a_idx][act.pool_key or act.equity_group].append(v)
                                # Diversité inter-jours : accumulation par base_offer_name
                                base = (act.base_offer_name or act.offer_name).strip()
                                key = (a_idx, base)
                                if key not in agent_offer_slots:
                                    agent_offer_slots[key] = []
                                agent_offer_slots[key].append(v)
                            
                            # HARD CONSTRAINT TT
                            if enforce_remote and t_idx in tt_slots and not act.is_remote_work_compatible:
                                model.Add(v == 0)
                    
                    if vars_sequence:
                        transitions = []
                        transitions.append((0, 0, 0))
                        transitions.append((0, 1, 1))
                        for s in range(1, MAX_SLOTS + 1):
                            if s < MAX_SLOTS: transitions.append((s, 1, s + 1))
                            if s >= MIN_SLOTS: transitions.append((s, 0, 0))
                        accepting_states = {0}
                        for s in range(MIN_SLOTS, MAX_SLOTS + 1): accepting_states.add(s)
                        model.AddAutomaton(vars_sequence, 0, accepting_states, transitions)

                # --- GESTION DU COLLAGE REPAS (P2) ---
                if p2_vars_for_agent:
                    mode = getattr(act, 'lunch_attach_mode', 'none')
                    if mode in ['before', 'after']:
                        t_target = -1
                        try:
                            if mode == 'before':
                                target_time = parse_t(act.start_time) - problem.lunch_duration_minutes
                                t_target = grid.index(target_time)
                            elif mode == 'after':
                                target_time = parse_t(act.end_time)
                                t_target = grid.index(target_time)
                        except ValueError: pass

                        if t_target != -1 and (a_idx, t_target) in start_lunch:
                            agent_does_p2 = model.NewBoolVar(f'does_p2_{a_idx}_{s_idx}')
                            model.AddMaxEquality(agent_does_p2, p2_vars_for_agent)
                            model.Add(start_lunch[a_idx, t_target] == 1).OnlyEnforceIf(agent_does_p2)
                        else:
                            if mode != 'none':
                                agent_does_p2 = model.NewBoolVar(f'does_p2_chk_{a_idx}_{s_idx}')
                                model.AddMaxEquality(agent_does_p2, p2_vars_for_agent)
                                model.Add(agent_does_p2 == 0)

        # PAUSES & REPAS
        am_vars = []
        for t in am_indices:
            t_time = grid[t]
            if t_time >= av_s and (t_time + break_dur_slots * slot_min) <= av_e:
                v = model.NewBoolVar(f'start_am_a{a_idx}_t{t}')
                start_am[a_idx, t] = v
                am_vars.append(v)
        if am_vars:
            model.Add(sum(am_vars) <= 1)
            has_work_am = model.NewBoolVar(f'work_am_a{a_idx}')
            if acts_in_am: 
                model.AddMaxEquality(has_work_am, acts_in_am)
                model.Add(sum(am_vars) == 0).OnlyEnforceIf(has_work_am.Not())
            else:
                model.Add(sum(am_vars) == 0)

        pm_vars = []
        for t in pm_indices:
            t_time = grid[t]
            if t_time >= av_s and (t_time + break_dur_slots * slot_min) <= av_e:
                v = model.NewBoolVar(f'start_pm_a{a_idx}_t{t}')
                start_pm[a_idx, t] = v
                pm_vars.append(v)
        if pm_vars:
            model.Add(sum(pm_vars) <= 1)
            has_work_pm = model.NewBoolVar(f'work_pm_a{a_idx}')
            if acts_in_pm: 
                model.AddMaxEquality(has_work_pm, acts_in_pm)
                model.Add(sum(pm_vars) == 0).OnlyEnforceIf(has_work_pm.Not())
            else:
                model.Add(sum(pm_vars) == 0)
        
        # LUNCH
        lunch_vars_agent = [start_lunch[a_idx, t] for t in lunch_indices if (a_idx, t) in start_lunch]
        if lunch_vars_agent:
            model.Add(sum(lunch_vars_agent) <= 1)
            is_present_all_day = model.NewBoolVar(f'present_all_a{a_idx}')
            model.AddBoolAnd([has_work_am, has_work_pm]).OnlyEnforceIf(is_present_all_day)
            model.Add(sum(lunch_vars_agent) == 1).OnlyEnforceIf(is_present_all_day)

            has_any_work = model.NewBoolVar(f'has_any_work_a{a_idx}')
            if acts_in_am or acts_in_pm:
                model.AddMaxEquality(has_any_work, acts_in_am + acts_in_pm)
                model.Add(sum(lunch_vars_agent) == 0).OnlyEnforceIf(has_any_work.Not())
            else:
                model.Add(sum(lunch_vars_agent) == 0)

        # Incompatibilités Offres
        for act in all_acts_combined:
            grp_a = activity_to_group[act.offer_name]
            for off_b in (act.incompatible_base_offers or []):
                if off_b in all_stagger_groups:
                    conflict_v = model.NewBoolVar(f'conf_{a_idx}_{grp_a}_{off_b}')
                    model.Add(conflict_v >= agent_active_on_group[a_idx, grp_a] + agent_active_on_group[a_idx, off_b] - 1)
                    agent_conflict_offers[a_idx].append(conflict_v)

    # NOUVEAU : Variables projetées pour la diversité inter-jours (en MINUTES)
    # Total projeté = historique PHP (current_offer_minutes) + slots assignés aujourd'hui
    agent_projected_minutes: Dict[Tuple[int, str], cp_model.IntVar] = {}
    for (a_idx, base_name), slot_vars in agent_offer_slots.items():
        if not slot_vars:
            continue
        agent_obj = agents[a_idx]
        historical_minutes = 0
        if agent_obj.current_offer_minutes:
            historical_minutes = agent_obj.current_offer_minutes.get(base_name, 0)

        today_minutes_var = sum(slot_vars) * slot_min  # slots → minutes
        max_possible = historical_minutes + (num_slots * slot_min)
        proj_min = model.NewIntVar(historical_minutes, max_possible, f'proj_a{a_idx}_{base_name}')
        model.Add(proj_min == historical_minutes + today_minutes_var)
        agent_projected_minutes[(a_idx, base_name)] = proj_min

    # NOUVEAU : Diversité V2 - pénalité d'écart à la cible par sous-activité
    diversity_penalty_vars: List[cp_model.IntVar] = []
    DIVERSITY_WEIGHT = 100

    for (a_idx, base_name), proj_min in agent_projected_minutes.items():
        target = target_offer_minutes.get((a_idx, base_name), 0)
        diff = model.NewIntVar(0, num_slots * slot_min * len(agents) + 10000,
                              f'diff_tgt_a{a_idx}_{base_name}')
        model.AddAbsEquality(diff, proj_min - target)
        diversity_penalty_vars.append(diff)

    # --- 4. CONTRAINTES GLOBALES ---
    is_eating = {}
    for a_idx in range(len(agents)):
        for t in range(num_slots):
            contributors = []
            for offset in range(lunch_dur_slots):
                st = t - offset
                if (a_idx, st) in start_lunch: contributors.append(start_lunch[a_idx, st])
            if contributors:
                v_eat = model.NewBoolVar(f'is_eating_a{a_idx}_t{t}')
                model.Add(sum(contributors) == v_eat)
                is_eating[a_idx, t] = v_eat

    for a_idx in range(len(agents)):
        for f_idx, act in enumerate(prio_acts):
            if not getattr(act, 'lunch_overlap_allowed', True):
                for t in range(num_slots):
                    if (a_idx, f_idx, t) in x_prio and (a_idx, t) in is_eating:
                        model.Add(x_prio[a_idx, f_idx, t] + is_eating[a_idx, t] <= 1)
        for s_idx, act in enumerate(sec_acts):
            if not getattr(act, 'lunch_overlap_allowed', True):
                for t in range(num_slots):
                    if (a_idx, s_idx, t) in x_sec and (a_idx, t) in is_eating:
                        model.Add(x_sec[a_idx, s_idx, t] + is_eating[a_idx, t] <= 1)

    # =================================================================
    # LOGIQUE DE LISSAGE (STAGGERING) : FOCUS AM/PM
    # =================================================================
    is_on_short_break = {} 
    
    for a_idx in range(len(agents)):
        for t in range(num_slots):
            short_break_contributors = []
            for offset in range(break_dur_slots):
                st = t - offset
                if (a_idx, st) in start_am: short_break_contributors.append(start_am[a_idx, st])
                if (a_idx, st) in start_pm: short_break_contributors.append(start_pm[a_idx, st])
            
            if short_break_contributors:
                v_sb = model.NewBoolVar(f'on_short_break_a{a_idx}_t{t}')
                model.Add(sum(short_break_contributors) == v_sb)
                is_on_short_break[a_idx, t] = v_sb

    staggering_penalty_vars = []
    
    for grp in all_stagger_groups:
        for t in range(num_slots):
            agents_breaking_on_group = []
            for a_idx in range(len(agents)):
                if (a_idx, t) in is_on_short_break:
                    sim_b = model.NewBoolVar(f'sim_short_brk_{grp}_a{a_idx}_t{t}')
                    model.AddBoolAnd([agent_active_on_group[a_idx, grp], is_on_short_break[a_idx, t]]).OnlyEnforceIf(sim_b)
                    model.AddBoolOr([agent_active_on_group[a_idx, grp].Not(), is_on_short_break[a_idx, t].Not()]).OnlyEnforceIf(sim_b.Not())
                    agents_breaking_on_group.append(sim_b)
            
            if len(agents_breaking_on_group) > 1:
                sum_breakers = model.NewIntVar(0, len(agents), f'sum_brk_{grp}_{t}')
                model.Add(sum_breakers == sum(agents_breaking_on_group))
                surplus = model.NewIntVar(0, len(agents), f'surplus_{grp}_{t}')
                model.Add(surplus >= sum_breakers - 1)
                staggering_penalty_vars.append(surplus)

    # =================================================================
    # EQUITÉ : quota proportionnel (obligatoire) vs équilibrage normalisé (optionnel)
    # =================================================================
    # Règle de résolution stricte : si au moins une activité du pool est obligatoire,
    # tout le pool devient obligatoire (le quota est plus fort que l'équilibrage).
    pool_has_mandatory: Dict[str, bool] = {}
    for a in all_acts_combined:
        if a.equity_group and a.equity_enabled:
            pool = a.pool_key or a.equity_group
            if not a.allow_shortfall:
                pool_has_mandatory[pool] = True
    # Un pool est optionnel seulement si TOUTES ses activités sont optionnelles.
    pool_is_optional: Dict[str, bool] = {}
    for a in all_acts_combined:
        if a.equity_group and a.equity_enabled:
            pool = a.pool_key or a.equity_group
            pool_is_optional[pool] = not pool_has_mandatory.get(pool, False)

    pool_cumul: Dict[str, List[Tuple[int, cp_model.IntVar]]] = {}
    pool_target: Dict[str, Dict[int, int]] = {}
    for i in range(len(agents)):
        for pool in agent_equity_minutes[i]:
            h_val = int(float((agents[i].current_realization or {}).get(pool, 0)))
            today_minutes = sum(agent_equity_minutes[i][pool]) * slot_min
            cumul = model.NewIntVar(0, 2000000, f'cumul_{i}_{pool}')
            model.Add(cumul == h_val + today_minutes)

            target = int(float((agents[i].target_quota_minutes or {}).get(pool, 0)))
            pool_cumul.setdefault(pool, []).append((i, cumul))
            pool_target.setdefault(pool, {})[i] = target

    equity_gap_weighted = 0
    balance_terms = []
    for pool, entries in pool_cumul.items():
        if pool_is_optional.get(pool, False):
            # Équilibrage normalisé : min-max sur cumul_i / target_i, exprimé en "pourcents".
            # Facteur d'échelle 100 : M devient un pourcentage fin (ex: 25, 75, 120).
            # Sans ce facteur, la division entière M*tgt >= cumul produit un effet
            # "escalier" : 25% et 99% de charge donnent tous deux M=1, aucun équilibrage.
            M = model.NewIntVar(0, 100000, f'balance_{pool}')
            for i, cumul in entries:
                tgt = pool_target[pool].get(i, 0)
                if tgt > 0:
                    model.Add(M * tgt >= cumul * 100)
            balance_terms.append(M)
        else:
            # Quota proportionnel : |cumul_i - cible_i|
            for i, cumul in entries:
                tgt = pool_target[pool].get(i, 0)
                gap = model.NewIntVar(0, 2000000, f'gap_{i}_{pool}')
                model.AddAbsEquality(gap, cumul - tgt)
                equity_gap_weighted += gap

    # --- 6. OBJECTIFS ---
    MANDATORY_COVERAGE_WEIGHT = 10**10
    OPTIONAL_COVERAGE_WEIGHT = 10**4
    EQUITY_WEIGHT = 10**6
    BALANCE_WEIGHT = 10

    def shortfall_weight(allow_shortfall: bool) -> int:
        return OPTIONAL_COVERAGE_WEIGHT if allow_shortfall else MANDATORY_COVERAGE_WEIGHT

    shortfall_weighted_terms = []

    for f_idx, act in enumerate(prio_acts):
        w = shortfall_weight(act.allow_shortfall)
        for t_idx, tm in enumerate(grid):
            if parse_t(act.start_time) <= tm < parse_t(act.end_time):
                sf = model.NewIntVar(0, act.quantity, f'sf_prio_{f_idx}_{t_idx}')
                assigned = [x_prio[a_idx, f_idx, t_idx] for a_idx in range(len(agents)) if (a_idx, f_idx, t_idx) in x_prio]
                model.Add(sum(assigned) == act.quantity - sf)
                shortfall_weighted_terms.append(sf * w)

    for s_idx, act in enumerate(sec_acts):
        w = shortfall_weight(act.allow_shortfall)
        ranges_to_cover = []
        if act.blocks:
            ranges_to_cover = [(parse_t(b.start), parse_t(b.end)) for b in act.blocks]
        else:
            ranges_to_cover = [(parse_t(act.start_time), parse_t(act.end_time))]

        for t_idx, tm in enumerate(grid):
            in_range = any(r[0] <= tm < r[1] for r in ranges_to_cover)
            if in_range:
                sf = model.NewIntVar(0, act.quantity, f'sf_sec_{s_idx}_{t_idx}')
                assigned = [x_sec[a_idx, s_idx, t_idx] for a_idx in range(len(agents)) if (a_idx, s_idx, t_idx) in x_sec]
                model.Add(sum(assigned) == act.quantity - sf)
                shortfall_weighted_terms.append(sf * w)

    # UNICITE GLOBALE
    for a_idx in range(len(agents)):
        for t_idx in range(num_slots):
            prio_tasks = [x_prio[a_idx, fi, t_idx] for fi in range(len(prio_acts)) if (a_idx, fi, t_idx) in x_prio]
            sec_tasks = [x_sec[a_idx, si, t_idx] for si in range(len(sec_acts)) if (a_idx, si, t_idx) in x_sec]
            lunch_task = [start_lunch[a_idx, t_idx]] if (a_idx, t_idx) in start_lunch else []
            model.Add(sum(prio_tasks + sec_tasks + lunch_task) <= 1)

    all_pauses = list(start_am.values()) + list(start_pm.values())
    all_lunches = list(start_lunch.values())

    # --- MINIMISATION (POIDS REAJUSTÉS) ---
    model.Minimize(
        sum(shortfall_weighted_terms) +
        sum(p2_diversification_penalties) * 10**5 +
        sum(sum(c) for c in agent_conflict_offers.values()) * 10**7 +
        sum(staggering_penalty_vars) * 10**6 +
        equity_gap_weighted * EQUITY_WEIGHT +
        sum(balance_terms) * BALANCE_WEIGHT +
        sum(all_pauses) * -10**5 +
        sum(all_lunches) * -10**5 -
        sum(x_prio.values()) -
        sum(x_sec.values()) +
        sum(diversity_penalty_vars) * DIVERSITY_WEIGHT
    )

    solver = cp_model.CpSolver()
    solver.parameters.max_time_in_seconds = max(1.0, float(problem.timeout_seconds))
    status = solver.Solve(model)

    # =================================================================
    # DEBUG / LOGS
    # =================================================================
    if status in [cp_model.OPTIMAL, cp_model.FEASIBLE]:
        print("\n" + "#"*60)
        print(" DIAGNOSTIC DES PAUSES COURTES SIMULTANÉES (AM/PM)")
        print(" (Le déjeuner est exclu de ce tableau)")
        print("#"*60)
        
        for grp in all_stagger_groups:
            print(f"\n--- GROUPE : {grp} ---")
            
            relevant_agents_indices = []
            for a_idx in range(len(agents)):
                if (a_idx, grp) in agent_active_on_group and solver.Value(agent_active_on_group[a_idx, grp]):
                    relevant_agents_indices.append(a_idx)
            
            if not relevant_agents_indices:
                print("  (Aucun agent affecté à ce groupe)")
                continue

            header_printed = False
            for t_idx, tm in enumerate(grid):
                agents_on_break_now = []
                for a_idx in relevant_agents_indices:
                    if (a_idx, t_idx) in is_on_short_break and solver.Value(is_on_short_break[a_idx, t_idx]):
                        agents_on_break_now.append(agents[a_idx].name)
                
                if len(agents_on_break_now) > 1: 
                    if not header_printed:
                         print(f"  {'HEURE':<10} | {'NB':<3} | AGENTS EN PAUSE (15min) SIMULTANÉE")
                         header_printed = True
                    print(f"  {fmt_t(tm):<10} | {len(agents_on_break_now):<3} | {', '.join(agents_on_break_now)}")
        
        print("\n" + "#"*60 + "\n")

    final_assignments = []
    
    if status in [cp_model.OPTIMAL, cp_model.FEASIBLE]:
        agent_assignments = collections.defaultdict(list)
        
        # P1
        for (a, f, t), var in x_prio.items():
            if solver.Value(var):
                ass_obj = {
                    "agent_id": agents[a].id, 
                    "activity": prio_acts[f].offer_name, 
                    "start": fmt_t(grid[t]), 
                    "end": fmt_t(grid[t] + slot_min),
                    "breaks": {} 
                }
                ass_obj["_ts_start"] = grid[t]
                ass_obj["_ts_end"] = grid[t] + slot_min
                agent_assignments[a].append(ass_obj)

        # P2
        for (a, s, t), var in x_sec.items():
            if solver.Value(var):
                ass_obj = {
                    "agent_id": agents[a].id, 
                    "activity": sec_acts[s].offer_name, 
                    "start": fmt_t(grid[t]), 
                    "end": fmt_t(grid[t] + slot_min),
                    "breaks": {} 
                }
                ass_obj["_ts_start"] = grid[t]
                ass_obj["_ts_end"] = grid[t] + slot_min
                agent_assignments[a].append(ass_obj)

        def inject_break(a_idx, t_start, type_key, d_slots):
            brk_s = grid[t_start]
            brk_e = grid[t_start + d_slots] if t_start + d_slots < len(grid) else grid[-1]
            if a_idx in agent_assignments:
                for ass in agent_assignments[a_idx]:
                    if (ass["_ts_start"] < brk_e and ass["_ts_end"] > brk_s) or \
                       (ass["_ts_end"] == brk_s) or \
                       (ass["_ts_start"] == brk_e):
                        ass["breaks"][type_key] = [fmt_t(brk_s), fmt_t(brk_e)]
                        break

        for (a_idx, t), var in start_am.items():
            if solver.Value(var): inject_break(a_idx, t, "am_break", break_dur_slots)
        
        for (a_idx, t), var in start_pm.items():
            if solver.Value(var): inject_break(a_idx, t, "pm_break", break_dur_slots)

        for (a_idx, t), var in start_lunch.items():
            if solver.Value(var): inject_break(a_idx, t, "lunch", lunch_dur_slots)

        for ag_list in agent_assignments.values():
            for ass in ag_list:
                del ass["_ts_start"]
                del ass["_ts_end"]
                if not ass["breaks"]: del ass["breaks"]
                final_assignments.append(ass)

    ok = status in (cp_model.OPTIMAL, cp_model.FEASIBLE)
    status_str = ("OPTIMAL" if status == cp_model.OPTIMAL
                  else "FEASIBLE" if status == cp_model.FEASIBLE
                  else solver.StatusName(status))
    return {
        "status": status_str,
        "assignments": final_assignments,
        "shortfalls": {},
        "wall_time_seconds": round(solver.WallTime(), 3),
        "objective_value": solver.ObjectiveValue() if ok else None,
        "best_bound": solver.BestObjectiveBound() if ok else None,
    }