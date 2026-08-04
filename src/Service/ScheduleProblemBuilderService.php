<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\WfmSetting;
use App\Service\Equity\EquityScoresProviderInterface;
use App\Service\OfferGroups\OfferGroupsNeedService;
use Cake\I18n\FrozenTime;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Construit les payloads (Passe 1 / Passe 2) et diagnostics à partir des mêmes règles
 * que la génération 1‑jour, pour être partagé avec le worker multi‑jours.
 *
 * Note: la source des scores d'équité n'est pas fixée ici (single-day vs job-period).
 */
class ScheduleProblemBuilderService
{
    use LocatorAwareTrait;

    public const SLOT_MINUTES = 15;

    private AgentAvailabilityService $agentAvailabilityService;
    private RemoteWorkIntervalsService $remoteWorkIntervalsService;
    private OfferGroupsNeedService $offerGroupsNeedService;

    public function __construct(?OfferGroupsNeedService $offerGroupsNeedService = null)
    {
        $this->agentAvailabilityService = new AgentAvailabilityService();
        $this->remoteWorkIntervalsService = new RemoteWorkIntervalsService();
        $this->offerGroupsNeedService = $offerGroupsNeedService ?? new OfferGroupsNeedService();
    }

    /**
     * @return array{
     *   date: FrozenTime,
     *   settings: WfmSetting,
     *   scenario_id:int,
     *   options: array<string,mixed>,
     *   need_curve: array<string,array<string,int>>,
     *   workday_start_time:string,
     *   workday_end_time:string,
     *   strict_work_hours:bool,
     *   enable_am_pm_breaks:bool,
     *   forbid_midday_singletons:bool,
     *   lunch_window: array{start:string,end:string},
     *   lunch_duration_minutes:int,
     *   break_duration_minutes:int,
     *   am_break_window: array{start:string,end:string},
     *   pm_break_window: array{start:string,end:string},
     *   agents: array<int,array<string,mixed>>,
     *   agent_site_by_id: array<int,string|null>,
     *   agent_name_by_id: array<int,string>,
     *   remote_work_intervals_by_agent: array<int,array<int,array{start:string,end:string}>>,
     *   fixed_activities: array<int,array<string,mixed>>,
     *   generated_virtual_offers: array<int,string>,
     *   offer_groups: list<array{name:string,mixed:string,members:list<string>,prefer_mixed:bool}>,
     *   offer_equity_buckets: array<string,string>,
     *   offer_groups_meta: list<array{name:string,mixed:string,members:list<string>}>,
     *   diagnostics: array<string,mixed>
     * }
     */
    public function build(
        FrozenTime $dateToCalc,
        WfmSetting $settings,
        int $scenarioId,
        array $options = [],
        ?EquityScoresProviderInterface $equityProvider = null,
        array $equityContext = [],
        array $additionalFixedActivities = [],
    ): array {
        $diagnostics = [
            'excluded_agents' => [],
            'warnings' => [],
            'missing_scenario_series' => [],
            'fixed_activities_outside_work_hours' => [],
            'fixed_activities_remote_work_incompatibilities' => [],
        ];

        // --- Need curve (scénario + fallback live) + groupes d'offres ---
        $needBuilt = $this->buildNeedCurve($dateToCalc, $settings, $scenarioId, $diagnostics);
        $needCurve = $needBuilt['need_curve'];
        $offerGroupsPayload = $needBuilt['offer_groups'];
        $offerEquityBuckets = $needBuilt['offer_to_bucket'];
        $offerGroupsMeta = $needBuilt['groups_meta'];

        // --- Paramètres journée ---
        $workdayStart = $this->normalizeTime((string)$settings->day_start_time, '09:00:00');
        $workdayEnd = $this->normalizeTime((string)$settings->day_end_time, '17:00:00');
        $strictWork = ($settings->strict_work_hours === null) ? true : (bool)$settings->strict_work_hours;
        $enableAmPmBreaks = ($settings->enable_am_pm_breaks === null) ? true : (bool)$settings->enable_am_pm_breaks;
        $forbidMiddaySingletons = ($settings->forbid_midday_singletons === null) ? false : (bool)$settings->forbid_midday_singletons;

        $amBreakWindow = [
            'start' => $this->normalizeTime($settings->am_pause_start_time ?? null, '10:00:00'),
            'end' => $this->normalizeTime($settings->am_pause_end_time ?? null, '11:00:00'),
        ];
        $pmBreakWindow = [
            'start' => $this->normalizeTime($settings->pm_pause_start_time ?? null, '15:00:00'),
            'end' => $this->normalizeTime($settings->pm_pause_end_time ?? null, '16:00:00'),
        ];
        $lunchWindow = [
            'start' => $this->normalizeTime($settings->lunch_start_time ?? null, '12:00:00'),
            'end' => $this->normalizeTime($settings->lunch_end_time ?? null, '14:00:00'),
        ];
        $breakDurationMinutes = (int)($settings->am_pause_duration_minutes ?? 15);
        $lunchDurationMinutes = (int)($settings->lunch_duration_minutes ?? 60);

        // --- Agents ---
        $agentIds = array_map('intval', $options['agent_ids'] ?? []);
        $buildAgents = $this->buildAgents(
            $dateToCalc,
            $needCurve,
            $scenarioId,
            $strictWork,
            $diagnostics,
            $agentIds,
            $offerGroupsMeta,
        );
        $agentsForJson = $buildAgents['agents'];
        $agentSiteById = $buildAgents['agent_site_by_id'];
        $agentNameById = $buildAgents['agent_name_by_id'];

        // --- Télétravail (ranges remote_work) injecté dans agents ---
        $remoteIntervalsByAgent = $this->remoteWorkIntervalsService->getIntervalsForAgents($dateToCalc, $agentsForJson);
        foreach ($agentsForJson as &$ag) {
            $aid = (int)($ag['id'] ?? 0);
            $ag['remote_work_intervals'] = (!empty($aid) && !empty($remoteIntervalsByAgent[$aid]))
                ? $remoteIntervalsByAgent[$aid]
                : null;
        }
        unset($ag);

        // Activités fixes : construites par FixedActivitiesBuilderService (Passe 1).
        // Ici on ne fait que fusionner d'éventuels blocs additionnels pour la Passe 2.
        $fixedActivities = [];
        if (!empty($additionalFixedActivities)) {
            $fixedActivities = array_values($additionalFixedActivities);
        }

        $equityScores = null;
        if ($equityProvider !== null && !empty($fixedActivities)) {
            $equityScores = $equityProvider->getFixedActivitiesEquityScores(
                $agentsForJson,
                $fixedActivities,
                $dateToCalc,
                $equityContext,
            );
        }

        return [
            'date' => $dateToCalc,
            'settings' => $settings,
            'scenario_id' => $scenarioId,
            'options' => $options,
            'need_curve' => $needCurve,
            'workday_start_time' => $workdayStart,
            'workday_end_time' => $workdayEnd,
            'strict_work_hours' => $strictWork,
            'enable_am_pm_breaks' => $enableAmPmBreaks,
            'forbid_midday_singletons' => $forbidMiddaySingletons,
            'lunch_window' => $lunchWindow,
            'lunch_duration_minutes' => $lunchDurationMinutes,
            'break_duration_minutes' => $breakDurationMinutes,
            'am_break_window' => $amBreakWindow,
            'pm_break_window' => $pmBreakWindow,
            'agents' => $agentsForJson,
            'agent_site_by_id' => $agentSiteById,
            'agent_name_by_id' => $agentNameById,
            'remote_work_intervals_by_agent' => $remoteIntervalsByAgent,
            'fixed_activities' => $fixedActivities,
            'generated_virtual_offers' => [],
            'offer_groups' => $offerGroupsPayload,
            'offer_equity_buckets' => $offerEquityBuckets,
            'offer_groups_meta' => $offerGroupsMeta,
            'fixed_equity_scores' => $equityScores,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string,mixed> $diagnostics (par référence)
     * @return array{
     *   need_curve: array<string,array<string,int>>,
     *   offer_groups: list<array{name:string,mixed:string,members:list<string>,prefer_mixed:bool}>,
     *   offer_to_bucket: array<string,string>,
     *   groups_meta: list<array{name:string,mixed:string,members:list<string>}>
     * }
     */
    private function buildNeedCurve(FrozenTime $dateToCalc, WfmSetting $settings, int $scenarioId, array &$diagnostics): array
    {
        $forecastService = new ForecastService();
        $calculatorService = new WfmCalculatorService($forecastService);

        if ($scenarioId <= 0) {
            $needCurve = $calculatorService->generateNeedCurve($dateToCalc, $settings);
        } else {
            $ScenarioLinks = $this->fetchTable('ForecastScenariosOffers');
            $Offers = $this->fetchTable('Offers');
            $WfmScenarioService = new WfmScenarioService($forecastService, $calculatorService);

            $needCurve = [];
            $links = $ScenarioLinks->find()->where(['scenario_id' => $scenarioId])->all();
            $missingOffers = [];
            foreach ($links as $link) {
                $offer = $Offers->get((int)$link->offer_id);
                $series = $WfmScenarioService->getSeries($scenarioId, (int)$offer->id, $dateToCalc, 'need');
                if ($series && !empty($series['data'])) {
                    $needCurve[(string)$offer->name] = $series['data'];
                } else {
                    $missingOffers[] = (string)$offer->name;
                }
            }

            if (!empty($missingOffers)) {
                $diagnostics['missing_scenario_series'] = $missingOffers;
                $diagnostics['warnings'][] = [
                    'type' => 'scenario_missing_series',
                    'message' => 'Séries manquantes pour: ' . implode(', ', $missingOffers) . ' — fallback calcul live.',
                ];
                $liveCurve = $calculatorService->generateNeedCurve($dateToCalc, $settings);
                foreach ($missingOffers as $offerName) {
                    if (isset($liveCurve[$offerName])) {
                        $needCurve[$offerName] = $liveCurve[$offerName];
                    }
                }
                if (empty($needCurve)) {
                    $needCurve = $liveCurve;
                }
            }
        }

        // Groupes d'offres : modes members / group (Largest Remainder) + payload solveur
        return $this->offerGroupsNeedService->applyToNeedCurve($needCurve);
    }

    /**
     * @param array<string,array<string,int>> $needCurve
     * @param array<string,mixed> $diagnostics (par référence)
     * @param array<int,int> $agentIds Filtre optionnel (sélection manuelle d'agents) ; vide = aucun filtre
     * @param list<array{name:string,mixed:string,members:list<string>}> $offerGroupsMeta
     * @return array{agents:array<int,array<string,mixed>>,agent_site_by_id:array<int,string|null>,agent_name_by_id:array<int,string>}
     */
    private function buildAgents(
        FrozenTime $dateToCalc,
        array $needCurve,
        int $scenarioId,
        bool $strictWork,
        array &$diagnostics,
        array $agentIds = [],
        array $offerGroupsMeta = [],
    ): array {
        $Users = $this->fetchTable('Users');
        $dow = (int)$dateToCalc->format('N');

        $agentsQuery = $Users->find('activeInPeriod', [
                'period' => [
                    'begin' => $dateToCalc,
                    'end' => $dateToCalc,
                ]
            ])
            ->contain([
                'Skills.Offers',
                'UserAvailabilities' => function ($q) use ($dow) {
                    return $q->where(['day_of_week' => $dow]);
                },
                'Sites',
            ]);

        $totalActiveCount = null;
        if (!empty($agentIds)) {
            $totalActiveCount = (clone $agentsQuery)->count();
            $agentsQuery->where(['Users.id IN' => $agentIds]);
        }

        $agentsList = $agentsQuery->all();

        if ($totalActiveCount !== null) {
            $diagnostics['manual_agent_filter_excluded_count'] = max(0, $totalActiveCount - count($agentsList));
        }

        $agentsForJson = [];
        $agentSiteById = [];
        $agentNameById = [];

        foreach ($agentsList as $agent) {
            $agentName = trim((string)($agent->first_name ?? '') . ' ' . (string)($agent->last_name ?? ''));
            $agentSite = isset($agent->site) ? (string)$agent->site->name : null;

            if (empty($agent->user_availabilities)) {
                $diagnostics['excluded_agents'][] = [
                    'id' => (int)$agent->id,
                    'name' => $agentName !== '' ? $agentName : 'Nom inconnu',
                    'site' => $agentSite ?? 'Site inconnu',
                    'reason' => 'Aucune disponibilité pour cette date',
                    'availability' => null,
                    'skills' => [],
                ];
                continue;
            }

            $availability = $agent->user_availabilities[0];
            $effective = $this->agentAvailabilityService->calculateEffectiveAvailability($agent, $dateToCalc, $availability);
            if ($effective === null) {
                $diagnostics['excluded_agents'][] = [
                    'id' => (int)$agent->id,
                    'name' => $agentName !== '' ? $agentName : 'Nom inconnu',
                    'site' => $agentSite ?? 'Site inconnu',
                    'reason' => 'Agent en congé complet pour cette date',
                    'availability' => [
                        'start' => $availability->availability_start_time ?? null,
                        'end' => $availability->availability_end_time ?? null,
                    ],
                    'skills' => [],
                ];
                continue;
            }

            $availabilityStartNorm = $effective['start'];
            $availabilityEndNorm = $effective['end'];

            // Skills valides
            $skills = [];
            foreach (($agent->skills ?? []) as $skill) {
                if (method_exists($skill, 'isValidForDate') && !$skill->isValidForDate($dateToCalc)) {
                    continue;
                }
                $name = $skill->offer->name ?? null;
                if (is_string($name) && $name !== '') {
                    $skills[] = $name;
                }
            }
            $skills = array_values(array_unique($skills));

            // En mode scénario, on garde même sans skill forecastable (pour fixes injectées plus tard).
            // En mode live, si l'agent n'a aucune skill pertinente du tout, on l'exclut.
            if ($scenarioId <= 0) {
                if (empty($skills)) {
                    $diagnostics['excluded_agents'][] = [
                        'id' => (int)$agent->id,
                        'name' => $agentName !== '' ? $agentName : 'Nom inconnu',
                        'site' => $agentSite ?? 'Site inconnu',
                        'reason' => 'Aucune compétence pertinente',
                        'availability' => [
                            'start' => $availabilityStartNorm,
                            'end' => $availabilityEndNorm,
                        ],
                        'skills' => [],
                    ];
                    continue;
                }
            }

            // Garder si skill ∈ need_curve OU skill = mixte d'un groupe dont un membre est dans need_curve
            $hasForecastableSkill = $this->offerGroupsNeedService->agentHasRelevantSkill(
                $skills,
                $needCurve,
                $offerGroupsMeta,
            );
            if ($scenarioId <= 0 && !$hasForecastableSkill) {
                // En live: exclure si aucune skill ne matche les offres de needCurve
                $diagnostics['excluded_agents'][] = [
                    'id' => (int)$agent->id,
                    'name' => $agentName !== '' ? $agentName : 'Nom inconnu',
                    'site' => $agentSite ?? 'Site inconnu',
                    'reason' => 'Aucune compétence pour les offres forecastables',
                    'availability' => [
                        'start' => $availabilityStartNorm,
                        'end' => $availabilityEndNorm,
                    ],
                    'skills' => $skills,
                ];
                continue;
            }

            $agentSiteById[(int)$agent->id] = $agentSite;
            $agentNameById[(int)$agent->id] = $agentName !== '' ? $agentName : 'Nom inconnu';

            $agentJson = [
                'id' => (int)$agent->id,
                'name' => $agentName !== '' ? $agentName : null,
                'site' => $agentSite,
                'skills' => $skills,
                'availability_start_time' => $availabilityStartNorm,
                'availability_end_time' => $availabilityEndNorm,
            ];

            // Fin anticipée: si non strict, on doit fournir earliest_end_time (borne minimale),
            // sinon le solveur considère la fin de disponibilité comme minimale => fin forcée.
            if (!$strictWork) {
                $earliestRaw = $availability->earliest_end_time ?? null;
                
                // CORRECTION : On cherche la vraie fin de contrat, pas la fin du premier créneau
                // $availabilityEndNorm peut être tronqué (ex: 11:00 si réunion 11h-12h)
                // On utilise real_window_end si disponible, sinon la valeur brute en DB
                $trueContractEnd = $effective['real_window_end'] 
                    ?? $this->normalizeTime($availability->availability_end_time ?? '23:59:00');
                
                if ($earliestRaw) {
                    $earliest = $this->normalizeTime($earliestRaw);
                    // On compare avec la vraie fin, pas la fin tronquée
                    if ($earliest <= $trueContractEnd) {
                        $agentJson['earliest_end_time'] = $earliest;
                    }
                }

                // Fallback (cohérent avec l'existant): plafonner par défaut à 16:30
                $hardcodedEarliest = '16:30:00';
                // Ici aussi, on utilise la vraie fin pour ne pas abaisser artificiellement le seuil
                $candidate = $trueContractEnd < $hardcodedEarliest ? $trueContractEnd : $hardcodedEarliest;
                if (!isset($agentJson['earliest_end_time']) || $agentJson['earliest_end_time'] > $candidate) {
                    $agentJson['earliest_end_time'] = $candidate;
                }
            }

            $agentsForJson[] = $agentJson;
        }

        return [
            'agents' => $agentsForJson,
            'agent_site_by_id' => $agentSiteById,
            'agent_name_by_id' => $agentNameById,
        ];
    }

    private function normalizeTime(mixed $t, string $default = '00:00:00'): string
    {
        if ($t instanceof \DateTimeInterface) {
            return $t->format('H:i:s');
        }
        if (!$t || !is_string($t)) {
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


