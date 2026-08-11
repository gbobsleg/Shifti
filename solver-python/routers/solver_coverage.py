# -*- coding: utf-8 -*-
# Solveur "coverage" — Passe 2
# Correctif v8 (Quadratic Smoothing) :
# 1. Labels STRICTS (LUNCH, AM_BREAK, PM_BREAK).
# 2. Suppression de la dispersion "pairwise" (trop lourde).
# 3. Implémentation du LISSAGE QUADRATIQUE (Sum of Squares) :
#    Le solveur minimise la somme des carrés du nombre d'agents en pause par créneau.
#    Cela force mathématiquement une répartition parfaitement égale (flat curve).
# 4. Maintien du "Soft Cap 33%" comme sécurité anti-crash.

from __future__ import annotations

from typing import List, Dict, Optional, Any, Tuple, Set
import time
from fastapi import APIRouter
from ortools.sat.python import cp_model
import re
import math

from models.data_models import Agent, Window, MinMaxOffer, Problem, OfferGroupSpec


def build_offer_equity_bucket_map(
    offers: List[str],
    offer_groups: Optional[List[OfferGroupSpec]],
) -> Dict[str, str]:
    """Mappe chaque offre vers son seau d'équité (nom de groupe ou offre elle-même)."""
    mapping: Dict[str, str] = {off: off for off in offers}
    for g in offer_groups or []:
        for m in g.members:
            if m in mapping:
                mapping[m] = g.name
        if g.mixed in mapping:
            mapping[g.mixed] = g.name
    return mapping


def _resolve_offer_groups(
    offers: List[str],
    offer_groups: Optional[List[OfferGroupSpec]],
) -> List[Dict[str, Any]]:
    """Indexe les groupes présents dans `offers` pour la couverture."""
    resolved: List[Dict[str, Any]] = []
    if not offer_groups:
        return resolved
    for g in offer_groups:
        if g.mixed not in offers:
            continue
        member_ks = [offers.index(m) for m in g.members if m in offers]
        if len(member_ks) < 2:
            continue
        resolved.append({
            "name": g.name,
            "mixed_k": offers.index(g.mixed),
            "member_ks": member_ks,
            "prefer_mixed": bool(g.prefer_mixed),
            "members": list(g.members),
            "mixed": g.mixed,
        })
    return resolved

# =========================
# Utilitaires temps
# =========================

TIME_RE_HHMM = re.compile(r"^\d{2}:\d{2}$")
TIME_RE_HHMMSS = re.compile(r"^\d{2}:\d{2}:\d{2}$")
INTERVAL_RE = re.compile(r"^interval_(\d+)$")


def parse_hhmm_or_hhmmss(s: str) -> int:
    if TIME_RE_HHMMSS.match(s):
        h, m, _ = map(int, s.split(":"))
        return h * 60 + m
    if TIME_RE_HHMM.match(s):
        h, m = map(int, s.split(":"))
        return h * 60 + m
    raise ValueError(f"Format horaire invalide: {s} (attendu HH:MM[:SS])")


def fmt_hhmmss(total_minutes: int) -> str:
    h = (total_minutes // 60) % 24
    m = total_minutes % 60
    return f"{h:02d}:{m:02d}:00"


def build_grid(start_min: int, end_min: int, step: int) -> Tuple[List[int], List[str], int]:
    if end_min <= start_min:
        raise ValueError("workday_end_time doit être > workday_start_time")
    starts = list(range(start_min, end_min, step))
    return starts, [fmt_hhmmss(x) for x in starts], end_min


def validate_curve_keys(offers: List[str], need_curve: Dict[str, Dict[str, int]], starts_str: List[str]) -> None:
    allowed_times = set(starts_str)
    invalid: Dict[str, List[str]] = {}
    for off, curve in need_curve.items():
        bad = []
        for tk in curve.keys():
            _tk = tk
            if TIME_RE_HHMM.match(_tk):
                _tk = _tk + ":00"
            if TIME_RE_HHMMSS.match(_tk):
                if _tk not in allowed_times:
                    bad.append(tk)
            else:
                m = INTERVAL_RE.match(_tk)
                if not m:
                    bad.append(tk)
        if bad:
            invalid[off] = bad[:10]
    if invalid:
        details = []
        for off, keys in invalid.items():
            details.append(f"{off}: clés invalides ou hors grille: {keys}")
        raise ValueError("need_curve contient des clés horaires invalides. " + ", ".join(details))



# =========================
# Explication d'infaisabilité (assumptions)
# =========================

ASSUMPTION_FAMILIES = (
    "forced_presence_fixed",
    "no_idle",
    "mandatory_lunch",
    "am_break",
    "pm_break",
    "earliest_end",
)

ASSUMPTION_MESSAGES_FR = {
    "forced_presence_fixed": "présence forcée après activité fixe",
    "no_idle": "interdiction d'inactivité (no idle)",
    "mandatory_lunch": "déjeuner obligatoire",
    "am_break": "pause AM obligatoire",
    "pm_break": "pause PM obligatoire",
    "earliest_end": "borne de fin minimale (earliest end)",
}


def _assumption_label(agent_id: int, family: str) -> str:
    return f"agent_{agent_id}_{family}"


def _message_fr_for_label(label: str) -> str:
    # label = agent_{id}_{family}
    parts = label.split("_", 2)
    if len(parts) < 3 or parts[0] != "agent":
        return label
    try:
        agent_id = int(parts[1])
    except ValueError:
        return label
    family = parts[2]
    fr = ASSUMPTION_MESSAGES_FR.get(family, family)
    return f"Agent #{agent_id} : {fr}"


def _add_hard(model: cp_model.CpModel, expr, lit=None):
    """Ajoute une contrainte dure, optionnellement sous assumption (OnlyEnforceIf)."""
    ct = model.Add(expr)
    if lit is not None:
        ct.OnlyEnforceIf(lit)
    return ct


def _add_hard_enf(model: cp_model.CpModel, expr, enforce_lits, lit=None):
    """Add(expr).OnlyEnforceIf(enforce_lits [+ lit assumption])."""
    ct = model.Add(expr)
    lits = list(enforce_lits) if isinstance(enforce_lits, (list, tuple)) else [enforce_lits]
    if lit is not None:
        lits = lits + [lit]
    ct.OnlyEnforceIf(lits)
    return ct



# =========================
# Solveur "coverage"
# =========================

router = APIRouter()


def build_hard_coverage_model(
    problem: Problem,
    *,
    assumption_agent_ids: Optional[Set[int]] = None,
    debug: bool = False,
) -> Dict[str, Any]:
    """Construit le modèle CP-SAT (variables + contraintes dures).

    Point unique de création des contraintes dures : utilisé par le solveur nominal
    (assumption_agent_ids=None → toutes les familles en dur) et par
    explain_infeasibility (littéraux d'assumption sur les agents ciblés).
    """
    def dlog(msg: str) -> None:
        if debug:
            print(msg)

    # Grille
    slot = problem.slot_minutes
    wd_start = parse_hhmm_or_hhmmss(problem.workday_start_time)
    wd_end = parse_hhmm_or_hhmmss(problem.workday_end_time)
    starts_min, starts_str, end_min = build_grid(wd_start, wd_end, slot)
    N = len(starts_min)
    offers = problem.offers
    K = len(offers)
    A = len(problem.agents)
    agents_with_unavailable = sum(1 for ag in problem.agents if ag.unavailable_intervals)
    dlog(f"[COVERAGE] solve_schedule: {A} agents, dont {agents_with_unavailable} avec unavailable_intervals")

    validate_curve_keys(offers, problem.need_curve, starts_str)

    # Tableaux de besoins
    time_to_idx = {t: i for i, t in enumerate(starts_str)}
    need_by_index: List[List[int]] = []
    for off in offers:
        curve = problem.need_curve.get(off, {})
        arr = [0] * N
        for tk, val in curve.items():
            _tk = tk
            if TIME_RE_HHMM.match(_tk):
                _tk = _tk + ":00"
            if TIME_RE_HHMMSS.match(_tk) and _tk in time_to_idx:
                arr[time_to_idx[_tk]] = int(val)
        for tk, val in curve.items():
            m = INTERVAL_RE.match(tk)
            if m:
                idx = int(m.group(1))
                if 0 <= idx < N:
                    arr[idx] = int(val)
        need_by_index.append(arr)

    # Groupes d'offres (optionnel) : need mixte forcé à 0 avant logs / équations
    resolved_groups = _resolve_offer_groups(offers, getattr(problem, "offer_groups", None))
    mixed_ks: Set[int] = {int(gs["mixed_k"]) for gs in resolved_groups}
    grouped_member_ks: Set[int] = set()
    for gs in resolved_groups:
        grouped_member_ks.update(int(k) for k in gs["member_ks"])
        mk = int(gs["mixed_k"])
        for i in range(N):
            need_by_index[mk][i] = 0
    if resolved_groups:
        print(
            "[COVERAGE] offer_groups actifs: "
            + ", ".join(
                f"{gs['name']}(mixed={gs['mixed']},members={gs['members']},prefer_mixed={gs['prefer_mixed']})"
                for gs in resolved_groups
            )
        )

    # Logs compacts needs
    try:
        sample_times = []
        for t in ["10:00:00", "11:00:00", "12:00:00", "13:00:00", "14:00:00", "15:00:00", "16:00:00"]:
            if t in time_to_idx:
                sample_times.append(t)

        total_need_all = 0
        peak_all = 0
        parts = []
        for k, off in enumerate(offers):
            total = sum(int(v) for v in need_by_index[k])
            peak = max((int(v) for v in need_by_index[k]), default=0)
            total_need_all += total
            peak_all = max(peak_all, peak)
            snaps = []
            for t in sample_times:
                snaps.append(f"{t[0:5]}={int(need_by_index[k][time_to_idx[t]])}")
            snap_s = (" " + ",".join(snaps)) if snaps else ""
            parts.append(f"{off}:total={total},peak={peak}{snap_s}")

        print(f"[COVERAGE] needs: total_all={total_need_all} peak_any={peak_all} | " + " | ".join(parts))
    except Exception:
        print("[COVERAGE] needs: (unavailable)")

    need_total_by_i: List[int] = [0] * N
    for i in range(N):
        need_total_by_i[i] = sum(int(need_by_index[k][i]) for k in range(K))

    model = cp_model.CpModel()
    x = [[[model.NewBoolVar(f"x_a{a}_i{i}_k{k}") for k in range(K)] for i in range(N)] for a in range(A)]

    # Restrict / Cap
    restrict_set = set(problem.restrict_to_need_offers or [])
    for k, off in enumerate(offers):
        if off in restrict_set:
            for i in range(N):
                if int(need_by_index[k][i]) == 0:
                    for a in range(A):
                        model.Add(x[a][i][k] == 0)

    # Fenêtres
    am_w = problem.am_break_window or Window(start="10:00:00", end="11:30:00")
    pm_w = problem.pm_break_window or Window(start="15:00:00", end="16:30:00")
    lu_w = problem.lunch_window or Window(start="12:00:00", end="14:00:00")
    break_slots = max(1, problem.break_duration_minutes // slot)
    lunch_slots = max(1, problem.lunch_duration_minutes // slot)
    am_start_min = parse_hhmm_or_hhmmss(am_w.start)
    am_end_min = parse_hhmm_or_hhmmss(am_w.end)
    pm_start_min = parse_hhmm_or_hhmmss(pm_w.start)
    pm_end_min = parse_hhmm_or_hhmmss(pm_w.end)
    lu_start_min = parse_hhmm_or_hhmmss(lu_w.start)
    lu_end_min = parse_hhmm_or_hhmmss(lu_w.end)

    w_morning = [i for i, s in enumerate(starts_min) if s + slot <= lu_start_min]
    w_midday = [i for i, s in enumerate(starts_min) if (s >= lu_start_min and s + slot <= lu_end_min)]
    w_afternoon = [i for i, s in enumerate(starts_min) if s >= lu_end_min]

    lu_candidates: List[int] = []
    for i, s in enumerate(starts_min):
        e = s + lunch_slots * slot
        if s >= lu_start_min and e <= lu_end_min:
            lu_candidates.append(i)
    am_candidates: List[int] = []
    pm_candidates: List[int] = []
    for i, s in enumerate(starts_min):
        e = s + break_slots * slot
        if s >= am_start_min and e <= am_end_min:
            am_candidates.append(i)
        if s >= pm_start_min and e <= pm_end_min:
            pm_candidates.append(i)
    
    lunch = [[model.NewBoolVar(f"lunch_a{a}_i{i}") for i in range(N)] for a in range(A)]
    am_break = [[model.NewBoolVar(f"am_break_a{a}_i{i}") for i in range(N)] for a in range(A)]
    pm_break = [[model.NewBoolVar(f"pm_break_a{a}_i{i}") for i in range(N)] for a in range(A)]

    lunch_block_need_avg_by_start: Dict[int, int] = {}
    for i in lu_candidates:
        block_sum = 0
        for j in range(i, i + lunch_slots):
            block_sum += need_total_by_i[j]
        lunch_block_need_avg_by_start[i] = block_sum // lunch_slots if lunch_slots > 0 else block_sum

    lunch_start_by_agent: List[Dict[int, cp_model.IntVar]] = [dict() for _ in range(A)]

    end_idx = [model.NewIntVar(0, N, f"end_idx_a{a}") for a in range(A)]
    before_end = [[model.NewBoolVar(f"before_end_a{a}_i{i}") for i in range(N)] for a in range(A)]

    same_offer_am_pm_flags: List[cp_model.IntVar] = []
    ampm_both_flags: List[cp_model.IntVar] = []
    continuity_penalties: List[cp_model.IntVar] = []
    sel_morning_by_a: List[List[cp_model.IntVar]] = []
    sel_afternoon_by_a: List[List[cp_model.IntVar]] = []
    lunch_attach_penalties: List[cp_model.IntVar] = []

    has_fixed_activity = [bool(ag.unavailable_intervals) for ag in problem.agents]
    am_idx_av_by_agent: List[List[int]] = [[] for _ in range(A)]
    pm_idx_av_by_agent: List[List[int]] = [[] for _ in range(A)]
    agent_diagnostics: List[dict] = []
    
    assumption_agent_ids = set(assumption_agent_ids or [])
    assumption_lits: List[cp_model.IntVar] = []
    assumption_labels: List[str] = []
    label_by_lit_index: Dict[int, str] = {}

    def _register_assumption(agent_id: int, family: str):
        label = _assumption_label(agent_id, family)
        lit = model.NewBoolVar(f"assump_{label}")
        assumption_lits.append(lit)
        assumption_labels.append(label)
        label_by_lit_index[lit.Index()] = label
        return lit

    for a, ag in enumerate(problem.agents):
        use_assump = ag.id in assumption_agent_ids
        lit_forced_presence = _register_assumption(ag.id, "forced_presence_fixed") if use_assump else None
        lit_no_idle = _register_assumption(ag.id, "no_idle") if use_assump else None
        lit_mandatory_lunch = _register_assumption(ag.id, "mandatory_lunch") if use_assump else None
        lit_am_break = _register_assumption(ag.id, "am_break") if use_assump else None
        lit_pm_break = _register_assumption(ag.id, "pm_break") if use_assump else None
        lit_earliest_end = _register_assumption(ag.id, "earliest_end") if use_assump else None
        allowed = set(ag.skills or [])
        agent_eligible_offers = [off for off in offers if off in allowed]
        avl_s = parse_hhmm_or_hhmmss(ag.availability_start_time)
        avl_e = parse_hhmm_or_hhmmss(ag.availability_end_time)
        has_any_skill = any(off in allowed for off in offers)
        in_av_idx = [i for i, s in enumerate(starts_min) if (s >= avl_s and s + slot <= avl_e)]

        blocked_slots_set = set()

        for i, s in enumerate(starts_min):
            in_avail = (s >= avl_s and s + slot <= avl_e)
            for k, off in enumerate(offers):
                if (not in_avail) or (off not in allowed):
                    model.Add(x[a][i][k] == 0)
            if not (s >= lu_start_min and s + slot <= lu_end_min):
                model.Add(lunch[a][i] == 0)
            if not (s >= am_start_min and s + slot <= am_end_min):
                model.Add(am_break[a][i] == 0)
            if not (s >= pm_start_min and s + slot <= pm_end_min):
                model.Add(pm_break[a][i] == 0)
            if not in_avail:
                model.Add(before_end[a][i] == 0)
        
        lunch_forbidden_slots_set: Set[int] = set()
        breaks_forbidden_slots_set: Set[int] = set()
        forces_lunch_slots_set: Set[int] = set()
        if ag.unavailable_intervals:
            for interval in ag.unavailable_intervals:
                start_block = parse_hhmm_or_hhmmss(interval['start'])
                end_block = parse_hhmm_or_hhmmss(interval['end'])
                allow_lunch = interval.get('allow_lunch', True)
                allow_breaks = interval.get('allow_breaks', True)
                forces_lunch = interval.get('forces_lunch', False)
                
                for i, s in enumerate(starts_min):
                    slot_start = s
                    slot_end = s + slot
                    if slot_start < end_block and slot_end > start_block:
                        blocked_slots_set.add(i)
                        for k in range(K):
                            model.Add(x[a][i][k] == 0)
                        if not allow_lunch:
                            lunch_forbidden_slots_set.add(i)
                        if not allow_breaks:
                            breaks_forbidden_slots_set.add(i)
                        if forces_lunch:
                            forces_lunch_slots_set.add(i)

            for i in lunch_forbidden_slots_set:
                model.Add(lunch[a][i] == 0)
            for i in breaks_forbidden_slots_set:
                model.Add(am_break[a][i] == 0)
                model.Add(pm_break[a][i] == 0)

        # --- CORRECTIF CONTINUITÉ : FORCE LA PRÉSENCE EN RÉUNION ---
        # PLACÉ APRÈS le remplissage de blocked_slots_set
        for i in blocked_slots_set:
            if i in in_av_idx:
                _add_hard(model, before_end[a][i] == 1, lit_forced_presence)
                # Force le slot d'après si dispo pour obliger la reprise
                if (i + 1) in in_av_idx and (i + 1) < N:
                    _add_hard(model, before_end[a][i+1] == 1, lit_forced_presence)

        # ==============================================================================
        # GESTION DU TRAVAIL IMPOSÉ (ROTATION / FIXED WORK)
        # ==============================================================================
        if hasattr(problem, "fixed_work") and problem.fixed_work:
            for work in problem.fixed_work:
                # 1. Vérifier si c'est pour cet agent
                w_uid = getattr(work, 'user_id', work.get('user_id') if isinstance(work, dict) else None)
                if w_uid != ag.id:
                    continue
                
                # 2. Parsing des horaires
                try:
                    w_start_val = getattr(work, 'start', work.get('start'))
                    w_end_val = getattr(work, 'end', work.get('end'))
                    w_start = parse_hhmm_or_hhmmss(w_start_val)
                    w_end = parse_hhmm_or_hhmmss(w_end_val)
                except ValueError:
                    continue # Horaire invalide, on ignore

                # 3. Récupération de l'Offre Cible (Nom -> Index k)
                target_k = None
                w_offer_name = getattr(work, 'offer_name', work.get('offer_name') if isinstance(work, dict) else None)
                
                if w_offer_name and w_offer_name in offers:
                    target_k = offers.index(w_offer_name)
                
                # 4. Application des contraintes sur les créneaux concernés
                # On scanne la grille temporelle
                for i, s in enumerate(starts_min):
                    # Si le créneau 'i' est DANS la période de travail imposé [Start, End[
                    if s >= w_start and s < w_end:
                        
                        # A. FORCER LE TRAVAIL
                        if target_k is not None:
                            # Cas : Offre précise imposée (ex: Rotation sur "Appels")
                            # L'agent DOIT travailler sur l'offre k
                            model.Add(x[a][i][target_k] == 1)
                            
                            # Par sécurité, on force les autres offres à 0 (implicite mais propre)
                            for k in range(K):
                                if k != target_k:
                                    model.Add(x[a][i][k] == 0)
                        else:
                            # Cas : Travail générique (pas d'offre précisée)
                            # La somme des activités doit faire 1
                            model.Add(sum(x[a][i][k] for k in range(K)) == 1)

                        # B. INTERDIRE LES PAUSES
                        # Si on est en "Fixed Work", on ne mange pas et on ne pause pas sur ce créneau
                        # (La rotation a dû gérer les trous pour le repas avant)
                        model.Add(lunch[a][i] == 0)
                        model.Add(am_break[a][i] == 0)
                        model.Add(pm_break[a][i] == 0)
                        
                        # C. FORCER LA PRÉSENCE (before_end)
                        # Si on travaille, on n'est pas "parti"
                        model.Add(before_end[a][i] == 1)

        # ... (Reprendre ici avec # Tie before_end)

        # Tie before_end
        in_av_idx = [i for i, s in enumerate(starts_min) if (s >= avl_s and s + slot <= avl_e)]
        for pos, i in enumerate(in_av_idx):
            model.Add(end_idx[a] >= pos + 1).OnlyEnforceIf(before_end[a][i])
            model.Add(end_idx[a] <= pos).OnlyEnforceIf(before_end[a][i].Not())
        model.Add(sum(before_end[a][i] for i in in_av_idx) == end_idx[a])
        for idx_pos in range(1, len(in_av_idx)):
            i_prev = in_av_idx[idx_pos - 1]
            i_curr = in_av_idx[idx_pos]
            model.Add(before_end[a][i_curr] <= before_end[a][i_prev])

        in_av_len = len(in_av_idx)
        if problem.strict_work_hours:
            _add_hard(model, end_idx[a] == in_av_len, lit_earliest_end)
        else:
            ee_min = parse_hhmm_or_hhmmss(ag.earliest_end_time) if ag.earliest_end_time else avl_e
            ee_slots_before = sum(1 for i in in_av_idx if (starts_min[i] + slot) < ee_min)
            _add_hard(model, end_idx[a] >= min(in_av_len, ee_slots_before + 1), lit_earliest_end)
            model.Add(end_idx[a] <= in_av_len)

        if has_any_skill:
            for i in in_av_idx:
                total_work = sum(x[a][i][k] for k in range(K))
                total_acts = total_work + lunch[a][i] + am_break[a][i] + pm_break[a][i]
                
                if i in blocked_slots_set:
                    model.Add(total_work == 0)
                    model.Add(lunch[a][i] + am_break[a][i] + pm_break[a][i] <= 1).OnlyEnforceIf(before_end[a][i])
                    model.Add(lunch[a][i] + am_break[a][i] + pm_break[a][i] == 0).OnlyEnforceIf(before_end[a][i].Not())
                    # Tie before_end to activity
                    # --- CORRECTIF 2 : PERMISSION (AUTORISE L'INACTIVITÉ SI RÉUNION) ---
                    # Cas Réunion : Si Présent, on a le droit de ne rien produire (total_acts = 0)
                    model.Add(total_acts == 0).OnlyEnforceIf(before_end[a][i].Not())
                else:
                    # Tie before_end to activity
                    # --- CORRECTIF 2 : PERMISSION (AUTORISE L'INACTIVITÉ SI RÉUNION) ---
                    # Cas normal : Si Présent, on doit bosser ou manger
                    _add_hard_enf(model, total_acts == 1, before_end[a][i], lit_no_idle)
                    model.Add(total_acts == 0).OnlyEnforceIf(before_end[a][i].Not())

        for i in range(N):
            model.AddImplication(lunch[a][i], before_end[a][i])
            model.AddImplication(am_break[a][i], before_end[a][i])
            model.AddImplication(pm_break[a][i], before_end[a][i])

        # --- CORRECTION INFAISABILITÉ (Maintenue) ---
        any_before = model.NewBoolVar(f"any_before_lunch_a{a}")
        any_after = model.NewBoolVar(f"any_after_lunch_a{a}")
        sum_before = []
        sum_after = []
        for i, s in enumerate(starts_min):
            if i in in_av_idx:
                if s + slot <= lu_start_min:
                    expr = sum(x[a][i][k] for k in range(K))
                    if i in forces_lunch_slots_set:
                        expr += 1
                    sum_before.append(expr)
                if s >= lu_end_min:
                    expr = sum(x[a][i][k] for k in range(K))
                    if i in forces_lunch_slots_set:
                        expr += 1
                    sum_after.append(expr)
        
        if sum_before:
            total_before = model.NewIntVar(0, len(sum_before), f"total_before_lunch_a{a}")
            model.Add(total_before == sum(sum_before))
            model.Add(total_before >= 1).OnlyEnforceIf(any_before)
            model.Add(total_before == 0).OnlyEnforceIf(any_before.Not())
        else:
            model.Add(any_before == 0)
        
        if sum_after:
            total_after = model.NewIntVar(0, len(sum_after), f"total_after_lunch_a{a}")
            model.Add(total_after == sum(sum_after))
            model.Add(total_after >= 1).OnlyEnforceIf(any_after)
            model.Add(total_after == 0).OnlyEnforceIf(any_after.Not())
        else:
            model.Add(any_after == 0)

        both_sides = model.NewBoolVar(f"both_sides_lunch_a{a}")
        model.AddImplication(both_sides, any_before)
        model.AddImplication(both_sides, any_after)
        model.AddBoolAnd([any_before, any_after]).OnlyEnforceIf(both_sides)
        model.AddBoolOr([any_before.Not(), any_after.Not()]).OnlyEnforceIf(both_sides.Not())

        lu_candidates_av = [
            i for i in lu_candidates
            if i in in_av_idx
            and (i + lunch_slots - 1) in in_av_idx
            and all(j not in lunch_forbidden_slots_set for j in range(i, i + lunch_slots))
        ]
        lu_start_vars = [model.NewBoolVar(f"lunch_start_a{a}_i{i}") for i in lu_candidates_av]
        for idx, i0 in enumerate(lu_candidates_av):
            for j in range(i0, i0 + lunch_slots):
                model.Add(lunch[a][j] >= lu_start_vars[idx])
            lunch_start_by_agent[a][i0] = lu_start_vars[idx]
        
        for j in range(N):
            cover = []
            for idx, i0 in enumerate(lu_candidates_av):
                if i0 <= j < i0 + lunch_slots:
                    cover.append(lu_start_vars[idx])
            if cover:
                model.Add(lunch[a][j] <= sum(cover))
            else:
                model.Add(lunch[a][j] == 0)

        if lu_start_vars:
            _add_hard_enf(model, sum(lu_start_vars) == 1, both_sides, lit_mandatory_lunch)
            _add_hard_enf(model, sum(lunch[a][j] for j in range(N)) == lunch_slots, both_sides, lit_mandatory_lunch)
        
        for j in range(N):
            model.Add(lunch[a][j] == 0).OnlyEnforceIf(both_sides.Not())

        preferred_lunch_starts = getattr(ag, "preferred_lunch_starts", None)
        if preferred_lunch_starts and lu_start_vars:
            preferred_indices: List[int] = []
            for t in preferred_lunch_starts:
                try:
                    t_min = parse_hhmm_or_hhmmss(t)
                except Exception:
                    continue
                for i0 in lu_candidates_av:
                    if starts_min[i0] == t_min:
                        preferred_indices.append(i0)
                        break
            if preferred_indices:
                dist_vars: List[cp_model.IntVar] = []
                max_dist = len(lu_candidates_av) if lu_candidates_av else 0
                for p in preferred_indices:
                    if not lu_candidates_av:
                        continue
                    dist_p = model.NewIntVar(0, max_dist, f"lunch_attach_dist_a{a}_p{p}")
                    terms: List[cp_model.LinearExpr] = []
                    for idx, i0 in enumerate(lu_candidates_av):
                        d = abs(i0 - p)
                        if d == 0:
                            terms.append(lu_start_vars[idx])
                        else:
                            terms.append(d * lu_start_vars[idx])
                    if terms:
                        model.Add(dist_p == sum(terms))
                    else:
                        model.Add(dist_p == 0)
                    dist_vars.append(dist_p)
                if dist_vars:
                    best = model.NewIntVar(0, max_dist, f"lunch_attach_best_a{a}")
                    model.AddMinEquality(best, dist_vars)
                    lunch_attach_penalties.append(best)

        if problem.enable_am_pm_breaks:
            any_before_am = model.NewBoolVar(f"any_before_am_a{a}")
            any_after_am = model.NewBoolVar(f"any_after_am_a{a}")
            sum_before_am: List[cp_model.LinearExpr] = []
            sum_after_am: List[cp_model.LinearExpr] = []
            for i, s in enumerate(starts_min):
                if i in in_av_idx:
                    if s + slot <= am_start_min:
                        expr = sum(x[a][i][k] for k in range(K))
                        sum_before_am.append(expr)
                    if s >= am_end_min:
                        expr = sum(x[a][i][k] for k in range(K))
                        sum_after_am.append(expr)
            if sum_before_am:
                total_before_am = model.NewIntVar(0, len(sum_before_am), f"total_before_am_a{a}")
                model.Add(total_before_am == sum(sum_before_am))
                model.Add(total_before_am >= 1).OnlyEnforceIf(any_before_am)
                model.Add(total_before_am == 0).OnlyEnforceIf(any_before_am.Not())
            else:
                model.Add(any_before_am == 0)
            if sum_after_am:
                total_after_am = model.NewIntVar(0, len(sum_after_am), f"total_after_am_a{a}")
                model.Add(total_after_am == sum(sum_after_am))
                model.Add(total_after_am >= 1).OnlyEnforceIf(any_after_am)
                model.Add(total_after_am == 0).OnlyEnforceIf(any_after_am.Not())
            else:
                model.Add(any_after_am == 0)
            both_sides_am = model.NewBoolVar(f"both_sides_am_a{a}")
            model.AddImplication(both_sides_am, any_before_am)
            model.AddImplication(both_sides_am, any_after_am)
            model.AddBoolAnd([any_before_am, any_after_am]).OnlyEnforceIf(both_sides_am)
            model.AddBoolOr([any_before_am.Not(), any_after_am.Not()]).OnlyEnforceIf(both_sides_am.Not())

            am_idx_av = [
                i for i in am_candidates
                if i in in_av_idx
                and (i + break_slots - 1) in in_av_idx
                and all(j not in breaks_forbidden_slots_set for j in range(i, i + break_slots))
            ]
            am_idx_av_by_agent[a] = am_idx_av
            if am_idx_av:
                _add_hard_enf(model, sum(am_break[a][i] for i in am_idx_av) == break_slots, both_sides_am, lit_am_break)
                for i in range(N):
                    if i not in am_idx_av:
                        model.Add(am_break[a][i] == 0)
            for i in range(N):
                model.Add(am_break[a][i] == 0).OnlyEnforceIf(both_sides_am.Not())

            any_before_pm = model.NewBoolVar(f"any_before_pm_a{a}")
            any_after_pm = model.NewBoolVar(f"any_after_pm_a{a}")
            sum_before_pm: List[cp_model.LinearExpr] = []
            sum_after_pm: List[cp_model.LinearExpr] = []
            for i, s in enumerate(starts_min):
                if i in in_av_idx:
                    if s + slot <= pm_start_min:
                        expr = sum(x[a][i][k] for k in range(K))
                        sum_before_pm.append(expr)
                    if s >= pm_end_min:
                        expr = sum(x[a][i][k] for k in range(K))
                        sum_after_pm.append(expr)
            if sum_before_pm:
                total_before_pm = model.NewIntVar(0, len(sum_before_pm), f"total_before_pm_a{a}")
                model.Add(total_before_pm == sum(sum_before_pm))
                model.Add(total_before_pm >= 1).OnlyEnforceIf(any_before_pm)
                model.Add(total_before_pm == 0).OnlyEnforceIf(any_before_pm.Not())
            else:
                model.Add(any_before_pm == 0)
            if sum_after_pm:
                total_after_pm = model.NewIntVar(0, len(sum_after_pm), f"total_after_pm_a{a}")
                model.Add(total_after_pm == sum(sum_after_pm))
                model.Add(total_after_pm >= 1).OnlyEnforceIf(any_after_pm)
                model.Add(total_after_pm == 0).OnlyEnforceIf(any_after_pm.Not())
            else:
                model.Add(any_after_pm == 0)
            both_sides_pm = model.NewBoolVar(f"both_sides_pm_a{a}")
            model.AddImplication(both_sides_pm, any_before_pm)
            model.AddImplication(both_sides_pm, any_after_pm)
            model.AddBoolAnd([any_before_pm, any_after_pm]).OnlyEnforceIf(both_sides_pm)
            model.AddBoolOr([any_before_pm.Not(), any_after_pm.Not()]).OnlyEnforceIf(both_sides_pm.Not())

            pm_idx_av = [
                i for i in pm_candidates
                if i in in_av_idx
                and (i + break_slots - 1) in in_av_idx
                and all(j not in breaks_forbidden_slots_set for j in range(i, i + break_slots))
            ]
            pm_idx_av_by_agent[a] = pm_idx_av
            if pm_idx_av:
                _add_hard_enf(model, sum(pm_break[a][i] for i in pm_idx_av) == break_slots, both_sides_pm, lit_pm_break)
                for i in range(N):
                    if i not in pm_idx_av:
                        model.Add(pm_break[a][i] == 0)
            for i in range(N):
                model.Add(pm_break[a][i] == 0).OnlyEnforceIf(both_sides_pm.Not())
        else:
            for i in range(N):
                model.Add(am_break[a][i] == 0)
                model.Add(pm_break[a][i] == 0)

        sel_morning = [model.NewBoolVar(f"sel_morning_a{a}_k{k}") for k in range(K)]
        any_morning = model.NewBoolVar(f"any_morning_a{a}")
        if w_morning:
            total_morning_work = model.NewIntVar(0, len(w_morning), f"total_morning_work_a{a}")
            model.Add(total_morning_work == sum(x[a][i][k] for i in w_morning for k in range(K)))
            model.Add(total_morning_work >= 1).OnlyEnforceIf(any_morning)
            model.Add(total_morning_work == 0).OnlyEnforceIf(any_morning.Not())
        else:
            model.Add(any_morning == 0)
        model.Add(sum(sel_morning) == 1).OnlyEnforceIf(any_morning)
        model.Add(sum(sel_morning) == 0).OnlyEnforceIf(any_morning.Not())
        for i in w_morning:
            for k in range(K):
                model.Add(x[a][i][k] <= sel_morning[k])

        sel_midday = [model.NewBoolVar(f"sel_midday_a{a}_k{k}") for k in range(K)]
        any_midday = model.NewBoolVar(f"any_midday_a{a}")
        if w_midday:
            total_midday_work = model.NewIntVar(0, len(w_midday), f"total_midday_work_a{a}")
            model.Add(total_midday_work == sum(x[a][i][k] for i in w_midday for k in range(K)))
            model.Add(total_midday_work >= 1).OnlyEnforceIf(any_midday)
            model.Add(total_midday_work == 0).OnlyEnforceIf(any_midday.Not())
        else:
            model.Add(any_midday == 0)
        model.Add(sum(sel_midday) == 1).OnlyEnforceIf(any_midday)
        model.Add(sum(sel_midday) == 0).OnlyEnforceIf(any_midday.Not())
        for i in w_midday:
            for k in range(K):
                model.Add(x[a][i][k] <= sel_midday[k])

        if problem.forbid_midday_singletons and w_midday:
            w_mid_av = [i for i in w_midday if i in in_av_idx]
            if w_mid_av:
                # Travail par créneau de la grille (y compris hors fenêtre midi, pour voisins ±1)
                work_at: Dict[int, cp_model.IntVar] = {}
                neighbor_idxs = set(w_mid_av)
                for i in w_mid_av:
                    if (i - 1) in in_av_idx:
                        neighbor_idxs.add(i - 1)
                    if (i + 1) in in_av_idx:
                        neighbor_idxs.add(i + 1)
                for j in neighbor_idxs:
                    work_j = model.NewIntVar(0, 1, f"mid_neigh_work_a{a}_i{j}")
                    model.Add(work_j == sum(x[a][j][k] for k in range(K)))
                    work_at[j] = work_j

                for i in w_mid_av:
                    left_sum = work_at[i - 1] if (i - 1) in work_at else 0
                    right_sum = work_at[i + 1] if (i + 1) in work_at else 0
                    neighbor_sum = model.NewIntVar(0, 2, f"mid_neigh_sum_a{a}_i{i}")
                    model.Add(neighbor_sum == left_sum + right_sum)
                    model.Add(work_at[i] <= neighbor_sum)

        sel_afternoon = [model.NewBoolVar(f"sel_afternoon_a{a}_k{k}") for k in range(K)]
        any_afternoon_window = model.NewBoolVar(f"any_afternoon_window_a{a}")
        if w_afternoon:
            total_afternoon_work = model.NewIntVar(0, len(w_afternoon), f"total_afternoon_work_a{a}")
            model.Add(total_afternoon_work == sum(x[a][i][k] for i in w_afternoon for k in range(K)))
            model.Add(total_afternoon_work >= 1).OnlyEnforceIf(any_afternoon_window)
            model.Add(total_afternoon_work == 0).OnlyEnforceIf(any_afternoon_window.Not())
        else:
            model.Add(any_afternoon_window == 0)
        model.Add(sum(sel_afternoon) == 1).OnlyEnforceIf(any_afternoon_window)
        model.Add(sum(sel_afternoon) == 0).OnlyEnforceIf(any_afternoon_window.Not())
        for i in w_afternoon:
            for k in range(K):
                model.Add(x[a][i][k] <= sel_afternoon[k])

        sel_morning_by_a.append(sel_morning)
        sel_afternoon_by_a.append(sel_afternoon)

        eq_mo_af_k: List[cp_model.IntVar] = []
        for k in range(K):
            both_k = model.NewBoolVar(f"eq_mo_af_a{a}_k{k}")
            model.Add(both_k <= sel_morning[k])
            model.Add(both_k <= sel_afternoon[k])
            model.Add(both_k >= sel_morning[k] + sel_afternoon[k] - 1)
            eq_mo_af_k.append(both_k)
        eq_mo_af = model.NewBoolVar(f"eq_mo_af_a{a}")
        if eq_mo_af_k:
            model.AddMaxEquality(eq_mo_af, eq_mo_af_k)
        else:
            model.Add(eq_mo_af == 0)
        model.Add(eq_mo_af == 0).OnlyEnforceIf(any_morning.Not())
        model.Add(eq_mo_af == 0).OnlyEnforceIf(any_afternoon_window.Not())
        # Filet mono-compétence : pas de pénalité « même offre AM+PM » s'il n'y a
        # qu'une seule offre de travail éligible (ex. TI-AE only, mono CESU).
        if len(agent_eligible_offers) > 1:
            same_offer_am_pm_flags.append(eq_mo_af)

        ampm_both = model.NewBoolVar(f"ampm_both_a{a}")
        model.AddImplication(ampm_both, any_morning)
        model.AddImplication(ampm_both, any_afternoon_window)
        model.AddBoolAnd([any_morning, any_afternoon_window]).OnlyEnforceIf(ampm_both)
        model.AddBoolOr([any_morning.Not(), any_afternoon_window.Not()]).OnlyEnforceIf(ampm_both.Not())
        ampm_both_flags.append(ampm_both)

        for idx_pos in range(1, len(in_av_idx)):
            i_prev = in_av_idx[idx_pos - 1]
            i_curr = in_av_idx[idx_pos]
            same_offer_terms: List[cp_model.IntVar] = []
            for k in range(K):
                same_k = model.NewBoolVar(f"same_offer_a{a}_i{i_prev}_to_{i_curr}_k{k}")
                model.Add(same_k <= x[a][i_prev][k])
                model.Add(same_k <= x[a][i_curr][k])
                model.Add(same_k >= x[a][i_prev][k] + x[a][i_curr][k] - 1)
                same_offer_terms.append(same_k)
            any_same = model.NewBoolVar(f"any_same_offer_a{a}_i{i_prev}_to_{i_curr}")
            if same_offer_terms:
                model.AddMaxEquality(any_same, same_offer_terms)
            else:
                model.Add(any_same == 0)
            transition = model.NewBoolVar(f"transition_a{a}_i{i_prev}_to_{i_curr}")
            model.Add(transition == 1).OnlyEnforceIf(any_same.Not())
            model.Add(transition == 0).OnlyEnforceIf(any_same)
            continuity_penalties.append(transition)

        reasons_fr = []
        if problem.strict_work_hours:
            min_end_slots_required = in_av_len
        else:
            ee_min = parse_hhmm_or_hhmmss(ag.earliest_end_time) if ag.earliest_end_time else avl_e
            ee_slots_before = sum(1 for i in in_av_idx if (starts_min[i] + slot) < ee_min)
            min_end_slots_required = ee_slots_before + 1
        
        am_idx_av = []
        pm_idx_av = []
        if problem.enable_am_pm_breaks:
            am_idx_av = [
                i for i in am_candidates
                if i in in_av_idx
                and (i + break_slots - 1) in in_av_idx
                and all(j not in breaks_forbidden_slots_set for j in range(i, i + break_slots))
            ]
            pm_idx_av = [
                i for i in pm_candidates
                if i in in_av_idx
                and (i + break_slots - 1) in in_av_idx
                and all(j not in breaks_forbidden_slots_set for j in range(i, i + break_slots))
            ]

        lu_candidates_av = [
            i for i in lu_candidates
            if i in in_av_idx
            and (i + lunch_slots - 1) in in_av_idx
            and all(j not in lunch_forbidden_slots_set for j in range(i, i + lunch_slots))
        ]
        
        agent_offers = list(agent_eligible_offers)
        
        agent_diagnostics.append({
            "agent_id": ag.id,
            "availability": {"start": fmt_hhmmss(avl_s), "end": fmt_hhmmss(avl_e), "slots": in_av_len},
            "earliest_end_time": (ag.earliest_end_time or None),
            "min_end_slots_required": min_end_slots_required,
            "has_skills": has_any_skill,
            "agent_offers": agent_offers,
            "is_mono_skill": len(agent_offers) == 1,
            "am": {"available_slots": len(am_idx_av), "required_slots": break_slots if problem.enable_am_pm_breaks else 0},
            "pm": {"available_slots": len(pm_idx_av), "required_slots": break_slots if problem.enable_am_pm_breaks else 0},
            "lunch": {"candidates": len(lu_candidates_av), "required_minutes": lunch_slots * slot},
            "reasons_fr": reasons_fr,
        })

    shortage_vars: List[cp_model.IntVar] = []
    surplus_vars: List[cp_model.IntVar] = []
    shortage_vars_by_offer: List[List[cp_model.IntVar]] = [[] for _ in range(K)]
    # Couverture effective membre = mono + alloc (pour reporting post-solve)
    effective_cover_by_offer: List[List[Optional[cp_model.IntVar]]] = [[None for _ in range(N)] for _ in range(K)]

    cap_set = set(problem.cap_to_need_offers or [])

    def _add_standard_cover_eq(k: int, i: int, staffed: cp_model.IntVar) -> None:
        need = int(need_by_index[k][i])
        shortage = model.NewIntVar(0, max(A, need), f"short_k{k}_i{i}")
        surplus = model.NewIntVar(0, A, f"surp_k{k}_i{i}")
        model.Add(staffed + shortage - surplus == need)
        if offers[k] in cap_set:
            model.Add(staffed <= need)
        shortage_vars.append(shortage)
        shortage_vars_by_offer[k].append(shortage)
        surplus_vars.append(surplus)
        effective_cover_by_offer[k][i] = staffed

    if not resolved_groups:
        # Chemin legacy : inchangé
        for k in range(K):
            for i in range(N):
                s_work = model.NewIntVar(0, A, f"s_work_k{k}_i{i}")
                model.Add(s_work == sum(x[a][i][k] for a in range(A)))
                # BORNE CORRIGÉE : shortage ∈ [0, max(A, need)] pour éviter INFEASIBLE
                # artificiel quand need > A (sous-effectif / sélection manuelle).
                _add_standard_cover_eq(k, i, s_work)
    else:
        # Offres hors groupe : équation classique
        for k in range(K):
            if k in mixed_ks or k in grouped_member_ks:
                continue
            for i in range(N):
                s_work = model.NewIntVar(0, A, f"s_work_k{k}_i{i}")
                model.Add(s_work == sum(x[a][i][k] for a in range(A)))
                _add_standard_cover_eq(k, i, s_work)

        # Offres mixtes : need=0, pas d'équation shortage (capacité allouée aux membres)
        # Membres : mono_m + alloc_m + shortage - surplus == need ; Σ alloc == mixed_count
        for gs in resolved_groups:
            gname = str(gs["name"]).replace(" ", "_")
            mixed_k = int(gs["mixed_k"])
            member_ks: List[int] = [int(k) for k in gs["member_ks"]]
            for i in range(N):
                mixed_count = model.NewIntVar(0, A, f"mixed_cnt_{gname}_i{i}")
                model.Add(mixed_count == sum(x[a][i][mixed_k] for a in range(A)))
                allocs: List[cp_model.IntVar] = []
                for m_k in member_ks:
                    alloc = model.NewIntVar(0, A, f"alloc_{gname}_k{m_k}_i{i}")
                    allocs.append(alloc)
                model.Add(sum(allocs) == mixed_count)
                for j, m_k in enumerate(member_ks):
                    mono = model.NewIntVar(0, A, f"mono_{gname}_k{m_k}_i{i}")
                    model.Add(mono == sum(x[a][i][m_k] for a in range(A)))
                    staffed = model.NewIntVar(0, A, f"staffed_{gname}_k{m_k}_i{i}")
                    model.Add(staffed == mono + allocs[j])
                    _add_standard_cover_eq(m_k, i, staffed)

    cont_penalty_vars: List[cp_model.IntVar] = []
    agents_used_pen_vars: List[cp_model.IntVar] = []
    fixed_offers_set = set((problem.restrict_to_need_offers or []) + (problem.cap_to_need_offers or []))
    indices_with_need_by_k: Dict[int, List[int]] = {}
    for kk in range(K):
        indices_with_need_by_k[kk] = [ii for ii in range(N) if int(need_by_index[kk][ii]) > 0]
    equity_set_global = set(getattr(problem, 'equity_offers', []) or [])
    for k in range(K):
        if offers[k] not in fixed_offers_set:
            continue
        block_idxs = indices_with_need_by_k.get(k, [])
        if not block_idxs:
            continue
        peak_need = max((int(need_by_index[k][ii]) for ii in block_idxs), default=0)
        for a in range(A):
            prev_i = None
            for idx_pos, i in enumerate(block_idxs):
                start_var = model.NewBoolVar(f"start_seg_a{a}_k{k}_i{i}")
                model.Add(start_var <= x[a][i][k])
                if idx_pos == 0:
                    model.Add(start_var >= x[a][i][k])
                else:
                    prev_i = block_idxs[idx_pos - 1]
                    model.Add(start_var >= x[a][i][k] - x[a][prev_i][k])
                    model.Add(start_var <= 1 - x[a][prev_i][k])
                if offers[k] not in equity_set_global:
                    cont_penalty_vars.append(start_var)
        y_agents: List[cp_model.IntVar] = []
        for a in range(A):
            y = model.NewBoolVar(f"used_block_a{a}_k{k}")
            total = model.NewIntVar(0, len(block_idxs), f"tot_block_a{a}_k{k}")
            model.Add(total == sum(x[a][i][k] for i in block_idxs))
            model.Add(total >= 1).OnlyEnforceIf(y)
            model.Add(total == 0).OnlyEnforceIf(y.Not())
            y_agents.append(y)
        if y_agents:
            if offers[k] not in equity_set_global and peak_need > 0:
                model.Add(sum(y_agents) <= peak_need)
                agents_used_pen_vars.append(sum(y_agents))

    if problem.min_max_offers is not None:
        work_slots_by_offer: List[cp_model.IntVar] = []
        for k in range(K):
            total_work_k = model.NewIntVar(0, A * N, f"total_work_k{k}")
            work_slots_by_offer.append(total_work_k)
            model.Add(total_work_k == sum(x[a][i][k] for a in range(A) for i in range(N)))
        for mm in problem.min_max_offers:
            if mm.offer in offers:
                k = offers.index(mm.offer)
                if mm.min is not None:
                    model.Add(work_slots_by_offer[k] >= mm.min)
                if mm.max is not None:
                    model.Add(work_slots_by_offer[k] <= mm.max)


    return {
        "model": model,
        "starts_min": starts_min,
        "starts_str": starts_str,
        "end_min": end_min,
        "slot": slot,
        "N": N,
        "A": A,
        "K": K,
        "offers": offers,
        "need_by_index": need_by_index,
        "time_to_idx": time_to_idx,
        "x": x,
        "lunch": lunch,
        "am_break": am_break,
        "pm_break": pm_break,
        "before_end": before_end,
        "end_idx": end_idx,
        "am_candidates": am_candidates,
        "pm_candidates": pm_candidates,
        "lu_candidates": lu_candidates,
        "w_morning": w_morning,
        "w_midday": w_midday,
        "w_afternoon": w_afternoon,
        "am_idx_av_by_agent": am_idx_av_by_agent,
        "pm_idx_av_by_agent": pm_idx_av_by_agent,
        "same_offer_am_pm_flags": same_offer_am_pm_flags,
        "ampm_both_flags": ampm_both_flags,
        "continuity_penalties": continuity_penalties,
        "sel_morning_by_a": sel_morning_by_a,
        "sel_afternoon_by_a": sel_afternoon_by_a,
        "lunch_attach_penalties": lunch_attach_penalties,
        "lunch_start_by_agent": lunch_start_by_agent,
        "lunch_block_need_avg_by_start": lunch_block_need_avg_by_start,
        "shortage_vars": shortage_vars,
        "surplus_vars": surplus_vars,
        "shortage_vars_by_offer": shortage_vars_by_offer,
        "effective_cover_by_offer": effective_cover_by_offer,
        "resolved_groups": resolved_groups,
        "cont_penalty_vars": cont_penalty_vars,
        "agents_used_pen_vars": agents_used_pen_vars,
        "agent_diagnostics": agent_diagnostics,
        "assumption_lits": assumption_lits,
        "assumption_labels": assumption_labels,
        "label_by_lit_index": label_by_lit_index,
    }


def explain_infeasibility(problem: Problem) -> Dict[str, Any]:
    """Second passage : SufficientAssumptionsForInfeasibility (mode diagnostic)."""
    t0 = time.perf_counter()
    attempted = True
    try:
        target_ids: Set[int] = {
            int(ag.id) for ag in problem.agents if ag.unavailable_intervals
        }
        labels, status_name = _run_assumption_solve(problem, target_ids)

        # Étape B : core vide (hors timeout) → élargir à tous les agents
        if not labels and status_name != "TIMEOUT":
            all_ids: Set[int] = {int(ag.id) for ag in problem.agents}
            labels, status_name = _run_assumption_solve(problem, all_ids)

        duration_ms = int((time.perf_counter() - t0) * 1000)
        out_status = "timeout" if status_name == "TIMEOUT" else "ok"
        out = {
            "attempted": attempted,
            "status": out_status,
            "assumption_labels": labels,
            "messages_fr": [_message_fr_for_label(x) for x in labels],
            "duration_ms": duration_ms,
        }
        print(
            f"[COVERAGE EXPLAIN] status={out['status']} labels={out['assumption_labels']} "
            f"duration_ms={duration_ms}"
        )
        return out
    except Exception as exc:
        duration_ms = int((time.perf_counter() - t0) * 1000)
        print(f"[COVERAGE EXPLAIN] error={exc!r} duration_ms={duration_ms}")
        return {
            "attempted": attempted,
            "status": "error",
            "assumption_labels": [],
            "messages_fr": [],
            "duration_ms": duration_ms,
            "error": str(exc),
        }


def _run_assumption_solve(
    problem: Problem,
    assumption_agent_ids: Set[int],
) -> Tuple[List[str], str]:
    """Construit le modèle hard + assumptions, résout, retourne (labels_core, status_name)."""
    bundle = build_hard_coverage_model(
        problem,
        assumption_agent_ids=assumption_agent_ids,
        debug=bool(getattr(problem, "debug_logging", False)),
    )
    model: cp_model.CpModel = bundle["model"]
    assumption_lits: List[Any] = bundle["assumption_lits"]
    label_by_lit_index: Dict[int, str] = bundle["label_by_lit_index"]

    if assumption_lits:
        model.AddAssumptions(assumption_lits)

    solver = cp_model.CpSolver()
    solver.parameters.max_time_in_seconds = 15.0
    solver.parameters.num_search_workers = 1
    status = solver.Solve(model)
    status_name = solver.StatusName(status)

    if status == cp_model.INFEASIBLE and assumption_lits:
        core = solver.SufficientAssumptionsForInfeasibility()
        labels = [label_by_lit_index[i] for i in core if i in label_by_lit_index]
        return labels, status_name

    # Timeout / limite de temps : OR-Tools renvoie souvent UNKNOWN
    if status == cp_model.UNKNOWN and solver.WallTime() >= 14.5:
        return [], "TIMEOUT"

    return [], status_name



@router.post("/solve-schedule")
def solve_schedule(problem: Problem):
    debug = getattr(problem, "debug_logging", False)

    def dlog(msg: str) -> None:
        if debug:
            print(msg)

    print(f"[COVERAGE] start: {len(problem.agents)} agents, {len(problem.offers)} offres, need_curve={len(problem.need_curve)}")

    # Contraintes dures (générateur commun — aucune duplication)
    _hard = build_hard_coverage_model(problem, assumption_agent_ids=None, debug=debug)
    model = _hard["model"]
    starts_min = _hard["starts_min"]
    starts_str = _hard["starts_str"]
    end_min = _hard["end_min"]
    slot = _hard["slot"]
    N = _hard["N"]
    A = _hard["A"]
    K = _hard["K"]
    offers = _hard["offers"]
    need_by_index = _hard["need_by_index"]
    time_to_idx = _hard["time_to_idx"]
    x = _hard["x"]
    lunch = _hard["lunch"]
    am_break = _hard["am_break"]
    pm_break = _hard["pm_break"]
    before_end = _hard["before_end"]
    end_idx = _hard["end_idx"]
    am_candidates = _hard["am_candidates"]
    pm_candidates = _hard["pm_candidates"]
    w_morning = _hard["w_morning"]
    w_afternoon = _hard["w_afternoon"]
    am_idx_av_by_agent = _hard["am_idx_av_by_agent"]
    pm_idx_av_by_agent = _hard["pm_idx_av_by_agent"]
    same_offer_am_pm_flags = _hard["same_offer_am_pm_flags"]
    continuity_penalties = _hard["continuity_penalties"]
    lunch_attach_penalties = _hard["lunch_attach_penalties"]
    shortage_vars = _hard["shortage_vars"]
    surplus_vars = _hard["surplus_vars"]
    shortage_vars_by_offer = _hard["shortage_vars_by_offer"]
    effective_cover_by_offer = _hard["effective_cover_by_offer"]
    resolved_groups = _hard["resolved_groups"]
    sel_morning_by_a = _hard["sel_morning_by_a"]
    sel_afternoon_by_a = _hard["sel_afternoon_by_a"]
    cont_penalty_vars = _hard["cont_penalty_vars"]
    agents_used_pen_vars = _hard["agents_used_pen_vars"]
    agent_diagnostics = _hard["agent_diagnostics"]

    W_SHORT = problem.weight_shortage
    W_SURP = problem.weight_surplus
    W_SAME = problem.weight_same_offer_windows
    W_EEND = problem.weight_early_end
    # W_BRK_ALIGN ignoré intentionnellement (voir commentaires)
    W_PER_EQ = getattr(problem, "weight_period_equity", 0) or 0
    W_PREF_MIXED = int(getattr(problem, "weight_prefer_mixed", 80) or 0)
    obj_terms: List[cp_model.LinearExpr] = []

    # Borne haute cohérente avec shortage ∈ [0, max(A, need)] par créneau/offre.
    # Avec A * N * K, le cumul devenait trop étroit dès que le besoin global
    # dépassait le nombre d'agents × créneaux × offres (cas fréquent en sélection
    # manuelle ou en sous-effectif réel).
    shortage_ub = sum(
        max(A, int(need_by_index[k][i]))
        for k in range(K)
        for i in range(N)
    )
    total_shortage_var = model.NewIntVar(0, max(A * N * K, shortage_ub), "total_shortage")
    total_surplus_var = model.NewIntVar(0, A * N * K, "total_surplus")
    if shortage_vars:
        model.Add(total_shortage_var == sum(shortage_vars))
    else:
        model.Add(total_shortage_var == 0)
    if surplus_vars:
        model.Add(total_surplus_var == sum(surplus_vars))
    else:
        model.Add(total_surplus_var == 0)

    cover_terms: List[cp_model.LinearExpr] = []
    priority_set = set(problem.priority_offers or [])
    for k, off in enumerate(offers):
        per_offer_weight = W_SHORT * (problem.priority_shortage_multiplier if off in priority_set else 1)
        if shortage_vars_by_offer[k]:
            cover_terms.append(per_offer_weight * sum(shortage_vars_by_offer[k]))
    if surplus_vars:
        cover_terms.append(W_SURP * sum(surplus_vars))

    obj_terms.extend(cover_terms)
    
    if W_SAME > 0 and same_offer_am_pm_flags:
        W_AMPM_FIXED = W_SAME * 100
        obj_terms.append(W_AMPM_FIXED * sum(same_offer_am_pm_flags))
    
    if W_SAME > 0 and continuity_penalties:
        W_CONTINUITY = W_SAME * 10
        obj_terms.append(W_CONTINUITY * sum(continuity_penalties))
    
    if W_SAME > 0 and lunch_attach_penalties:
        W_LUNCH_ATTACH = W_SAME * 6
        obj_terms.append(W_LUNCH_ATTACH * sum(lunch_attach_penalties))
    
    if W_EEND > 0:
        obj_terms.append(W_EEND * sum(end_idx))

    # Préférence soft pour le profil mixte : pénalise la sélection d'un membre
    # (matin / après-midi) si l'agent a aussi la skill mixte.
    if W_PREF_MIXED > 0 and resolved_groups:
        for gs in resolved_groups:
            if not gs.get("prefer_mixed"):
                continue
            mixed_name = gs["mixed"]
            member_names = gs["members"]
            member_ks = [int(k) for k in gs["member_ks"]]
            for a, ag in enumerate(problem.agents):
                skills = set(ag.skills or [])
                if mixed_name not in skills:
                    continue
                if not any(m in skills for m in member_names):
                    continue
                for k in member_ks:
                    obj_terms.append(W_PREF_MIXED * sel_morning_by_a[a][k])
                    obj_terms.append(W_PREF_MIXED * sel_afternoon_by_a[a][k])

    if W_PER_EQ > 0 and getattr(problem, "period_equity_offers", None) and getattr(problem, "period_equity_scores", None):
        per_eq_set = set(problem.period_equity_offers or [])
        scores_map = problem.period_equity_scores or {}
        bucket_map = build_offer_equity_bucket_map(offers, getattr(problem, "offer_groups", None))
        for k, off in enumerate(offers):
            bucket = bucket_map.get(off, off)
            # Accepte bucket (groupe) ou nom d'offre legacy dans period_equity_offers
            if bucket not in per_eq_set and off not in per_eq_set:
                continue
            off_scores = None
            if isinstance(scores_map, dict):
                off_scores = scores_map.get(bucket)
                if off_scores is None:
                    off_scores = scores_map.get(off, {})
            if not isinstance(off_scores, dict):
                continue
            for a, ag in enumerate(problem.agents):
                base_min = off_scores.get(ag.id) or off_scores.get(str(ag.id)) or 0
                try:
                    base_min = int(base_min)
                except Exception:
                    base_min = 0
                if base_min <= 0:
                    continue
                base_slots = base_min // slot
                if base_slots <= 0:
                    continue
                tot_slots = model.NewIntVar(0, N, f"per_eq_tot_a{a}_k{k}")
                model.Add(tot_slots == sum(x[a][i][k] for i in range(N)))
                obj_terms.append(W_PER_EQ * base_slots * tot_slots)
    
    # MODIF V8 : LISSAGE QUADRATIQUE (VERTICAL) + SOFT CAP 33%
    # - On minimise la somme des carrés des pauses par créneau (force l'étalement plat).
    # - On garde le Soft Cap 33% (global, basé sur les éligibles) pour la sécurité.
    
    if A > 0:
        # Soft Cap 33% (Global)
        if am_candidates or pm_candidates:
            count_am_eligible = sum(1 for a in range(A) if len(am_idx_av_by_agent[a]) > 0)
            max_simultaneous_am = max(1, math.ceil(count_am_eligible * 0.33))
            
            count_pm_eligible = sum(1 for a in range(A) if len(pm_idx_av_by_agent[a]) > 0)
            max_simultaneous_pm = max(1, math.ceil(count_pm_eligible * 0.33))
            
            print(f"[COVERAGE DEBUG] Cap 33% (Soft): AM={max_simultaneous_am} (sur {count_am_eligible}), PM={max_simultaneous_pm} (sur {count_pm_eligible})")

            W_OVERLOAD = 1_000_000 
            excess_vars = []

            if am_candidates:
                for i in am_candidates:
                    total_am_i = sum(am_break[a][i] for a in range(A))
                    excess = model.NewIntVar(0, A, f"excess_am_i{i}")
                    model.Add(total_am_i <= max_simultaneous_am + excess)
                    excess_vars.append(excess)

            if pm_candidates:
                for i in pm_candidates:
                    total_pm_i = sum(pm_break[a][i] for a in range(A))
                    excess = model.NewIntVar(0, A, f"excess_pm_i{i}")
                    model.Add(total_pm_i <= max_simultaneous_pm + excess)
                    excess_vars.append(excess)

            if excess_vars:
                obj_terms.append(W_OVERLOAD * sum(excess_vars))

        # Lissage Quadratique (Sum of Squares)
        # Poids moyen (assez fort pour battre les petits gains de shortfall, mais < Soft Cap)
        W_QUAD = 5_000 
        
        if am_candidates:
            for i in am_candidates:
                total_am = model.NewIntVar(0, A, f"sum_am_{i}")
                model.Add(total_am == sum(am_break[a][i] for a in range(A)))
                
                # Astuce CP-SAT pour le carré : AddMultiplicationEquality(target, [src, src])
                sq_am = model.NewIntVar(0, A*A, f"sq_am_{i}")
                model.AddMultiplicationEquality(sq_am, [total_am, total_am])
                
                obj_terms.append(sq_am * W_QUAD)

        if pm_candidates:
            for i in pm_candidates:
                total_pm = model.NewIntVar(0, A, f"sum_pm_{i}")
                model.Add(total_pm == sum(pm_break[a][i] for a in range(A)))
                
                sq_pm = model.NewIntVar(0, A*A, f"sq_pm_{i}")
                model.AddMultiplicationEquality(sq_pm, [total_pm, total_pm])
                
                obj_terms.append(sq_pm * W_QUAD)

    if getattr(problem, 'equity_offers', None):
        equity_set = set(problem.equity_offers or [])
        W_EQUITY = max(int(getattr(problem, 'weight_equity', 40) or 0), 120)
        if W_EQUITY > 0 and (w_morning or w_afternoon):
            equity_pen_vars: List[cp_model.IntVar] = []
            for k, off in enumerate(offers):
                if off not in equity_set:
                    continue
                for a in range(A):
                    if w_morning:
                        tot_mo_a_k = model.NewIntVar(0, len(w_morning), f"eq_tot_mo_a{a}_k{k}")
                        model.Add(tot_mo_a_k == sum(x[a][i][k] for i in w_morning))
                        y_mo = model.NewBoolVar(f"eq_y_mo_a{a}_k{k}")
                        model.Add(tot_mo_a_k >= 1).OnlyEnforceIf(y_mo)
                        model.Add(tot_mo_a_k == 0).OnlyEnforceIf(y_mo.Not())
                    else:
                        y_mo = model.NewBoolVar(f"eq_y_mo_a{a}_k{k}")
                        model.Add(y_mo == 0)
                    if w_afternoon:
                        tot_af_a_k = model.NewIntVar(0, len(w_afternoon), f"eq_tot_af_a{a}_k{k}")
                        model.Add(tot_af_a_k == sum(x[a][i][k] for i in w_afternoon))
                        y_af = model.NewBoolVar(f"eq_y_af_a{a}_k{k}")
                        model.Add(tot_af_a_k >= 1).OnlyEnforceIf(y_af)
                        model.Add(tot_af_a_k == 0).OnlyEnforceIf(y_af.Not())
                    else:
                        y_af = model.NewBoolVar(f"eq_y_af_a{a}_k{k}")
                        model.Add(y_af == 0)
                    both = model.NewBoolVar(f"eq_both_a{a}_k{k}")
                    model.AddImplication(both, y_mo)
                    model.AddImplication(both, y_af)
                    model.AddBoolAnd([y_mo, y_af]).OnlyEnforceIf(both)
                    model.AddBoolOr([y_mo.Not(), y_af.Not()]).OnlyEnforceIf(both.Not())
                    equity_pen_vars.append(both)
            if equity_pen_vars:
                obj_terms.append(W_EQUITY * sum(equity_pen_vars))

    W_CONT = 20
    if cont_penalty_vars:
        obj_terms.append(W_CONT * sum(cont_penalty_vars))
    if agents_used_pen_vars:
        obj_terms.append(W_CONT * sum(agents_used_pen_vars))

    phase1_terms = list(cover_terms)
    model.Minimize(sum(phase1_terms))

    solver = cp_model.CpSolver()
    solver.parameters.max_time_in_seconds = 20.0
    solver.parameters.num_search_workers = 8
    # Configuration du Gap Limit (Performance)
    if hasattr(problem, 'relative_gap_limit') and problem.relative_gap_limit > 0:
        solver.parameters.relative_gap_limit = problem.relative_gap_limit
    status = solver.Solve(model)
    ok = status in (cp_model.OPTIMAL, cp_model.FEASIBLE)

    if ok:
        best_total_shortage = int(solver.Value(total_shortage_var))
        best_total_surplus = int(solver.Value(total_surplus_var))
        model.Add(total_shortage_var == best_total_shortage)
        model.Add(total_surplus_var == best_total_surplus)
        model.Minimize(sum(obj_terms))
        solver2 = cp_model.CpSolver()
        solver2.parameters.max_time_in_seconds = 10.0
        solver2.parameters.num_search_workers = 8
        # Configuration du Gap Limit (Performance)
        if hasattr(problem, 'relative_gap_limit') and problem.relative_gap_limit > 0:
            solver2.parameters.relative_gap_limit = problem.relative_gap_limit
        status2 = solver2.Solve(model)
        if status2 in (cp_model.OPTIMAL, cp_model.FEASIBLE):
            solver = solver2
            status = status2
            ok = True

    # Build schedule
    def push_segment(segs: List[Dict[str, Any]], start_i: Optional[int], end_i: int, label: str, offer: Optional[str], agent_id: int):
        if start_i is None:
            return
        s = starts_min[start_i]
        e = starts_min[end_i] + slot
        segs.append({
            "agent_id": agent_id,
            "start": fmt_hhmmss(s),
            "end": fmt_hhmmss(e),
            "label": label,
            "offer": offer,
        })

    schedules_segments: Dict[str, List[Dict[str, Any]]] = {}
    schedule_flat: List[Dict[str, Any]] = []

    for a, ag in enumerate(problem.agents):
        segs: List[Dict[str, Any]] = []
        cur_label = None
        cur_offer = None
        cur_start = None
        
        for i in range(N):
            label = None
            label_offer = None
            is_working = False  # Initialiser pour le debug
            if ok:
                # --- CORRECTIF 3 : AFFICHAGE PRIORITAIRE ---
                if solver.BooleanValue(lunch[a][i]):
                    label = "LUNCH"
                elif solver.BooleanValue(am_break[a][i]):
                    label = "AM_BREAK"
                elif solver.BooleanValue(pm_break[a][i]):
                    label = "PM_BREAK"
                else:
                    # Travail productif
                    for k, off in enumerate(offers):
                        if solver.BooleanValue(x[a][i][k]):
                            label = "WORK"
                            label_offer = off
                            is_working = True
                            break
                    
                    # Si aucun label (pas de work/lunch) MAIS Présent => Réunion
                    if label is None and solver.BooleanValue(before_end[a][i]):
                        label = "UNAVAILABLE"
            if label == cur_label and (label != "WORK" or label_offer == cur_offer) and label is not None:
                pass
            else:
                if cur_label is not None:
                    push_segment(segs, cur_start, i - 1, cur_label, cur_offer, ag.id)
                if label is not None:
                    cur_label = label
                    cur_offer = label_offer if label == "WORK" else None
                    cur_start = i
                else:
                    cur_label = None
                    cur_offer = None
                    cur_start = None
        if cur_label is not None:
            push_segment(segs, cur_start, N - 1, cur_label, cur_offer, ag.id)
        schedules_segments[f"#{ag.id}"] = segs
        schedule_flat.extend(segs)

    coverage: List[Dict[str, Any]] = []
    mixed_ks_report = {int(gs["mixed_k"]) for gs in (resolved_groups or [])}
    for k, off in enumerate(offers):
        times: List[Dict[str, Any]] = []
        for i in range(N):
            need_val = int(need_by_index[k][i])
            if ok and effective_cover_by_offer[k][i] is not None:
                cov = int(solver.Value(effective_cover_by_offer[k][i]))
            elif ok:
                cov = sum(solver.BooleanValue(x[a][i][k]) for a in range(A))
            else:
                cov = 0
            if k in mixed_ks_report:
                # Pas d'équation shortage sur le mixte : reporting informatif seulement
                times.append({
                    "time": fmt_hhmmss(starts_min[i]),
                    "need": 0,
                    "covered": int(cov),
                    "shortage": 0,
                    "surplus": 0,
                })
            else:
                times.append({
                    "time": fmt_hhmmss(starts_min[i]),
                    "need": need_val,
                    "covered": int(cov),
                    "shortage": max(0, need_val - cov),
                    "surplus": max(0, cov - need_val),
                })
        coverage.append({"offer": off, "times": times})

    result_status = "OPTIMAL" if status == cp_model.OPTIMAL else ("FEASIBLE" if status == cp_model.FEASIBLE else solver.StatusName(status))

    result = {
        "status": result_status,
        "grid": {"starts": starts_str, "end": fmt_hhmmss(end_min)},
        "offers": offers,
        "schedules_segments": schedules_segments,
        "schedule": schedule_flat,
        "coverage": coverage,
        "solver": {"status": solver.StatusName(status), "objective_value": (solver.ObjectiveValue() if ok else None)},
    }
    
    if not ok:
        result["diagnostics"] = {"agents": agent_diagnostics}
        if debug:
            result["diagnostics"]["explanation"] = explain_infeasibility(problem)

    return result

