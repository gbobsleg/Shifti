# -*- coding: utf-8 -*-
"""Tests passe 1 — activités fixes (éligibilité non scindable, repas collé, manques)."""

from __future__ import annotations

import asyncio
import unittest

from routers.solver_fixed import (
    Agent,
    FixedActivity,
    FixedActivityProblem,
    TimeWindow,
    solve_fixed_activities,
)

ACCUEIL = 'Accueil physique - Rouen'
RDV = 'RDV physique - Rouen'


def _agent(aid: int, start: str, end: str, skills: list[str]) -> Agent:
    return Agent(
        id=aid,
        name=f'Agent {aid}',
        availability_start_time=start,
        availability_end_time=end,
        site='Rouen',
        skills=skills,
    )


def _accueil(quantity: int = 1) -> FixedActivity:
    return FixedActivity(
        offer_name=ACCUEIL,
        base_offer_name='Accueil physique',
        start_time='13:30:00',
        end_time='17:00:00',
        quantity=quantity,
        is_splittable=False,
        equity_enabled=False,
        lunch_attach_mode='before',
        lunch_overlap_allowed=False,
    )


def _rdv() -> FixedActivity:
    return FixedActivity(
        offer_name=RDV,
        base_offer_name='RDV physique',
        start_time='09:00:00',
        end_time='12:00:00',
        quantity=1,
        is_splittable=False,
        equity_enabled=False,
        lunch_attach_mode='after',
        lunch_overlap_allowed=False,
    )


def _solve(agents: list[Agent], activities: list[FixedActivity]) -> dict:
    problem = FixedActivityProblem(
        workday_start_time='09:00:00',
        workday_end_time='17:00:00',
        slot_minutes=15,
        agents=agents,
        fixed_activities=activities,
        timeout_seconds=10,
        enable_am_pm_breaks=False,
        lunch_duration_minutes=60,
        lunch_window=TimeWindow(start='12:00:00', end='14:00:00'),
    )
    return asyncio.run(solve_fixed_activities(problem, None))


def _slots(result: dict, agent_id: int, activity: str) -> list[dict]:
    return [a for a in result['assignments'] if a['agent_id'] == agent_id and a['activity'] == activity]


def _lunch(result: dict, agent_id: int) -> list[str] | None:
    for a in result['assignments']:
        if a['agent_id'] == agent_id and 'lunch' in a.get('breaks', {}):
            return a['breaks']['lunch']
    return None


class LunchAttachTests(unittest.TestCase):
    def test_afternoon_only_agent_gets_accueil_without_lunch(self) -> None:
        result = _solve([_agent(62, '13:30:00', '17:00:00', [ACCUEIL])], [_accueil()])
        self.assertIn(result['status'], ('OPTIMAL', 'FEASIBLE'))
        self.assertEqual(len(_slots(result, 62, ACCUEIL)), 14)
        self.assertIsNone(_lunch(result, 62))
        self.assertEqual(result['shortfalls'], {})

    def test_late_arrival_agent_gets_accueil_without_lunch(self) -> None:
        result = _solve([_agent(1, '12:30:00', '17:00:00', [ACCUEIL])], [_accueil()])
        self.assertEqual(len(_slots(result, 1, ACCUEIL)), 14)
        self.assertIsNone(_lunch(result, 1))

    def test_full_day_agent_has_lunch_attached_before_accueil(self) -> None:
        result = _solve([_agent(1, '09:00:00', '17:00:00', [ACCUEIL])], [_accueil()])
        self.assertEqual(len(_slots(result, 1, ACCUEIL)), 14)
        self.assertEqual(_lunch(result, 1), ['12:30:00', '13:30:00'])

    def test_morning_only_agent_gets_rdv_without_lunch(self) -> None:
        result = _solve([_agent(62, '09:00:00', '12:30:00', [RDV])], [_rdv()])
        self.assertEqual(len(_slots(result, 62, RDV)), 12)
        self.assertIsNone(_lunch(result, 62))
        self.assertEqual(result['shortfalls'], {})


class NonSplittableEligibilityTests(unittest.TestCase):
    def test_agent_ending_before_activity_end_is_not_assigned(self) -> None:
        result = _solve([_agent(93, '09:00:00', '16:30:00', [ACCUEIL])], [_accueil()])
        self.assertIn(result['status'], ('OPTIMAL', 'FEASIBLE'))
        self.assertEqual(_slots(result, 93, ACCUEIL), [])
        self.assertEqual(result['shortfalls'], {ACCUEIL: 14})

    def test_fully_available_agent_is_preferred(self) -> None:
        agents = [
            _agent(93, '09:00:00', '16:30:00', [ACCUEIL]),
            _agent(62, '13:30:00', '17:00:00', [ACCUEIL]),
        ]
        result = _solve(agents, [_accueil()])
        self.assertEqual(_slots(result, 93, ACCUEIL), [])
        self.assertEqual(len(_slots(result, 62, ACCUEIL)), 14)
        self.assertEqual(result['shortfalls'], {})

    def test_partial_shortfall_reported_per_slot(self) -> None:
        agents = [
            _agent(60, '09:00:00', '17:00:00', [ACCUEIL]),
            _agent(93, '09:00:00', '16:30:00', [ACCUEIL]),
        ]
        result = _solve(agents, [_accueil(quantity=2)])
        self.assertEqual(len(_slots(result, 60, ACCUEIL)), 14)
        self.assertEqual(_slots(result, 93, ACCUEIL), [])
        self.assertEqual(result['shortfalls'], {ACCUEIL: 14})


if __name__ == '__main__':
    unittest.main()
