<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\ForecastService;
use App\Service\AgentsAfterFixedActivitiesService;
use App\Service\FixedActivitiesBuilderService;
use App\Service\PlanningDayHistoryService;
use App\Service\ScheduleProblemBuilderService;
use App\Service\WfmCalculatorService;
use App\Service\WfmScenarioService;
use App\Service\Equity\SingleDayEquityScoresProvider;
use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\I18n\FrozenTime;
use Cake\Log\Log;
use DateTime;
use DateTimeInterface;
use Exception;
use Throwable;

/**
 * Schedules Controller
 * Gère la génération et l'affichage des plannings.
 */
class SchedulesController extends AppController
{
    /**
     * Page principale pour lancer une génération de planning
     *
     * @return \Cake\Http\Response|null
     */
    public function generate()
    {
        $this->Authorization->authorize(new \App\Resource\SchedulesResource(), 'generate');
        set_time_limit(0);

        // --- 1. Préparation (GET) ---
        $WfmSettingsTable = $this->fetchTable('WfmSettings');
        $wfmSettingsList = $WfmSettingsTable->find('list', [
            'keyField' => 'id',
            'valueField' => 'name',
        ])->all();

        // Liste des scénarios disponibles :
        // - uniquement les scénarios terminés (status = completed)
        // - et publiés au moins une fois (enregistrement dans forecast_scenario_publications)
        $ForecastScenarios = $this->fetchTable('ForecastScenarios');
        $scenariosList = $ForecastScenarios->find('list', [
                'keyField' => 'id',
                'valueField' => function ($row) {
                    return sprintf(
                        '%s (%s → %s)',
                        (string)$row->name,
                        (string)$row->start_date,
                        (string)$row->end_date
                    );
                },
            ])
            ->matching('ForecastScenarioPublications')
            ->where(['ForecastScenarios.status' => 'completed'])
            ->distinct(['ForecastScenarios.id'])
            ->orderDesc('ForecastScenarios.modified')
            ->toArray();

        $this->set(compact('wfmSettingsList', 'scenariosList'));

        // --- 2. Lancement (POST) ---
        if ($this->request->is('post')) {
            $data = $this->request->getData();

            $dateToCalc = new FrozenTime($data['date']);
            $settingsId = $data['wfm_setting_id'];
            $settings = $WfmSettingsTable->get($settingsId, ['contain' => ['PauseOffers', 'LunchOffers']]);

            $this->log('=== DÉBUT GÉNÉRATION PLANNING ===', 'debug');
            $this->log('Date: ' . $dateToCalc->format('Y-m-d'), 'debug');
            $this->log("Settings ID: {$settingsId}", 'debug');

            // Options d'exécution
            $scenarioId = (int)($data['scenario_id'] ?? 0);
            $debugSolvers = !empty($data['debug_solvers'] ?? false);
            $ignoreFixedActivities = !empty($data['ignore_fixed_activities'] ?? false);
            $ignoreForecastSolver = !empty($data['ignore_forecast_solver'] ?? false);

            // --- Préparation unifiée (service partagé avec le worker multi-jours) ---
            $builder = new ScheduleProblemBuilderService();
            $equityProvider = new SingleDayEquityScoresProvider();
            $build = $builder->build(
                $dateToCalc,
                $settings,
                $scenarioId,
                [
                    'ignore_fixed_activities' => $ignoreFixedActivities,
                    'ignore_forecast_solver' => $ignoreForecastSolver,
                    'debug_solvers' => $debugSolvers,
                ],
                $equityProvider,
            );

            // Variables alignées (conservées pour compat avec le reste de la méthode)
            $needCurve = $build['need_curve'];
            $workdayStart = $build['workday_start_time'];
            $workdayEnd = $build['workday_end_time'];
            $strictWork = $build['strict_work_hours'];
            $enableAmPmBreaks = $build['enable_am_pm_breaks'];
            $forbidMiddaySingletons = $build['forbid_midday_singletons'];
            $amBreakWindow = $build['am_break_window'];
            $pmBreakWindow = $build['pm_break_window'];
            $lunchWindow = $build['lunch_window'];
            $breakDurationMinutes = $build['break_duration_minutes'];
            $lunchDurationMinutes = $build['lunch_duration_minutes'];
            $agentsForJson = $build['agents'];
            $agentSiteById = $build['agent_site_by_id'];
            $agentNameById = $build['agent_name_by_id'];
            $remoteWorkIntervalsByAgent = $build['remote_work_intervals_by_agent'];
            $fixedActivities = $build['fixed_activities'];
            $generatedVirtualOffers = $build['generated_virtual_offers'];
            $diagnostics = $build['diagnostics'];
            $equityScores = $build['fixed_equity_scores'] ?? [];
            // Groupes d'offres (étape 4 via ScheduleProblemBuilderService) :
            // need_curve déjà splité (members/group) + mixte forcé à 0 ;
            // agents déjà élargis aux compétences mixtes (OfferGroupsNeedService).
            // Pas de mutation equity_state_json sur ce chemin 1-jour (contrairement au lot).
            $offerGroupsPayload = $build['offer_groups'] ?? [];

            // Format des needs pour le solver
            $needsForJson = $needCurve;
            $offersNames = array_keys($needsForJson);
            $priorityOffers = [];
            $equityOffers = [];

            if (!empty($offerGroupsPayload)) {
                $this->log(
                    'Offer groups actifs: ' . implode(', ', array_map(
                        static fn(array $g): string => (string)($g['name'] ?? ''),
                        $offerGroupsPayload
                    )),
                    'debug'
                );
            }

            // Log diagnostics synthétiques (alignement: UI + logs)
            if (!empty($diagnostics['warnings'])) {
                foreach ($diagnostics['warnings'] as $w) {
                    $this->log('[Diagnostics] ' . (string)($w['message'] ?? json_encode($w)), 'warning');
                }
            }

            // --- Bloc historique de préparation: conservé mais désactivé (remplacé par services partagés) ---
            if (false) {
            // --- 3. Calcul du Besoin (via scénario si fourni) ---
            $forecastService = new ForecastService();
            $calculatorService = new WfmCalculatorService($forecastService);

            $needCurve = [];
            $scenarioId = (int)($data['scenario_id'] ?? 0);
            if ($scenarioId > 0) {
                $this->log("Utilisation du scénario ID={$scenarioId}", 'debug');
                $ScenarioLinks = $this->fetchTable('ForecastScenariosOffers');
                $Offers = $this->fetchTable('Offers');
                $WfmScenarioService = new WfmScenarioService($forecastService, $calculatorService);

                $links = $ScenarioLinks->find()->where(['scenario_id' => $scenarioId])->all();

                $missingOffers = [];
                foreach ($links as $link) {
                    $offer = $Offers->get($link->offer_id);
                    $series = $WfmScenarioService->getSeries($scenarioId, (int)$offer->id, $dateToCalc, 'need');
                    
                    // DIAGNOSTIC : Vérifier ce qui est récupéré depuis le scénario
                    if (in_array($offer->name, ['Employeurs', 'TI-AE'])) {
                        $this->log("=== DIAG [RÉCUPÉRATION SCÉNARIO] {$offer->name} ===", 'debug');
                        $this->log("  Offer ID: {$offer->id}", 'debug');
                        $this->log("  Date: " . $dateToCalc->format('Y-m-d'), 'debug');
                        if ($series) {
                            $this->log("  Série trouvée: OUI", 'debug');
                            $this->log("  startTime: " . ($series['startTime'] ?? 'N/A'), 'debug');
                            $this->log("  endTime: " . ($series['endTime'] ?? 'N/A'), 'debug');
                            $this->log("  stepSeconds: " . ($series['stepSeconds'] ?? 'N/A'), 'debug');
                            $this->log("  Nombre de clés dans data: " . count($series['data'] ?? []), 'debug');
                            if (!empty($series['data'])) {
                                $dataKeys = array_keys($series['data']);
                                $dataValues = array_values($series['data']);
                                $this->log("  Premières clés: " . implode(', ', array_slice($dataKeys, 0, 5)), 'debug');
                                $this->log("  Premières valeurs: " . implode(', ', array_slice($dataValues, 0, 5)), 'debug');
                                $this->log("  Total: " . array_sum($series['data']), 'debug');
                            } else {
                                $this->log("  ❌ data est vide", 'debug');
                            }
                        } else {
                            $this->log("  ❌ Série NON trouvée dans ScenarioSeries", 'debug');
                        }
                    }
                    
                    if ($series && !empty($series['data'])) {
                        $needCurve[$offer->name] = $series['data'];
                    } else {
                        $missingOffers[] = $offer->name;
                    }
                }

                if (!empty($missingOffers)) {
                    $this->log('Séries manquantes pour: ' . implode(', ', $missingOffers) . ' — fallback calcul live pour ces offres.', 'warning');
                    // Calcul live global puis filtrage
                    $liveCurve = $calculatorService->generateNeedCurve($dateToCalc, $settings);
                    foreach ($missingOffers as $offerName) {
                        if (isset($liveCurve[$offerName])) {
                            $needCurve[$offerName] = $liveCurve[$offerName];
                        }
                    }
                    if (empty($needCurve)) {
                        $needCurve = $liveCurve; // dernier recours
                    }
                }
            } else {
                $needCurve = $calculatorService->generateNeedCurve($dateToCalc, $settings);
            }

            $this->log('=== NEED CURVE GÉNÉRÉE ===', 'debug');
            $this->log('Offres: ' . implode(', ', array_keys($needCurve)), 'debug');

            // Vérifier que needCurve n'est pas vide
            if (empty($needCurve)) {
                $this->log('❌ ERREUR: needCurve est VIDE !', 'error');
                $this->Flash->error('Aucun besoin calculé pour cette date. Vérifiez les prévisions.');

                return $this->redirect(['action' => 'generate']);
            }

            foreach ($needCurve as $offer => $intervals) {
                $total = array_sum($intervals);
                $this->log("  - {$offer}: {$total} agents-intervalles", 'debug');
            }
            
            // DIAGNOSTIC : Vérifier les besoins pour Employeurs et TI-AE
            $diagnosticOffers = ['Employeurs', 'TI-AE'];
            foreach ($diagnosticOffers as $diagOffer) {
                if (isset($needCurve[$diagOffer])) {
                    $diagData = $needCurve[$diagOffer];
                    $diagTotal = array_sum($diagData);
                    $diagKeys = array_keys($diagData);
                    $this->log("=== DIAG [SCÉNARIO] {$diagOffer} ===", 'debug');
                    $this->log("  Total: {$diagTotal} agents-intervalles", 'debug');
                    $this->log("  Nombre de créneaux: " . count($diagData), 'debug');
                    $this->log("  Premières clés: " . implode(', ', array_slice($diagKeys, 0, 5)), 'debug');
                    $this->log("  Exemples valeurs: " . json_encode(array_slice($diagData, 0, 5, true)), 'debug');
                } else {
                    $this->log("=== DIAG [SCÉNARIO] {$diagOffer} ===", 'debug');
                    $this->log("  ❌ OFFRE ABSENTE de needCurve", 'debug');
                }
            }

            // --- 4. Paramètres solveur (définir avant la construction des agents) ---
            $workdayStart = $this->normalizeTime((string)$settings->day_start_time, '09:00:00');
            $workdayEnd = $this->normalizeTime((string)$settings->day_end_time, '17:00:00');
            $strictWorkRaw = $settings->strict_work_hours ?? null;
            $strictWork = $strictWorkRaw === null ? true : (bool)$strictWorkRaw;
            $enableAmPmBreaksRaw = $settings->enable_am_pm_breaks ?? null;
            $enableAmPmBreaks = $enableAmPmBreaksRaw === null ? true : (bool)$enableAmPmBreaksRaw;
            $forbidMiddaySingletonsRaw = $settings->forbid_midday_singletons ?? null;
            $forbidMiddaySingletons = $forbidMiddaySingletonsRaw === null ? false : (bool)$forbidMiddaySingletonsRaw;
            $debugSolvers = !empty($data['debug_solvers'] ?? false);

            // --- 5. Préparation des agents ---
            $AgentsTable = $this->fetchTable('Users');
            $agentsList = $AgentsTable->find()
                ->contain([
                    // Inclure toutes les offres (forecastables ou non) pour récupérer la skill de base des activités fixes
                    'Skills.Offers',
                    'UserAvailabilities' => function ($q) use ($dateToCalc) {
                        $dayOfWeek_N = (int)$dateToCalc->format('N');

                        return $q->where(['day_of_week' => $dayOfWeek_N]);
                    },
                    'Sites',
                ])
                ->all();

            $this->log('=== AGENTS ===', 'debug');
            $this->log('Agents trouvés: ' . count($agentsList), 'debug');

            $agentsForJson = [];
            $agentSiteById = [];
            $agentNameById = []; // Mémoriser les noms des agents
            $excludedAgentsBeforeSolver = []; // Collecter les agents exclus avec leurs raisons
            foreach ($agentsList as $agent) {
                $agentName = trim(($agent->first_name ?? '') . ' ' . ($agent->last_name ?? ''));
                $agentSite = isset($agent->site) ? (string)$agent->site->name : null;
                
                if (empty($agent->user_availabilities)) {
                    // Filtrer les compétences valides pour la date du planning
                    $validSkillsForExcluded = array_filter(
                        array_map(function ($skill) use ($dateToCalc) {
                            if (!$skill->isValidForDate($dateToCalc)) {
                                return null;
                            }
                            return $skill->offer->name ?? null;
                        }, $agent->skills)
                    );
                    $excludedAgentsBeforeSolver[] = [
                        'id' => (int)$agent->id,
                        'name' => $agentName ?: 'Nom inconnu',
                        'site' => $agentSite ?? 'Site inconnu',
                        'reason' => 'Aucune disponibilité pour cette date',
                        'availability' => null,
                        'skills' => array_values($validSkillsForExcluded),
                    ];
                    continue;
                }

                $availability = $agent->user_availabilities[0];

                // --- PRÉ-TRAITEMENT : Calculer la disponibilité effective (congés partiels) ---
                $effectiveAvailability = $this->_calculateEffectiveAvailability($agent, $dateToCalc, $availability);
                if ($effectiveAvailability === null) {
                    // Agent en congé complet, exclure
                    $excludedAgentsBeforeSolver[] = [
                        'id' => (int)$agent->id,
                        'name' => $agentName ?: 'Nom inconnu',
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
                // Utiliser la disponibilité effective au lieu de la disponibilité contrat
                $availabilityStartNorm = $effectiveAvailability['start'];
                $availabilityEndNorm = $effectiveAvailability['end'];

                // --- CORRECTION : Ignore si pas de compétences pertinentes ---
                // En mode scénario, on doit aussi considérer les compétences pour les activités fixes
                // car elles seront injectées après. En mode live, toutes les offres forecastables sont déjà présentes.
                // Filtrer les compétences valides pour la date du planning
                $relevantSkills = array_map(function ($skill) use ($dateToCalc) {
                    // Vérifier d'abord si la compétence est valide pour cette date
                    if (!$skill->isValidForDate($dateToCalc)) {
                        return null; // Compétence non valide pour cette date
                    }
                    // Vérifie si l'offre associée existe (elle devrait car filtrée avant)
                    return $skill->offer->name ?? null;
                }, $agent->skills);
                // Enlève les nulls potentiels
                $relevantSkills = array_filter($relevantSkills);

                // Vérifier si l'agent a des compétences pour les offres forecastables actuelles
                $hasForecastableSkill = false;
                foreach ($relevantSkills as $skillName) {
                    if (isset($needCurve[$skillName])) {
                        $hasForecastableSkill = true;
                        break;
                    }
                }

                // Si pas de compétence pour les offres forecastables, vérifier s'il y a des activités fixes
                // qui pourraient utiliser cet agent (il sera enrichi plus tard)
                if (!$hasForecastableSkill) {
                    // En mode scénario, on garde quand même l'agent s'il a des compétences
                    // car les activités fixes seront injectées après et pourront l'utiliser
                    // En mode live, toutes les offres sont déjà présentes, donc ce cas ne devrait pas arriver
                    if ($scenarioId > 0) {
                        // Mode scénario : garder l'agent s'il a des compétences (pour activités fixes futures)
                        if (empty($relevantSkills)) {
                            $this->log("Agent ID {$agent->id} ignoré car aucune compétence.", 'debug');
                            $excludedAgentsBeforeSolver[] = [
                                'id' => (int)$agent->id,
                                'name' => $agentName ?: 'Nom inconnu',
                                'site' => $agentSite ?? 'Site inconnu',
                                'reason' => 'Aucune compétence',
                                'availability' => [
                                    'start' => $availability->availability_start_time ?? null,
                                    'end' => $availability->availability_end_time ?? null,
                                ],
                                'skills' => [],
                            ];
                            continue;
                        }
                        // Sinon, on garde l'agent pour les activités fixes
                    } else {
                        // Mode live : exclure si pas de compétence pour les offres forecastables
                        if (empty($relevantSkills)) {
                            $this->log("Agent ID {$agent->id} ignoré car aucune compétence 'forecastable'.", 'debug');
                            $excludedAgentsBeforeSolver[] = [
                                'id' => (int)$agent->id,
                                'name' => $agentName ?: 'Nom inconnu',
                                'site' => $agentSite ?? 'Site inconnu',
                                'reason' => 'Aucune compétence pertinente',
                                'availability' => [
                                    'start' => $availability->availability_start_time ?? null,
                                    'end' => $availability->availability_end_time ?? null,
                                ],
                                'skills' => [],
                            ];
                            continue;
                        }
                    }
                }
                // --- FIN CORRECTION ---

                // Filtrer les compétences valides pour la date du planning
                $skills = array_filter(
                    array_map(
                        function ($skill) use ($dateToCalc) {
                            // Vérifier si la compétence est valide pour cette date
                            if (!$skill->isValidForDate($dateToCalc)) {
                                return null; // Compétence non valide pour cette date
                            }
                            return $skill->offer->name;
                        },
                        $agent->skills,
                    )
                );
                // Réindexer le tableau après filtrage
                $skills = array_values($skills);

                // Mémorise le site et le nom pour enrichissement des skills virtuelles et pour le rapport
                $agentSiteById[(int)$agent->id] = isset($agent->site) ? (string)$agent->site->name : null;
                $agentNameById[(int)$agent->id] = $agentName ?: 'Nom inconnu';

                // --- 6.5. Filtrer les agents avec des contraintes impossibles ---
                // Vérifier que la disponibilité n'est pas invalide (00:00:00 - 00:00:00 ou start >= end)
                if ($availabilityStartNorm === '00:00:00' && $availabilityEndNorm === '00:00:00') {
                    $this->log("Agent ID {$agent->id} ignoré : disponibilité invalide (00:00:00 - 00:00:00).", 'debug');
                    $excludedAgentsBeforeSolver[] = [
                        'id' => (int)$agent->id,
                        'name' => $agentName ?: 'Nom inconnu',
                        'site' => $agentSite ?? 'Site inconnu',
                        'reason' => 'Disponibilité invalide (00:00:00 - 00:00:00)',
                        'availability' => [
                            'start' => $availabilityStartNorm,
                            'end' => $availabilityEndNorm,
                        ],
                        'skills' => array_values($skills),
                    ];
                    continue;
                }
                if ($availabilityStartNorm >= $availabilityEndNorm) {
                    $this->log("Agent ID {$agent->id} ignoré : disponibilité invalide ({$availabilityStartNorm} >= {$availabilityEndNorm}).", 'debug');
                    $excludedAgentsBeforeSolver[] = [
                        'id' => (int)$agent->id,
                        'name' => $agentName ?: 'Nom inconnu',
                        'site' => $agentSite ?? 'Site inconnu',
                        'reason' => "Disponibilité invalide ({$availabilityStartNorm} >= {$availabilityEndNorm})",
                        'availability' => [
                            'start' => $availabilityStartNorm,
                            'end' => $availabilityEndNorm,
                        ],
                        'skills' => array_values($skills),
                    ];
                    continue;
                }
                // --- FIN FILTRAGE ---

                $agentJson = [
                    'id' => (int)$agent->id,
                    'skills' => array_values($skills),
                    'availability_start_time' => $availabilityStartNorm,
                    'availability_end_time' => $availabilityEndNorm,
                ];

                // Fin la plus tôt autorisée (souplesse), uniquement si non strict
                if (!$strictWork) {
                    // Bon code: utiliser la valeur métier si saisie et cohérente
                    $earliestRaw = $availability->earliest_end_time ?? null;
                    if ($earliestRaw) {
                        $earliest = $this->normalizeTime($earliestRaw);
                        if ($earliest <= $availabilityEndNorm) {
                            $agentJson['earliest_end_time'] = $earliest;
                        }
                    }

                    // DIRTY PATCH (à supprimer ensuite): plafonner à 16:30 par défaut
                    $hardcodedEarliest = '16:30:00';
                    $candidate = $availabilityEndNorm < $hardcodedEarliest ? $availabilityEndNorm : $hardcodedEarliest;
                    if (!isset($agentJson['earliest_end_time']) || $agentJson['earliest_end_time'] > $candidate) {
                        $agentJson['earliest_end_time'] = $candidate;
                    }
                }

                $agentsForJson[] = $agentJson;
            }

            if (empty($agentsForJson)) {
                $this->log('❌ ERREUR: Aucun agent disponible !', 'error');
                $this->Flash->error('Aucun agent disponible pour cette date.');

                return $this->redirect(['action' => 'generate']);
            }

            if (!empty($agentsForJson)) {
                $firstAgent = $agentsForJson[0];
                $this->log("Premier agent: ID={$firstAgent['id']}, Skills=" . implode(',', $firstAgent['skills']), 'debug');
            }

            // --- 1.11 Diagnostic : Agents avec disponibilités très courtes ---
            $breakDurationSlots = (int)ceil(($settings->am_pause_duration_minutes ?? 15) / 15);
            $lunchDurationSlots = (int)ceil(($settings->lunch_duration_minutes ?? 60) / 15);
            $minWorkSlots = 4; // Minimum de travail requis
            
            foreach ($agentsForJson as $agent) {
                $availStart = $agent['availability_start_time'] ?? '00:00:00';
                $availEnd = $agent['availability_end_time'] ?? '23:59:59';
                
                // Calculer le nombre de créneaux disponibles
                $current = new \Cake\I18n\FrozenTime($availStart);
                $end = new \Cake\I18n\FrozenTime($availEnd);
                $availableSlots = 0;
                while ($current < $end) {
                    $availableSlots++;
                    $current = $current->addMinutes(15);
                }
                
                $requiredSlots = $minWorkSlots;
                $canPlaceAmBreak = false;
                $canPlaceLunch = false;
                $canPlacePmBreak = false;
                $issue = null;
                
                if ($enableAmPmBreaks) {
                    $requiredSlots += $breakDurationSlots;
                    // Vérifier si la pause AM peut être placée
                    $amBreakStart = $this->normalizeTime($settings->am_pause_start_time ?? '10:00:00');
                    $amBreakEnd = $this->normalizeTime($settings->am_pause_end_time ?? '11:00:00');
                    if ($availStart < $amBreakEnd && $availEnd > $amBreakStart) {
                        $canPlaceAmBreak = true;
                    }
                }
                
                if ($lunchDurationSlots > 0) {
                    $requiredSlots += $lunchDurationSlots;
                    // Vérifier si le déjeuner peut être placé
                    $lunchStart = $this->normalizeTime($settings->lunch_start_time ?? '12:00:00');
                    $lunchEnd = $this->normalizeTime($settings->lunch_end_time ?? '14:00:00');
                    if ($availStart < $lunchEnd && $availEnd > $lunchStart) {
                        $canPlaceLunch = true;
                    }
                }
                
                if ($enableAmPmBreaks) {
                    $requiredSlots += $breakDurationSlots;
                    // Vérifier si la pause PM peut être placée
                    $pmBreakStart = $this->normalizeTime($settings->pm_pause_start_time ?? '15:00:00');
                    $pmBreakEnd = $this->normalizeTime($settings->pm_pause_end_time ?? '16:00:00');
                    if ($availStart < $pmBreakEnd && $availEnd > $pmBreakStart) {
                        $canPlacePmBreak = true;
                    } else {
                        $issue = 'Impossible de placer pause PM (nécessite travail après ' . $pmBreakStart . ')';
                    }
                }
                
                if ($availableSlots < $requiredSlots || !$canPlaceAmBreak || !$canPlaceLunch || !$canPlacePmBreak) {
                    if (!$issue) {
                        if ($availableSlots < $requiredSlots) {
                            $issue = 'Disponibilité insuffisante (requis ' . $requiredSlots . ' créneaux, disponibles ' . $availableSlots . ')';
                        } elseif (!$canPlaceAmBreak) {
                            $issue = 'Impossible de placer pause AM';
                        } elseif (!$canPlaceLunch) {
                            $issue = 'Impossible de placer déjeuner';
                        }
                    }
                    
                    $diagnostics['agents_short_availability'][] = [
                        'agent_id' => $agent['id'],
                        'availability' => $availStart . '-' . $availEnd,
                        'available_slots' => $availableSlots,
                        'required_slots' => $requiredSlots,
                        'can_place_am_break' => $canPlaceAmBreak,
                        'can_place_lunch' => $canPlaceLunch,
                        'can_place_pm_break' => $canPlacePmBreak,
                        'issue' => $issue,
                    ];
                }
            }

            // --- 6. Fenêtres et durées ---
            $amBreakWindow = [
                'start' => $this->normalizeTime($settings->am_pause_start_time),
                'end' => $this->normalizeTime($settings->am_pause_end_time),
            ];
            $pmBreakWindow = [
                'start' => $this->normalizeTime($settings->pm_pause_start_time),
                'end' => $this->normalizeTime($settings->pm_pause_end_time),
            ];
            $lunchWindow = [
                'start' => $this->normalizeTime($settings->lunch_start_time),
                'end' => $this->normalizeTime($settings->lunch_end_time),
            ];
            $breakDurationMinutes = (int)$settings->am_pause_duration_minutes; // Unique champ pour AM/PM
            $lunchDurationMinutes = (int)$settings->lunch_duration_minutes;

            // --- 7. Format des needs pour le solver (clés horaires) ---
            // Le solver attend des clés "HH:MM[:SS]" alignées sur 15 min.
            $needsForJson = $needCurve;
            $offersNames = array_keys($needsForJson);
            $priorityOffers = [];
            $equityOffers = [];

            // --- Initialisation des diagnostics ---
            $diagnostics = [
                'agents_at_risk' => [],
                'fixed_activity_conflicts' => [],
                'fixed_activities_no_competent_agents' => [],
                'fixed_activities_outside_work_hours' => [],
                'fixed_activity_slot_shortages' => [],
                'site_competency_issues' => [],
                'overlapping_needs' => [],
                'excluded_agents_potentially_useful' => [],
                'skill_distribution' => [],
                'capacity_by_need_type' => [],
                'agents_short_availability' => [],
                'fixed_activities_remote_work_incompatibilities' => [],
            ];

            // --- Activités fixes: extraire séparément pour Passe 1 ---
            $generatedVirtualOffers = [];
            $fixedActivities = []; // Liste des activités fixes pour Passe 1
            $ignoreFixedActivities = !empty($data['ignore_fixed_activities'] ?? false);
            // NOUVEAU : possibilité d'ignorer la Passe 2 (forecast)
            $ignoreForecastSolver = !empty($data['ignore_forecast_solver'] ?? false);
            $siteSep = ' - '; // Séparateur pour les offres virtuelles (toujours défini)
            
            if ($ignoreFixedActivities) {
                $this->log('⚠️  Option activée: les activités fixes seront ignorées', 'debug');
            }
            
            try {
                $Rules = $this->fetchTable('FixedActivityRules');
                if ($Rules && !$ignoreFixedActivities) {
                    $rules = $Rules->find()
                        ->contain(['Offers', 'Sites', 'FixedActivityBlocks'])
                        ->where(['FixedActivityRules.active' => 1])
                        ->all();
                    $dow = (int)$dateToCalc->format('N');
                    $siteSep = ' - ';
                    foreach ($rules as $r) {
                        // Filtre par jour
                        $days = $r->days_of_week ?? null;
                        $applies = true;
                        if (!empty($days)) {
                            if (is_string($days)) {
                                $parsed = json_decode($days, true);
                                $days = is_array($parsed) ? $parsed : [];
                            }
                            if (is_array($days) && !in_array($dow, array_map('intval', $days), true)) {
                                $applies = false;
                            }
                        }
                        if (!$applies) { continue; }

                        $baseOffer = (string)($r->offer->name ?? '');
                        if ($baseOffer === '') { continue; }
                        $start = $this->normalizeTime($r->start_time ?? null);
                        $end = $this->normalizeTime($r->end_time ?? null);
                        $qty = (int)($r->quantity ?? 0);
                        if (!$start || !$end || $qty <= 0) { continue; }
                        
                        // --- 1.4 Diagnostic : Activités fixes en dehors des heures de travail ---
                        if ($start < $workdayStart || $end > $workdayEnd) {
                            $sitesArr = (array)$r->sites;
                            $siteMode = $r->site_mode ?? 'per_site';
                            $virtualOfferName = $baseOffer . $siteSep . ($siteMode === 'global' ? 'Global' : ($siteMode === 'pooled' && !empty($sitesArr) ? implode('+', array_map(fn($s) => (string)$s->name, $sitesArr)) : (string)($sitesArr[0]->name ?? '')));
                            $diagnostics['fixed_activities_outside_work_hours'][] = [
                                'activity' => $virtualOfferName,
                                'time_range' => ['start' => $start, 'end' => $end],
                                'work_hours' => ['start' => $workdayStart, 'end' => $workdayEnd],
                                'recommendation' => 'Ajuster les plages horaires de l\'activité fixe pour qu\'elles soient dans les heures de travail (' . $workdayStart . ' - ' . $workdayEnd . ')',
                            ];
                        }
                        
                        // Générer les clés 15'
                        $timeKeys = [];
                        $cursor = new \Cake\I18n\FrozenTime($start);
                        $limit = new \Cake\I18n\FrozenTime($end);
                        while ($cursor < $limit) {
                            $timeKeys[] = $cursor->format('H:i:s');
                            $cursor = $cursor->addMinutes(15);
                        }
                        $sitesArr = (array)$r->sites;
                        $siteMode = $r->site_mode ?? 'per_site';

                        // Extraire l'activité fixe pour la Passe 1 (au lieu de l'injecter dans need_curve)
                        // Préparer les blocs éventuels (uniquement si règle scindable)
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
                        $lunchAttachMode = $r->lunch_attach_mode ?? 'none';
                        $remoteWorkCompatible = isset($r->offer) && isset($r->offer->is_remote_work_compatible)
                            ? (bool)$r->offer->is_remote_work_compatible
                            : true;

                        if ($siteMode === 'global') {
                            $virtualOffer = $baseOffer . $siteSep . 'Global';
                            $fixedActivities[] = [
                                'offer_name' => $virtualOffer,
                                'start_time' => $start,
                                'end_time' => $end,
                                'quantity' => $qty,
                                // - equity_weight => scindable intra-journée (relais) : basé uniquement sur is_splittable
                                // - period_equity_weight => équité multi-jours : basé sur equity_enabled effectif
                                'equity_weight' => !empty($r->is_splittable) ? 1 : null,
                                'period_equity_weight' => (($r->equity_enabled === null) ? !empty($r->offer->equity_enabled) : !empty($r->equity_enabled)) ? 1 : null,
                                'blocks' => $blocks,
                                'lunch_overlap_allowed' => $lunchOverlapAllowed,
                                'lunch_attach_mode' => $lunchAttachMode,
                                'is_remote_work_compatible' => $remoteWorkCompatible,
                            ];
                            if (!in_array($virtualOffer, $generatedVirtualOffers, true)) { $generatedVirtualOffers[] = $virtualOffer; }
                        } elseif ($siteMode === 'pooled' && !empty($sitesArr)) {
                            $siteNames = implode('+', array_map(fn($s) => (string)$s->name, $sitesArr));
                            $virtualOffer = $baseOffer . $siteSep . $siteNames;
                            $fixedActivities[] = [
                                'offer_name' => $virtualOffer,
                                'start_time' => $start,
                                'end_time' => $end,
                                'quantity' => $qty,
                                // - equity_weight => scindable intra-journée (relais) : basé uniquement sur is_splittable
                                // - period_equity_weight => équité multi-jours : basé sur equity_enabled effectif
                                'equity_weight' => !empty($r->is_splittable) ? 1 : null,
                                'period_equity_weight' => (($r->equity_enabled === null) ? !empty($r->offer->equity_enabled) : !empty($r->equity_enabled)) ? 1 : null,
                                'blocks' => $blocks,
                                'lunch_overlap_allowed' => $lunchOverlapAllowed,
                                'lunch_attach_mode' => $lunchAttachMode,
                                'is_remote_work_compatible' => $remoteWorkCompatible,
                            ];
                            if (!in_array($virtualOffer, $generatedVirtualOffers, true)) { $generatedVirtualOffers[] = $virtualOffer; }
                        } else {
                            // Par site (per_site): une activité fixe par site sélectionné
                            foreach ($sitesArr as $site) {
                                $siteName = (string)$site->name;
                                if ($siteName === '') { continue; }
                                $virtualOffer = $baseOffer . $siteSep . $siteName;
                                $fixedActivities[] = [
                                    'offer_name' => $virtualOffer,
                                    'start_time' => $start,
                                    'end_time' => $end,
                                    'quantity' => $qty,
                                    // - equity_weight => scindable intra-journée (relais) : basé uniquement sur is_splittable
                                    // - period_equity_weight => équité multi-jours : basé sur equity_enabled effectif
                                    'equity_weight' => !empty($r->is_splittable) ? 1 : null,
                                    'period_equity_weight' => (($r->equity_enabled === null) ? !empty($r->offer->equity_enabled) : !empty($r->equity_enabled)) ? 1 : null,
                                    'blocks' => $blocks,
                                    'lunch_overlap_allowed' => $lunchOverlapAllowed,
                                    'lunch_attach_mode' => $lunchAttachMode,
                                    'is_remote_work_compatible' => $remoteWorkCompatible,
                                ];
                                if (!in_array($virtualOffer, $generatedVirtualOffers, true)) {
                                    $generatedVirtualOffers[] = $virtualOffer;
                                }
                            }
                        }
                    }
                }
                
                // Enrichir les skills des agents: si l'agent a la skill de base et appartient au site, ajouter "Offre - Site/Pooled/Global"
                // Construire un mapping offre virtuelle → sites concernés pour mode pooled
                if (!$ignoreFixedActivities && isset($rules)) {
                    $virtualOfferToSites = [];
                    foreach ($rules as $r) {
                        $rDow = [];
                        if (!empty($r->days_of_week)) {
                            $decoded = is_string($r->days_of_week) ? json_decode($r->days_of_week, true) : (array)$r->days_of_week;
                            $rDow = array_map('intval', (array)$decoded);
                        }
                        if (!empty($rDow) && !in_array((int)$dow, $rDow, true)) { continue; }
                        
                        $baseOffer = (string)($r->offer->name ?? '');
                        if ($baseOffer === '') { continue; }
                        $siteMode = $r->site_mode ?? 'per_site';
                        $sitesArr = (array)$r->sites;
                        
                        if ($siteMode === 'pooled' && !empty($sitesArr)) {
                            $siteNames = implode('+', array_map(fn($s) => (string)$s->name, $sitesArr));
                            $virtualOffer = $baseOffer . $siteSep . $siteNames;
                            $virtualOfferToSites[$virtualOffer] = array_map(fn($s) => (string)$s->name, $sitesArr);
                        }
                    }
                    
                    $enrichmentCount = 0;
                    foreach ($agentsForJson as &$agentJsonRef) {
                        $aid = (int)$agentJsonRef['id'];
                        $agentSite = $agentSiteById[$aid] ?? null;
                        $skillsBefore = count($agentJsonRef['skills'] ?? []);
                        
                        // Pour chaque offre virtuelle générée, si elle correspond au site de l'agent (ou Global/Pooled) et que l'agent a la skill de base, on ajoute
                        // IMPORTANT: Utiliser $generatedVirtualOffers au lieu de $offersNames car les activités fixes ne sont plus dans $needsForJson
                        foreach ($generatedVirtualOffers as $offName) {
                            $parts = explode($siteSep, $offName, 2);
                            if (count($parts) === 2) {
                                [$baseName, $siteName] = $parts;
                                $eligible = false;
                                
                                // Global: tous les agents avec la skill de base
                                if ($siteName === 'Global') {
                                    $eligible = true;
                                }
                                // Pooled: vérifier si l'agent appartient à l'un des sites du pool
                                elseif (isset($virtualOfferToSites[$offName])) {
                                    $eligible = in_array((string)$agentSite, $virtualOfferToSites[$offName], true);
                                }
                                // Per site: vérifier si l'agent appartient au site unique
                                else {
                                    $eligible = ((string)$agentSite !== '' && (string)$siteName === (string)$agentSite);
                                }
                                
                                if ($eligible && in_array($baseName, $agentJsonRef['skills'], true) && !in_array($offName, $agentJsonRef['skills'], true)) {
                                    $agentJsonRef['skills'][] = $offName;
                                    $enrichmentCount++;
                                }
                            }
                        }
                    }
                    unset($agentJsonRef);
                    $this->log("Enrichissement des compétences: {$enrichmentCount} compétences virtuelles ajoutées aux agents", 'debug');
                } else {
                    // Si on ignore les activités fixes, on ne fait rien
                    $this->log('Les activités fixes sont ignorées, pas d\'enrichissement des compétences', 'debug');
                }
            } catch (\Throwable $e) {
                $this->log('[FixedAct] Erreur injection besoins: ' . $e->getMessage(), 'warning');
            }

            // Diagnostics: vérifier que les offres virtuelles et les skills agents sont alignées
            if (!empty($generatedVirtualOffers)) {
                foreach ($generatedVirtualOffers as $voff) {
                    $needSum = 0;
                    if (!empty($needsForJson[$voff])) {
                        foreach ($needsForJson[$voff] as $tk => $v) { $needSum += (int)$v; }
                    }
                    $agentsWithSkill = 0;
                    foreach ($agentsForJson as $ag) {
                        if (in_array($voff, $ag['skills'] ?? [], true)) { $agentsWithSkill++; }
                    }
                    $this->log('[FixedAct DIAG] ' . $voff . ' need_sum=' . $needSum . ' agents_with_skill=' . $agentsWithSkill, 'debug');
                }
            }

            // --- 1.1 Diagnostic : Agents mono-compétence problématique ---
            if (!empty($agentsForJson)) {
                foreach ($agentsForJson as $agent) {
                    $skills = $agent['skills'] ?? [];
                    // Filtrer les valeurs null et s'assurer que $siteSep est défini
                    $skills = array_filter($skills, fn($s) => $s !== null && is_string($s));
                    $siteSep = $siteSep ?? ' - '; // Fallback si non défini
                    $virtualOffers = array_filter($skills, fn($s) => strpos($s, $siteSep) !== false);
                    $baseOffers = array_filter($skills, fn($s) => strpos($s, $siteSep) === false);
                    
                    // Agent qui n'a que des compétences pour des activités fixes
                    if (count($baseOffers) === 0 && count($virtualOffers) > 0) {
                        // Identifier les offres de base manquantes
                        $missingBaseOffers = [];
                        foreach ($virtualOffers as $voff) {
                            $parts = explode($siteSep, $voff, 2);
                            if (count($parts) === 2) {
                                $base = $parts[0];
                                if (!isset($needCurve[$base])) {
                                    $missingBaseOffers[] = $base;
                                }
                            }
                        }
                        
                        if (!empty($missingBaseOffers)) {
                            $missingBaseOffers = array_unique($missingBaseOffers);
                            $recommendation = 'Ajouter la compétence ' . implode(' ou ', $missingBaseOffers) . ' au scénario';
                            if (!empty($offersNames)) {
                                $availableOffers = array_filter($offersNames, fn($o) => strpos($o, $siteSep) === false);
                                if (!empty($availableOffers)) {
                                    $recommendation .= ' ou ajouter la compétence ' . implode(' ou ', array_slice($availableOffers, 0, 2)) . ' à l\'agent';
                                }
                            }
                            
                            $diagnostics['agents_at_risk'][] = [
                                'agent_id' => $agent['id'],
                                'agent_name' => $agentNameById[$agent['id']] ?? 'Nom inconnu',
                                'site' => $agentSiteById[$agent['id']] ?? 'Site inconnu',
                                'issue' => 'mono_skill_fixed_activity_only',
                                'skills' => array_values($virtualOffers),
                                'missing_base_offers' => array_values($missingBaseOffers),
                                'recommendation' => $recommendation,
                            ];
                        }
                    }
                    
                    // Agent limité à une seule activité fixe
                    if (count($virtualOffers) === 1 && count($baseOffers) === 0) {
                        $singleActivity = reset($virtualOffers);
                        $parts = explode($siteSep, $singleActivity, 2);
                        $baseOffer = count($parts) === 2 ? $parts[0] : '';
                        
                        $recommendation = 'Ajouter d\'autres compétences à l\'agent pour plus de flexibilité';
                        if (!empty($offersNames)) {
                            $availableOffers = array_filter($offersNames, fn($o) => strpos($o, $siteSep) === false);
                            if (!empty($availableOffers)) {
                                $recommendation .= ' (ex: ' . implode(', ', array_slice($availableOffers, 0, 2)) . ')';
                            }
                        }
                        
                        $diagnostics['agents_at_risk'][] = [
                            'agent_id' => $agent['id'],
                            'agent_name' => $agentNameById[$agent['id']] ?? 'Nom inconnu',
                            'site' => $agentSiteById[$agent['id']] ?? 'Site inconnu',
                            'issue' => 'single_fixed_activity_only',
                            'skills' => [$singleActivity],
                            'missing_base_offers' => $baseOffer ? [$baseOffer] : [],
                            'recommendation' => $recommendation,
                        ];
                    }
                }
            }

            // --- 1.3 Diagnostic : Activités fixes sur des sites sans agents compétents ---
            if (!empty($generatedVirtualOffers) && !$ignoreFixedActivities) {
                foreach ($generatedVirtualOffers as $voff) {
                    $parts = explode($siteSep, $voff, 2);
                    if (count($parts) === 2) {
                        [$baseName, $siteName] = $parts;
                        // Ignorer Global et Pooled
                        if ($siteName !== 'Global' && !isset($virtualOfferToSites[$voff])) {
                            // Vérifier s'il existe au moins un agent sur ce site avec la compétence
                            $hasCompetentAgent = false;
                            foreach ($agentsForJson as $agent) {
                                $agentSite = $agentSiteById[$agent['id']] ?? null;
                                if ($agentSite === $siteName && in_array($voff, $agent['skills'] ?? [], true)) {
                                    $hasCompetentAgent = true;
                                    break;
                                }
                            }
                            
                            if (!$hasCompetentAgent) {
                                $diagnostics['fixed_activities_no_competent_agents'][] = [
                                    'activity' => $voff,
                                    'site' => $siteName,
                                    'base_offer' => $baseName,
                                    'recommendation' => 'Ajouter la compétence "' . $baseName . '" aux agents du site "' . $siteName . '" ou déplacer des agents avec cette compétence vers ce site',
                                ];
                            }
                        }
                    }
                }
            }

            // --- 1.5 Diagnostic : Analyse de pénurie par créneau (activités fixes) ---
            if (!empty($generatedVirtualOffers) && !$ignoreFixedActivities) {
                foreach ($generatedVirtualOffers as $voff) {
                    $needData = $needsForJson[$voff] ?? [];
                    $criticalSlots = [];
                    
                    foreach ($needData as $timeSlot => $need) {
                        if ($need > 0) {
                            // Compter les agents compétents disponibles à ce créneau
                            $agentsAvailable = 0;
                            foreach ($agentsForJson as $agent) {
                                if (in_array($voff, $agent['skills'] ?? [], true)) {
                                    $availStart = $agent['availability_start_time'] ?? '00:00:00';
                                    $availEnd = $agent['availability_end_time'] ?? '23:59:59';
                                    if ($timeSlot >= $availStart && $timeSlot < $availEnd) {
                                        $agentsAvailable++;
                                    }
                                }
                            }
                            
                            if ($agentsAvailable < $need) {
                                $reason = 'pauses/déjeuner';
                                // Vérifier si c'est dans une fenêtre de pause
                                $amBreakStart = $this->normalizeTime($settings->am_pause_start_time ?? '10:00:00');
                                $amBreakEnd = $this->normalizeTime($settings->am_pause_end_time ?? '11:00:00');
                                $pmBreakStart = $this->normalizeTime($settings->pm_pause_start_time ?? '15:00:00');
                                $pmBreakEnd = $this->normalizeTime($settings->pm_pause_end_time ?? '16:00:00');
                                $lunchStart = $this->normalizeTime($settings->lunch_start_time ?? '12:00:00');
                                $lunchEnd = $this->normalizeTime($settings->lunch_end_time ?? '14:00:00');
                                
                                if ($timeSlot >= $lunchStart && $timeSlot < $lunchEnd) {
                                    $reason = 'déjeuner';
                                } elseif (($timeSlot >= $amBreakStart && $timeSlot < $amBreakEnd) || 
                                         ($timeSlot >= $pmBreakStart && $timeSlot < $pmBreakEnd)) {
                                    $reason = 'pauses';
                                }
                                
                                $criticalSlots[] = [
                                    'time' => $timeSlot,
                                    'need' => $need,
                                    'agents_available' => $agentsAvailable,
                                    'reason' => $reason,
                                ];
                            }
                        }
                    }
                    
                    if (!empty($criticalSlots)) {
                        $diagnostics['fixed_activity_slot_shortages'][] = [
                            'activity' => $voff,
                            'critical_slots' => $criticalSlots,
                        ];
                    }
                }
            }

            // --- 1.6 Diagnostic : Problèmes de compétences par site ---
            if (!empty($generatedVirtualOffers) && !$ignoreFixedActivities) {
                // Grouper les agents par site
                $agentsBySite = [];
                foreach ($agentsForJson as $agent) {
                    $site = $agentSiteById[$agent['id']] ?? null;
                    if ($site) {
                        if (!isset($agentsBySite[$site])) {
                            $agentsBySite[$site] = [];
                        }
                        $agentsBySite[$site][] = $agent;
                    }
                }
                
                // Pour chaque site avec activités fixes
                $sitesWithFixedActivities = [];
                foreach ($generatedVirtualOffers as $voff) {
                    $parts = explode($siteSep, $voff, 2);
                    if (count($parts) === 2) {
                        [$baseName, $siteName] = $parts;
                        if ($siteName !== 'Global' && !isset($virtualOfferToSites[$voff])) {
                            if (!isset($sitesWithFixedActivities[$siteName])) {
                                $sitesWithFixedActivities[$siteName] = [];
                            }
                            $sitesWithFixedActivities[$siteName][] = $baseName;
                        }
                    }
                }
                
                foreach ($sitesWithFixedActivities as $siteName => $requiredSkills) {
                    $requiredSkills = array_unique($requiredSkills);
                    $agentsOnSite = $agentsBySite[$siteName] ?? [];
                    $agentsWithSkills = 0;
                    
                    foreach ($agentsOnSite as $agent) {
                        $hasRequiredSkill = false;
                        foreach ($requiredSkills as $skill) {
                            if (in_array($skill, $agent['skills'] ?? [], true)) {
                                $hasRequiredSkill = true;
                                break;
                            }
                        }
                        if ($hasRequiredSkill) {
                            $agentsWithSkills++;
                        }
                    }
                    
                    if ($agentsWithSkills === 0 && !empty($agentsOnSite)) {
                        $diagnostics['site_competency_issues'][] = [
                            'site' => $siteName,
                            'required_skills' => array_values($requiredSkills),
                            'agents_on_site' => count($agentsOnSite),
                            'agents_with_skills' => 0,
                            'recommendation' => 'Ajouter les compétences ' . implode(', ', $requiredSkills) . ' aux agents du site "' . $siteName . '" ou déplacer des agents avec ces compétences vers ce site',
                        ];
                    }
                }
            }

            // --- 1.7 Diagnostic : Chevauchement entre besoins forecastables et activités fixes ---
            if (!empty($generatedVirtualOffers) && !$ignoreFixedActivities) {
                // Pour chaque créneau, calculer les besoins totaux
                $allTimeSlots = [];
                foreach ($needsForJson as $offer => $intervals) {
                    $allTimeSlots = array_merge($allTimeSlots, array_keys($intervals));
                }
                $allTimeSlots = array_unique($allTimeSlots);
                
                foreach ($allTimeSlots as $timeSlot) {
                    $forecastableNeeds = [];
                    $fixedActivityNeeds = [];
                    $totalNeed = 0;
                    
                    foreach ($needsForJson as $offer => $intervals) {
                        $need = (int)($intervals[$timeSlot] ?? 0);
                        if ($need > 0) {
                            if (in_array($offer, $generatedVirtualOffers, true)) {
                                $fixedActivityNeeds[$offer] = $need;
                            } else {
                                $forecastableNeeds[$offer] = $need;
                            }
                            $totalNeed += $need;
                        }
                    }
                    
                    if ($totalNeed > 0) {
                        // Calculer la capacité disponible à ce créneau
                        $availableCapacity = 0;
                        foreach ($agentsForJson as $agent) {
                            $availStart = $agent['availability_start_time'] ?? '00:00:00';
                            $availEnd = $agent['availability_end_time'] ?? '23:59:59';
                            if ($timeSlot >= $availStart && $timeSlot < $availEnd) {
                                $availableCapacity++;
                            }
                        }
                        
                        if ($totalNeed > $availableCapacity && (!empty($forecastableNeeds) || !empty($fixedActivityNeeds))) {
                            $diagnostics['overlapping_needs'][] = [
                                'time_slot' => $timeSlot,
                                'forecastable_needs' => $forecastableNeeds,
                                'fixed_activity_needs' => $fixedActivityNeeds,
                                'total_need' => $totalNeed,
                                'available_capacity' => $availableCapacity,
                                'shortage' => $totalNeed - $availableCapacity,
                            ];
                        }
                    }
                }
            }

            // --- 1.9 Diagnostic : Analyse de la répartition des compétences ---
            foreach ($offersNames as $offer) {
                $agentsCount = 0;
                $totalNeedSlots = 0;
                
                foreach ($agentsForJson as $agent) {
                    if (in_array($offer, $agent['skills'] ?? [], true)) {
                        $agentsCount++;
                    }
                }
                
                $needData = $needsForJson[$offer] ?? [];
                foreach ($needData as $need) {
                    $totalNeedSlots += (int)$need;
                }
                
                if ($agentsCount > 0 && $totalNeedSlots > 0) {
                    $ratio = $totalNeedSlots / $agentsCount;
                    $riskLevel = 'low';
                    if ($ratio > 0.8) {
                        $riskLevel = 'high';
                    } elseif ($ratio > 0.5) {
                        $riskLevel = 'medium';
                    }
                    
                    $diagnostics['skill_distribution'][] = [
                        'offer' => $offer,
                        'agents_count' => $agentsCount,
                        'total_need_slots' => $totalNeedSlots,
                        'ratio' => round($ratio, 2),
                        'risk_level' => $riskLevel,
                    ];
                }
            }

            // --- 1.10 Diagnostic : Analyse de capacité globale vs besoins par type ---
            // IMPORTANT :
            // - Les besoins forecastables viennent directement de $needsForJson (courbes de besoins envoyées au solveur de couverture)
            // - Les besoins en activités fixes NE SONT PLUS dans $needsForJson : ils sont extraits dans $fixedActivities pour la Passe 1.
            //   Il faut donc les recalculer à partir de $fixedActivities, sinon le besoin total ressort à 0 alors que des activités fixes existent.
            $forecastableNeed = 0;
            $fixedActivityNeed = 0;
            $forecastableCapacity = 0;
            $fixedActivityCapacity = 0;
            
            // Besoins forecastables : somme de toutes les courbes de besoins restantes (hors activités fixes)
            foreach ($needsForJson as $offer => $intervals) {
                $offerNeed = 0;
                foreach ($intervals as $need) {
                    $offerNeed += (int)$need;
                }
                $forecastableNeed += $offerNeed;
            }

            // Besoins d'activités fixes : calculés à partir des règles extraites pour la Passe 1
            // On considère : nombre de créneaux de 15 minutes × quantité demandée.
            if (!empty($fixedActivities)) {
                foreach ($fixedActivities as $fa) {
                    $start = $fa['start_time'] ?? null;
                    $end = $fa['end_time'] ?? null;
                    $qty = (int)($fa['quantity'] ?? 0);
                    if (empty($start) || empty($end) || $qty <= 0) {
                        continue;
                    }

                    $slots = 0;
                    $cursor = new \Cake\I18n\FrozenTime($start);
                    $limit = new \Cake\I18n\FrozenTime($end);
                    while ($cursor < $limit) {
                        $slots++;
                        $cursor = $cursor->addMinutes(15);
                    }

                    if ($slots > 0 && $qty > 0) {
                        $fixedActivityNeed += $slots * $qty;
                    }
                }
            }
            
            // Calculer la capacité disponible (approximative : nombre d'agents × créneaux moyens)
            foreach ($agentsForJson as $agent) {
                $availStart = $agent['availability_start_time'] ?? '00:00:00';
                $availEnd = $agent['availability_end_time'] ?? '23:59:59';
                // Calculer le nombre de créneaux disponibles (approximatif)
                $slots = 0;
                $current = new \Cake\I18n\FrozenTime($availStart);
                $end = new \Cake\I18n\FrozenTime($availEnd);
                while ($current < $end) {
                    $slots++;
                    $current = $current->addMinutes(15);
                }
                
                // Répartir la capacité entre forecastable et fixed (approximatif)
                $forecastableCapacity += $slots * 0.7; // 70% pour forecastable
                $fixedActivityCapacity += $slots * 0.3; // 30% pour fixed
            }
            
            $diagnostics['capacity_by_need_type'] = [
                'forecastable' => [
                    'total_need' => $forecastableNeed,
                    'total_capacity' => (int)$forecastableCapacity,
                    'coverage_rate' => $forecastableCapacity > 0 ? round(($forecastableNeed / $forecastableCapacity) * 100, 1) : 0,
                ],
                'fixed_activities' => [
                    'total_need' => $fixedActivityNeed,
                    'total_capacity' => (int)$fixedActivityCapacity,
                    'coverage_rate' => $fixedActivityCapacity > 0 ? round(($fixedActivityNeed / $fixedActivityCapacity) * 100, 1) : 0,
                ],
            ];

            $this->log('=== NEEDS FORMATÉES POUR LE SOLVER ===', 'debug');
            foreach ($needsForJson as $offer => $intervals) {
                $this->log("  {$offer}: " . count($intervals) . ' intervalles', 'debug');
                $sampleKeys = array_slice(array_keys($intervals), 0, 5);
                $this->log('    Clés exemples: ' . implode(', ', $sampleKeys), 'debug');

                // Afficher quelques valeurs
                $sampleValues = array_slice($intervals, 0, 5, true);
                foreach ($sampleValues as $k => $v) {
                    $this->log("      {$k} = {$v}", 'debug');
                }
            }

            // --- 7.5. Filtrage intelligent : exclure les offres sans agents compétents ---
            $excludedOffers = [];
            $offersWithCompetentAgents = [];
            
            // Pour chaque offre, vérifier s'il existe au moins un agent compétent
            foreach ($needsForJson as $offerName => $needData) {
                $hasCompetentAgent = false;
                foreach ($agentsForJson as $agent) {
                    if (in_array($offerName, $agent['skills'] ?? [], true)) {
                        $hasCompetentAgent = true;
                        break;
                    }
                }
                if ($hasCompetentAgent) {
                    $offersWithCompetentAgents[] = $offerName;
                } else {
                    $excludedOffers[] = $offerName;
                    $this->log("⚠️ Offre '{$offerName}' exclue : aucun agent compétent disponible.", 'warning');
                }
            }
            
            // Filtrer les besoins : ne garder que les offres avec agents compétents
            if (!empty($excludedOffers)) {
                $needsForJson = array_intersect_key($needsForJson, array_flip($offersWithCompetentAgents));
                $offersNames = array_values(array_intersect($offersNames, $offersWithCompetentAgents));
                
                // Mettre à jour les listes d'offres spéciales (utiliser array_values pour garantir des tableaux JSON)
                if (!empty($priorityOffers)) {
                    $priorityOffers = array_values(array_intersect($priorityOffers, $offersWithCompetentAgents));
                }
                if (!empty($equityOffers)) {
                    $equityOffers = array_values(array_intersect($equityOffers, $offersWithCompetentAgents));
                }
                if (!empty($generatedVirtualOffers)) {
                    $generatedVirtualOffers = array_values(array_intersect($generatedVirtualOffers, $offersWithCompetentAgents));
                }
                
                $this->log('=== APRÈS FILTRAGE ===', 'debug');
                $this->log('Offres retenues: ' . count($offersWithCompetentAgents) . ' / ' . (count($offersWithCompetentAgents) + count($excludedOffers)), 'debug');
                $this->log('Offres exclues: ' . implode(', ', $excludedOffers), 'debug');
            } else {
                $this->log('=== FILTRAGE ===', 'debug');
                $this->log('Toutes les offres ont au moins un agent compétent.', 'debug');
            }

            // --- 7.6. Filtrer les agents sans compétences pour les offres restantes ---
            $excludedAgents = [];
            $agentsForJsonFiltered = [];
            $offersSet = array_flip($offersWithCompetentAgents ?? $offersNames);
            
            foreach ($agentsForJson as $agent) {
                $agentSkills = $agent['skills'] ?? [];
                $hasRelevantSkill = false;
                
                // Vérifier si l'agent a au moins une compétence pour une offre restante
                foreach ($agentSkills as $skill) {
                    if (isset($offersSet[$skill])) {
                        $hasRelevantSkill = true;
                        break;
                    }
                }
                
                if ($hasRelevantSkill) {
                    $agentsForJsonFiltered[] = $agent;
                } else {
                    $excludedAgents[] = $agent['id'];
                    $this->log("Agent ID {$agent['id']} exclu : aucune compétence pour les offres restantes.", 'debug');
                    $excludedAgentsBeforeSolver[] = [
                        'id' => $agent['id'],
                        'name' => $agentNameById[$agent['id']] ?? 'Nom inconnu',
                        'site' => $agentSiteById[$agent['id']] ?? 'Site inconnu',
                        'reason' => 'Aucune compétence pour les offres restantes après filtrage',
                        'availability' => [
                            'start' => $agent['availability_start_time'] ?? null,
                            'end' => $agent['availability_end_time'] ?? null,
                        ],
                        'skills' => $agentSkills,
                        'offers_remaining' => array_values($offersWithCompetentAgents ?? $offersNames),
                    ];
                }
            }
            
            if (!empty($excludedAgents)) {
                $this->log('=== FILTRAGE AGENTS ===', 'debug');
                $this->log('Agents retenus: ' . count($agentsForJsonFiltered) . ' / ' . (count($agentsForJsonFiltered) + count($excludedAgents)), 'debug');
                $this->log('Agents exclus: ' . implode(', ', $excludedAgents), 'debug');
                $agentsForJson = $agentsForJsonFiltered;
            }
            // --- FIN FILTRAGE AGENTS ---

            // --- 1.8 Diagnostic : Agents exclus qui auraient pu être utiles ---
            if (!empty($excludedAgentsBeforeSolver)) {
                foreach ($excludedAgentsBeforeSolver as $excludedAgent) {
                    $exclusionReason = $excludedAgent['reason'] ?? '';
                    $agentSkills = $excludedAgent['skills'] ?? [];
                    
                    // Vérifier si l'agent aurait pu couvrir des besoins non couverts
                    $couldCover = [];
                    $ifCondition = null;
                    
                    // Si l'agent a été exclu pour "Aucune compétence pertinente" ou "Aucune compétence"
                    if (strpos($exclusionReason, 'compétence') !== false) {
                        // Vérifier si l'agent a des compétences pour des activités fixes
                        foreach ($agentSkills as $skill) {
                            if (strpos($skill, $siteSep) !== false) {
                                $parts = explode($siteSep, $skill, 2);
                                if (count($parts) === 2) {
                                    $baseOffer = $parts[0];
                                    // Si l'offre de base n'est pas dans le scénario
                                    if (!isset($needCurve[$baseOffer])) {
                                        $couldCover[] = $skill;
                                        if (!$ifCondition) {
                                            $ifCondition = 'Si l\'offre "' . $baseOffer . '" était dans le scénario';
                                        }
                                    }
                                }
                            }
                        }
                        
                        if (!empty($couldCover)) {
                            $recommendation = 'Ajouter "' . explode($siteSep, $couldCover[0], 2)[0] . '" au scénario';
                            if (count($couldCover) > 1) {
                                $recommendation .= ' ou ajouter d\'autres compétences à l\'agent';
                            } else {
                                $recommendation .= ' ou ajouter d\'autres compétences à l\'agent pour les offres du scénario';
                            }
                            
                            $diagnostics['excluded_agents_potentially_useful'][] = [
                                'agent_id' => $excludedAgent['id'],
                                'exclusion_reason' => $exclusionReason,
                                'could_cover' => $couldCover,
                                'if_condition' => $ifCondition,
                                'recommendation' => $recommendation,
                            ];
                        }
                    }
                }
            }

            // --- 8. Assemblage du JSON pour solver.py ---
            $wfmProblem = [
                'offers' => array_values($offersNames),
                'need_curve' => $needsForJson,
                'agents' => $agentsForJson,
                'workday_start_time' => $workdayStart,
                'workday_end_time' => $workdayEnd,
                'slot_minutes' => 15,
                'strict_work_hours' => $strictWork,
                'enable_am_pm_breaks' => $enableAmPmBreaks,
                'am_break_window' => $amBreakWindow,
                'pm_break_window' => $pmBreakWindow,
                'lunch_window' => $lunchWindow,
                'break_duration_minutes' => $breakDurationMinutes,
                'lunch_duration_minutes' => $lunchDurationMinutes,
                // 'min_max_offers' => [] // Optionnel: omis par défaut
            ];
            // Restreindre/caper les offres virtuelles générées
            if (!empty($generatedVirtualOffers)) {
                $wfmProblem['restrict_to_need_offers'] = $generatedVirtualOffers;
                $wfmProblem['cap_to_need_offers'] = $generatedVirtualOffers;
            }
            if (!empty($priorityOffers)) {
                $wfmProblem['priority_offers'] = $priorityOffers;
                $wfmProblem['priority_shortage_multiplier'] = 5;
            }
            if (!empty($equityOffers)) {
                $wfmProblem['equity_offers'] = $equityOffers;
                $wfmProblem['weight_equity'] = 60; // renforcer l'équité AM/PM
            }

            $this->log('=== JSON FINAL ===', 'debug');
            $this->log('Agents: ' . count($wfmProblem['agents']), 'debug');
            $this->log('Need Curve: ' . count($wfmProblem['need_curve']), 'debug');
            $jsonPreview = substr(json_encode($wfmProblem), 0, 500);
            $this->log("Preview: {$jsonPreview}...", 'debug');
            
            
            // DIAGNOSTIC : Vérifier les besoins avant envoi au solveur
            foreach ($diagnosticOffers as $diagOffer) {
                if (isset($wfmProblem['need_curve'][$diagOffer])) {
                    $diagData = $wfmProblem['need_curve'][$diagOffer];
                    $diagTotal = array_sum($diagData);
                    $diagKeys = array_keys($diagData);
                    $this->log("=== DIAG [AVANT SOLVER] {$diagOffer} ===", 'debug');
                    $this->log("  Total: {$diagTotal} agents-intervalles", 'debug');
                    $this->log("  Nombre de créneaux: " . count($diagData), 'debug');
                    $this->log("  Premières clés: " . implode(', ', array_slice($diagKeys, 0, 5)), 'debug');
                    $this->log("  Présente dans offers: " . (in_array($diagOffer, $wfmProblem['offers'], true) ? 'OUI' : 'NON'), 'debug');
                } else {
                    $this->log("=== DIAG [AVANT SOLVER] {$diagOffer} ===", 'debug');
                    $this->log("  ❌ OFFRE ABSENTE de need_curve", 'debug');
                    $this->log("  Présente dans offers: " . (in_array($diagOffer, $wfmProblem['offers'], true) ? 'OUI' : 'NON'), 'debug');
                }
            }
            if (!empty($priorityOffers)) {
                $this->log('Priority offers: ' . implode(', ', $priorityOffers), 'debug');
            }
            if (!empty($equityOffers)) {
                $this->log('Equity offers: ' . implode(', ', $equityOffers), 'debug');
            }
            }

            // --- 8. Appel au solver Python (2 passes) ---
            try {
                $http = new Client(['timeout' => 300]);
                $fixedActivityAssignments = [];
                $fixedActivityShortfalls = [];
                $updatedAgentsForPasse2 = $agentsForJson;
                // Alignement: les intervalles remote_work ont déjà été injectés dans agentsForJson
                // via ScheduleProblemBuilderService (service partagé avec le worker multi-jours).
                $remoteWorkIntervalsByAgent = $build['remote_work_intervals_by_agent'] ?? [];

                // --- PASSE 1 : Résoudre les activités fixes ---
                if (!$ignoreFixedActivities) {
                    $this->log('=== PASSE 1 : Résolution des activités fixes ===', 'debug');

                    $solverUrl = (string)\Cake\Core\Configure::read('PythonSolver.url', 'http://127.0.0.1:8000');
                    $this->log('Passe 1 : solveur activités fixes (ciblage global)', 'debug');
                    $fixedBuilder = new FixedActivitiesBuilderService();
                    $dateStr = $dateToCalc->format('Y-m-d');
                    $fixedOptions = [
                        'wfm_setting_id' => $settingsId,
                        'scenario_id' => $scenarioId,
                        'currentRealization' => [],
                        'ignore_fixed_activities' => $ignoreFixedActivities,
                        'ignore_forecast_solver' => $ignoreForecastSolver,
                        'debug_solvers' => $debugSolvers,
                    ];
                    $fixedPayload = $fixedBuilder->build($dateStr, $fixedOptions);
                    $agentsForJson = $fixedPayload['agents'];
                    $fixedActivities = $fixedPayload['fixed_activities'];
                    if (empty($fixedActivities)) {
                        $this->log('Passe 1 : aucune activité fixe à résoudre', 'debug');
                        $responsePasse1 = null;
                        $fixedProblem = [
                            'agents' => $agentsForJson,
                            'fixed_activities' => [],
                        ];
                    } else {
                        $fixedProblem = [
                            'agents' => $fixedPayload['agents'],
                            'fixed_activities' => $fixedPayload['fixed_activities'],
                            'workday_start_time' => $fixedPayload['workday_start_time'],
                            'workday_end_time' => $fixedPayload['workday_end_time'],
                            'slot_minutes' => $fixedPayload['slot_minutes'],
                            'lunch_window' => $fixedPayload['lunch_window'],
                            'lunch_duration_minutes' => $fixedPayload['lunch_duration_minutes'],
                            'am_break_window' => $fixedPayload['am_break_window'],
                            'pm_break_window' => $fixedPayload['pm_break_window'],
                            'break_duration_minutes' => $fixedPayload['break_duration_minutes'],
                            'enable_am_pm_breaks' => $fixedPayload['enable_am_pm_breaks'],
                            'lunch_activity_name' => $fixedPayload['lunch_activity_name'],
                            'break_activity_name' => $fixedPayload['break_activity_name'],
                            'enforce_remote_work_incompatibilities' => $fixedPayload['enforce_remote_work_incompatibilities'],
                            'relative_gap_limit' => $fixedPayload['relative_gap_limit'],
                            'min_block_minutes' => $fixedPayload['min_block_minutes'],
                            'max_block_minutes' => $fixedPayload['max_block_minutes'],
                        ];
                        $responsePasse1 = $http->post(
                            rtrim($solverUrl, '/') . '/api/v1/solve-fixed-activities',
                            json_encode($fixedProblem),
                            ['type' => 'application/json'],
                        );
                    }

                    // Log pour debug Passe 1
                    $this->log('=== PAYLOAD PASSE 1 ===', 'debug');
                    $this->log('Agents: ' . count($fixedProblem['agents']), 'debug');
                    $this->log('Fixed activities: ' . count($fixedProblem['fixed_activities']), 'debug');
                    if (!empty($fixedProblem['fixed_activities'])) {
                        $this->log('Première activité fixe: ' . json_encode($fixedProblem['fixed_activities'][0]), 'debug');
                    }
                    if (!empty($fixedProblem['agents'])) {
                        $this->log('Premier agent: ' . json_encode($fixedProblem['agents'][0]), 'debug');
                    }

                    if ($responsePasse1 === null) {
                        $responseBodyPasse1 = '';
                        $responseCodePasse1 = 0;
                        $solutionPasse1 = null;
                    } else {
                    $responseBodyPasse1 = $responsePasse1->getStringBody();
                    $responseCodePasse1 = $responsePasse1->getStatusCode();
                    
                    $this->log("Passe 1 - Code HTTP: {$responseCodePasse1}", 'debug');
                    
                    if ($responseCodePasse1 !== 200) {
                        $this->log("❌ Erreur Passe 1 (HTTP {$responseCodePasse1}): " . substr($responseBodyPasse1, 0, 1000), 'error');
                        $this->log("Payload envoyé (premiers 500 chars): " . substr(json_encode($fixedProblem), 0, 500), 'debug');
                    }
                    
                    $solutionPasse1 = json_decode($responseBodyPasse1, true);
                    
                    if ($solutionPasse1 && isset($solutionPasse1['status']) && $solutionPasse1['status'] === 'FEASIBLE') {
                        $fixedActivityAssignments = $solutionPasse1['assignments'] ?? [];
                        $fixedActivityShortfalls = $solutionPasse1['shortfalls'] ?? [];
                        
                        $this->log('✅ Passe 1 réussie: ' . count($fixedActivityAssignments) . ' assignations', 'debug');
                        if (!empty($fixedActivityAssignments)) {
                            $this->log('Exemple assignment Passe 1: ' . json_encode($fixedActivityAssignments[0]), 'debug');
                            // Log des agents uniques assignés
                            $uniqueAgents = array_unique(array_column($fixedActivityAssignments, 'agent_id'));
                            $this->log('Agents uniques assignés en Passe 1: ' . json_encode($uniqueAgents), 'debug');
                        }
                        if (!empty($fixedActivityShortfalls)) {
                            $this->log('⚠️ Shortfalls Passe 1: ' . json_encode($fixedActivityShortfalls), 'warning');
                        }
                        
                        // Mettre à jour les agents avec unavailable_intervals + preferred_lunch_starts
                        $afterFixed = new AgentsAfterFixedActivitiesService();
                        $updatedAgentsForPasse2 = $afterFixed->update(
                            $agentsForJson,
                            $fixedActivityAssignments,
                            $fixedActivities,
                            $lunchWindow,
                        );
                        
                        $this->log('Agents mis à jour pour Passe 2: ' . count($updatedAgentsForPasse2), 'debug');
                        
                        // Log détaillé des agents avec unavailable_intervals
                        $countWithUnavailable = 0;
                        foreach ($updatedAgentsForPasse2 as $agent) {
                            if (!empty($agent['unavailable_intervals'])) {
                                $countWithUnavailable++;
                                $this->log("Agent ID {$agent['id']} a unavailable_intervals: " . json_encode($agent['unavailable_intervals']), 'debug');
                            }
                        }
                        $this->log("Total agents avec unavailable_intervals: {$countWithUnavailable}", 'debug');
                    } else {
                        $this->log('❌ Passe 1 échouée: ' . json_encode($solutionPasse1), 'error');
                    }
                    } // fin responsePasse1 non null
                }

                // --- PASSE 2 : Résoudre le forecast avec agents mis à jour ---
                if ($ignoreForecastSolver) {
                    $this->log('⚠️ Passe 2 ignorée à la demande de l’utilisateur.', 'debug');
                    // On construit une solution factice: seules les activités fixes (Passe 1) seront utilisées.
                    $solution = [
                        'status' => 'FEASIBLE',
                        'grid' => [],
                        'offers' => [],
                        'schedules_segments' => [],
                        'schedule' => [],
                        'coverage' => [],
                        'diagnostics' => null,
                        'message' => 'Passe 2 ignorée à la demande de l’utilisateur.',
                    ];
                } else {
                    $this->log('=== PASSE 2 : Résolution du forecast ===', 'debug');
                    
                    // Construire need_curve sans les activités fixes (uniquement forecastables)
                    $forecastableNeeds = [];
                    $forecastableOffers = [];
                    foreach ($needsForJson as $offer => $intervals) {
                        if (!in_array($offer, $generatedVirtualOffers, true)) {
                            $forecastableNeeds[$offer] = $intervals;
                            $forecastableOffers[] = $offer;
                        }
                    }
                    
                    // Log pour debug Passe 2
                    $this->log('Passe 2 - Offres forecastables: ' . count($forecastableOffers), 'debug');
                    $this->log('Passe 2 - Agents avec unavailable_intervals: ' . count(array_filter($updatedAgentsForPasse2, fn($a) => !empty($a['unavailable_intervals'] ?? []))), 'debug');
                    
                    // Log des agents avec unavailable_intervals pour debug
                    foreach ($updatedAgentsForPasse2 as $ag) {
                        if (!empty($ag['unavailable_intervals'])) {
                            $this->log("Agent {$ag['id']} pour Passe 2: availability={$ag['availability_start_time']}-{$ag['availability_end_time']}, earliest_end=" . ($ag['earliest_end_time'] ?? 'none') . ", unavailable=" . count($ag['unavailable_intervals']) . " intervals", 'debug');
                        }
                    }
                    
                    $wfmProblemPasse2 = [
                        'offers' => array_values($forecastableOffers),
                        'need_curve' => $forecastableNeeds,
                        'agents' => $updatedAgentsForPasse2,
                        'workday_start_time' => $workdayStart,
                        'workday_end_time' => $workdayEnd,
                        'slot_minutes' => 15,
                        'strict_work_hours' => $strictWork,
                        // Si fin anticipée autorisée, ajouter une légère incitation à finir plus tôt.
                        // La couverture reste prioritaire via weight_shortage beaucoup plus élevé côté solver (1000).
                        'weight_early_end' => $strictWork ? 0 : 20,
                        'enable_am_pm_breaks' => $enableAmPmBreaks,
                        'am_break_window' => $amBreakWindow,
                        'pm_break_window' => $pmBreakWindow,
                        'lunch_window' => $lunchWindow,
                        'break_duration_minutes' => $breakDurationMinutes,
                        'lunch_duration_minutes' => $lunchDurationMinutes,
                        'forbid_midday_singletons' => $forbidMiddaySingletons,
                        // Dispersion des pauses: pénalité renforcée pour éviter que plusieurs agents fassent la pause au même moment
                        // Particulièrement important pour les agents avec activités fixes (même contraintes → même créneau optimal)
                        'weight_break_dispersion' => 12,  // Augmenté de 3 (défaut) à 12 pour forcer le décalage
                        'debug_logging' => $debugSolvers,
                    ];
                    
                    if (!empty($priorityOffers)) {
                        // Filtrer pour ne garder que les offres forecastables
                        $forecastablePriorityOffers = array_intersect($priorityOffers, $forecastableOffers);
                        if (!empty($forecastablePriorityOffers)) {
                            $wfmProblemPasse2['priority_offers'] = array_values($forecastablePriorityOffers);
                            $wfmProblemPasse2['priority_shortage_multiplier'] = 5;
                        }
                    }
                    
                    if (!empty($equityOffers)) {
                        // Filtrer pour ne garder que les offres forecastables
                        $forecastableEquityOffers = array_intersect($equityOffers, $forecastableOffers);
                        if (!empty($forecastableEquityOffers)) {
                            $wfmProblemPasse2['equity_offers'] = array_values($forecastableEquityOffers);
                            $wfmProblemPasse2['weight_equity'] = 60;
                        }
                    }

                    // Alignement lot (ScheduleDayGenerationService) : payload groupes d'offres
                    if (!empty($offerGroupsPayload)) {
                        $wfmProblemPasse2['offer_groups'] = array_values($offerGroupsPayload);
                    }
                    
                    $solverUrlPasse2 = rtrim((string)Configure::read('PythonSolver.url', 'http://127.0.0.1:8000'), '/');
                    $response = $http->post(
                        $solverUrlPasse2 . '/api/v1/solve-schedule',
                        json_encode($wfmProblemPasse2),
                        ['type' => 'application/json'],
                    );

                    // ✅ NOUVEAU : Afficher la réponse BRUTE du solver
                    $responseBody = $response->getStringBody();
                    $this->log('=== RÉPONSE BRUTE DU SOLVER ===', 'debug');
                    $this->log(substr($responseBody, 0, 1000), 'debug'); // Premiers 1000 caractères

                    $solution = json_decode($responseBody, true);

                    if ($solution === null) {
                        $this->log('ERREUR : JSON invalide reçu', 'debug');
                        $this->Flash->error('Crash du solveur Python. Réponse invalide (pas un JSON). <pre>' . h(substr($responseBody, 0, 500)) . '...</pre>', ['escape' => false]);

                        return $this->redirect(['action' => 'generate']);
                    }

                    $this->log('=== RÉPONSE DU SOLVER ===', 'debug');
                    $this->log('Status: ' . ($solution['status'] ?? 'unknown'), 'debug');

                    // ✅ NOUVEAU : Afficher TOUTE la structure reçue
                    $this->log('Structure complète: ' . json_encode($solution, JSON_PRETTY_PRINT), 'debug');
                    $this->log('Solver status: ' . ($solution['solver_status'] ?? 'unknown'), 'debug');
                }

                // Convertir les assignments de la Passe 1 en format segments (même si Passe 2 échoue)
                $this->log("Avant fusion - fixedActivityAssignments count: " . count($fixedActivityAssignments ?? []), 'debug');
                $scheduleFromPasse1 = [];
                if (!empty($fixedActivityAssignments)) {
                    foreach ($fixedActivityAssignments as $assignment) {
                        $scheduleFromPasse1[] = [
                            'agent_id' => $assignment['agent_id'],
                            'start' => $assignment['start'],
                            'end' => $assignment['end'],
                            'label' => 'WORK',
                            'offer' => $assignment['activity'],
                        ];
                    }
                } else {
                    $this->log("⚠️ Aucun assignment de la Passe 1 à fusionner", 'warning');
                }
                
                $this->log("Segments Passe 1 (activités fixes): " . count($scheduleFromPasse1), 'debug');
                
                // Si l'utilisateur a demandé d'ignorer la Passe 2, on s'arrête ici:
                // on ne sauvegarde que les segments issus de la Passe 1 (activités fixes).
                if ($ignoreForecastSolver) {
                    $schedule = $scheduleFromPasse1;
                    $schedulePasse2 = [];
                    $this->log("✅ Mode 'activités fixes uniquement' : " . count($schedule) . " segments WORK issus de la Passe 1, aucune Passe 2 exécutée.", 'debug');

                    // Compter uniquement les segments de travail (WORK)
                    $scheduleCount = 0;
                    $hasWorkSegments = false;
                    $workSegmentsByOffer = [];
                    if (is_array($schedule)) {
                        foreach ($schedule as $segment) {
                            if (is_array($segment)) {
                                $label = $segment['label'] ?? null;
                                if ($label === 'WORK') {
                                    $hasWorkSegments = true;
                                    $scheduleCount++;
                                    $offer = $segment['offer'] ?? 'unknown';
                                    $workSegmentsByOffer[$offer] = ($workSegmentsByOffer[$offer] ?? 0) + 1;
                                }
                            }
                        }
                    }

                    if ($hasWorkSegments) {
                        $this->_saveSolution($schedule, $dateToCalc, $settings);
                    } else {
                        $this->log("⚠️ Aucune activité fixe produite en Passe 1 (scheduleFromPasse1 vide).", 'warning');
                    }

                    $this->request->getSession()->write('generation_report', [
                        'date' => $dateToCalc->format('Y-m-d'),
                        'status' => 'FEASIBLE',
                        'diagnostics' => null,
                        'coverage' => [],
                        'excluded_offers' => $excludedOffers ?? [],
                        'offers' => $offersNames ?? [],
                        'settings_id' => $settingsId,
                        'error_message' => null,
                        'schedule_count' => $scheduleCount,
                        'excluded_agents' => $excludedAgentsBeforeSolver ?? [],
                        'agents_sent_to_solver' => count($agentsForJson ?? []),
                        'pre_solver_diagnostics' => $diagnostics ?? [],
                        // Informations Passe 1 (activités fixes)
                        'fixed_activities' => $fixedActivities ?? [],
                        'fixed_activity_assignments' => $fixedActivityAssignments ?? [],
                        'fixed_activity_assignments_count' => count($fixedActivityAssignments ?? []),
                        'fixed_activity_shortfalls' => $fixedActivityShortfalls ?? [],
                    ]);

                    return $this->redirect(['action' => 'generationReport']);
                }

                // Fusionner les résultats: Passe 1 (activités fixes) + Passe 2 (forecast si réussie)
                $schedule = [];
                
                if (isset($solution['status']) && in_array($solution['status'], ['success','FEASIBLE','OPTIMAL'], true)) {
                    $schedulePasse2 = $solution['schedule'] ?? [];
                    $this->log("Segments Passe 2 (forecast): " . count($schedulePasse2), 'debug');
                    
                    // Ajouter les segments de la Passe 2
                    $schedule = array_merge($scheduleFromPasse1, $schedulePasse2);
                    
                    $this->log("✅ Planning généré: " . count($schedule) . " segments au total (" . count($scheduleFromPasse1) . " fixes + " . count($schedulePasse2) . " forecast)", 'debug');
                    
                    // Log détaillé des segments de la Passe 1
                    if (!empty($scheduleFromPasse1)) {
                        $this->log("📋 Segments Passe 1 (premiers 3): " . json_encode(array_slice($scheduleFromPasse1, 0, 3)), 'debug');
                    }

                    // Le schedule est un tableau plat de segments avec structure :
                    // { "agent_id": int, "start": "HH:MM:SS", "end": "HH:MM:SS", "label": "WORK"|"LUNCH"|"AM_BREAK"|"PM_BREAK", "offer": "..." }
                    // Compter uniquement les segments de travail (WORK)
                    $scheduleCount = 0;
                    $hasWorkSegments = false;
                    $workSegmentsByOffer = [];
                    if (is_array($schedule)) {
                        foreach ($schedule as $segment) {
                            if (is_array($segment)) {
                                // Le solver utilise "label" et non "activity"
                                $label = $segment['label'] ?? null;
                                if ($label === 'WORK') {
                                    $hasWorkSegments = true;
                                    $scheduleCount++;
                                    $offer = $segment['offer'] ?? 'unknown';
                                    $workSegmentsByOffer[$offer] = ($workSegmentsByOffer[$offer] ?? 0) + 1;
                                }
                            }
                        }
                    }
                    
                    $this->log("📊 Analyse du planning: {$scheduleCount} segments WORK sur " . count($schedule) . " segments au total", 'debug');
                    $this->log("📊 Segments WORK par offre: " . json_encode($workSegmentsByOffer), 'debug');

                    // Si le planning est vide (aucun segment WORK), rediriger vers le rapport
                    if (!$hasWorkSegments) {
                        $this->log("⚠️ Planning vide détecté: aucun segment WORK trouvé (sur " . count($schedule) . " segments au total)", 'warning');
                        
                        // Stocker les données dans la session pour le rapport
                        $this->request->getSession()->write('generation_report', [
                            'date' => $dateToCalc->format('Y-m-d'),
                            'status' => $solution['status'],
                            'diagnostics' => $solution['diagnostics'] ?? null,
                            'coverage' => $solution['coverage'] ?? [],
                            'excluded_offers' => $excludedOffers ?? [],
                            'offers' => $offersNames ?? [],
                            'settings_id' => $settingsId,
                            'error_message' => 'Le solveur a retourné le statut "' . $solution['status'] . '" mais aucun segment de travail (WORK) n\'a été généré. Le planning ne contient que des pauses ou est vide.',
                            'schedule_count' => 0,
                            'excluded_agents' => $excludedAgentsBeforeSolver ?? [],
                            'agents_sent_to_solver' => count($agentsForJson ?? []),
                            'pre_solver_diagnostics' => $diagnostics ?? [],
                        ]);

                        return $this->redirect(['action' => 'generationReport']);
                    }

                    // --- 9. Sauvegarde ---
                    $this->_saveSolution($schedule, $dateToCalc, $settings);

                    // Stocker les données dans la session pour le rapport de succès
                    $status = $solution['status'] ?? 'UNKNOWN';
                    $this->request->getSession()->write('generation_report', [
                        'date' => $dateToCalc->format('Y-m-d'),
                        'status' => $status,
                        'diagnostics' => $solution['diagnostics'] ?? null,
                        'coverage' => $solution['coverage'] ?? [],
                        'excluded_offers' => $excludedOffers ?? [],
                        'offers' => $offersNames ?? [],
                        'settings_id' => $settingsId,
                        'error_message' => null,
                        'schedule_count' => $scheduleCount,
                        'excluded_agents' => $excludedAgentsBeforeSolver ?? [],
                        'agents_sent_to_solver' => count($agentsForJson ?? []),
                        'pre_solver_diagnostics' => $diagnostics ?? [],
                        // Informations Passe 1 (activités fixes)
                        'fixed_activities' => $fixedActivities ?? [],
                        'fixed_activity_assignments' => $fixedActivityAssignments ?? [],
                        'fixed_activity_assignments_count' => count($fixedActivityAssignments ?? []),
                        'fixed_activity_shortfalls' => $fixedActivityShortfalls ?? [],
                    ]);

                    return $this->redirect(['action' => 'generationReport']);
                } else {
                    // Passe 2 INFEASIBLE : sauvegarder quand même les activités fixes de la Passe 1
                    $errorMessage = $solution['message'] ?? $solution['detail'] ?? 'Erreur inconnue';
                    $status = $solution['status'] ?? 'UNKNOWN';

                    $this->log("❌ Échec du solver (Passe 2): {$status} - {$errorMessage}", 'error');
                    
                    // Logger les shortfalls de la Passe 1 si présents
                    if (!empty($fixedActivityShortfalls)) {
                        $this->log('⚠️ Shortfalls Passe 1 (activités fixes non complètement couvertes): ' . json_encode($fixedActivityShortfalls), 'warning');
                    }
                    
                    // Si on a des activités fixes de la Passe 1, les sauvegarder quand même
                    if (!empty($scheduleFromPasse1)) {
                        $this->log("💾 Sauvegarde des activités fixes de la Passe 1 malgré l'échec de la Passe 2: " . count($scheduleFromPasse1) . " segments", 'info');
                        $schedule = $scheduleFromPasse1;
                        
                        // Compter les segments WORK
                        $scheduleCount = count($scheduleFromPasse1);
                        $hasWorkSegments = $scheduleCount > 0;
                        
                        if ($hasWorkSegments) {
                            // Sauvegarder les activités fixes
                            $this->_saveSolution($schedule, $dateToCalc, $settings);
                            
                            $this->log("✅ Activités fixes sauvegardées: {$scheduleCount} segments", 'info');
                            
                            // Stocker les données dans la session pour le rapport
                            $this->request->getSession()->write('generation_report', [
                                'date' => $dateToCalc->format('Y-m-d'),
                                'status' => 'PARTIAL', // Statut partiel : activités fixes OK, forecast INFEASIBLE
                                'diagnostics' => $solution['diagnostics'] ?? null,
                                'coverage' => $solution['coverage'] ?? [],
                                'excluded_offers' => $excludedOffers ?? [],
                                'offers' => $offersNames ?? [],
                                'settings_id' => $settingsId,
                                'error_message' => "La Passe 2 (forecast) a échoué ({$status}), mais les activités fixes ont été sauvegardées ({$scheduleCount} segments).",
                                'schedule_count' => $scheduleCount,
                                'excluded_agents' => $excludedAgentsBeforeSolver ?? [],
                                'agents_sent_to_solver' => count($agentsForJson ?? []),
                                'pre_solver_diagnostics' => $diagnostics ?? [],
                                // Informations Passe 1 (activités fixes)
                                'fixed_activities' => $fixedActivities ?? [],
                                'fixed_activity_assignments' => $fixedActivityAssignments ?? [],
                                'fixed_activity_assignments_count' => count($fixedActivityAssignments ?? []),
                                'fixed_activity_shortfalls' => $fixedActivityShortfalls ?? [],
                            ]);

                            return $this->redirect(['action' => 'generationReport']);
                        }
                    }
                    
                    // Aucune activité fixe à sauvegarder : rediriger vers le rapport d'erreur
                    $this->request->getSession()->write('generation_report', [
                        'date' => $dateToCalc->format('Y-m-d'),
                        'status' => $status,
                        'diagnostics' => $solution['diagnostics'] ?? null,
                        'coverage' => $solution['coverage'] ?? [],
                        'excluded_offers' => $excludedOffers ?? [],
                        'offers' => $offersNames ?? [],
                        'settings_id' => $settingsId,
                        'error_message' => is_string($errorMessage) ? $errorMessage : json_encode($errorMessage),
                        'schedule_count' => 0,
                        'excluded_agents' => $excludedAgentsBeforeSolver ?? [],
                        'agents_sent_to_solver' => count($agentsForJson ?? []),
                        'pre_solver_diagnostics' => $diagnostics ?? [],
                        'fixed_activity_shortfalls' => $fixedActivityShortfalls ?? [],
                        'fixed_activity_assignments_count' => count($fixedActivityAssignments ?? []),
                    ]);

                    return $this->redirect(['action' => 'generationReport']);
                }
            } catch (Exception $e) {
                $this->log('❌ EXCEPTION: ' . $e->getMessage(), 'error');
                $this->log('Stack trace: ' . $e->getTraceAsString(), 'error');
                
                // Logger les shortfalls de la Passe 1 même en cas d'exception
                if (!empty($fixedActivityShortfalls)) {
                    $this->log('⚠️ Shortfalls Passe 1 (avant exception): ' . json_encode($fixedActivityShortfalls), 'warning');
                }
                
                $this->Flash->error('Erreur de connexion au service Python : ' . $e->getMessage());

                return $this->redirect(['action' => 'generate']);
            }
        }
    }

    /**
     * Affiche le rapport de génération (infaisabilité, erreur, ou planning vide)
     *
     * @return \Cake\Http\Response|null
     */
    public function generationReport()
    {
        $this->Authorization->authorize(new \App\Resource\SchedulesResource(), 'generate');
        
        $session = $this->request->getSession();
        $reportData = $session->read('generation_report');
        
        if (empty($reportData)) {
            $this->Flash->error('Aucun rapport de génération disponible.');
            return $this->redirect(['action' => 'generate']);
        }
        
        // Récupérer les données
        $date = $reportData['date'] ?? date('Y-m-d');
        $status = $reportData['status'] ?? 'UNKNOWN';
        $diagnostics = $reportData['diagnostics'] ?? null;
        $preSolverDiagnostics = $reportData['pre_solver_diagnostics'] ?? [];
        $coverage = $reportData['coverage'] ?? [];
        $excludedOffers = $reportData['excluded_offers'] ?? [];
        $offers = $reportData['offers'] ?? [];
        $settingsId = $reportData['settings_id'] ?? null;
        $errorMessage = $reportData['error_message'] ?? null; // Ne pas mettre de valeur par défaut, on vérifiera si c'est null
        $scheduleCount = $reportData['schedule_count'] ?? 0;
        $excludedAgents = $reportData['excluded_agents'] ?? [];
        $agentsSentToSolver = $reportData['agents_sent_to_solver'] ?? 0;
        
        // Informations Passe 1 (activités fixes)
        $fixedActivities = $reportData['fixed_activities'] ?? [];
        $fixedActivityAssignments = $reportData['fixed_activity_assignments'] ?? [];
        $fixedActivityAssignmentsCount = $reportData['fixed_activity_assignments_count'] ?? 0;
        $fixedActivityShortfalls = $reportData['fixed_activity_shortfalls'] ?? [];
        
        // Calculer les statistiques globales
        $totalNeedSlots = 0;
        $totalAvailableSlots = 0;
        $agentsWithSkills = 0;
        $agentsWithNoSkills = 0;
        $agentsWithNoAvailability = 0;
        $agentsWithLunchIssues = 0;
        $agentsWithBreakIssues = 0;
        $totalAgentsChecked = 0;
        
        // Calculer le besoin total depuis la couverture
        foreach ($coverage as $offerCoverage) {
            if (isset($offerCoverage['times']) && is_array($offerCoverage['times'])) {
                foreach ($offerCoverage['times'] as $timeSlot) {
                    if (isset($timeSlot['need'])) {
                        $totalNeedSlots += (int)$timeSlot['need'];
                    }
                }
            }
        }
        
        // Analyser les diagnostics des agents
        $agentDiagnosticsList = [];
        if ($diagnostics && isset($diagnostics['agents']) && is_array($diagnostics['agents'])) {
            $agentDiagnosticsList = $diagnostics['agents'];
            $totalAgentsChecked = count($agentDiagnosticsList);
            
            foreach ($agentDiagnosticsList as $diag) {
                if (!is_array($diag)) {
                    continue;
                }
                
                if (isset($diag['has_skills']) && $diag['has_skills']) {
                    $agentsWithSkills++;
                } else {
                    $agentsWithNoSkills++;
                }
                
                if (isset($diag['availability']['slots'])) {
                    $slots = (int)$diag['availability']['slots'];
                    $totalAvailableSlots += $slots;
                    if ($slots == 0) {
                        $agentsWithNoAvailability++;
                    }
                }
                
                // Vérifier les problèmes de déjeuner
                if (isset($diag['lunch']['candidates']) && isset($diag['lunch']['required_minutes'])) {
                    if ($diag['lunch']['candidates'] == 0 && $diag['lunch']['required_minutes'] > 0) {
                        $agentsWithLunchIssues++;
                    }
                }
                
                // Vérifier les problèmes de pauses
                if (isset($diag['am']['available_slots']) && isset($diag['am']['required_slots'])) {
                    if ($diag['am']['available_slots'] < $diag['am']['required_slots']) {
                        $agentsWithBreakIssues++;
                    }
                }
                if (isset($diag['pm']['available_slots']) && isset($diag['pm']['required_slots'])) {
                    if ($diag['pm']['available_slots'] < $diag['pm']['required_slots']) {
                        $agentsWithBreakIssues++;
                    }
                }
            }
        }
        
        // Si les diagnostics ne sont pas disponibles (statut FEASIBLE/OPTIMAL), estimer la capacité
        // en fonction du nombre d'agents envoyés au solveur (approximation : 32 créneaux par agent en moyenne)
        if ($totalAvailableSlots === 0 && $agentsSentToSolver > 0) {
            // Estimation : 32 créneaux par agent (8h * 4 créneaux/h) - c'est une approximation
            $totalAvailableSlots = $agentsSentToSolver * 32;
            $agentsWithSkills = $agentsSentToSolver; // Tous les agents envoyés ont des compétences
        }
        
        $coverageRate = $totalAvailableSlots > 0 ? round(($totalNeedSlots / $totalAvailableSlots) * 100, 1) : 0;
        
        // Calculer les besoins par offre
        $offersNeeds = [];
        foreach ($coverage as $offerCoverage) {
            $offerName = $offerCoverage['offer'] ?? '';
            if ($offerName === '') {
                continue;
            }
            
            $totalNeed = 0;
            $totalCovered = 0;
            $totalShortage = 0;
            
            if (isset($offerCoverage['times']) && is_array($offerCoverage['times'])) {
                foreach ($offerCoverage['times'] as $timeSlot) {
                    if (isset($timeSlot['need'])) {
                        $totalNeed += (int)$timeSlot['need'];
                    }
                    if (isset($timeSlot['covered'])) {
                        $totalCovered += (int)$timeSlot['covered'];
                    }
                    if (isset($timeSlot['shortage'])) {
                        $totalShortage += (int)$timeSlot['shortage'];
                    }
                }
            }
            
            $offersNeeds[$offerName] = [
                'need' => $totalNeed,
                'covered' => $totalCovered,
                'shortage' => $totalShortage,
            ];
        }
        
        // DIAGNOSTIC : Vérifier les besoins reçus du solveur dans coverage
        $diagnosticOffers = ['Employeurs', 'TI-AE'];
        foreach ($diagnosticOffers as $diagOffer) {
            $foundInCoverage = false;
            foreach ($coverage as $offerCoverage) {
                if (($offerCoverage['offer'] ?? '') === $diagOffer) {
                    $foundInCoverage = true;
                    $this->log("=== DIAG [APRÈS SOLVER] {$diagOffer} ===", 'debug');
                    $this->log("  Présente dans coverage: OUI", 'debug');
                    $this->log("  Besoin total calculé: " . ($offersNeeds[$diagOffer]['need'] ?? 0), 'debug');
                    if (isset($offerCoverage['times']) && is_array($offerCoverage['times'])) {
                        $firstTimes = array_slice($offerCoverage['times'], 0, 5);
                        $this->log("  Premiers créneaux: " . json_encode($firstTimes), 'debug');
                        $nonZeroNeeds = array_filter($offerCoverage['times'], fn($t) => ($t['need'] ?? 0) > 0);
                        $this->log("  Créneaux avec besoin > 0: " . count($nonZeroNeeds) . " / " . count($offerCoverage['times']), 'debug');
                    }
                    break;
                }
            }
            if (!$foundInCoverage) {
                $this->log("=== DIAG [APRÈS SOLVER] {$diagOffer} ===", 'debug');
                $this->log("  ❌ ABSENTE de coverage", 'debug');
            }
        }
        
        // Récupérer les paramètres
        $settings = null;
        if ($settingsId) {
            $WfmSettingsTable = $this->fetchTable('WfmSettings');
            try {
                $settings = $WfmSettingsTable->get($settingsId);
            } catch (\Exception $e) {
                // Ignorer si les paramètres ne sont plus disponibles
            }
        }
        
        // Passer les données à la vue
        $this->set(compact(
            'date',
            'status',
            'diagnostics',
            'preSolverDiagnostics',
            'agentDiagnosticsList',
            'coverage',
            'excludedOffers',
            'offers',
            'settings',
            'errorMessage',
            'excludedAgents',
            'agentsSentToSolver',
            'totalNeedSlots',
            'totalAvailableSlots',
            'coverageRate',
            'agentsWithSkills',
            'agentsWithNoSkills',
            'agentsWithNoAvailability',
            'agentsWithLunchIssues',
            'agentsWithBreakIssues',
            'totalAgentsChecked',
            'offersNeeds',
            'scheduleCount',
            // Informations Passe 1 (activités fixes)
            'fixedActivities',
            'fixedActivityAssignments',
            'fixedActivityAssignmentsCount',
            'fixedActivityShortfalls'
        ));
    }

    /**
     * Sauvegarde la solution en BDD (méthode privée) - VERSION CORRIGÉE
     */
    private function _saveSolution(array $schedule, DateTimeInterface $date, $settings): void
    {
        $this->log('=== DÉBUT SAUVEGARDE ===', 'debug');
        $this->log('Intervalles reçus: ' . count($schedule), 'debug');

        // Normaliser le planning AVANT sauvegarde:
        // - supprime les chevauchements (1 seule activité par agent/créneau)
        // - applique la priorité Pause/Repas > WORK existant (fixe) > WORK (forecast)
        // - concatène les créneaux contigus en segments
        $schedule = $this->_normalizeScheduleNoOverlap($schedule, 15);
        $this->log('Intervalles après normalisation: ' . count($schedule), 'debug');
        
        // Compter les segments WORK par offre avant sauvegarde
        $workSegmentsBeforeSave = [];
        foreach ($schedule as $seg) {
            if (isset($seg['label']) && $seg['label'] === 'WORK' && isset($seg['offer'])) {
                $offer = $seg['offer'];
                $workSegmentsBeforeSave[$offer] = ($workSegmentsBeforeSave[$offer] ?? 0) + 1;
            }
        }
        $this->log('📊 Segments WORK avant sauvegarde par offre: ' . json_encode($workSegmentsBeforeSave), 'debug');

        $RangesTable = $this->fetchTable('Ranges');
        $OffersTable = $this->fetchTable('Offers');

        // 1. Supprimer les ranges existants POUR LE PLANNING GÉNÉRÉ uniquement.
        //    On conserve:
        //    - toutes les absences (offer_type = absence) : congés, réunions, formations, mandats, etc.
        //    - le télétravail (offer_type = remote_work)
        //
        //    Note: on supprime toujours ce qui a été généré précédemment (comment "Généré par WFM"),
        //    même si, par erreur de configuration, ces segments ont été mappés sur une offre "absence".
        $dateStr = $date->format('Y-m-d');

        $historyUserIds = [];
        foreach ($schedule as $segForHistory) {
            if (!isset($segForHistory['agent_id'])) {
                continue;
            }
            $uid = (int)$segForHistory['agent_id'];
            if ($uid > 0) {
                $historyUserIds[$uid] = $uid;
            }
        }
        $existingUserIds = $RangesTable->find()
            ->select(['user_id'])
            ->distinct(['user_id'])
            ->where(['DATE(date_start)' => $dateStr])
            ->all()
            ->extract('user_id')
            ->map(fn($id) => (int)$id)
            ->toList();
        foreach ($existingUserIds as $uid) {
            if ($uid > 0) {
                $historyUserIds[$uid] = $uid;
            }
        }

        $protectedOfferIds = $OffersTable->find()
            ->select(['id'])
            ->where(['offer_type IN' => ['absence', 'remote_work']])
            ->all()
            ->extract('id')
            ->map(fn($id) => (int)$id)
            ->toList();

        $deleteConditions = [
            'OR' => [
                // Tout ce qui a été généré avant est supprimé pour être remplacé
                ['comment LIKE' => 'Généré par WFM%'],
                // Les autres offres (non protégées) de la journée sont remplacées
                ['offer_id NOT IN' => $protectedOfferIds],
            ],
            'DATE(date_start)' => $dateStr,
        ];

        // Si aucune offre protégée n'existe (cas anormal), fallback: supprimer toute la journée
        if (empty($protectedOfferIds)) {
            $deleteConditions = ['DATE(date_start)' => $dateStr];
        }

        $deleted = $RangesTable->deleteAll($deleteConditions);
        $this->log("Ranges supprimés (hors absences/télétravail): {$deleted}", 'debug');

        // 2. Map des offres (incluant les pauses mappées sur les offres configurées)
        $offerMap = $OffersTable->find('list', ['keyField' => 'name', 'valueField' => 'id'])->toArray();
        
        // Récupération des offres configurées pour les pauses
        $pauseOfferId = $settings->pause_offer_id ?? null;
        $lunchOfferId = $settings->lunch_offer_id ?? null;
        
        $this->log("🔍 DEBUG - pause_offer_id configuré: " . ($pauseOfferId ?? 'NULL'), 'debug');
        $this->log("🔍 DEBUG - lunch_offer_id configuré: " . ($lunchOfferId ?? 'NULL'), 'debug');
        
        // Fallback sur offres système par type
        $OffersTable = $this->fetchTable('Offers');
        $absenceOffer = $OffersTable->find('ByType', ['type' => 'absence'])->first();
        $absenceOfferId = $absenceOffer ? $absenceOffer->id : null;
        
        if ($pauseOfferId === null && $absenceOfferId === null) {
            $this->log("⚠️ ATTENTION : Aucune offre Pause configurée et aucune offre Absence trouvée.", 'warning');
        }
        
        if ($lunchOfferId === null && $absenceOfferId === null) {
            $this->log("⚠️ ATTENTION : Aucune offre Repas configurée et aucune offre Absence trouvée.", 'warning');
        }

        // Ajoute les noms d'activité Python à la map avec les offres configurées (ou fallback sur Absence)
        $offerMap['Pause AM'] = $pauseOfferId ?? $absenceOfferId;
        $offerMap['Pause Déjeuner'] = $lunchOfferId ?? $absenceOfferId;
        $offerMap['Pause PM'] = $pauseOfferId ?? $absenceOfferId;

        $this->log("🔍 DEBUG - offerMap['Pause AM']: " . ($offerMap['Pause AM'] ?? 'NULL'), 'debug');
        $this->log("🔍 DEBUG - offerMap['Pause Déjeuner']: " . ($offerMap['Pause Déjeuner'] ?? 'NULL'), 'debug');
        $this->log("🔍 DEBUG - offerMap['Pause PM']: " . ($offerMap['Pause PM'] ?? 'NULL'), 'debug');
        $this->log('Offres mappées (avec pauses): ' . count($offerMap), 'debug');

        // 3. Construire les entités selon le format de sortie
        $entities = [];
        $skippedCount = 0;
        // $dateStr déjà défini plus haut (pour la suppression)

        // Un seul format supporté: solver.py (segments): {start,end,label,offer?,agent_id}
        foreach ($schedule as $seg) {
            if (!isset($seg['agent_id'], $seg['start'], $seg['end'], $seg['label'])) {
                $this->log('Segment invalide: ' . print_r($seg, true), 'warning');
                $skippedCount++;
                continue;
            }

            $label = (string)$seg['label'];
            $offerId = null;

            if ($label === 'WORK') {
                $workOfferName = $seg['offer'] ?? null;
                if (!$workOfferName) {
                    $this->log('Offre manquante pour WORK: ' . print_r($seg, true), 'warning');
                    $skippedCount++;
                    continue;
                }
                if (!isset($offerMap[$workOfferName])) {
                    // Fallback: offres virtuelles "Base - Site" mappées sur l'offre de base
                    $base = $workOfferName;
                    if (strpos($workOfferName, ' - ') !== false) {
                        $parts = explode(' - ', $workOfferName, 2);
                        $base = $parts[0];
                    }
                    if (isset($offerMap[$base])) {
                        $offerId = $offerMap[$base];
                        $this->log("✅ Offre virtuelle mappée: '{$workOfferName}' -> offre de base '{$base}' (ID: {$offerId})", 'debug');
                    } else {
                        $this->log("❌ Offre inconnue pour WORK (après fallback base): '{$workOfferName}' base='{$base}' - Segment ignoré. Offres disponibles: " . implode(', ', array_keys($offerMap)), 'warning');
                        $skippedCount++;
                        continue;
                    }
                } else {
                    $offerId = $offerMap[$workOfferName];
                    $this->log("✅ Offre trouvée directement: '{$workOfferName}' (ID: {$offerId})", 'debug');
                }
            } elseif ($label === 'AM_BREAK') {
                $offerId = $offerMap['Pause AM'] ?? null;
                $this->log("🔍 DEBUG - AM_BREAK assigné à offer_id: {$offerId}", 'debug');
                if ($offerId === null) {
                    $this->log('⚠️ Pas d\'offre configurée pour Pause AM', 'warning');
                    $skippedCount++;
                    continue;
                }
            } elseif ($label === 'PM_BREAK') {
                $offerId = $offerMap['Pause PM'] ?? null;
                $this->log("🔍 DEBUG - PM_BREAK assigné à offer_id: {$offerId}", 'debug');
                if ($offerId === null) {
                    $this->log('⚠️ Pas d\'offre configurée pour Pause PM', 'warning');
                    $skippedCount++;
                    continue;
                }
            } elseif ($label === 'LUNCH') {
                $offerId = $offerMap['Pause Déjeuner'] ?? null;
                $this->log("🔍 DEBUG - LUNCH assigné à offer_id: {$offerId}", 'debug');
                if ($offerId === null) {
                    $this->log('⚠️ Pas d\'offre configurée pour Pause Déjeuner', 'warning');
                    $skippedCount++;
                    continue;
                }
            } else {
                $this->log('Label inconnu: ' . $label, 'warning');
                $skippedCount++;
                continue;
            }

            $entities[] = $RangesTable->newEntity([
                'user_id' => (int)$seg['agent_id'],
                'offer_id' => $offerId,
                'date_start' => $dateStr . ' ' . $seg['start'],
                'date_end' => $dateStr . ' ' . $seg['end'],
                'comment' => 'Généré par WFM',
            ]);
        }

        $this->log('Entités créées: ' . count($entities), 'debug');
        $this->log("Blocs ignorés: {$skippedCount}", 'debug');

        // 5. Sauvegarder
        $saveSucceeded = false;
        if (!empty($entities)) {
            if ($RangesTable->saveMany($entities)) {
                $this->log('✅ Sauvegarde réussie: ' . count($entities) . ' ranges', 'debug');
                $saveSucceeded = true;
            } else {
                // ... (log des erreurs comme avant) ...
                $this->log('❌ Échec de la sauvegarde', 'error');
                foreach ($entities as $entity) {
/* ... log errors ... */
                }
            }
        } else {
            $this->log('⚠️ Aucune entité à sauvegarder', 'warning');
            // Delete déjà appliqué sans erreur : jour potentiellement vidé / inchangé côté inserts
            $saveSucceeded = true;
        }

        // Historique : uniquement si l'écriture ranges a réussi
        if ($saveSucceeded && !empty($historyUserIds)) {
            $identity = $this->request->getAttribute('identity');
            $actorUserId = (int)($identity?->get('id') ?? 0);
            try {
                (new PlanningDayHistoryService())->recordAffectedUsers(
                    array_values($historyUserIds),
                    [$dateStr],
                    PlanningDayHistoryService::SOURCE_GENERATION,
                    $actorUserId > 0 ? $actorUserId : null,
                );
            } catch (Throwable $historyError) {
                Log::error('PlanningDayHistory (generation) échoué: ' . $historyError->getMessage());
            }
        }
    }

    /**
     * Normalise un planning au format segments:
     *   {agent_id,start,end,label,offer?}
     *
     * Objectifs:
     * - garantir qu'il n'y a jamais 2 activités au même instant pour un agent
     * - permettre que LUNCH/AM_BREAK/PM_BREAK "remplacent" une activité fixe (WORK) sur le même créneau
     * - empêcher un WORK (forecast) d'écraser un WORK déjà présent (activité fixe)
     * - regrouper les créneaux contigus identiques en segments.
     *
     * @param array $schedule Tableau de segments
     * @param int $slotMinutes Taille de créneau (15)
     * @return array Planning normalisé en segments
     */
    private function _normalizeScheduleNoOverlap(array $schedule, int $slotMinutes = 15): array
    {
        if (empty($schedule)) {
            return [];
        }

        $slotsByAgent = [];
        $conflicts = 0;
        $invalid = 0;

        foreach ($schedule as $seg) {
            if (!is_array($seg) || !isset($seg['agent_id'], $seg['start'], $seg['end'], $seg['label'])) {
                $invalid++;
                continue;
            }

            $agentId = (int)$seg['agent_id'];
            $label = (string)$seg['label'];
            $start = (string)$seg['start'];
            $end = (string)$seg['end'];
            $offer = isset($seg['offer']) ? (string)$seg['offer'] : null;

            if (!in_array($label, ['WORK', 'LUNCH', 'AM_BREAK', 'PM_BREAK'], true)) {
                $invalid++;
                continue;
            }

            if ($label === 'WORK' && ($offer === null || trim($offer) === '')) {
                // Un WORK sans offer est inutilisable pour la sauvegarde.
                $invalid++;
                continue;
            }

            $startMin = $this->_timeToMinutes($this->normalizeTime($start, '00:00:00'));
            $endMin = $this->_timeToMinutes($this->normalizeTime($end, '00:00:00'));
            if ($endMin <= $startMin) {
                $invalid++;
                continue;
            }

            // Parcours à la grille 15'
            for ($m = $startMin; $m < $endMin; $m += $slotMinutes) {
                if (!isset($slotsByAgent[$agentId])) {
                    $slotsByAgent[$agentId] = [];
                }

                // Si on est sur un slot partiel (end non aligné), on ignore le dernier morceau
                if ($m + $slotMinutes > $endMin) {
                    break;
                }

                $existing = $slotsByAgent[$agentId][$m] ?? null;
                if ($existing === null) {
                    $slotsByAgent[$agentId][$m] = [
                        'label' => $label,
                        'offer' => ($label === 'WORK') ? $offer : null,
                    ];
                    continue;
                }

                // Priorité: pauses/repas écrasent tout, WORK n'écrase rien
                if ($label === 'WORK') {
                    // Ne jamais écraser un slot existant (fixe ou pause/repas).
                    // Si le slot était vide, on l'aurait rempli plus haut.
                    if (($existing['label'] ?? null) !== 'WORK') {
                        $conflicts++;
                    }
                    continue;
                }

                // LUNCH/AM/PM remplacent l'existant (notamment activité fixe)
                $slotsByAgent[$agentId][$m] = [
                    'label' => $label,
                    'offer' => null,
                ];
            }
        }

        if ($invalid > 0) {
            $this->log("⚠️ Normalisation: {$invalid} segments invalides ignorés", 'warning');
        }
        if ($conflicts > 0) {
            $this->log("⚠️ Normalisation: {$conflicts} conflits WORK ignorés (un slot était déjà occupé)", 'warning');
        }

        // Re-concaténation en segments
        $normalized = [];
        foreach ($slotsByAgent as $agentId => $slots) {
            if (empty($slots) || !is_array($slots)) {
                continue;
            }
            ksort($slots); // clé = minutes

            $currentLabel = null;
            $currentOffer = null;
            $segStartMin = null;
            $prevMin = null;

            foreach ($slots as $m => $info) {
                $label = (string)($info['label'] ?? '');
                $offer = ($label === 'WORK') ? ($info['offer'] ?? null) : null;

                $isNew =
                    $currentLabel === null ||
                    $segStartMin === null ||
                    $prevMin === null ||
                    ($m !== $prevMin + $slotMinutes) ||
                    ($label !== $currentLabel) ||
                    (($offer ?? null) !== ($currentOffer ?? null));

                if ($isNew) {
                    // fermer le segment courant
                    if ($currentLabel !== null && $segStartMin !== null && $prevMin !== null) {
                        $seg = [
                            'agent_id' => (int)$agentId,
                            'start' => $this->_minutesToTime($segStartMin),
                            'end' => $this->_minutesToTime($prevMin + $slotMinutes),
                            'label' => $currentLabel,
                        ];
                        if ($currentLabel === 'WORK') {
                            $seg['offer'] = (string)($currentOffer ?? '');
                        }
                        $normalized[] = $seg;
                    }

                    // démarrer un nouveau segment
                    $currentLabel = $label;
                    $currentOffer = $offer;
                    $segStartMin = (int)$m;
                    $prevMin = (int)$m;
                } else {
                    $prevMin = (int)$m;
                }
            }

            // fermer le dernier segment agent
            if ($currentLabel !== null && $segStartMin !== null && $prevMin !== null) {
                $seg = [
                    'agent_id' => (int)$agentId,
                    'start' => $this->_minutesToTime($segStartMin),
                    'end' => $this->_minutesToTime($prevMin + $slotMinutes),
                    'label' => $currentLabel,
                ];
                if ($currentLabel === 'WORK') {
                    $seg['offer'] = (string)($currentOffer ?? '');
                }
                $normalized[] = $seg;
            }
        }

        // Tri final pour stabilité
        usort($normalized, function ($a, $b) {
            if (($a['agent_id'] ?? 0) !== ($b['agent_id'] ?? 0)) {
                return ($a['agent_id'] ?? 0) <=> ($b['agent_id'] ?? 0);
            }
            return strcmp((string)($a['start'] ?? ''), (string)($b['start'] ?? ''));
        });

        return $normalized;
    }

    /**
     * Fusionne les intervalles consécutifs d'une même activité
     * Attend un schedule avec des entrées du type:
     *   { "agent_id": int, "time": "HH:MM:SS", "activity": "..." [, "offer": "..."] }
     */
    private function mergeIntervals(array $schedule): array
    {
        if (empty($schedule)) {
            return [];
        }

        // Trier par agent_id puis par heure de début ("time")
        usort($schedule, function ($a, $b) {
            if ($a['agent_id'] !== $b['agent_id']) {
                return $a['agent_id'] <=> $b['agent_id'];
            }
            // "time" vient directement du solver (HH:MM:SS)
            return strcmp($a['time'], $b['time']);
        });

        $merged = [];
        $currentBlock = null;

        foreach ($schedule as $interval) {
            // Sécurise la présence des clés attendues
            if (!isset($interval['agent_id'], $interval['activity'], $interval['time'])) {
                // Ignore proprement toute entrée mal formée
                $this->log('Intervalle invalide (clé manquante): ' . print_r($interval, true), 'warning');
                continue;
            }

            $agentId = $interval['agent_id'];
            $activity = $interval['activity'];
            $offer = $interval['offer'] ?? null; // optionnel
            $startTime = $interval['time']; // clé correcte fournie par le solver

            if ($currentBlock === null) {
                // Démarre un nouveau bloc
                $currentBlock = [
                    'agent_id' => $agentId,
                    'activity' => $activity,
                    'offer' => $offer,
                    'start_time' => $startTime,
                    'last_interval_time' => $startTime,
                ];
                continue;
            }

            $sameAgent = ($agentId === $currentBlock['agent_id']);
            $sameAct = ($activity === $currentBlock['activity']);
            $sameOffer = (($offer ?? null) === ($currentBlock['offer'] ?? null));
            $consecutive = $this->isConsecutive($currentBlock['last_interval_time'], $startTime);

            if ($sameAgent && $sameAct && $sameOffer && $consecutive) {
                // Prolonge le bloc en cours
                $currentBlock['last_interval_time'] = $startTime;
            } else {
                // Termine le bloc courant
                $merged[] = [
                    'agent_id' => $currentBlock['agent_id'],
                    'activity' => $currentBlock['activity'],
                    'offer' => $currentBlock['offer'],
                    'start_time' => $currentBlock['start_time'],
                    'end_time' => $this->addQuarterHour($currentBlock['last_interval_time']),
                ];

                // Nouveau bloc
                $currentBlock = [
                    'agent_id' => $agentId,
                    'activity' => $activity,
                    'offer' => $offer,
                    'start_time' => $startTime,
                    'last_interval_time' => $startTime,
                ];
            }
        }

        // Ajoute le dernier bloc
        if ($currentBlock !== null) {
            $merged[] = [
                'agent_id' => $currentBlock['agent_id'],
                'activity' => $currentBlock['activity'],
                'offer' => $currentBlock['offer'],
                'start_time' => $currentBlock['start_time'],
                'end_time' => $this->addQuarterHour($currentBlock['last_interval_time']),
            ];
        }

        return $merged;
    }

    /**
     * Vérifie si time2 est exactement 15 minutes après time1.
     */
    private function isConsecutive(string $time1, string $time2): bool
    {
        // Vérifie que time2 = time1 + 15 minutes
        $dt1 = new FrozenTime($time1);
        $dt2 = new FrozenTime($time2);
        $diff = $dt1->diff($dt2);

        // On considère consécutif si dt2 est exactement 15 minutes après dt1
        return $diff->invert === 0 && $diff->h === 0 && $diff->i === 15 && $diff->s === 0;
    }

//    private function isConsecutive(string $t1, string $t2): bool
//    {
//        return $this->addQuarterHour($t1) === $this->normalizeTime($t2, $t2);
//    }

    /**
     * Ajoute 15 minutes à une heure au format H:i[:s].
     */
    private function addQuarterHour(string $time): string
    {
        $dt = FrozenTime::createFromFormat('H:i:s', $time) ?: FrozenTime::createFromFormat('H:i', $time);
        if (!$dt) {
            return $time;
        }
        $dt = $dt->addMinutes(15);

        return $dt->format('H:i:s');
    }

    /**
     * Normalise une valeur temporelle en HH:MM:SS.
     *
     * @param mixed $t Valeur temporelle (string|DateTimeInterface)
     */
    private function normalizeTime(mixed $t, string $default = '00:00:00'): string
    {
        if ($t instanceof DateTimeInterface) {
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

    /**
     * Calcule la disponibilité effective d'un agent en tenant compte des congés partiels.
     * 
     * @param \App\Model\Entity\User $agent Agent
     * @param \Cake\I18n\FrozenTime $date Date du planning
     * @param \App\Model\Entity\UserAvailability|null $availability Disponibilité contrat
     * @return array|null ['start' => 'HH:MM:SS', 'end' => 'HH:MM:SS'] ou null si indisponible
     */
    private function _calculateEffectiveAvailability($agent, $date, $availability): ?array
    {
        if (!$availability) {
            return null;
        }

        $contractStart = $this->normalizeTime($availability->availability_start_time);
        $contractEnd = $this->normalizeTime($availability->availability_end_time);

        // Récupérer les congés de l'agent pour cette date (offre type absence)
        $RangesTable = $this->fetchTable('Ranges');
        $OffersTable = $this->fetchTable('Offers');
        
        // Trouver TOUTES les offres "Absence" (réunion/formation/mandat peuvent être des offres différentes)
        $absenceOfferIds = $OffersTable->find('ByType', ['type' => 'absence'])
            ->all()
            ->extract('id')
            ->map(fn($id) => (int)$id)
            ->toList();

        if (empty($absenceOfferIds)) {
            // Pas d'offre Absence configurée, retourner la disponibilité contrat
            return [
                'start' => $contractStart,
                'end' => $contractEnd,
            ];
        }

        // Récupérer les ranges d'absence pour cet agent et cette date
        $dayStart = $date->setTime(0, 0, 0);
        $dayEnd = $date->setTime(23, 59, 59);
        
        $absences = $RangesTable->find()
            ->where([
                'user_id' => $agent->id,
                'offer_id IN' => $absenceOfferIds,
                'date_start <=' => $dayEnd,
                'date_end >=' => $dayStart,
            ])
            ->all();

        if ($absences->isEmpty()) {
            // Pas de congé, retourner la disponibilité contrat
            return [
                'start' => $contractStart,
                'end' => $contractEnd,
            ];
        }

        // Convertir les heures en minutes depuis minuit pour faciliter les calculs
        $contractStartMin = $this->_timeToMinutes($contractStart);
        $contractEndMin = $this->_timeToMinutes($contractEnd);

        // Construire la liste des intervalles de congés sur la journée
        $absenceIntervals = [];
        foreach ($absences as $absence) {
            $absStart = $absence->date_start;
            $absEnd = $absence->date_end;
            
            // Si le congé chevauche la journée, prendre l'intersection
            $dayAbsStart = max($absStart, $dayStart);
            $dayAbsEnd = min($absEnd, $dayEnd);
            
            if ($dayAbsStart < $dayAbsEnd) {
                $absStartMin = $this->_timeToMinutes($dayAbsStart->format('H:i:s'));
                $absEndMin = $this->_timeToMinutes($dayAbsEnd->format('H:i:s'));
                $absenceIntervals[] = ['start' => $absStartMin, 'end' => $absEndMin];
            }
        }

        // Fusionner les intervalles qui se chevauchent
        usort($absenceIntervals, fn($a, $b) => $a['start'] <=> $b['start']);
        $merged = [];
        foreach ($absenceIntervals as $interval) {
            if (empty($merged) || $interval['start'] > $merged[count($merged) - 1]['end']) {
                $merged[] = $interval;
            } else {
                $merged[count($merged) - 1]['end'] = max($merged[count($merged) - 1]['end'], $interval['end']);
            }
        }

        // Calculer l'intersection : disponibilité contrat ∩ (complément des congés)
        $availableIntervals = [];
        $currentStart = $contractStartMin;

        foreach ($merged as $absence) {
            if ($currentStart < $absence['start']) {
                // Il y a un intervalle disponible avant le congé
                $availableIntervals[] = [
                    'start' => $currentStart,
                    'end' => min($absence['start'], $contractEndMin),
                ];
            }
            $currentStart = max($currentStart, $absence['end']);
        }

        // Ajouter l'intervalle après le dernier congé
        if ($currentStart < $contractEndMin) {
            $availableIntervals[] = [
                'start' => $currentStart,
                'end' => $contractEndMin,
            ];
        }

        // Si aucun intervalle disponible, l'agent est en congé complet
        if (empty($availableIntervals)) {
            return null;
        }

        // Retourner le premier intervalle disponible (ou fusionner si plusieurs)
        // Pour simplifier, on retourne le premier intervalle non vide
        $firstInterval = $availableIntervals[0];
        if ($firstInterval['start'] >= $firstInterval['end']) {
            return null;
        }

        return [
            'start' => $this->_minutesToTime($firstInterval['start']),
            'end' => $this->_minutesToTime($firstInterval['end']),
        ];
    }

    /**
     * Convertit une heure HH:MM:SS en minutes depuis minuit.
     */
    private function _timeToMinutes(string $time): int
    {
        $parts = explode(':', $time);
        $h = (int)($parts[0] ?? 0);
        $m = (int)($parts[1] ?? 0);
        return $h * 60 + $m;
    }

    /**
     * Convertit des minutes depuis minuit en HH:MM:SS.
     */
    private function _minutesToTime(int $minutes): string
    {
        $h = (int)($minutes / 60) % 24;
        $m = $minutes % 60;
        return sprintf('%02d:%02d:00', $h, $m);
    }

    /**
     * Calcule les scores d'équité historiques pour les agents et activités fixes.
     * 
     * @param array $agents Liste d'agents
     * @param \Cake\I18n\FrozenTime $date Date du planning
     * @param array $fixedActivities Liste des activités fixes avec is_splittable=true
     * @return array Dict[agent_id => score]
     */
    private function _getEquityScores(array $agents, $date, array $fixedActivities): array
    {
        $scores = [];
        $RangesTable = $this->fetchTable('Ranges');
        
        // Pour chaque agent, initialiser le score à 0
        foreach ($agents as $agent) {
            $scores[(int)$agent['id']] = 0.0;
        }

        // Pour chaque activité fixe avec equity_weight, calculer l'historique
        foreach ($fixedActivities as $activity) {
            if (empty($activity['equity_weight'])) {
                continue;
            }

            $offerName = $activity['offer_name'] ?? null;
            if (!$offerName) {
                continue;
            }

            // Trouver l'offre correspondante
            $OffersTable = $this->fetchTable('Offers');
            $offer = $OffersTable->find()
                ->where(['name' => $offerName])
                ->first();

            if (!$offer) {
                continue;
            }

            // Compter les assignations historiques pour chaque agent
            // On regarde les 30 derniers jours pour avoir une base statistique
            $startDate = $date->subDays(30);
            $endDate = $date->copy();

            foreach ($agents as $agent) {
                $agentId = (int)$agent['id'];
                
                $assignments = $RangesTable->find()
                    ->where([
                        'user_id' => $agentId,
                        'offer_id' => $offer->id,
                        'date_start >=' => $startDate,
                        'date_start <=' => $endDate,
                    ])
                    ->count();

                // Normaliser par le nombre de jours travaillés (approximation)
                // Score = nombre d'assignations / nombre de jours
                $daysWorked = 30; // Approximation
                if ($daysWorked > 0) {
                    $scores[$agentId] += (float)$assignments / $daysWorked;
                }
            }
        }

        return $scores;
    }

    /**
     * Met à jour les agents après la Passe 1 en ajoutant unavailable_intervals.
     * 
     * @param array $agents Liste d'agents initiaux
     * @param array $assignments Assignments de la Passe 1
     * @param array $fixedActivities Activités fixes utilisées en Passe 1 (pour récupérer lunch_overlap_allowed / lunch_attach_mode)
     * @return array Liste d'agents mise à jour
     */
    private function _updateAgentsAfterFixedActivities(array $agents, array $assignments, array $fixedActivities): array
    {
        // Construire un mapping activité fixe (offer_name virtuel) => politiques associées
        $lunchPolicyByActivity = [];
        $lunchAttachModeByActivity = [];
        foreach ($fixedActivities as $fa) {
            if (empty($fa['offer_name'])) {
                continue;
            }
            $name = (string)$fa['offer_name'];
            $allow = array_key_exists('lunch_overlap_allowed', $fa) ? (bool)$fa['lunch_overlap_allowed'] : true;
            $lunchPolicyByActivity[$name] = $allow;
            $mode = isset($fa['lunch_attach_mode']) ? (string)$fa['lunch_attach_mode'] : 'none';
            if (!in_array($mode, ['none', 'before', 'after'], true)) {
                $mode = 'none';
            }
            $lunchAttachModeByActivity[$name] = $mode;
        }

        // Construire un mapping agent_id => liste d'intervalles indisponibles
        $unavailableByAgent = [];
        // Et un mapping agent_id => heures de repas préférées (HH:MM:SS)
        $preferredLunchStartsByAgent = [];
        
        foreach ($assignments as $assignment) {
            $agentId = (int)($assignment['agent_id'] ?? 0);
            if ($agentId <= 0) {
                continue;
            }

            $start = $assignment['start'] ?? null;
            $end = $assignment['end'] ?? null;
            $activityName = $assignment['activity'] ?? null;
            
            if (!$start || !$end) {
                continue;
            }

            // Par défaut, le repas peut recouvrir l'activité; si une politique est définie, l'appliquer
            $allowLunch = true;
            if ($activityName && isset($lunchPolicyByActivity[$activityName])) {
                $allowLunch = $lunchPolicyByActivity[$activityName];
            }

            if (!isset($unavailableByAgent[$agentId])) {
                $unavailableByAgent[$agentId] = [];
            }

            $unavailableByAgent[$agentId][] = [
                'start' => $start,
                'end' => $end,
                'allow_lunch' => $allowLunch,
            ];

            // Calculer d'éventuels départs de repas "préférés" en fonction du mode de collage
            if ($activityName && isset($lunchAttachModeByActivity[$activityName])) {
                $mode = $lunchAttachModeByActivity[$activityName];
                if ($mode !== 'none') {
                    try {
                        $startTime = new \Cake\I18n\FrozenTime($start);
                        $endTime = new \Cake\I18n\FrozenTime($end);
                    } catch (\Exception $e) {
                        $startTime = null;
                        $endTime = null;
                    }
                    if ($startTime && $endTime) {
                        // Récupérer les paramètres globaux de déjeuner depuis les réglages WFM
                        // NB: on suppose que lunch_start_time/end_time sont déjà normalisés sur la journée.
                        // On utilise ici les mêmes valeurs que pour le solveur (lunchWindow dans generate()).
                        // Pour éviter une dépendance circulaire, on considère simplement 11h30-14h
                        // si les infos précises ne sont pas disponibles ici.
                        $lunchStartDefault = new \Cake\I18n\FrozenTime('11:30:00');
                        $lunchEndDefault = new \Cake\I18n\FrozenTime('14:00:00');

                        // Idéalement ces bornes devraient être passées en paramètre, mais pour rester simple,
                        // on ne filtre ici que grossièrement avec cette fenêtre par défaut.
                        $idealStart = null;
                        if ($mode === 'before') {
                            $idealStart = (clone $startTime)->subMinutes(60);
                        } elseif ($mode === 'after') {
                            $idealStart = clone $endTime;
                        }

                        if ($idealStart !== null) {
                            if ($idealStart >= $lunchStartDefault && $idealStart < $lunchEndDefault) {
                                $idealStr = $idealStart->format('H:i:s');
                                if (!isset($preferredLunchStartsByAgent[$agentId])) {
                                    $preferredLunchStartsByAgent[$agentId] = [];
                                }
                                if (!in_array($idealStr, $preferredLunchStartsByAgent[$agentId], true)) {
                                    $preferredLunchStartsByAgent[$agentId][] = $idealStr;
                                }
                            }
                        }
                    }
                }
            }
        }

        // Mettre à jour les agents
        $updatedAgents = [];
        foreach ($agents as $agent) {
            $agentId = (int)($agent['id'] ?? 0);
            $updatedAgent = $agent;
            
            if (isset($unavailableByAgent[$agentId]) && !empty($unavailableByAgent[$agentId])) {
                $updatedAgent['unavailable_intervals'] = $unavailableByAgent[$agentId];
            } else {
                $updatedAgent['unavailable_intervals'] = null;
            }
            if (isset($preferredLunchStartsByAgent[$agentId]) && !empty($preferredLunchStartsByAgent[$agentId])) {
                $updatedAgent['preferred_lunch_starts'] = $preferredLunchStartsByAgent[$agentId];
            } else {
                $updatedAgent['preferred_lunch_starts'] = null;
            }
            
            $updatedAgents[] = $updatedAgent;
        }

        return $updatedAgents;
    }
}
