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
        [$needByPool, $sortOrderByPool] = $this->computeNeedByPoolFromRules($rules, (int)$dateToCalc->format('N'));

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

        /** @var array<int, array<string, float>> Cibles journalières (jour J uniquement) pour cumul côté boucle PHP */
        $dailyTargets = [];

        // Pools triés par ordre croissant (1 = priorité la plus haute).
        $poolOrder = array_keys($needByPool);
        usort($poolOrder, fn($a, $b) =>
            ($sortOrderByPool[$a] ?? PHP_INT_MAX) <=> ($sortOrderByPool[$b] ?? PHP_INT_MAX));

        // Capacité résiduelle initiale = capacité nette du jour, convertie en minutes.
        // computeCapacityHours renvoie des heures ; l'allocation se fait en minutes.
        $residualByAgent = [];
        foreach ($capacityByAgentId as $aid => $hours) {
            $residualByAgent[$aid] = (float)$hours * 60.0;
        }

        // Index id => référence agent, pour répercuter les cibles dans $agentsForJson.
        $agentsById = [];
        foreach ($agentsForJson as &$ag) {
            $aid = (int)($ag['id'] ?? 0);
            $cap = $capacityByAgentId[$aid] ?? 0.0;
            $ag['capacity_hours'] = round($cap, 2);
            $ag['target_ratio'] = $totalCapacity > 0 ? round($cap / $totalCapacity, 6) : 0.0;
            $ag['target_quota_minutes'] = [];
            $agentsById[$aid] = &$ag;
            $dailyTargets[$aid] = [];
            if ($currentRealization !== null && isset($currentRealization[$aid]) && is_array($currentRealization[$aid])) {
                $ag['current_realization'] = $currentRealization[$aid];
            }
        }
        unset($ag);

        // Initialisation : chaque agent reçoit 0.0 pour chaque pool de son scope
        // (global, ou per_site de son site), même s'il n'est pas éligible.
        // Garantit que target_quota_minutes reste un objet JSON non vide pour Pydantic.
        foreach ($poolOrder as $pool) {
            $parts = explode('::', $pool);
            $poolSite = $parts[1] ?? null;
            foreach ($agentsForJson as &$ag) {
                $aid = (int)($ag['id'] ?? 0);
                $agentSite = (string)($agentSiteById[$aid] ?? '');
                if ($poolSite !== null && $agentSite !== $poolSite) {
                    continue;
                }
                $ag['target_quota_minutes'][$pool] = 0.0;
                $dailyTargets[$aid][$pool] = 0.0;
            }
            unset($ag);
        }

        foreach ($poolOrder as $pool) {
            $needMinutes = (float)($needByPool[$pool] ?? 0.0);
            $parts = explode('::', $pool);
            $groupKey = $parts[0];
            $poolSite = $parts[1] ?? null;
            $offerNames = $offerNamesByGroup[$groupKey] ?? [];

            // Agents éligibles sur ce pool (bon site + compétence).
            $eligible = [];
            foreach ($agentsForJson as $ag) {
                $aid = (int)($ag['id'] ?? 0);
                $agentSite = (string)($agentSiteById[$aid] ?? '');
                if ($poolSite !== null && $agentSite !== $poolSite) {
                    continue;
                }
                $skills = (array)($ag['skills'] ?? []);
                $ok = false;
                foreach ($offerNames as $on) {
                    if (in_array($on, $skills, true)) {
                        $ok = true;
                        break;
                    }
                }
                if ($ok) {
                    $eligible[] = $aid;
                }
            }

            // Capacité résiduelle totale des agents éligibles (minutes).
            $poolCap = 0.0;
            foreach ($eligible as $aid) {
                $poolCap += $residualByAgent[$aid] ?? 0.0;
            }
            if ($poolCap <= 0.0) {
                continue; // protection division par zéro
            }

            foreach ($eligible as $aid) {
                $res = $residualByAgent[$aid] ?? 0.0;
                if ($res <= 0.0) {
                    continue;
                }
                $alloc = min($res, $needMinutes * ($res / $poolCap));
                $minutes = round($alloc, 2);
                $agentsById[$aid]['target_quota_minutes'][$pool] = $minutes;
                $dailyTargets[$aid][$pool] = $minutes;
                $residualByAgent[$aid] = $res - $alloc;
            }
        }

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
     * @return array{offer_to_group: array<string, string>, group_mode: array<string, string>}
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
        $groupMode = [];
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
            $groupMode[$equityGroup] = (string)$siteMode;
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
        return [
            'offer_to_group' => $map,
            'group_mode' => $groupMode,
        ];
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
     * Besoin total en minutes par pool d'équité.
     * Une règle per_site applique sa quantité à CHAQUE site (un pool par site) ;
     * pooled/global partagent un pool unique. Seules les règles applicables au jour $dow comptent.
     *
     * @param list<\Cake\ORM\Entity> $rules
     * @return array{0: array<string, float>, 1: array<string, int>} [needByPool, sortOrderByPool]
     */
    private function computeNeedByPoolFromRules(array $rules, int $dow): array
    {
        $needByPool = [];
        $sortOrderByPool = [];
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
            $siteMode = (string)($r->site_mode ?? 'per_site');
            $durationMinutes = max(0, $this->timeToMinutes($end) - $this->timeToMinutes($start));
            $need = $durationMinutes * $qty;
            $order = (int)($r->sort_order ?? 0);

            if ($siteMode === 'per_site') {
                foreach ((array)$r->sites as $site) {
                    $siteName = (string)$site->name;
                    if ($siteName === '') {
                        continue;
                    }
                    $pool = $groupKey . '::' . $siteName;
                    $needByPool[$pool] = ($needByPool[$pool] ?? 0.0) + $need;
                    $sortOrderByPool[$pool] = isset($sortOrderByPool[$pool])
                        ? min($sortOrderByPool[$pool], $order)
                        : $order;
                }
            } else {
                $needByPool[$groupKey] = ($needByPool[$groupKey] ?? 0.0) + $need;
                $sortOrderByPool[$groupKey] = isset($sortOrderByPool[$groupKey])
                    ? min($sortOrderByPool[$groupKey], $order)
                    : $order;
            }
        }
        return [$needByPool, $sortOrderByPool];
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
            $periodEquityWeight = $equityEnabledBool ? 1 : null;
            $allowShortfall = isset($r->allow_shortfall) ? (bool)$r->allow_shortfall : false;
            $active = isset($r->active) ? (bool)$r->active : true;

            $incompatibleBaseOffers = [];
            if (!empty($r->incompatible_offers)) {
                foreach ($r->incompatible_offers as $incOffer) {
                    $incompatibleBaseOffers[] = (string)$incOffer->name;
                }
            }

            $sitesArr = (array)$r->sites;
            $siteMode = $r->site_mode ?? 'per_site';

            $pushFixed = function (string $virtualOffer, string $poolSiteName = '') use (
                &$fixedActivities,
                $start,
                $end,
                $qty,
                $isSplittable,
                $equityEnabledBool,
                $periodEquityWeight,
                $allowShortfall,
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
                $groupKey = $equityGroupId ?? $baseOffer;
                $poolKey = $groupKey;
                if ($siteMode === 'per_site' && $poolSiteName !== '') {
                    $poolKey .= '::' . $poolSiteName;
                }
                $fixedActivities[] = [
                    'offer_name' => $virtualOffer,
                    'start_time' => $start,
                    'end_time' => $end,
                    'quantity' => $qty,
                    'is_splittable' => $isSplittable,
                    'equity_enabled' => $equityEnabledBool,
                    'period_equity_weight' => $periodEquityWeight,
                    'allow_shortfall' => $allowShortfall,
                    'active' => $active,
                    'blocks' => $blocks,
                    'lunch_overlap_allowed' => $lunchOverlapAllowed,
                    'lunch_attach_mode' => $lunchAttachMode,
                    'is_remote_work_compatible' => $remoteWorkCompatible,
                    'base_offer_name' => $baseOffer,
                    'incompatible_base_offers' => $incompatibleBaseOffers,
                    'equity_group' => $groupKey,
                    'pool_key' => $poolKey,
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
                        $pushFixed($baseOffer . $siteSep . $siteName, $siteName);
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
