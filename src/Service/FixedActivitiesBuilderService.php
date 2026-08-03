<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\WfmSetting;
use Cake\I18n\FrozenTime;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Construit le payload pour le solveur activit�s fixes (ciblage global) : Global Targeting.
 * - Capacité nette par agent (contractuel moins absences / unavailable).
 * - Meta-groupes d'équité (equity_group_id).
 * - Cibles (target_quota_minutes) au prorata de la capacité.
 * - Rétroaction via currentRealization pour la boucle multi-jours.
 */
class FixedActivitiesBuilderService
{
    use LocatorAwareTrait;

    private const SITE_SEP = ' - ';

    /**
     * Construit la structure JSON pour POST /api/v1/solve-fixed-activities.
     *
     * @param string $date Date au format Y-m-d
     * @param array<string, mixed> $options wfm_setting_id?, scenario_id?, currentRealization?: array<int, array<string, float>>
     * @return array{
     *   agents: array<int, array<string, mixed>>,
     *   fixed_activities: array<int, array<string, mixed>>,
     *   daily_targets: array<int, array<string, float>>,
     *   workday_start_time: string,
     *   workday_end_time: string,
     *   slot_minutes: int,
     *   lunch_window: array{start: string, end: string}|null,
     *   lunch_duration_minutes: int,
     *   am_break_window: array{start: string, end: string}|null,
     *   pm_break_window: array{start: string, end: string}|null,
     *   break_duration_minutes: int,
     *   enable_am_pm_breaks: bool,
     *   lunch_activity_name: string,
     *   break_activity_name: string,
     *   enforce_remote_work_incompatibilities: bool,
     *   relative_gap_limit: float
     * }
     */
    public function build(string $date, array $options = []): array
    {
        $dateToCalc = new FrozenTime($date . ' 00:00:00');
        $settings = $this->resolveSettings($options);
        $workdayStart = $this->normalizeTime((string)($settings->day_start_time ?? null), '09:00:00');
        $workdayEnd = $this->normalizeTime((string)($settings->day_end_time ?? null), '17:00:00');
        $scenarioId = (int)($options['scenario_id'] ?? 0);
        $currentRealization = $options['currentRealization'] ?? null;
        if (!is_array($currentRealization)) {
            $currentRealization = null;
        }

        $problemBuilder = new ScheduleProblemBuilderService();
        $fullBuild = $problemBuilder->build(
            $dateToCalc,
            $settings,
            $scenarioId,
            $options,
            null,
            [],
            [],
        );

        $agentsForJson = $fullBuild['agents'];
        $agentSiteById = $fullBuild['agent_site_by_id'];

        $Rules = $this->fetchTable('FixedActivityRules');
        $rules = $Rules->find()
            ->contain(['Offers', 'Sites', 'FixedActivityBlocks', 'IncompatibleOffers'])
            ->where(['FixedActivityRules.active' => 1])
            ->all()
            ->toList();

        $fixedActivities = $this->buildFixedActivitiesWithEquityGroup(
            $rules,
            $dateToCalc,
            $workdayStart,
            $workdayEnd,
        );

        $generatedVirtualOffers = [];
        $virtualOfferToSites = [];
        foreach ($fixedActivities as $fa) {
            $voff = $fa['offer_name'];
            if (!in_array($voff, $generatedVirtualOffers, true)) {
                $generatedVirtualOffers[] = $voff;
            }
        }

        $this->enrichAgentSkills($agentsForJson, $agentSiteById, $generatedVirtualOffers);

        $agentAvailabilityService = new AgentAvailabilityService();
        $dow = (int)$dateToCalc->format('N');

        $capacityByAgentId = [];
        foreach ($agentsForJson as $ag) {
            $aid = (int)($ag['id'] ?? 0);
            $capacityByAgentId[$aid] = $this->computeCapacityHours(
                $aid,
                $dow,
                $dateToCalc,
                $ag,
                $agentAvailabilityService,
            );
        }

        $totalCapacity = array_sum($capacityByAgentId);
        $needByEquityGroup = $this->computeNeedByEquityGroupFromRules($rules, (int)$dateToCalc->format('N'));

        // Offre(s) virtuelle(s) par groupe d'équité (pour savoir quels agents sont éligibles au groupe)
        $offerNamesByGroup = [];
        foreach ($fixedActivities as $fa) {
            $g = (string)($fa['equity_group'] ?? $fa['offer_name'] ?? '');
            if ($g !== '') {
                $offerNamesByGroup[$g] = $offerNamesByGroup[$g] ?? [];
                $on = (string)($fa['offer_name'] ?? '');
                if ($on !== '' && !in_array($on, $offerNamesByGroup[$g], true)) {
                    $offerNamesByGroup[$g][] = $on;
                }
            }
        }

        // Capacité totale par groupe d'équité (uniquement les agents qui ont le skill correspondant)
        $capacityByGroup = [];
        foreach ($needByEquityGroup as $groupKey => $needMinutes) {
            $capacityByGroup[$groupKey] = 0.0;
            $offerNames = $offerNamesByGroup[$groupKey] ?? [];
            foreach ($agentsForJson as $ag) {
                $aid = (int)($ag['id'] ?? 0);
                $skills = (array)($ag['skills'] ?? []);
                $eligible = false;
                foreach ($offerNames as $on) {
                    if (in_array($on, $skills, true)) {
                        $eligible = true;
                        break;
                    }
                }
                if ($eligible) {
                    $capacityByGroup[$groupKey] += $capacityByAgentId[$aid] ?? 0.0;
                }
            }
        }

        /** @var array<int, array<string, float>> Cibles journalières (jour J uniquement) pour cumul côté boucle PHP */
        $dailyTargets = [];
        foreach ($agentsForJson as &$ag) {
            $aid = (int)($ag['id'] ?? 0);
            $cap = $capacityByAgentId[$aid] ?? 0.0;
            $skills = (array)($ag['skills'] ?? []);
            $ag['capacity_hours'] = round($cap, 2);
            $ag['target_ratio'] = $totalCapacity > 0 ? round($cap / $totalCapacity, 6) : 0.0;
            $ag['target_quota_minutes'] = [];
            $dailyTargets[$aid] = [];
            foreach ($needByEquityGroup as $groupKey => $needMinutes) {
                $totalCapacityForGroup = $capacityByGroup[$groupKey] ?? 0.0;
                $offerNames = $offerNamesByGroup[$groupKey] ?? [];
                $eligible = false;
                foreach ($offerNames as $on) {
                    if (in_array($on, $skills, true)) {
                        $eligible = true;
                        break;
                    }
                }
                if (!$eligible || $totalCapacityForGroup <= 0) {
                    $minutes = 0.0;
                } else {
                    $minutes = round($needMinutes * ($cap / $totalCapacityForGroup), 2);
                }
                $ag['target_quota_minutes'][$groupKey] = $minutes;
                $dailyTargets[$aid][$groupKey] = $minutes;
            }
            if ($currentRealization !== null && isset($currentRealization[$aid]) && is_array($currentRealization[$aid])) {
                $ag['current_realization'] = $currentRealization[$aid];
            }
        }
        unset($ag);

        $lunchWindow = [
            'start' => $this->normalizeTime($settings->lunch_start_time ?? null, '12:00:00'),
            'end' => $this->normalizeTime($settings->lunch_end_time ?? null, '14:00:00'),
        ];
        $amBreakWindow = [
            'start' => $this->normalizeTime($settings->am_pause_start_time ?? null, '10:00:00'),
            'end' => $this->normalizeTime($settings->am_pause_end_time ?? null, '11:00:00'),
        ];
        $pmBreakWindow = [
            'start' => $this->normalizeTime($settings->pm_pause_start_time ?? null, '15:00:00'),
            'end' => $this->normalizeTime($settings->pm_pause_end_time ?? null, '16:00:00'),
        ];
        $pauseName = 'Pause';
        if (isset($settings->pause_offer) && $settings->pause_offer !== null) {
            $pauseName = (string)($settings->pause_offer->name ?? $pauseName);
        } elseif (isset($settings->pause_offers) && $settings->pause_offers !== null) {
            $pauseName = (string)($settings->pause_offers->name ?? $pauseName);
        }
        $lunchName = 'LUNCH';
        if (isset($settings->lunch_offer) && $settings->lunch_offer !== null) {
            $lunchName = (string)($settings->lunch_offer->name ?? $lunchName);
        } elseif (isset($settings->lunch_offers) && $settings->lunch_offers !== null) {
            $lunchName = (string)($settings->lunch_offers->name ?? $lunchName);
        }

        return [
            'agents' => $agentsForJson,
            'fixed_activities' => $fixedActivities,
            'daily_targets' => $dailyTargets,
            'workday_start_time' => $workdayStart,
            'workday_end_time' => $workdayEnd,
            'slot_minutes' => 15,
            'lunch_window' => $lunchWindow,
            'lunch_duration_minutes' => (int)($settings->lunch_duration_minutes ?? 60),
            'am_break_window' => $amBreakWindow,
            'pm_break_window' => $pmBreakWindow,
            'break_duration_minutes' => (int)($settings->am_pause_duration_minutes ?? 15),
            'enable_am_pm_breaks' => ($settings->enable_am_pm_breaks === null) ? true : (bool)$settings->enable_am_pm_breaks,
            'lunch_activity_name' => $lunchName,
            'break_activity_name' => $pauseName,
            'enforce_remote_work_incompatibilities' => !empty($settings->enforce_remote_work_incompatibilities),
            'relative_gap_limit' => 0.01,
            'min_block_minutes' => (int)($settings->min_block_minutes ?? 60),
            'max_block_minutes' => (int)($settings->max_block_minutes ?? 240),
        ];
    }

    private function resolveSettings(array $options): WfmSetting
    {
        $WfmSettings = $this->fetchTable('WfmSettings');
        $wfmSettingId = $options['wfm_setting_id'] ?? null;
        if ($wfmSettingId !== null) {
            return $WfmSettings->get((int)$wfmSettingId, ['contain' => ['PauseOffers', 'LunchOffers']]);
        }
        $first = $WfmSettings->find()->contain(['PauseOffers', 'LunchOffers'])->first();
        if ($first === null) {
            throw new \RuntimeException('Aucun WfmSetting trouvé. Définir wfm_setting_id dans les options.');
        }
        return $first;
    }

    /**
     * Retourne la map offer_name (virtuel ou base) → equity_group pour agréger les réalisations (drafts).
     *
     * @return array<string, string>
     */
    public function getOfferNameToEquityGroupMap(string $date): array
    {
        $dateToCalc = new FrozenTime($date . ' 00:00:00');
        $dow = (int)$dateToCalc->format('N');
        $Rules = $this->fetchTable('FixedActivityRules');
        $rules = $Rules->find()
            ->contain(['Offers', 'Sites'])
            ->where(['FixedActivityRules.active' => 1])
            ->all()
            ->toList();
        $siteSep = self::SITE_SEP;
        $map = [];
        foreach ($rules as $r) {
            $days = $r->days_of_week ?? null;
            if ($days !== null && $days !== '') {
                if (is_string($days)) {
                    $parsed = json_decode($days, true);
                    $days = is_array($parsed) ? $parsed : [];
                }
                if (is_array($days) && !in_array($dow, array_map('intval', $days), true)) {
                    continue;
                }
            }
            $baseOffer = (string)($r->offer->name ?? '');
            if ($baseOffer === '') {
                continue;
            }
            $equityGroup = $r->equity_group_id !== null && (string)$r->equity_group_id !== ''
                ? (string)$r->equity_group_id
                : $baseOffer;
            $sitesArr = (array)$r->sites;
            $siteMode = $r->site_mode ?? 'per_site';
            $map[$baseOffer] = $equityGroup;
            if ($siteMode === 'global') {
                $map[$baseOffer . $siteSep . 'Global'] = $equityGroup;
            } elseif ($siteMode === 'pooled' && !empty($sitesArr)) {
                $siteNames = implode('+', array_map(fn($s) => (string)$s->name, $sitesArr));
                $map[$baseOffer . $siteSep . $siteNames] = $equityGroup;
            } else {
                foreach ($sitesArr as $site) {
                    $siteName = (string)$site->name;
                    if ($siteName !== '') {
                        $map[$baseOffer . $siteSep . $siteName] = $equityGroup;
                    }
                }
            }
        }
        return $map;
    }

    /**
     * Capacité nette : heures contractuelles (all_intervals) moins unavailable_intervals.
     * Retourne un float en heures (ex: 7.5). Minimum 0.
     */
    private function computeCapacityHours(
        int $userId,
        int $dow,
        FrozenTime $date,
        array $agentPayload,
        AgentAvailabilityService $agentAvailabilityService,
    ): float {
        $Users = $this->fetchTable('Users');
        $user = $Users->get($userId, [
            'contain' => [
                'UserAvailabilities' => fn($q) => $q->where(['day_of_week' => $dow]),
                'Sites',
            ],
        ]);

        if (empty($user->user_availabilities)) {
            return 0.0;
        }

        $availability = $user->user_availabilities[0];
        $effective = $agentAvailabilityService->calculateEffectiveAvailability($user, $date, $availability);
        if ($effective === null) {
            return 0.0;
        }

        $totalAvailableMinutes = 0;
        if (isset($effective['all_intervals']) && is_array($effective['all_intervals'])) {
            foreach ($effective['all_intervals'] as $iv) {
                $s = $this->timeToMinutes($iv['start'] ?? '00:00:00');
                $e = $this->timeToMinutes($iv['end'] ?? '00:00:00');
                $totalAvailableMinutes += max(0, $e - $s);
            }
        } else {
            $s = $this->timeToMinutes($effective['start'] ?? '00:00:00');
            $e = $this->timeToMinutes($effective['end'] ?? '00:00:00');
            $totalAvailableMinutes = max(0, $e - $s);
        }

        $unavailableMinutes = 0;
        $unavailable = $agentPayload['unavailable_intervals'] ?? null;
        if (is_array($unavailable)) {
            foreach ($unavailable as $iv) {
                $start = $iv['start'] ?? '00:00:00';
                $end = $iv['end'] ?? '00:00:00';
                $unavailableMinutes += max(0, $this->timeToMinutes($end) - $this->timeToMinutes($start));
            }
        }

        $netMinutes = max(0, $totalAvailableMinutes - $unavailableMinutes);
        return round($netMinutes / 60.0, 2);
    }

    private function timeToMinutes(string $t): int
    {
        $parts = explode(':', $t);
        $h = (int)($parts[0] ?? 0);
        $m = (int)($parts[1] ?? 0);
        return $h * 60 + $m;
    }

    /**
     * Besoin total en minutes par groupe d'équité (equity_group_id ou offer name).
     * Une règle ne compte qu'une fois (pas de double compte par variante site).
     * Seules les règles applicables au jour $dow sont prises en compte.
     *
     * @param list<\Cake\ORM\Entity> $rules
     * @return array<string, float>
     */
    private function computeNeedByEquityGroupFromRules(array $rules, int $dow): array
    {
        $needByGroup = [];
        foreach ($rules as $r) {
            $days = $r->days_of_week ?? null;
            if ($days !== null && $days !== '') {
                if (is_string($days)) {
                    $parsed = json_decode($days, true);
                    $days = is_array($parsed) ? $parsed : [];
                }
                if (is_array($days) && !in_array($dow, array_map('intval', $days), true)) {
                    continue;
                }
            }
            $baseOffer = (string)($r->offer->name ?? '');
            if ($baseOffer === '') {
                continue;
            }
            $start = $this->normalizeTime($r->start_time ?? null);
            $end = $this->normalizeTime($r->end_time ?? null);
            $qty = (int)($r->quantity ?? 0);
            if (!$start || !$end || $qty <= 0) {
                continue;
            }
            $groupKey = $r->equity_group_id !== null && (string)$r->equity_group_id !== ''
                ? (string)$r->equity_group_id
                : $baseOffer;
            $durationMinutes = max(0, $this->timeToMinutes($end) - $this->timeToMinutes($start));
            $need = $durationMinutes * $qty;
            if (!isset($needByGroup[$groupKey])) {
                $needByGroup[$groupKey] = 0.0;
            }
            $needByGroup[$groupKey] += $need;
        }
        return $needByGroup;
    }

    /**
     * Construit le tableau fixed_activities avec equity_group (meta-groupes).
     *
     * @param list<\Cake\ORM\Entity> $rules
     * @return array<int, array<string, mixed>>
     */
    private function buildFixedActivitiesWithEquityGroup(
        array $rules,
        FrozenTime $dateToCalc,
        string $workdayStart,
        string $workdayEnd,
    ): array {
        $dow = (int)$dateToCalc->format('N');
        $fixedActivities = [];
        $siteSep = self::SITE_SEP;

        foreach ($rules as $r) {
            $days = $r->days_of_week ?? null;
            $applies = true;
            if ($days !== null && $days !== '') {
                if (is_string($days)) {
                    $parsed = json_decode($days, true);
                    $days = is_array($parsed) ? $parsed : [];
                }
                if (is_array($days) && !in_array($dow, array_map('intval', $days), true)) {
                    $applies = false;
                }
            }
            if (!$applies) {
                continue;
            }

            $baseOffer = (string)($r->offer->name ?? '');
            if ($baseOffer === '') {
                continue;
            }

            $start = $this->normalizeTime($r->start_time ?? null);
            $end = $this->normalizeTime($r->end_time ?? null);
            $qty = (int)($r->quantity ?? 0);
            if (!$start || !$end || $qty <= 0 || $start >= $end) {
                continue;
            }

            $equityGroupId = $r->equity_group_id !== null && (string)$r->equity_group_id !== ''
                ? (string)$r->equity_group_id
                : null;

            $blocks = [];
            if (!empty($r->is_splittable) && !empty($r->fixed_activity_blocks)) {
                foreach ($r->fixed_activity_blocks as $block) {
                    if (!empty($block->start_time) && !empty($block->end_time)) {
                        $blocks[] = [
                            'start' => $this->normalizeTime($block->start_time),
                            'end' => $this->normalizeTime($block->end_time),
                        ];
                    }
                }
            }

            $lunchOverlapAllowed = isset($r->lunch_overlap_allowed) ? (bool)$r->lunch_overlap_allowed : true;
            $lunchAttachModeRaw = $r->lunch_attach_mode ?? 'none';
            $lunchAttachMode = in_array((string)$lunchAttachModeRaw, ['before', 'after'], true)
                ? (string)$lunchAttachModeRaw
                : 'none';
            $remoteWorkCompatible = isset($r->offer->is_remote_work_compatible)
                ? (bool)$r->offer->is_remote_work_compatible
                : true;
            $equityEnabled = $r->equity_enabled;
            if ($equityEnabled === null) {
                $equityEnabled = !empty($r->offer->equity_enabled);
            }
            $isSplittable = isset($r->is_splittable) ? (bool)$r->is_splittable : true;
            $equityEnabledBool = (bool)$equityEnabled;
            $equityStrengthForWeight = isset($r->equity_strength) ? (int)$r->equity_strength : 100;
            $periodEquityWeight = $equityEnabledBool ? $equityStrengthForWeight : null;
            $equityStrength = isset($r->equity_strength) ? (int)$r->equity_strength : 0;
            $priority = isset($r->priority) ? (int)$r->priority : 0;
            $active = isset($r->active) ? (bool)$r->active : true;

            $incompatibleBaseOffers = [];
            if (!empty($r->incompatible_offers)) {
                foreach ($r->incompatible_offers as $incOffer) {
                    $incompatibleBaseOffers[] = (string)$incOffer->name;
                }
            }

            $sitesArr = (array)$r->sites;
            $siteMode = $r->site_mode ?? 'per_site';

            $pushFixed = function (string $virtualOffer) use (
                &$fixedActivities,
                $start,
                $end,
                $qty,
                $isSplittable,
                $equityEnabledBool,
                $periodEquityWeight,
                $equityStrength,
                $priority,
                $active,
                $blocks,
                $lunchOverlapAllowed,
                $lunchAttachMode,
                $remoteWorkCompatible,
                $baseOffer,
                $incompatibleBaseOffers,
                $equityGroupId,
                $siteMode,
            ): void {
                $fixedActivities[] = [
                    'offer_name' => $virtualOffer,
                    'start_time' => $start,
                    'end_time' => $end,
                    'quantity' => $qty,
                    'is_splittable' => $isSplittable,
                    'equity_enabled' => $equityEnabledBool,
                    'period_equity_weight' => $periodEquityWeight,
                    'equity_strength' => $equityStrength,
                    'priority' => $priority,
                    'active' => $active,
                    'blocks' => $blocks,
                    'lunch_overlap_allowed' => $lunchOverlapAllowed,
                    'lunch_attach_mode' => $lunchAttachMode,
                    'is_remote_work_compatible' => $remoteWorkCompatible,
                    'base_offer_name' => $baseOffer,
                    'incompatible_base_offers' => $incompatibleBaseOffers,
                    'equity_group' => $equityGroupId ?? $baseOffer,
                    'site_mode' => $siteMode,
                ];
            };

            if ($siteMode === 'global') {
                $pushFixed($baseOffer . $siteSep . 'Global');
            } elseif ($siteMode === 'pooled' && !empty($sitesArr)) {
                $siteNames = implode('+', array_map(fn($s) => (string)$s->name, $sitesArr));
                $pushFixed($baseOffer . $siteSep . $siteNames);
            } else {
                foreach ($sitesArr as $site) {
                    $siteName = (string)$site->name;
                    if ($siteName !== '') {
                        $pushFixed($baseOffer . $siteSep . $siteName);
                    }
                }
            }
        }

        return $fixedActivities;
    }

    private function enrichAgentSkills(
        array &$agentsForJson,
        array $agentSiteById,
        array $generatedVirtualOffers,
    ): void {
        $normalizeSite = static function (string $s): string {
            return strtolower(trim($s));
        };

        $virtualOfferToSites = [];
        foreach ($generatedVirtualOffers as $voff) {
            $parts = explode(self::SITE_SEP, $voff, 2);
            if (count($parts) !== 2) {
                continue;
            }
            [$baseName, $target] = $parts;
            if ($target !== 'Global' && str_contains($target, '+')) {
                $virtualOfferToSites[$voff] = array_map(
                    fn(string $site): string => $normalizeSite($site),
                    explode('+', $target),
                );
            }
        }

        foreach ($agentsForJson as &$ag) {
            $aid = (int)($ag['id'] ?? 0);
            $agentSiteRaw = $agentSiteById[$aid] ?? null;
            $agentSite = $agentSiteRaw !== null && $agentSiteRaw !== '' ? $normalizeSite((string)$agentSiteRaw) : '';
            $skills = (array)($ag['skills'] ?? []);
            foreach ($generatedVirtualOffers as $voff) {
                $parts = explode(self::SITE_SEP, $voff, 2);
                if (count($parts) !== 2) {
                    continue;
                }
                [$baseName, $target] = $parts;
                $normalizedTarget = $normalizeSite((string)$target);
                $eligible = false;
                if (strtolower(trim((string)$target)) === 'global') {
                    $eligible = true;
                } elseif (isset($virtualOfferToSites[$voff])) {
                    $eligible = $agentSite !== '' && in_array($agentSite, $virtualOfferToSites[$voff], true);
                } else {
                    $eligible = $agentSite !== '' && $agentSite === $normalizedTarget;
                }
                $hasBaseSkill = in_array($baseName, $skills, true);
                $alreadyHasVirtual = in_array($voff, $skills, true);
                if ($eligible && $hasBaseSkill && !$alreadyHasVirtual) {
                    $ag['skills'][] = $voff;
                    Log::debug(sprintf(
                        '[FixedActivitiesBuilder] Agent %d (Site: %s) éligible pour %s',
                        $aid,
                        $agentSiteRaw ?? 'null',
                        $voff,
                    ));
                }
            }
        }
        unset($ag);
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
