# -*- coding: utf-8 -*-
"""Tests passe 1.5 — rotation (legacy + multi-lignes)."""

from __future__ import annotations

import unittest

from routers.solver_rotation import (
    RotationAgent,
    RotationLine,
    RotationLineSlot,
    RotationRequest,
    UnavailableInterval,
    solve_rotation,
)


def _agent(aid: int, **kwargs) -> RotationAgent:
    data = dict(
        id=aid,
        offer_id=kwargs.pop('offer_id', 10),
        target_slots=kwargs.pop('target_slots', 2),
        duration=kwargs.pop('duration', 180),
        window_start=kwargs.pop('window_start', '09:00:00'),
        window_end=kwargs.pop('window_end', '17:00:00'),
        lunch_window_start=kwargs.pop('lunch_window_start', '12:00:00'),
        lunch_window_end=kwargs.pop('lunch_window_end', '14:00:00'),
        lunch_duration=kwargs.pop('lunch_duration', 60),
        history_worked_days=kwargs.pop('history_worked_days', []),
        unavailable_by_day=kwargs.pop('unavailable_by_day', None),
        skills=kwargs.pop('skills', None),
        target_slots_by_line=kwargs.pop('target_slots_by_line', None),
        history_slots_by_line=kwargs.pop('history_slots_by_line', None),
    )
    data.update(kwargs)
    return RotationAgent(**data)


class LegacyRotationTests(unittest.TestCase):
    def test_legacy_two_shifts_one_per_day_respects_lunch(self) -> None:
        req = RotationRequest(
            date='2026-09-07',
            timeout_seconds=8,
            agents=[_agent(1, target_slots=2, offer_id=10)],
        )
        result = solve_rotation(req)
        self.assertEqual(result.status, 'FEASIBLE')
        self.assertEqual(len(result.blocks), 2)
        days = [b.day_index for b in result.blocks]
        self.assertEqual(len(days), len(set(days)))
        for b in result.blocks:
            self.assertEqual(b.offer_id, 10)
            start_h = int(b.start.split(':')[0])
            end_h = int(b.end.split(':')[0])
            if start_h < 12:
                self.assertLessEqual(end_h, 13)
            # 3h block cannot eat the whole 12-14 lunch window
            self.assertNotEqual((b.start, b.end), ('11:00:00', '14:00:00'))


class MultiLineRotationTests(unittest.TestCase):
    def _quota_line(self, lid=1, offer_id=10, sort_order=1) -> RotationLine:
        return RotationLine(
            id=lid,
            line_type='quota',
            offer_id=offer_id,
            sort_order=sort_order,
            target_count=2,
            shift_duration=180,
            window_start='09:00:00',
            window_end='17:00:00',
            fit_need_curve=False,
        )

    def _coverage_line(
        self,
        lid=2,
        offer_id=20,
        sort_order=2,
        same_person=False,
    ) -> RotationLine:
        return RotationLine(
            id=lid,
            line_type='coverage',
            offer_id=offer_id,
            sort_order=sort_order,
            quantity=1,
            equity_enabled=True,
            same_person_day_slots=same_person,
            days_of_week=[1, 2, 3, 4, 5],
            slots=[
                RotationLineSlot(start='09:00:00', end='12:00:00'),
                RotationLineSlot(start='14:00:00', end='17:00:00'),
            ],
        )

    def test_grc_eleven_agents(self) -> None:
        agents = []
        for i in range(1, 12):
            agents.append(_agent(
                i,
                offer_id=10,
                target_slots=2,
                skills=[10, 20],
                target_slots_by_line={'1': 2},
                history_slots_by_line={'2': 0},
            ))
        req = RotationRequest(
            date='2026-09-07',
            timeout_seconds=20,
            exclusive_day=True,
            lines=[self._quota_line(), self._coverage_line()],
            agents=agents,
        )
        result = solve_rotation(req)
        self.assertEqual(result.status, 'FEASIBLE', result.debug_info)
        tel = [b for b in result.blocks if b.offer_id == 10]
        live = [b for b in result.blocks if b.offer_id == 20]
        self.assertEqual(len(tel), 22)
        self.assertEqual(len(live), 10)
        tel_by_agent = {}
        live_by_agent = {}
        for b in tel:
            tel_by_agent[b.user_id] = tel_by_agent.get(b.user_id, 0) + 1
        for b in live:
            live_by_agent[b.user_id] = live_by_agent.get(b.user_id, 0) + 1
        self.assertEqual(len(tel_by_agent), 11)
        self.assertTrue(all(v == 2 for v in tel_by_agent.values()))
        self.assertEqual(sum(1 for v in live_by_agent.values() if v == 1), 10)
        self.assertEqual(sum(1 for i in range(1, 12) if live_by_agent.get(i, 0) == 0), 1)
        # exclusive day: no agent with tel+live same day
        by_user_day = {}
        for b in result.blocks:
            by_user_day.setdefault((b.user_id, b.day_index), []).append(b.offer_id)
        for offers in by_user_day.values():
            self.assertEqual(len(offers), 1)

    def test_two_absent_agents_someone_gets_two_livechat(self) -> None:
        agents = []
        full_unavail = {
            str(d): [UnavailableInterval(start='09:00:00', end='17:00:00')]
            for d in range(5)
        }
        for i in range(1, 10):
            unavail = full_unavail if i <= 2 else None
            agents.append(_agent(
                i,
                skills=[10, 20],
                target_slots=2 if i > 2 else 0,
                target_slots_by_line={'1': 0 if i <= 2 else 2},
                unavailable_by_day=unavail,
            ))
        req = RotationRequest(
            date='2026-09-07',
            timeout_seconds=20,
            exclusive_day=True,
            lines=[self._quota_line(), self._coverage_line()],
            agents=agents,
        )
        result = solve_rotation(req)
        self.assertEqual(result.status, 'FEASIBLE', result.debug_info)
        tel = [b for b in result.blocks if b.offer_id == 10]
        live = [b for b in result.blocks if b.offer_id == 20]
        self.assertEqual(len(tel), 14)  # 7 agents * 2
        self.assertEqual(len(live), 10)
        live_by_agent = {}
        for b in live:
            live_by_agent[b.user_id] = live_by_agent.get(b.user_id, 0) + 1
        self.assertTrue(any(v >= 2 for v in live_by_agent.values()))
        for uid in (1, 2):
            self.assertEqual(sum(1 for b in result.blocks if b.user_id == uid), 0)

    def test_agent_without_livechat_skill(self) -> None:
        agents = [
            _agent(1, skills=[10], target_slots_by_line={'1': 2}),
            _agent(2, skills=[10, 20], target_slots_by_line={'1': 2}),
            _agent(3, skills=[10, 20], target_slots_by_line={'1': 2}),
        ]
        req = RotationRequest(
            date='2026-09-07',
            timeout_seconds=12,
            exclusive_day=True,
            lines=[self._quota_line(), self._coverage_line()],
            agents=agents,
        )
        result = solve_rotation(req)
        self.assertEqual(result.status, 'FEASIBLE', result.debug_info)
        live_users = {b.user_id for b in result.blocks if b.offer_id == 20}
        self.assertNotIn(1, live_users)

    def test_exclusive_day_off_live_am_tel_pm(self) -> None:
        unavail = {
            str(d): [UnavailableInterval(start='09:00:00', end='17:00:00')]
            for d in range(1, 5)
        }
        agents = [_agent(
            1,
            skills=[10, 20],
            target_slots_by_line={'1': 1},
            unavailable_by_day=unavail,
        )]
        req = RotationRequest(
            date='2026-09-07',
            timeout_seconds=12,
            exclusive_day=False,
            lines=[
                self._quota_line(),
                RotationLine(
                    id=2,
                    line_type='coverage',
                    offer_id=20,
                    sort_order=2,
                    quantity=1,
                    equity_enabled=False,
                    same_person_day_slots=False,
                    days_of_week=[1],
                    slots=[RotationLineSlot(start='09:00:00', end='12:00:00')],
                ),
            ],
            agents=agents,
        )
        result = solve_rotation(req)
        self.assertEqual(result.status, 'FEASIBLE', result.debug_info)
        live = [b for b in result.blocks if b.offer_id == 20]
        tel = [b for b in result.blocks if b.offer_id == 10]
        self.assertEqual(len(live), 1)
        self.assertEqual(len(tel), 1)
        self.assertEqual(live[0].day_index, 0)
        self.assertEqual(tel[0].day_index, 0)
        self.assertEqual(live[0].start, '09:00:00')
        self.assertGreaterEqual(tel[0].start, '14:00:00')

    def test_lunch_union_rejects_squeezed_gap(self) -> None:
        """09:00-13:00 + 13:30-17:00 ne laisse pas 60 min repas."""
        unavail = {
            str(d): [UnavailableInterval(start='09:00:00', end='17:00:00')]
            for d in range(1, 5)
        }
        agents = [_agent(
            1,
            skills=[10, 20],
            target_slots=1,
            target_slots_by_line={'1': 1},
            unavailable_by_day=unavail,
        )]
        req = RotationRequest(
            date='2026-09-07',
            timeout_seconds=12,
            exclusive_day=False,
            lines=[
                RotationLine(
                    id=1,
                    line_type='quota',
                    offer_id=10,
                    sort_order=1,
                    target_count=1,
                    shift_duration=210,  # 3h30 → 09:00-12:30 or 13:30-17:00
                    window_start='13:30:00',
                    window_end='17:00:00',
                    fit_need_curve=False,
                ),
                RotationLine(
                    id=2,
                    line_type='coverage',
                    offer_id=20,
                    sort_order=2,
                    quantity=1,
                    equity_enabled=False,
                    days_of_week=[1],
                    slots=[RotationLineSlot(start='09:00:00', end='13:00:00')],
                ),
            ],
            agents=agents,
        )
        result = solve_rotation(req)
        self.assertEqual(result.status, 'FEASIBLE', result.debug_info)
        tel = [b for b in result.blocks if b.offer_id == 10]
        live = [b for b in result.blocks if b.offer_id == 20]
        self.assertEqual(len(tel), 1)
        self.assertEqual(len(live), 0)

    def test_same_person_with_exclusive_day_is_feasible(self) -> None:
        agents = [
            _agent(i, skills=[10, 20], target_slots_by_line={'1': 1 if i <= 3 else 0}, target_slots=1 if i <= 3 else 0)
            for i in range(1, 6)
        ]
        req = RotationRequest(
            date='2026-09-07',
            timeout_seconds=15,
            exclusive_day=True,
            lines=[
                RotationLine(
                    id=1,
                    line_type='quota',
                    offer_id=10,
                    sort_order=1,
                    target_count=1,
                    shift_duration=180,
                    window_start='09:00:00',
                    window_end='17:00:00',
                    fit_need_curve=False,
                ),
                self._coverage_line(same_person=True),
            ],
            agents=agents,
        )
        result = solve_rotation(req)
        self.assertEqual(result.status, 'FEASIBLE', result.debug_info)
        live_by_user_day = {}
        for b in result.blocks:
            if b.offer_id != 20:
                continue
            live_by_user_day.setdefault((b.user_id, b.day_index), []).append(b)
        for pair, blocks in live_by_user_day.items():
            self.assertEqual(len(blocks), 2, pair)
            starts = sorted(x.start for x in blocks)
            self.assertEqual(starts, ['09:00:00', '14:00:00'])
        for (uid, day), _ in live_by_user_day.items():
            tel_same = [
                b for b in result.blocks
                if b.user_id == uid and b.day_index == day and b.offer_id == 10
            ]
            self.assertEqual(tel_same, [])


if __name__ == '__main__':
    unittest.main()
