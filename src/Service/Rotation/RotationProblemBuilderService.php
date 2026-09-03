<?php
declare(strict_types=1);

namespace App\Service\Rotation;

use Cake\I18n\FrozenDate;
use Cake\I18n\FrozenTime;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Construit le payload multi-lignes pour POST /api/v1/solve-rotation.
 */
class RotationProblemBuilderService
{
    use LocatorAwareTrait;

    /**
     * @param iterable $usersForRotation
     * @param array<string, mixed> $needCurveById
     * @return array{agents: list<array<string, mixed>>, lines: list<array<string, mixed>>, exclusive_day: bool, user_ids: list<int>}
     */
    public function build(
        iterable $usersForRotation,
        FrozenDate $weekStart,
        FrozenDate $weekEnd,
        int $jobId,
        array $needCurveById,
        RotationTargetCalculatorService $rotationCalculator,
        array $lunchWindow,
        int $lunchDurationMinutes,
        $Ranges,
        $DraftRanges,
        array $fixedActivityAssignments,
        FrozenDate $currentDate,
        bool $debugSolvers,
        callable $truncateForLog,
        callable $timeToMinutes
    ): array {
        $users = is_array($usersForRotation) ? $usersForRotation : iterator_to_array($usersForRotation);
        shuffle($users);

        $ruleIds = [];
        foreach ($users as $user) {
            if (!empty($user->users_rotation_rule->rotation_rule_id)) {
                $ruleIds[(string)$user->users_rotation_rule->rotation_rule_id] = true;
            }
        }

        $rulesById = [];
        if ($ruleIds !== []) {
            $loaded = $this->fetchTable('RotationRules')->find()
                ->contain(['RotationRuleLines.RotationRuleLineSlots', 'RotationRuleLines.Offers'])
                ->where(['RotationRules.id IN' => array_keys($ruleIds)])
                ->all();
            foreach ($loaded as $rule) {
                $rulesById[(string)$rule->id] = $rule;
            }
        }

        $linesOut = [];
        $linesSeen = [];
        $exclusiveDay = true;
        foreach ($rulesById as $rule) {
            $exclusiveDay = $exclusiveDay && (bool)($rule->exclusive_day ?? true);
            foreach ($rule->rotation_rule_lines ?? [] as $line) {
                $lid = (int)$line->id;
                if (isset($linesSeen[$lid])) {
                    continue;
                }
                $linesSeen[$lid] = true;
                $linesOut[] = $this->serializeLine($line);
            }
        }

        $agentsOut = [];
        $userIds = [];
        $currentDayIndex = (int)$currentDate->format('N') - 1;

        foreach ($users as $user) {
            if (empty($user->users_rotation_rule)) {
                continue;
            }
            $ruleId = (string)$user->users_rotation_rule->rotation_rule_id;
            $rule = $rulesById[$ruleId] ?? null;
            if ($rule === null || empty($rule->rotation_rule_lines)) {
                continue;
            }

            $skillOfferIds = $this->skillOfferIds($user, $weekStart);
            $quotaLine = null;
            foreach ($rule->rotation_rule_lines as $line) {
                if ((string)$line->line_type === 'quota') {
                    $quotaLine = $line;
                    break;
                }
            }

            $eligible = false;
            foreach ($rule->rotation_rule_lines as $line) {
                $oid = (int)($line->offer_id ?? 0);
                if ($oid > 0 && in_array($oid, $skillOfferIds, true)) {
                    $eligible = true;
                    break;
                }
            }
            if (!$eligible) {
                Log::warning(sprintf(
                    '[ROTATION] agent=%d dans le pool sans skill des lignes du modèle %s — ignoré.',
                    $user->id,
                    $ruleId
                ));
                continue;
            }

            $activeContract = $this->activeContract($user, $weekStart, $weekEnd);
            $targetSlotsByLine = [];
            $targetSlots = 0;
            if ($quotaLine !== null) {
                $oid = (int)($quotaLine->offer_id ?? 0);
                $hasSkill = $oid <= 0 || in_array($oid, $skillOfferIds, true);
                if ($hasSkill) {
                    $targetSlots = $rotationCalculator->calculateTargetForUser(
                        (int)$user->id,
                        $ruleId,
                        $weekStart,
                        $weekEnd,
                        $activeContract ? $activeContract->start_date : null,
                        $activeContract ? $activeContract->end_date : null,
                        (int)($quotaLine->target_count ?? 0) ?: null
                    );
                    if ($user->users_rotation_rule->target_count_override !== null) {
                        $targetSlots = (int)$user->users_rotation_rule->target_count_override;
                    }
                    $targetSlotsByLine[(string)$quotaLine->id] = $targetSlots;
                }
            }

            $historyWorkedDays = [];
            $historySlotsByLine = [];
            foreach ($rule->rotation_rule_lines as $line) {
                $oid = (int)($line->offer_id ?? 0);
                $past = $DraftRanges->find()
                    ->select(['date_start'])
                    ->where([
                        'job_id' => $jobId,
                        'user_id' => $user->id,
                        'date_start <' => $weekStart->format('Y-m-d 00:00:00'),
                        'source' => 'ROTATION',
                    ]);
                if ($oid > 0) {
                    $past->where(['offer_id' => $oid]);
                }
                $pastRanges = $past->all();
                $count = 0;
                $days = [];
                foreach ($pastRanges as $range) {
                    $count++;
                    $rangeDate = $range->date_start;
                    if ($rangeDate instanceof \DateTimeInterface) {
                        $days[] = (int)$rangeDate->format('N') - 1;
                    }
                }
                $historySlotsByLine[(string)$line->id] = $count;
                if ($quotaLine && (int)$line->id === (int)$quotaLine->id) {
                    $historyWorkedDays = $days;
                }
            }

            $unavailableByDay = $this->unavailableByDay(
                (int)$user->id,
                $weekStart,
                $weekEnd,
                $Ranges,
                $fixedActivityAssignments,
                $currentDayIndex
            );

            $wStart = '09:00:00';
            $wEnd = '17:00:00';
            $duration = 180;
            $offerId = null;
            if ($quotaLine) {
                $offerId = $quotaLine->offer_id !== null ? (int)$quotaLine->offer_id : null;
                $duration = (int)($quotaLine->shift_duration ?? 180);
                $wStart = $this->formatTime($quotaLine->time_window_start, '09:00:00');
                $wEnd = $this->formatTime($quotaLine->time_window_end, '17:00:00');
            }

            $userIds[] = (int)$user->id;
            $agentsOut[] = [
                'id' => (int)$user->id,
                'offer_id' => $offerId,
                'target_slots' => $targetSlots,
                'duration' => $duration,
                'window_start' => $wStart,
                'window_end' => $wEnd,
                'history_worked_days' => $historyWorkedDays,
                'lunch_window_start' => $lunchWindow['start'] ?? null,
                'lunch_window_end' => $lunchWindow['end'] ?? null,
                'lunch_duration' => (int)$lunchDurationMinutes,
                'unavailable_by_day' => !empty($unavailableByDay) ? (object)$unavailableByDay : null,
                'skills' => $skillOfferIds,
                'target_slots_by_line' => (object)$targetSlotsByLine,
                'history_slots_by_line' => (object)$historySlotsByLine,
            ];

            if ($debugSolvers) {
                Log::debug(sprintf(
                    '[ROTATION] agent=%d skills=%s targets=%s',
                    $user->id,
                    $truncateForLog(json_encode($skillOfferIds)),
                    $truncateForLog(json_encode($targetSlotsByLine))
                ));
            }
        }

        return [
            'agents' => $agentsOut,
            'lines' => $linesOut,
            'exclusive_day' => $exclusiveDay,
            'user_ids' => $userIds,
        ];
    }

    private function serializeLine($line): array
    {
        $slots = [];
        foreach ($line->rotation_rule_line_slots ?? [] as $pos => $slot) {
            $slots[] = [
                'start' => $this->formatTime($slot->start_time, '09:00:00'),
                'end' => $this->formatTime($slot->end_time, '12:00:00'),
            ];
        }
        $days = $line->days_of_week;
        if (is_string($days)) {
            $days = json_decode($days, true);
        }
        if (!is_array($days)) {
            $days = null;
        }

        return [
            'id' => (int)$line->id,
            'line_type' => (string)$line->line_type,
            'offer_id' => $line->offer_id !== null ? (int)$line->offer_id : null,
            'sort_order' => (int)($line->sort_order ?? 1),
            'target_count' => $line->target_count !== null ? (int)$line->target_count : null,
            'shift_duration' => $line->shift_duration !== null ? (int)$line->shift_duration : null,
            'window_start' => $line->time_window_start ? $this->formatTime($line->time_window_start, '09:00:00') : null,
            'window_end' => $line->time_window_end ? $this->formatTime($line->time_window_end, '17:00:00') : null,
            'fit_need_curve' => (bool)($line->fit_need_curve ?? true),
            'quantity' => $line->quantity !== null ? (int)$line->quantity : 1,
            'equity_enabled' => (bool)($line->equity_enabled ?? true),
            'same_person_day_slots' => (bool)($line->same_person_day_slots ?? false),
            'days_of_week' => $days,
            'slots' => $slots,
        ];
    }

    private function skillOfferIds($user, FrozenDate $weekStart): array
    {
        $ids = [];
        foreach ($user->skills ?? [] as $skill) {
            if (method_exists($skill, 'isValidForDate') && !$skill->isValidForDate($weekStart)) {
                continue;
            }
            $oid = (int)($skill->offer_id ?? 0);
            if ($oid > 0) {
                $ids[] = $oid;
            }
        }

        return array_values(array_unique($ids));
    }

    private function activeContract($user, FrozenDate $weekStart, FrozenDate $weekEnd)
    {
        if (empty($user->user_contracts)) {
            return null;
        }
        foreach ($user->user_contracts as $contract) {
            $cStart = $contract->start_date;
            $cEnd = $contract->end_date;
            $overlaps = $cStart <= $weekEnd && ($cEnd === null || $cEnd >= $weekStart);
            if ($overlaps) {
                return $contract;
            }
        }

        return null;
    }

    private function unavailableByDay(
        int $userId,
        FrozenDate $weekStart,
        FrozenDate $weekEnd,
        $Ranges,
        array $fixedActivityAssignments,
        int $currentDayIndex
    ): array {
        $unavailableByDay = [];
        $userRangesForRotation = $Ranges->find()
            ->where([
                'user_id' => $userId,
                'date_start <=' => $weekEnd->format('Y-m-d 23:59:59'),
                'date_end >=' => $weekStart->format('Y-m-d 00:00:00'),
            ])
            ->contain(['Offers'])
            ->all();

        foreach ($userRangesForRotation as $r) {
            $type = strtolower($r->offer->offer_type ?? 'unknown');
            if ($type !== 'absence' && $type !== 'meeting') {
                continue;
            }
            $rangeDate = $r->date_start instanceof \DateTimeInterface
                ? $r->date_start
                : new FrozenTime($r->date_start);
            $rangeEndDate = $r->date_end instanceof \DateTimeInterface
                ? $r->date_end
                : new FrozenTime($r->date_end);
            $dayIndex = (int)$rangeDate->format('N') - 1;
            if (!isset($unavailableByDay[$dayIndex])) {
                $unavailableByDay[$dayIndex] = [];
            }
            $unavailableByDay[$dayIndex][] = [
                'start' => $rangeDate->format('H:i:s'),
                'end' => $rangeEndDate->format('H:i:s'),
            ];
        }

        if (!empty($fixedActivityAssignments)) {
            foreach ($fixedActivityAssignments as $assignment) {
                if ((int)($assignment['agent_id'] ?? 0) !== $userId) {
                    continue;
                }
                $assignStart = (string)($assignment['start'] ?? '');
                $assignEnd = (string)($assignment['end'] ?? '');
                if ($assignStart === '' || $assignEnd === '') {
                    continue;
                }
                if (!isset($unavailableByDay[$currentDayIndex])) {
                    $unavailableByDay[$currentDayIndex] = [];
                }
                $unavailableByDay[$currentDayIndex][] = [
                    'start' => $assignStart,
                    'end' => $assignEnd,
                ];
            }
        }

        return $unavailableByDay;
    }

    private function formatTime($value, string $default): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i:s');
        }
        $s = trim((string)$value);
        if ($s === '') {
            return $default;
        }
        if (strlen($s) === 5) {
            return $s . ':00';
        }

        return $s;
    }
}
