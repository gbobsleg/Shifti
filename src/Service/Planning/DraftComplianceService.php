<?php
declare(strict_types=1);

namespace App\Service\Planning;

use App\Service\Rotation\RotationTargetCalculatorService;
use Cake\I18n\FrozenDate;
use Cake\I18n\FrozenTime;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Contrôle de conformité pré-publication sur le brouillon d'un job :
 * - activités fixes : couverture (effectif concurrent / fenêtre)
 * - rotations : nombre de plages vs cible
 */
class DraftComplianceService
{
    use LocatorAwareTrait;

    private const SITE_SEP = ' - ';
    private const DURATION_TOLERANCE = 0.90;

    private RotationTargetCalculatorService $rotationCalculator;

    public function __construct(?RotationTargetCalculatorService $rotationCalculator = null)
    {
        $this->rotationCalculator = $rotationCalculator ?? new RotationTargetCalculatorService();
    }

    /**
     * @return array{
     *   fixed: list<array<string, mixed>>,
     *   rotation: list<array<string, mixed>>,
     *   summary: array<string, int>
     * }
     */
    public function analyze(int $jobId): array
    {
        $Jobs = $this->fetchTable('PlanningGenerationJobs');
        $Days = $this->fetchTable('PlanningGenerationJobDays');
        $DraftRanges = $this->fetchTable('DraftRanges');
        $FixedActivityRules = $this->fetchTable('FixedActivityRules');
        $UsersRotationRules = $this->fetchTable('UsersRotationRules');
        $Offers = $this->fetchTable('Offers');
        $WfmSettings = $this->fetchTable('WfmSettings');

        $job = $Jobs->get($jobId, ['contain' => ['WfmSettings']]);

        $okDays = $Days->find()
            ->where(['job_id' => $jobId, 'status' => 'ok'])
            ->orderAsc('date')
            ->all()
            ->toList();

        $empty = [
            'fixed' => [],
            'rotation' => [],
            'summary' => [
                'fixed_ok' => 0,
                'fixed_ko' => 0,
                'fixed_total' => 0,
                'rotation_ok' => 0,
                'rotation_ko' => 0,
                'rotation_total' => 0,
                'ko_total' => 0,
            ],
        ];

        if ($okDays === []) {
            return $empty;
        }

        $okDates = [];
        foreach ($okDays as $d) {
            $date = $d->date instanceof FrozenDate ? $d->date : new FrozenDate((string)$d->date);
            $okDates[] = $date->format('Y-m-d');
        }
        $okDates = array_values(array_unique($okDates));
        sort($okDates);

        $periodStart = new FrozenDate($okDates[0]);
        $periodEnd = new FrozenDate($okDates[count($okDates) - 1]);

        $dayStart = new FrozenTime($okDates[0] . ' 00:00:00');
        $dayEnd = new FrozenTime($okDates[count($okDates) - 1] . ' 23:59:59');

        $settings = $job->wfm_setting ?? $WfmSettings->find()->first();
        if ($settings) {
            $this->rotationCalculator->setDayBoundaries(
                $this->normalizeTime($settings->day_start_time ?? null, '09:00:00'),
                $this->normalizeTime($settings->day_end_time ?? null, '17:00:00'),
            );
        }

        $excludedOfferIds = [];
        if ($settings) {
            if (!empty($settings->pause_offer_id)) {
                $excludedOfferIds[] = (int)$settings->pause_offer_id;
            }
            if (!empty($settings->lunch_offer_id)) {
                $excludedOfferIds[] = (int)$settings->lunch_offer_id;
            }
        }
        if ($excludedOfferIds === []) {
            $excludedOfferIds = array_merge(
                $Offers->find('ByType', ['type' => 'pause'])->extract('id')->toList(),
                $Offers->find('ByType', ['type' => 'lunch'])->extract('id')->toList(),
            );
        }
        $excludedOfferIds = array_values(array_unique(array_map('intval', $excludedOfferIds)));

        $draftQuery = $DraftRanges->find()
            ->contain(['Offers', 'Users' => ['Sites']])
            ->where([
                'DraftRanges.job_id' => $jobId,
                'DraftRanges.date_start <' => $dayEnd,
                'DraftRanges.date_end >' => $dayStart,
            ]);
        if ($excludedOfferIds !== []) {
            $draftQuery->where(['DraftRanges.offer_id NOT IN' => $excludedOfferIds]);
        }
        $draftRows = $draftQuery->all()->toList();

        // Index drafts par date Y-m-d
        $draftsByDate = [];
        foreach ($draftRows as $dr) {
            $ds = $dr->date_start instanceof \DateTimeInterface
                ? new FrozenTime($dr->date_start->format('Y-m-d H:i:s'))
                : new FrozenTime((string)$dr->date_start);
            $dateKey = $ds->format('Y-m-d');
            if (!in_array($dateKey, $okDates, true)) {
                continue;
            }
            $draftsByDate[$dateKey][] = $dr;
        }

        $rules = $FixedActivityRules->find()
            ->contain(['Offers', 'Sites', 'FixedActivityBlocks'])
            ->where(['FixedActivityRules.active' => true])
            ->all()
            ->toList();

        $fixed = $this->analyzeFixed($rules, $okDates, $draftsByDate);
        $rotation = $this->analyzeRotation(
            $UsersRotationRules,
            $draftRows,
            $periodStart,
            $periodEnd,
            $okDates,
        );

        $summary = [
            'fixed_ok' => 0,
            'fixed_ko' => 0,
            'fixed_total' => count($fixed),
            'rotation_ok' => 0,
            'rotation_ko' => 0,
            'rotation_total' => count($rotation),
            'ko_total' => 0,
        ];
        foreach ($fixed as $row) {
            if (($row['status'] ?? '') === 'ok') {
                $summary['fixed_ok']++;
            } else {
                $summary['fixed_ko']++;
            }
        }
        foreach ($rotation as $row) {
            if (($row['status'] ?? '') === 'ok') {
                $summary['rotation_ok']++;
            } else {
                $summary['rotation_ko']++;
            }
        }
        $summary['ko_total'] = $summary['fixed_ko'] + $summary['rotation_ko'];

        return [
            'fixed' => $fixed,
            'rotation' => $rotation,
            'summary' => $summary,
        ];
    }

    /**
     * @param list<\Cake\Datasource\EntityInterface> $rules
     * @param list<string> $okDates
     * @param array<string, list<\Cake\Datasource\EntityInterface>> $draftsByDate
     * @return list<array<string, mixed>>
     */
    private function analyzeFixed(array $rules, array $okDates, array $draftsByDate): array
    {
        $rows = [];

        foreach ($okDates as $dateStr) {
            $date = new FrozenDate($dateStr);
            $dow = (int)$date->format('N');
            $dayDrafts = $draftsByDate[$dateStr] ?? [];

            foreach ($rules as $rule) {
                if (!$this->ruleAppliesOnDow($rule, $dow)) {
                    continue;
                }

                $offerName = (string)($rule->offer->name ?? '');
                $offerId = (int)($rule->offer_id ?? 0);
                if ($offerName === '' || $offerId <= 0) {
                    continue;
                }

                $qty = (int)($rule->quantity ?? 0);
                if ($qty <= 0) {
                    continue;
                }

                $windows = $this->ruleWindows($rule);
                if ($windows === []) {
                    continue;
                }

                $siteMode = (string)($rule->site_mode ?? 'per_site');
                $sites = (array)($rule->sites ?? []);

                $scopes = [];
                if ($siteMode === 'global' || $sites === []) {
                    $scopes[] = ['label' => 'Global', 'site_ids' => null, 'site_names' => []];
                } elseif ($siteMode === 'pooled') {
                    $siteIds = [];
                    $siteNames = [];
                    foreach ($sites as $site) {
                        $siteIds[] = (int)$site->id;
                        $siteNames[] = (string)$site->name;
                    }
                    $scopes[] = [
                        'label' => implode('+', $siteNames),
                        'site_ids' => $siteIds,
                        'site_names' => $siteNames,
                    ];
                } else {
                    foreach ($sites as $site) {
                        $scopes[] = [
                            'label' => (string)$site->name,
                            'site_ids' => [(int)$site->id],
                            'site_names' => [(string)$site->name],
                        ];
                    }
                }

                foreach ($scopes as $scope) {
                    foreach ($windows as $window) {
                        $actual = $this->countCoveringAgents(
                            $dayDrafts,
                            $offerId,
                            $dateStr,
                            $window['start'],
                            $window['end'],
                            $scope['site_ids'],
                        );
                        $status = $this->compareStatus($qty, $actual);
                        $rows[] = [
                            'rule_id' => (int)$rule->id,
                            'offer' => $offerName,
                            'offer_id' => $offerId,
                            'site' => $scope['label'],
                            'date' => $dateStr,
                            'window_start' => $window['start'],
                            'window_end' => $window['end'],
                            'window_label' => substr($window['start'], 0, 5) . '–' . substr($window['end'], 0, 5),
                            'required' => $qty,
                            'actual' => $actual,
                            'status' => $status,
                        ];
                    }
                }
            }
        }

        usort($rows, static function (array $a, array $b): int {
            $statusOrder = ['manque' => 0, 'excedent' => 1, 'ok' => 2];
            $sa = $statusOrder[$a['status']] ?? 9;
            $sb = $statusOrder[$b['status']] ?? 9;
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }
            if ($a['date'] !== $b['date']) {
                return strcmp((string)$a['date'], (string)$b['date']);
            }
            return strcmp((string)$a['offer'], (string)$b['offer']);
        });

        return $rows;
    }

    /**
     * @param \Cake\ORM\Table $UsersRotationRules
     * @param list<\Cake\Datasource\EntityInterface> $draftRows
     * @param list<string> $okDates
     * @return list<array<string, mixed>>
     */
    private function analyzeRotation(
        $UsersRotationRules,
        array $draftRows,
        FrozenDate $periodStart,
        FrozenDate $periodEnd,
        array $okDates,
    ): array {
        $userIdsInDraft = [];
        foreach ($draftRows as $dr) {
            $userIdsInDraft[(int)$dr->user_id] = true;
        }
        $userIds = array_keys($userIdsInDraft);
        if ($userIds === []) {
            return [];
        }

        $weeks = $this->enumerateWeeksTouchingOkDates($periodStart, $periodEnd, $okDates);
        if ($weeks === []) {
            return [];
        }
        $weeksCount = count($weeks);

        $userRules = $UsersRotationRules->find()
            ->contain(['Users' => ['UserContracts'], 'RotationRules' => ['Offers']])
            ->where(['UsersRotationRules.user_id IN' => $userIds])
            ->all()
            ->toList();

        // Index drafts by user+offer
        $byUserOffer = [];
        foreach ($draftRows as $dr) {
            $uid = (int)$dr->user_id;
            $oid = (int)$dr->offer_id;
            $byUserOffer[$uid][$oid][] = $dr;
        }

        $rows = [];
        foreach ($userRules as $ur) {
            $rule = $ur->rotation_rule;
            if (!$rule) {
                continue;
            }
            $user = $ur->user;
            $userId = (int)$ur->user_id;
            $offerId = (int)($rule->offer_id ?? 0);
            if ($offerId <= 0) {
                continue;
            }

            $targetBase = $ur->target_count_override !== null
                ? (int)$ur->target_count_override
                : (int)($rule->target_count ?? 0);
            if ($targetBase <= 0) {
                continue;
            }

            $requiredProrated = 0;
            foreach ($weeks as $week) {
                $weekStart = $week['start'];
                $weekEnd = $week['end'];
                [$contractStart, $contractEnd] = $this->findOverlappingContractDates(
                    (array)($user->user_contracts ?? []),
                    $weekStart,
                    $weekEnd,
                );

                $weekly = $this->rotationCalculator->calculateTargetForUser(
                    $userId,
                    (string)$rule->id,
                    $weekStart,
                    $weekEnd,
                    $contractStart,
                    $contractEnd,
                );
                // Aligné génération : override sans prorata si présent
                if ($ur->target_count_override !== null) {
                    $weekly = (int)$ur->target_count_override;
                }
                $requiredProrated += max(0, $weekly);
            }

            $shiftDuration = max(1, (int)$rule->shift_duration);
            $winStart = $this->normalizeTime($rule->time_window_start ?? null, '00:00:00');
            $winEnd = $this->normalizeTime($rule->time_window_end ?? null, '23:59:59');

            $userOfferDrafts = $byUserOffer[$userId][$offerId] ?? [];
            $plages = $this->countRotationPlages(
                $userOfferDrafts,
                $okDates,
                $winStart,
                $winEnd,
                $shiftDuration,
            );

            $actual = count($plages);
            if ($requiredProrated <= 0 && $actual === 0) {
                continue;
            }

            $status = $this->compareStatus($requiredProrated, $actual);
            $userName = trim((string)($user->first_name ?? '') . ' ' . (string)($user->last_name ?? ''));
            if ($userName === '') {
                $userName = '#' . $userId;
            }

            $isMonthly = ((string)$rule->period_type === 'MONTHLY');
            $periodLabel = $isMonthly ? 'Mensuel' : 'Hebdomadaire';
            $periodLabel .= ' · ' . $periodStart->format('d/m/Y') . ' → ' . $periodEnd->format('d/m/Y');

            $targetNominal = $targetBase * $weeksCount;
            $targetLabel = $isMonthly
                ? sprintf('%d / mois · nominal %d (%d sem.)', $targetBase, $targetNominal, $weeksCount)
                : sprintf('%d / sem. × %d sem.', $targetBase, $weeksCount);

            $rows[] = [
                'user_id' => $userId,
                'name' => $userName,
                'rule_id' => (string)$rule->id,
                'rule_name' => (string)($rule->name ?? ''),
                'offer' => (string)($rule->offer->name ?? ''),
                'period_label' => $periodLabel,
                'target_base' => $targetBase,
                'target_nominal' => $targetNominal,
                'target_label' => $targetLabel,
                'weeks_count' => $weeksCount,
                'required' => $requiredProrated,
                'actual' => $actual,
                'status' => $status,
                'plages' => $plages,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $statusOrder = ['manque' => 0, 'excedent' => 1, 'ok' => 2];
            $sa = $statusOrder[$a['status']] ?? 9;
            $sb = $statusOrder[$b['status']] ?? 9;
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }
            return strcasecmp((string)$a['name'], (string)$b['name']);
        });

        return $rows;
    }

    /**
     * Semaines calendaires (lun–dim) intersectant la période et touchant au moins un jour OK.
     *
     * @param list<string> $okDates
     * @return list<array{start: FrozenDate, end: FrozenDate}>
     */
    private function enumerateWeeksTouchingOkDates(
        FrozenDate $periodStart,
        FrozenDate $periodEnd,
        array $okDates,
    ): array {
        if ($okDates === [] || $periodStart > $periodEnd) {
            return [];
        }

        $okSet = array_fill_keys($okDates, true);
        $weeks = [];
        $cursor = $periodStart->startOfWeek();
        $lastWeekStart = $periodEnd->startOfWeek();

        while ($cursor <= $lastWeekStart) {
            $weekStart = $cursor;
            $weekEnd = $cursor->endOfWeek();
            $touchesOk = false;
            $day = $weekStart;
            while ($day <= $weekEnd) {
                if ($day >= $periodStart && $day <= $periodEnd && isset($okSet[$day->format('Y-m-d')])) {
                    $touchesOk = true;
                    break;
                }
                $day = $day->addDays(1);
            }
            if ($touchesOk) {
                $weeks[] = ['start' => $weekStart, 'end' => $weekEnd];
            }
            $cursor = $cursor->addWeeks(1);
        }

        return $weeks;
    }

    /**
     * Premier contrat chevauchant la semaine (aligné génération).
     *
     * @param list<\Cake\Datasource\EntityInterface> $contracts
     * @return array{0: ?FrozenDate, 1: ?FrozenDate}
     */
    private function findOverlappingContractDates(array $contracts, FrozenDate $weekStart, FrozenDate $weekEnd): array
    {
        foreach ($contracts as $contract) {
            $cStart = $contract->start_date ?? null;
            if (!$cStart instanceof FrozenDate) {
                if ($cStart === null || $cStart === '') {
                    continue;
                }
                $cStart = new FrozenDate((string)$cStart);
            }
            $cEnd = $contract->end_date ?? null;
            if ($cEnd !== null && !$cEnd instanceof FrozenDate) {
                $cEnd = new FrozenDate((string)$cEnd);
            }
            $overlaps = $cStart <= $weekEnd && ($cEnd === null || $cEnd >= $weekStart);
            if ($overlaps) {
                return [$cStart, $cEnd instanceof FrozenDate ? $cEnd : null];
            }
        }

        return [null, null];
    }

    /**
     * @param list<\Cake\Datasource\EntityInterface> $dayDrafts
     * @param list<int>|null $siteIds null = tous sites
     */
    private function countCoveringAgents(
        array $dayDrafts,
        int $offerId,
        string $dateStr,
        string $windowStart,
        string $windowEnd,
        ?array $siteIds,
    ): int {
        $winStart = new FrozenTime($dateStr . ' ' . $windowStart);
        $winEnd = new FrozenTime($dateStr . ' ' . $windowEnd);
        $agents = [];

        foreach ($dayDrafts as $dr) {
            if ((int)$dr->offer_id !== $offerId) {
                continue;
            }
            if ($siteIds !== null) {
                $userSiteId = (int)($dr->user->site_id ?? 0);
                if ($userSiteId <= 0 && !empty($dr->user->site)) {
                    $userSiteId = (int)$dr->user->site->id;
                }
                if ($userSiteId <= 0 || !in_array($userSiteId, $siteIds, true)) {
                    continue;
                }
            }
            $ds = $dr->date_start instanceof \DateTimeInterface
                ? new FrozenTime($dr->date_start->format('Y-m-d H:i:s'))
                : new FrozenTime((string)$dr->date_start);
            $de = $dr->date_end instanceof \DateTimeInterface
                ? new FrozenTime($dr->date_end->format('Y-m-d H:i:s'))
                : new FrozenTime((string)$dr->date_end);

            if ($ds < $winEnd && $de > $winStart) {
                $agents[(int)$dr->user_id] = true;
            }
        }

        return count($agents);
    }

    /**
     * @param list<\Cake\Datasource\EntityInterface> $drafts
     * @param list<string> $okDates
     * @return list<array{date: string, start: string, end: string, duration_min: int}>
     */
    private function countRotationPlages(
        array $drafts,
        array $okDates,
        string $winStart,
        string $winEnd,
        int $shiftDuration,
    ): array {
        $minDuration = (int)max(1, round($shiftDuration * self::DURATION_TOLERANCE));
        $merged = $this->mergeContiguousPlages($drafts);
        $plages = [];

        foreach ($merged as $block) {
            $dateKey = $block['start']->format('Y-m-d');
            if (!in_array($dateKey, $okDates, true)) {
                continue;
            }
            $startTod = $block['start']->format('H:i:s');
            if ($startTod < $winStart || $startTod > $winEnd) {
                continue;
            }
            $durationMin = (int)(($block['end']->getTimestamp() - $block['start']->getTimestamp()) / 60);
            if ($durationMin < $minDuration) {
                continue;
            }
            $plages[] = [
                'date' => $dateKey,
                'start' => $block['start']->format('H:i'),
                'end' => $block['end']->format('H:i'),
                'duration_min' => $durationMin,
            ];
        }

        return $plages;
    }

    /**
     * @param list<\Cake\Datasource\EntityInterface> $drafts
     * @return list<array{start: FrozenTime, end: FrozenTime}>
     */
    private function mergeContiguousPlages(array $drafts): array
    {
        if ($drafts === []) {
            return [];
        }

        $intervals = [];
        foreach ($drafts as $dr) {
            $ds = $dr->date_start instanceof \DateTimeInterface
                ? new FrozenTime($dr->date_start->format('Y-m-d H:i:s'))
                : new FrozenTime((string)$dr->date_start);
            $de = $dr->date_end instanceof \DateTimeInterface
                ? new FrozenTime($dr->date_end->format('Y-m-d H:i:s'))
                : new FrozenTime((string)$dr->date_end);
            $intervals[] = ['start' => $ds, 'end' => $de];
        }

        usort($intervals, static fn($a, $b) => $a['start'] <=> $b['start']);

        $merged = [];
        $current = $intervals[0];
        for ($i = 1, $n = count($intervals); $i < $n; $i++) {
            $next = $intervals[$i];
            // Contigus ou chevauchants (tolérance 1s)
            if ($next['start']->getTimestamp() <= $current['end']->getTimestamp() + 1) {
                if ($next['end'] > $current['end']) {
                    $current['end'] = $next['end'];
                }
            } else {
                $merged[] = $current;
                $current = $next;
            }
        }
        $merged[] = $current;

        return $merged;
    }

    /**
     * @return list<array{start: string, end: string}>
     */
    private function ruleWindows(object $rule): array
    {
        $windows = [];
        if (!empty($rule->is_splittable) && !empty($rule->fixed_activity_blocks)) {
            foreach ($rule->fixed_activity_blocks as $block) {
                $s = $this->normalizeTime($block->start_time ?? null, '');
                $e = $this->normalizeTime($block->end_time ?? null, '');
                if ($s !== '' && $e !== '' && $s < $e) {
                    $windows[] = ['start' => $s, 'end' => $e];
                }
            }
        }
        if ($windows === []) {
            $s = $this->normalizeTime($rule->start_time ?? null, '');
            $e = $this->normalizeTime($rule->end_time ?? null, '');
            if ($s !== '' && $e !== '' && $s < $e) {
                $windows[] = ['start' => $s, 'end' => $e];
            }
        }
        return $windows;
    }

    private function ruleAppliesOnDow(object $rule, int $dow): bool
    {
        $days = $rule->days_of_week ?? null;
        if ($days === null || $days === '') {
            return true;
        }
        if (is_string($days)) {
            $parsed = json_decode($days, true);
            $days = is_array($parsed) ? $parsed : [];
        }
        if (!is_array($days) || $days === []) {
            return true;
        }
        return in_array($dow, array_map('intval', $days), true);
    }

    private function compareStatus(int $required, int $actual): string
    {
        if ($actual < $required) {
            return 'manque';
        }
        if ($actual > $required) {
            return 'excedent';
        }
        return 'ok';
    }

    private function normalizeTime(mixed $t, string $default = '00:00:00'): string
    {
        if ($t instanceof \DateTimeInterface) {
            return $t->format('H:i:s');
        }
        if ($t === null || $t === '' || !is_string($t)) {
            return $default;
        }
        if (preg_match('/^\d{2}:\d{2}$/', $t)) {
            return $t . ':00';
        }
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $t)) {
            return $t;
        }
        return $default;
    }
}
