<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\I18n\FrozenDate;
use Cake\I18n\FrozenTime;
use Cake\ORM\Locator\LocatorAwareTrait;
use App\Service\Equity\JobPeriodEquityScoresProvider;
use App\Service\OfferGroups\EquityBucketsMigrator;
use App\Service\Rotation\RotationProblemBuilderService;
use App\Service\Rotation\RotationTargetCalculatorService;

/**
 * Génère un planning pour une journée dans le contexte d'un job (brouillon).
 *
 * Endpoints Python :
 * - /api/v1/solve-fixed-activities (Passe 1)
 * - /api/v1/solve-schedule (Passe 2)
 *
 * Les segments sont sauvegardés dans planning_range_drafts (alias DraftRanges).
 */
class ScheduleDayGenerationService
{
    use LocatorAwareTrait;

    /**
     * @return array{status:string, report_json:?string, error_message:?string}
     */
    public function generateDayForJob(int $jobId, FrozenDate $date): array
    {
        $Jobs = $this->fetchTable('PlanningGenerationJobs');
        $Days = $this->fetchTable('PlanningGenerationJobDays');
        $DraftRanges = $this->fetchTable('DraftRanges');
        $WfmSettings = $this->fetchTable('WfmSettings');
        $Offers = $this->fetchTable('Offers');
        $Ranges = $this->fetchTable('Ranges');

        $job = $Jobs->get($jobId);
        $settings = $WfmSettings->get((int)$job->wfm_setting_id, ['contain' => ['PauseOffers', 'LunchOffers']]);

        // Timeouts solveurs dynamiques (depuis wfm_settings.solver_settings_json)
        $solverSettings = $settings->solver_settings_json;
        $solverTimeoutGlobal = (int)($solverSettings['global'] ?? 300);
        $solverTimeoutPass1 = (int)($solverSettings['pass1'] ?? 60);
        $solverTimeoutPass1_5 = (int)($solverSettings['pass1_5'] ?? 30);
        $solverTimeoutPass2 = (int)($solverSettings['pass2'] ?? 195);

        // Étape 1: Préparation
        $Jobs->updateAll(
            ['current_step' => 'preparation_donnees', 'modified' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')],
            ['id' => $jobId],
        );

        $options = [];
        if (!empty($job->options_json)) {
            $decoded = json_decode((string)$job->options_json, true);
            if (is_array($decoded)) {
                $options = $decoded;
            }
        }
        $ignoreFixedActivities = !empty($options['ignore_fixed_activities'] ?? false);
        $ignoreRotation = !empty($options['ignore_rotation'] ?? false);
        $ignoreForecastSolver = !empty($options['ignore_forecast_solver'] ?? false);
        $debugSolvers = !empty($options['debug_solvers'] ?? false);
        $agentIds = array_map('intval', $options['agent_ids'] ?? []);

        $scenarioId = (int)($job->scenario_id ?? 0);

        // Paramètres journée (utilisés par la Passe 2, et pour fin anticipée)
        $strictWork = $settings->strict_work_hours === null ? true : (bool)$settings->strict_work_hours;

        // --- Préparation unifiée (service partagé avec la génération 1-jour) ---
        $dateToCalc = new FrozenTime($date->format('Y-m-d'));
        $dateStr = $date->format('Y-m-d');
        $builder = new ScheduleProblemBuilderService();

        // État d'équité du job (période générée) - décodage AVANT build (pour provider)
        $equityState = [];
        $equityStateActivities = [];
        $equityStateForecastables = [];
        $legacyGlobalEquity = [];
        if (!empty($job->equity_state_json)) {
            $decoded = json_decode((string)$job->equity_state_json, true);
            if (is_array($decoded)) {
                $equityState = $decoded;
            }
        }
        if (isset($equityState['activities']) && is_array($equityState['activities'])) {
            $equityStateActivities = $equityState['activities'];
            if (isset($equityState['forecastables']) && is_array($equityState['forecastables'])) {
                $equityStateForecastables = $equityState['forecastables'];
            }
        } else {
            foreach ($equityState as $k => $v) {
                if (is_numeric($k)) {
                    $legacyGlobalEquity[(int)$k] = (int)$v;
                }
            }
        }
        // Cibles cumulées (J0…Jn) pour aligner scope avec currentRealization
        $cumulativeTargets = isset($equityState['cumulative_targets']) && is_array($equityState['cumulative_targets'])
            ? $equityState['cumulative_targets']
            : [];

        $equityProvider = new JobPeriodEquityScoresProvider();
        $build = $builder->build(
            $dateToCalc,
            $settings,
            $scenarioId,
            $options,
            $equityProvider,
            ['activities' => $equityStateActivities, 'legacy_global' => $legacyGlobalEquity],
        );

        $needCurve = $build['need_curve'];
        $offerGroupsPayload = $build['offer_groups'] ?? [];
        $offerEquityBuckets = $build['offer_equity_buckets'] ?? [];
        $offerGroupsMeta = $build['offer_groups_meta'] ?? [];

        // Migration equity buckets (idempotente) — avant Passe 2, persistée immédiatement
        $equityStateForMigrate = [
            'activities' => $equityStateActivities,
            'forecastables' => $equityStateForecastables,
            'cumulative_targets' => $cumulativeTargets,
        ];
        if (isset($equityState[EquityBucketsMigrator::VERSION_KEY])) {
            $equityStateForMigrate[EquityBucketsMigrator::VERSION_KEY] =
                $equityState[EquityBucketsMigrator::VERSION_KEY];
        }
        $equityMigration = (new EquityBucketsMigrator())->migrateState(
            $equityStateForMigrate,
            $offerGroupsMeta,
        );
        $equityState = $equityMigration['state'];
        $equityStateActivities = is_array($equityState['activities'] ?? null)
            ? $equityState['activities']
            : $equityStateActivities;
        $equityStateForecastables = is_array($equityState['forecastables'] ?? null)
            ? $equityState['forecastables']
            : [];
        $cumulativeTargets = is_array($equityState['cumulative_targets'] ?? null)
            ? $equityState['cumulative_targets']
            : $cumulativeTargets;
        $equityBucketsVersion = (int)($equityState[EquityBucketsMigrator::VERSION_KEY] ?? EquityBucketsMigrator::VERSION);

        if (!empty($equityMigration['migrated'])) {
            $job->equity_state_json = json_encode([
                'activities' => $equityStateActivities,
                'forecastables' => $equityStateForecastables,
                'cumulative_targets' => $cumulativeTargets,
                EquityBucketsMigrator::VERSION_KEY => $equityBucketsVersion,
            ], JSON_UNESCAPED_UNICODE);
            $Jobs->saveOrFail($job);
        }

        if (empty($needCurve)) {
            // Réinitialiser current_step en cas d'erreur
            $Jobs->updateAll(
                ['current_step' => null, 'modified' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')],
                ['id' => $jobId],
            );
            return [
                'status' => 'error',
                'report_json' => null,
                'error_message' => 'Aucun besoin calculé pour cette date.',
            ];
        }

        $workdayStart = $build['workday_start_time'];
        $workdayEnd = $build['workday_end_time'];
        $enableAmPmBreaks = $build['enable_am_pm_breaks'];
        $forbidMiddaySingletons = $build['forbid_midday_singletons'];
        $amBreakWindow = $build['am_break_window'];
        $pmBreakWindow = $build['pm_break_window'];
        $lunchWindow = $build['lunch_window'];
        $breakDurationMinutes = $build['break_duration_minutes'];
        $lunchDurationMinutes = $build['lunch_duration_minutes'];
        $agentsForJson = $build['agents'];
        $fixedActivities = $build['fixed_activities'];
        $equityScoresFromProvider = $build['fixed_equity_scores'] ?? [];

        if (empty($agentsForJson)) {
            // Réinitialiser current_step en cas d'erreur
            $Jobs->updateAll(
                ['current_step' => null, 'modified' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')],
                ['id' => $jobId],
            );
            return [
                'status' => 'error',
                'report_json' => null,
                'error_message' => 'Aucun agent disponible.',
            ];
        }

        // --- 5) HTTP client vers service Python ---
        $solverUrl = Configure::read('PythonSolver.url', 'http://127.0.0.1:8000');
        $http = new Client(['timeout' => $solverTimeoutGlobal]);

        $fixedActivityAssignments = [];
        $fixedActivityShortfalls = [];
        $updatedAgentsForPasse2 = $agentsForJson;
        $fixedPass1 = [
            'attempted' => false,
            'http_status' => null,
            'status' => null,
            'error' => null,
        ];

        // Passe 1
        if (!$ignoreFixedActivities) {
            // Étape 2: Passe 1 - Activités fixes
            $Jobs->updateAll(
                ['current_step' => 'passe1_activites_fixes', 'modified' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')],
                ['id' => $jobId],
            );

            // Ciblage global + rétroaction (currentRealization cumulé vs cible cumulée)
            $fixedBuilder = new FixedActivitiesBuilderService();
            $equityMeta = $fixedBuilder->getOfferNameToEquityGroupMap($dateStr);
            $userSiteById = [];
            foreach ($agentsForJson as $ag) {
                $userSiteById[(int)($ag['id'] ?? 0)] = (string)($ag['site'] ?? '');
            }
            $globalRealization = $this->buildCurrentRealizationFromEquityState(
                $equityStateActivities,
                $equityMeta['offer_to_group'],
                $equityMeta['group_mode'],
                $userSiteById,
            );
            $fixedOptions = [
                'wfm_setting_id' => (int)$job->wfm_setting_id,
                'scenario_id' => $scenarioId,
                'currentRealization' => $globalRealization,
            ] + $options;
            $fixedPayload = $fixedBuilder->build($dateStr, $fixedOptions);
            $fixedActivities = $fixedPayload['fixed_activities'] ?? [];

            if (empty($fixedActivities)) {
                $fixedPass1['attempted'] = false;
                $updatedAgentsForPasse2 = $this->enrichAgentsWithManualAbsences($agentsForJson, $dateToCalc);
            } else {
            $fixedPass1['attempted'] = true;
            $dailyTargets = $fixedPayload['daily_targets'] ?? [];
            foreach ($dailyTargets as $aid => $groups) {
                if (!is_array($groups)) {
                    continue;
                }
                foreach ($groups as $groupKey => $minutes) {
                    $cumulativeTargets[$aid][$groupKey] = ($cumulativeTargets[$aid][$groupKey] ?? 0) + (float)$minutes;
                }
            }
            $agentsForSolver = [];
            foreach ($fixedPayload['agents'] as $ag) {
                $agCopy = $ag;
                $aid = (int)($agCopy['id'] ?? 0);
                $agCopy['target_quota_minutes'] = $cumulativeTargets[$aid] ?? (object)[];
                $agentsForSolver[] = $agCopy;
            }

            // Injecter l'historique des compteurs par base_offer_name pour la diversité inter-jours
            // $equityStateActivities est déjà lu depuis equity_state_json (lignes 84-101)
            foreach ($agentsForSolver as &$agCopy) {
                $aid = (int)($agCopy['id'] ?? 0);
                $counts = [];
                foreach ($equityStateActivities as $offerName => $byAgent) {
                    if (is_array($byAgent) && isset($byAgent[$aid])) {
                        $counts[(string)$offerName] = (int)$byAgent[$aid];
                    }
                }
                if (!empty($counts)) {
                    $agCopy['current_offer_minutes'] = $counts;
                }
            }
            unset($agCopy);

            $agentsForJson = $agentsForSolver;
            $fixedProblem = [
                'agents' => $agentsForSolver,
                'fixed_activities' => $fixedPayload['fixed_activities'],
                'generation_date' => $dateStr,
                'timeout_seconds' => $solverTimeoutPass1,
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
            $resp = $http->post(
                $solverUrl . '/api/v1/solve-fixed-activities',
                json_encode($fixedProblem),
                ['type' => 'application/json'],
            );

            $fixedPass1['http_status'] = $resp->getStatusCode();
            $body = $resp->getStringBody();
            $solutionPasse1 = json_decode($body, true);
            if ($resp->getStatusCode() !== 200) {
                $fixedPass1['status'] = 'HTTP_' . $resp->getStatusCode();
                $fixedPass1['error'] = is_string($body) && $body !== '' ? mb_substr($body, 0, 500) : 'Erreur HTTP solver Passe 1.';
                // Passe 1 échoue: on continue quand même avec Passe 2 (sans indisponibilités fixes)
                $fixedActivityAssignments = [];
                $fixedActivityShortfalls = [];
            } elseif (!$solutionPasse1 || !in_array($solutionPasse1['status'] ?? '', ['OPTIMAL', 'FEASIBLE'], true)) {
                // Passe 1 échoue: on continue quand même avec Passe 2 (sans indisponibilités fixes)
                $fixedActivityAssignments = [];
                $fixedActivityShortfalls = [];
                $fixedPass1['status'] = is_array($solutionPasse1) ? (string)($solutionPasse1['status'] ?? 'INVALID') : 'INVALID';
                $fixedPass1['error'] = is_array($solutionPasse1) ? json_encode($solutionPasse1) : (is_string($body) ? mb_substr($body, 0, 500) : 'Réponse invalide');
            } else {
                $fixedActivityAssignments = $solutionPasse1['assignments'] ?? [];
                $fixedActivityShortfalls = $solutionPasse1['shortfalls'] ?? [];
                $fixedPass1['status'] = (string)($solutionPasse1['status'] ?? 'FEASIBLE');
                $fixedPass1['wall_time_seconds'] = $solutionPasse1['wall_time_seconds'] ?? null;
                $fixedPass1['objective_value'] = $solutionPasse1['objective_value'] ?? null;
                $fixedPass1['best_bound'] = $solutionPasse1['best_bound'] ?? null;

                // Maj équité période (Option 1): incrémenter en demi-journées/blocs (pas par créneau).
                // Pour chaque activité équitable, un agent prend +1 par bloc couvert ce jour.

                // Mapping offer_name virtuel (ex: "RDV - Site A") -> base_offer_name (ex: "RDV")
                // pour indexer equityStateActivities par le nom pur (sans suffixe de site)
                $activityToBaseName = [];
                foreach ($fixedActivities as $fa) {
                    if (!empty($fa['offer_name'])) {
                        $activityToBaseName[(string)$fa['offer_name']] = (string)($fa['base_offer_name'] ?? $fa['offer_name']);
                    }
                }

                $equitableActivityBlocks = [];
                // Durée globale (bornes start_time/end_time) par offer_name virtuel, en minutes.
                // Sert de fallback pour les activités NON splittables (aucun sous-bloc) :
                // sinon leur durée serait 0 et elles ne seraient jamais comptabilisées.
                $activityDurations = [];
                foreach ($fixedActivities as $fa) {
                    if (!empty($fa['period_equity_weight']) && !empty($fa['offer_name'])) {
                        $offerName = (string)$fa['offer_name'];
                        $blocks = $fa['blocks'] ?? null;
                        $equitableActivityBlocks[$offerName] = is_array($blocks) ? $blocks : null;
                    }
                    if (!empty($fa['offer_name']) && !empty($fa['start_time']) && !empty($fa['end_time'])) {
                        $s = $this->timeToMinutes((string)$fa['start_time']);
                        $e = $this->timeToMinutes((string)$fa['end_time']);
                        $activityDurations[(string)$fa['offer_name']] = ($e - $s + 1440) % 1440;
                    }
                }

                $covered = []; // [offer_name][agent_id][block_key] = true
                foreach ($fixedActivityAssignments as $as) {
                    $aid = (int)($as['agent_id'] ?? 0);
                    $activity = (string)($as['activity'] ?? '');
                    $start = (string)($as['start'] ?? '');
                    if ($aid <= 0 || $activity === '' || !isset($equitableActivityBlocks[$activity]) || $start === '') {
                        continue;
                    }

                    $blocks = $equitableActivityBlocks[$activity];
                    $blockKey = 'full';
                    if (is_array($blocks) && !empty($blocks)) {
                        $sMin = $this->timeToMinutes($start);
                        foreach ($blocks as $idx => $b) {
                            if (empty($b['start']) || empty($b['end'])) {
                                continue;
                            }
                            $bStart = $this->timeToMinutes((string)$b['start']);
                            $bEnd = $this->timeToMinutes((string)$b['end']);
                            if ($sMin >= $bStart && $sMin < $bEnd) {
                                $blockKey = (string)$idx;
                                break;
                            }
                        }
                    }
                    // Calculer la durée du bloc en minutes (modulo 24h pour vacations de nuit)
                    $blockDuration = 0;
                    if ($blockKey !== 'full' && is_array($blocks) && isset($blocks[(int)$blockKey])) {
                        $bStart = $this->timeToMinutes((string)$blocks[(int)$blockKey]['start']);
                        $bEnd   = $this->timeToMinutes((string)$blocks[(int)$blockKey]['end']);
                        $blockDuration = ($bEnd - $bStart + 1440) % 1440;
                    } else {
                        // Cas 'full' ou non-match : sommer la durée de tous les blocs
                        if (is_array($blocks)) {
                            foreach ($blocks as $b) {
                                if (!empty($b['start']) && !empty($b['end'])) {
                                    $bStart = $this->timeToMinutes((string)$b['start']);
                                    $bEnd   = $this->timeToMinutes((string)$b['end']);
                                    $blockDuration += ($bEnd - $bStart + 1440) % 1440;
                                }
                            }
                        }
                        // Activité non splittable (aucun sous-bloc) : fallback sur la durée globale
                        // de l'activité, sinon la durée resterait 0 et l'activité serait ignorée.
                        if ($blockDuration <= 0) {
                            $blockDuration = $activityDurations[$activity] ?? 0;
                        }
                    }
                    $covered[$activity][$aid][$blockKey] = $blockDuration;
                }

                // Mise à jour des compteurs d'équité pour les agents présents
                foreach ($covered as $activity => $byAgent) {
                    $baseName = $activityToBaseName[$activity] ?? $activity;
                    foreach ($byAgent as $aid => $blocksSet) {
                        $inc = is_array($blocksSet) ? array_sum($blocksSet) : 0;
                        if ($inc <= 0) {
                            continue;
                        }
                        if (!isset($equityStateActivities[$baseName]) || !is_array($equityStateActivities[$baseName])) {
                            $equityStateActivities[$baseName] = [];
                        }
                        $equityStateActivities[$baseName][$aid] = (int)($equityStateActivities[$baseName][$aid] ?? 0) + $inc;
                    }
                }

                $afterFixed = new AgentsAfterFixedActivitiesService();
                $updatedAgentsForPasse2 = $afterFixed->update($agentsForJson, $fixedActivityAssignments, $fixedActivities, $lunchWindow);
                
                // Enrichir les agents avec les absences manuelles et corriger window_end
                $updatedAgentsForPasse2 = $this->enrichAgentsWithManualAbsences($updatedAgentsForPasse2, $dateToCalc);

                // Nettoyer current_offer_minutes (utilisé uniquement par la Passe 1)
                // sinon rejeté par le modèle Pydantic strict de /api/v1/solve-schedule
                foreach ($updatedAgentsForPasse2 as &$agP2) {
                    unset($agP2['current_offer_minutes']);
                }
                unset($agP2);
            }
            } // fin activités fixes non vides
        } else {
            // Même si Passe 1 est ignorée, enrichir les agents avec les absences manuelles
            $updatedAgentsForPasse2 = $this->enrichAgentsWithManualAbsences($agentsForJson, $dateToCalc);
        }

        // --- LOG DE SYNTHÈSE PASSE 1 ---
        $totalFixedActivities = count($fixedActivities);
        if ($totalFixedActivities > 0) {
            // Compter les agents uniques concernés via les assignations (si Passe 1 réussie)
            $uniqueAgentsCount = 0;
            if (!empty($fixedActivityAssignments)) {
                // ATTENTION : ne pas nommer cette variable $agentIds, qui est déjà utilisée
                // pour la sélection manuelle d'agents (filtre agent_ids) et est lue plus loin
                // par la logique de la Passe 1.5 (rotation).
                $fixedActivityAgentIds = [];
                foreach ($fixedActivityAssignments as $assignment) {
                    $agentId = (int)($assignment['agent_id'] ?? 0);
                    if ($agentId > 0) {
                        $fixedActivityAgentIds[$agentId] = true;
                    }
                }
                $uniqueAgentsCount = count($fixedActivityAgentIds);
            }
            
            if ($uniqueAgentsCount > 0) {
                \Cake\Log\Log::info("🔒 [PASSE 1] Activités Fixes : {$totalFixedActivities} activités intégrées pour {$uniqueAgentsCount} agents.");
            } else {
                // Passe 1 non exécutée ou échouée, on compte juste les activités
                \Cake\Log\Log::info("🔒 [PASSE 1] Activités Fixes : {$totalFixedActivities} activités détectées (non assignées).");
            }
        } else {
            \Cake\Log\Log::info("ℹ️ [PASSE 1] Aucune activité fixe (réunion/absence) détectée.");
        }

        // --- Passe 1.5 : Rotation et équité ---
        // On définit la semaine complète pour avoir une vision globale
        $weekStart = $date->startOfWeek();
        $weekEnd = $date->endOfWeek();
        
        // CORRECTION SCOPE : On cherche tous les users éligibles à la rotation,
        // même ceux qui sont absents le jour de lancement (ex: congé le Lundi).
        // On ne se base plus sur $agentsForJson (qui exclut les absents du jour).
        
        $usersTable = $this->fetchTable('Users');
        $rotationRuleQuery = $usersTable->find('activeInPeriod', [
                'period' => [
                    'begin' => $weekStart,
                    'end' => $weekEnd
                ]
            ])
            ->innerJoinWith('UsersRotationRule') // Jointure : On ne garde que ceux qui ont une règle
            ->select(['Users.id']);

        if (!empty($agentIds)) {
            $rotationRuleQuery->where(['Users.id IN' => $agentIds]);
        }

        // Récupération des IDs de tous les utilisateurs avec une règle de rotation
        $userIds = $rotationRuleQuery->all()->extract('id')->toArray();

        if (empty($userIds)) {
            // Fallback : si aucun utilisateur avec règle de rotation, on utilise le scope du jour
            \Cake\Log\Log::warning("[FIX SCOPE] ⚠️ Aucun agent avec règle de rotation trouvé. Retour au fallback journalier.");
            $userIds = array_column($agentsForJson, 'id');
        } else {
            \Cake\Log\Log::debug("[FIX SCOPE] ✅ " . count($userIds) . " agents trouvés via UsersRotationRule (Scope Global).");
        }
        $Users = $this->fetchTable('Users');
        $usersForRotationQuery = $Users->find('activeInPeriod', [
                'period' => [
                    'begin' => $weekStart,
                    'end' => $weekEnd
                ]
            ])
            ->contain([
                'UsersRotationRule',
                'UserContracts',
                'Skills',
            ])
            ->where(['Users.id IN' => $userIds]);

        if (!empty($agentIds)) {
            $usersForRotationQuery->where(['Users.id IN' => $agentIds]);
        }

        $usersForRotation = $usersForRotationQuery->all();

        // Optimisation : On ne lance le solveur de rotation qu'une seule fois par semaine
        // (Le Lundi, ou le tout premier jour du job si on commence en milieu de semaine)
        $isFirstJobDay = $job->start_date->equals($date);
        $shouldRunRotationSolver = $date->equals($weekStart) || $isFirstJobDay;

        if ($debugSolvers) {
            \Cake\Log\Log::debug(sprintf(
                '[ROTATION] conditions: date=%s weekStart=%s isFirstJobDay=%s shouldRun=%s ignoreRotation=%s nbUsers=%d',
                $date->format('Y-m-d'),
                $weekStart->format('Y-m-d'),
                $isFirstJobDay ? '1' : '0',
                $shouldRunRotationSolver ? '1' : '0',
                $ignoreRotation ? '1' : '0',
                count($usersForRotation)
            ));
        }
        if (!$shouldRunRotationSolver) {
            \Cake\Log\Log::info('[ROTATION] Passe 1.5 non lancée (ni lundi, ni premier jour du job).');
        }
        if ($ignoreRotation) {
            \Cake\Log\Log::info('[ROTATION] Passe 1.5 ignorée (option ignore_rotation).');
        }

        // Initialisation des résultats de rotation (même si la passe est sautée)
        $rotationBlocks = [];
        $rotationPass = [
            'attempted' => false,
            'status' => null,
            'nb_blocks' => 0,
            'error' => null,
            'shortfalls' => [],
        ];

        if ($shouldRunRotationSolver && !$ignoreRotation) {
            $rotationAgents = [];
            
            // Initialiser le calculateur de rotation avec les paramètres de journée
            $rotationCalculator = new RotationTargetCalculatorService();
            
            // Configurer les bornes de journée depuis wfm_settings
            // Permet de détecter les absences "journée complète" (ex: congés) vs partielles (ex: réunions)
            $dayStart = $settings->day_start_time instanceof \DateTimeInterface
                ? $settings->day_start_time->format('H:i:s')
                : (string)($settings->day_start_time ?? '09:00:00');
            $dayEnd = $settings->day_end_time instanceof \DateTimeInterface
                ? $settings->day_end_time->format('H:i:s')
                : (string)($settings->day_end_time ?? '17:00:00');
            $rotationCalculator->setDayBoundaries($dayStart, $dayEnd);
            
            if ($debugSolvers) {
                \Cake\Log\Log::debug(sprintf(
                    '[ROTATION] scope: nbUserIds=%d ids=%s',
                    count($userIds),
                    $this->truncateForLog(json_encode(array_values($userIds)))
                ));
            }

            $rotationLines = [];
            $rotationExclusiveDay = true;
            $usersForRotationArray = $usersForRotation->toArray();
            $builtRotation = (new RotationProblemBuilderService())->build(
                $usersForRotationArray,
                $weekStart,
                $weekEnd,
                $jobId,
                [],
                $rotationCalculator,
                is_array($lunchWindow) ? $lunchWindow : ['start' => null, 'end' => null],
                (int)($lunchDurationMinutes ?? 0),
                $Ranges,
                $DraftRanges,
                $fixedActivityAssignments,
                $date instanceof FrozenDate ? $date : new FrozenDate($date->format('Y-m-d')),
                $debugSolvers,
                fn($s) => $this->truncateForLog((string)$s),
                fn($t) => $this->timeToMinutes((string)$t),
            );
            $rotationLines = $builtRotation['lines'];
            $rotationExclusiveDay = (bool)$builtRotation['exclusive_day'];
            if (!empty($rotationLines)) {
                $rotationAgents = $builtRotation['agents'];
                \Cake\Log\Log::info('[ROTATION] Payload multi-lignes : ' . count($rotationLines) . ' lignes, ' . count($rotationAgents) . ' agents.');
            }

            if (empty($rotationLines)) {
            // --- SHUFFLE : Mélanger l'ordre des agents pour éviter les biais systématiques ---
            // Convertir la collection en tableau pour pouvoir utiliser shuffle()
            $usersForRotationArray = $usersForRotation->toArray();
            shuffle($usersForRotationArray);
            if ($debugSolvers) {
                \Cake\Log\Log::debug(sprintf(
                    '[ROTATION] ordre après shuffle: %s',
                    $this->truncateForLog(json_encode(array_column($usersForRotationArray, 'id')))
                ));
            }

            // 1. Calcul des cibles pour la semaine entière
            foreach ($usersForRotationArray as $user) {
                // Skip silencieux des agents sans règle de rotation (évite de polluer les logs)
                if (empty($user->users_rotation_rule)) {
                    continue;
                }

                // IMPORTANT : On passe la semaine entière ($weekStart, $weekEnd)
                // Cela permet au calculateur de voir "5 jours ouvrés" et de renvoyer la cible complète (ex: 3)
                
                // Trouver le contrat actif pour cette période
                $activeContract = null;
                if (!empty($user->user_contracts)) {
                    foreach ($user->user_contracts as $contract) {
                        // On cherche un contrat qui chevauche la semaine
                        $cStart = $contract->start_date;
                        $cEnd = $contract->end_date;
                        
                        // Chevauchement : start <= weekEnd AND (end IS NULL OR end >= weekStart)
                        $overlaps = $cStart <= $weekEnd && ($cEnd === null || $cEnd >= $weekStart);
                        
                        if ($overlaps) {
                            $activeContract = $contract;
                            break; // On prend le premier trouvé (simplification, le finder garantit l'existence)
                        }
                    }
                }

                $targetSlots = $rotationCalculator->calculateTargetForUser(
                    $user->id,
                    $user->users_rotation_rule->rotation_rule_id,
                    $weekStart, 
                    $weekEnd,
                    $activeContract ? $activeContract->start_date : null,
                    $activeContract ? $activeContract->end_date : null
                );

                // Application de l'override manuel s'il existe
                if ($user->users_rotation_rule->target_count_override !== null) {
                    $targetSlots = $user->users_rotation_rule->target_count_override;
                }

                if ($debugSolvers) {
                    \Cake\Log\Log::debug(sprintf(
                        '[ROTATION] agent=%d rule=%s target=%d override=%s',
                        $user->id,
                        $user->users_rotation_rule->rotation_rule_id,
                        $targetSlots,
                        $user->users_rotation_rule->target_count_override !== null
                            ? (string)$user->users_rotation_rule->target_count_override
                            : 'non'
                    ));
                }

                if ($targetSlots > 0) {
                    $rule = $this->fetchTable('RotationRules')->get($user->users_rotation_rule->rotation_rule_id);
                    
                    // --- HISTOIRE D'ÉQUITÉ (Feedback Loop) ---
                    // Au lieu de lire l'historique "réel" (Ranges), on lit les DraftRanges générés
                    // par ce job dans les semaines précédentes pour créer une rotation interne
                    $DraftRanges = $this->fetchTable('DraftRanges');
                    $pastRanges = $DraftRanges->find()
                        ->select(['date_start'])
                        ->where([
                            'job_id' => $jobId,
                            'user_id' => $user->id,
                            'date_start <' => $weekStart->format('Y-m-d 00:00:00'),
                            'source' => 'ROTATION', // Optionnel mais plus propre : on regarde uniquement les blocs de rotation
                        ])
                        ->all();
                    
                    // Extraire les indices de jour de la semaine (0=Lundi, 6=Dimanche)
                    $historyIndices = [];
                    foreach ($pastRanges as $range) {
                        $rangeDate = $range->date_start;
                        if ($rangeDate instanceof \DateTimeInterface) {
                            // format('N') retourne 1=Lundi, 7=Dimanche
                            // Conversion en 0=Lundi, 6=Dimanche pour Python
                            $dayOfWeek = (int)$rangeDate->format('N') - 1;
                            $historyIndices[] = $dayOfWeek;
                        }
                    }

                    // Sécurisation des formats d'heure (String vs Objet)
                    $wStart = $rule->time_window_start;
                    $wEnd = $rule->time_window_end;
                    
                    // --- NOUVEAU : Récupération des indisponibilités pour la semaine ---
                    // Format : { day_index: [{start: "HH:MM:SS", end: "HH:MM:SS"}, ...], ... }
                    // où day_index = 0 (Lundi) à 6 (Dimanche)
                    $unavailableByDay = [];
                    
                    // A. Récupérer les Ranges existants en base (absences, réunions) pour la semaine
                    $userRangesForRotation = $Ranges->find()
                        ->where([
                            'user_id' => $user->id,
                            'date_start <=' => $weekEnd->format('Y-m-d 23:59:59'),
                            'date_end >=' => $weekStart->format('Y-m-d 00:00:00'),
                        ])
                        ->contain(['Offers'])
                        ->all();
                    
                    foreach ($userRangesForRotation as $r) {
                        $type = strtolower($r->offer->offer_type ?? 'unknown');

                        // Seuls les evenements de type absence/meeting (conges, reunions, formations...) bloquent la rotation.
                        // Le teletravail ou autres types ne doivent pas empecher la planification.
                        if ($type === 'absence' || $type === 'meeting') {
                            $rangeDate = $r->date_start instanceof \DateTimeInterface 
                                ? $r->date_start 
                                : new FrozenTime($r->date_start);
                            $rangeEndDate = $r->date_end instanceof \DateTimeInterface 
                                ? $r->date_end 
                                : new FrozenTime($r->date_end);

                            // Calculer l'index du jour (0=Lundi, 6=Dimanche)
                            $dayIndex = (int)$rangeDate->format('N') - 1;

                            if (!isset($unavailableByDay[$dayIndex])) {
                                $unavailableByDay[$dayIndex] = [];
                            }
                            $unavailableByDay[$dayIndex][] = [
                                'start' => $rangeDate->format('H:i:s'),
                                'end' => $rangeEndDate->format('H:i:s'),
                            ];
                        }
                    }

                    // B. Récupérer les assignations Passe 1 (Activités Fixes) pour le jour courant
                    // Note: $fixedActivityAssignments ne contient que les assignations du jour $date
                    if (!empty($fixedActivityAssignments)) {
                        $currentDayIndex = (int)$date->format('N') - 1;

                        foreach ($fixedActivityAssignments as $assignment) {
                            if ((int)($assignment['agent_id'] ?? 0) !== (int)$user->id) {
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

                    if ($debugSolvers) {
                        $nbIndispoDays = count($unavailableByDay);
                        $nbIndispoIntervals = 0;
                        foreach ($unavailableByDay as $intervals) {
                            $nbIndispoIntervals += count($intervals);
                        }
                        \Cake\Log\Log::debug(sprintf(
                            '[ROTATION] agent=%d historyDays=%d indispoDays=%d indispoIntervals=%d',
                            $user->id,
                            count($historyIndices),
                            $nbIndispoDays,
                            $nbIndispoIntervals
                        ));
                        if ($nbIndispoDays > 0) {
                            $joursDisponiblesTheoriques = 5 - $nbIndispoDays;
                            if ($targetSlots > $joursDisponiblesTheoriques) {
                                \Cake\Log\Log::warning(sprintf(
                                    '[ROTATION] agent=%d cible=%d > joursDispoEstimés=%d (indispoDays=%d)',
                                    $user->id,
                                    $targetSlots,
                                    $joursDisponiblesTheoriques,
                                    $nbIndispoDays
                                ));
                            }
                        }
                    }

                    $rotationAgents[] = [
                        'id' => $user->id,
                        'offer_id' => $rule->offer_id,
                        'target_slots' => $targetSlots,
                        'duration' => $rule->shift_duration,
                        // Si c'est un objet (FrozenTime), on formate. Sinon on utilise la chaîne telle quelle.
                        'window_start' => ($wStart instanceof \DateTimeInterface) ? $wStart->format('H:i:s') : (string)$wStart,
                        'window_end' => ($wEnd instanceof \DateTimeInterface) ? $wEnd->format('H:i:s') : (string)$wEnd,
                        'history_worked_days' => $historyIndices,
                        // --- CORRECTION : Injection des contraintes de pause ---
                        'lunch_window_start' => $lunchWindow['start'] ?? null,
                        'lunch_window_end' => $lunchWindow['end'] ?? null,
                        'lunch_duration' => (int)($lunchDurationMinutes ?? 0),
                        // --- NOUVEAU : Indisponibilités par jour de la semaine ---
                        // Cast en (object) pour forcer JSON {} au lieu de [] quand les clés sont 0,1,2...
                        'unavailable_by_day' => !empty($unavailableByDay) ? (object)$unavailableByDay : null,
                    ];
                } else {
                    if ($debugSolvers) {
                        \Cake\Log\Log::debug(sprintf('[ROTATION] agent=%d ignoré (target<=0)', $user->id));
                    }
                }
            }
            } // fin fallback sans lignes

            \Cake\Log\Log::info("[ROTATION] 🚀 Envoi de " . count($rotationAgents) . " agents au solveur...");

            // 2. Appel au Solveur Python (si des agents sont concernés)
            if (!empty($rotationAgents)) {
                // Traduction de la need_curve (Nom -> ID) pour le solveur de Rotation
                $offerMapNameId = $Offers->find('list', ['keyField' => 'name', 'valueField' => 'id'])->toArray();
                $needCurveById = [];
                foreach ($needCurve as $name => $curve) {
                    if (isset($offerMapNameId[$name])) {
                        $offerId = $offerMapNameId[$name];
                        // CORRECTION : array_values() force le format JSON [ ... ] au lieu de { ... }
                        $needCurveById[$offerId] = array_values($curve); 
                    }
                }
                                
                $rotationPayload = [
                    'date' => $weekStart->format('Y-m-d'), // Toujours caler la grille sur le Lundi
                    'agents' => $rotationAgents,
                    'slot_minutes' => 15,
                    'need_curve' => $needCurveById, // Courbe de besoin indexée par ID d'offre
                    'timeout_seconds' => $solverTimeoutPass1_5,
                    'exclusive_day' => $rotationExclusiveDay ?? true,
                ];
                if (!empty($rotationLines)) {
                    $rotationPayload['lines'] = $rotationLines;
                }

                if ($debugSolvers) {
                    \Cake\Log\Log::debug(sprintf(
                        '[ROTATION] payload résumé: date=%s nbAgents=%d needOffers=%s',
                        $rotationPayload['date'],
                        count($rotationAgents),
                        $this->truncateForLog(json_encode(array_keys($needCurveById)))
                    ));
                    foreach ($rotationAgents as $agentDebug) {
                        $windowMinutes = ($this->timeToMinutes($agentDebug['window_end']) - $this->timeToMinutes($agentDebug['window_start']));
                        $nbIndispoDays = 0;
                        if (!empty($agentDebug['unavailable_by_day'])) {
                            $indispos = is_object($agentDebug['unavailable_by_day'])
                                ? (array)$agentDebug['unavailable_by_day']
                                : $agentDebug['unavailable_by_day'];
                            $nbIndispoDays = count($indispos);
                            foreach ($indispos as $dayIdx => $intervals) {
                                $totalBlocked = 0;
                                foreach ($intervals as $interval) {
                                    $totalBlocked += $this->timeToMinutes($interval['end']) - $this->timeToMinutes($interval['start']);
                                }
                                if (($windowMinutes - $totalBlocked) < $agentDebug['duration']) {
                                    \Cake\Log\Log::warning(sprintf(
                                        '[ROTATION] agent=%d jour=%s fenêtre insuffisante après indispos (reste=%d besoin=%d)',
                                        $agentDebug['id'],
                                        $dayIdx,
                                        $windowMinutes - $totalBlocked,
                                        $agentDebug['duration']
                                    ));
                                }
                            }
                        }
                        \Cake\Log\Log::debug(sprintf(
                            '[ROTATION] agent=%d target=%d window=%s-%s duration=%d indispoDays=%d',
                            $agentDebug['id'],
                            $agentDebug['target_slots'],
                            $agentDebug['window_start'],
                            $agentDebug['window_end'],
                            $agentDebug['duration'],
                            $nbIndispoDays
                        ));
                        if ($windowMinutes < $agentDebug['duration']) {
                            \Cake\Log\Log::warning(sprintf(
                                '[ROTATION] agent=%d fenêtre trop courte (%d min) pour shift %d min',
                                $agentDebug['id'],
                                $windowMinutes,
                                $agentDebug['duration']
                            ));
                        }
                    }
                }

                try {
                    $rotationPass['attempted'] = true;
                    $rotationSolverUrl = $solverUrl . '/api/v1/solve-rotation';
                    if ($debugSolvers) {
                        \Cake\Log\Log::debug('[ROTATION] POST ' . $rotationSolverUrl);
                    }
                    $response = $http->post(
                        $rotationSolverUrl,
                        json_encode($rotationPayload),
                        ['type' => 'json']
                    );

                    if ($debugSolvers) {
                        \Cake\Log\Log::debug('[ROTATION] HTTP status=' . $response->getStatusCode());
                    }

                    if ($response->getStatusCode() === 200) {
                        $result = $response->getJson();
                        if ($debugSolvers) {
                            $nbBlocksDebug = count($result['blocks'] ?? []);
                            \Cake\Log\Log::debug(sprintf(
                                '[ROTATION] réponse status=%s nbBlocks=%d',
                                (string)($result['status'] ?? 'UNKNOWN'),
                                $nbBlocksDebug
                            ));
                        }
                        
                        // Log de résultat (toujours affiché, pas seulement en debug)
                        $nbBlocks = count($result['blocks'] ?? []);
                        $rotationPass['nb_blocks'] = $nbBlocks;
                        if ($nbBlocks === 0) {
                            \Cake\Log\Log::warning("[ROTATION] ⚠️ Le solveur a renvoyé 0 shifts pour " . count($rotationAgents) . " agents demandés (Contraintes trop strictes ?).");
                        } else {
                            \Cake\Log\Log::info("[ROTATION] ✅ Le solveur a généré {$nbBlocks} shifts.");
                        }
                        
                        if (($result['status'] ?? 'ERROR') === 'FEASIBLE') {
                            $rotationPass['status'] = 'FEASIBLE';
                            $rotationPass['shortfalls'] = $result['shortfalls'] ?? [];
                            if ($debugSolvers) {
                                \Cake\Log\Log::debug("[ROTATION] Solution FEASIBLE trouvée, nombre de blocs: " . count($result['blocks'] ?? []));
                            }
                            // 3. Nettoyage Idempotent : On supprime les anciens brouillons DE LA SEMAINE ENTIÈRE
                            // pour ces utilisateurs, pour éviter les doublons si on relance le job.
                            $userIds = array_column($rotationAgents, 'id');
                            $DraftRanges->deleteAll([
                                'job_id' => $jobId,
                                'date_start >=' => $weekStart->format('Y-m-d 00:00:00'),
                                'date_end <=' => $weekEnd->format('Y-m-d 23:59:59'),
                                'source' => 'ROTATION',
                                'user_id IN' => $userIds
                            ]);

                            // 4. Sauvegarde des nouveaux blocs
                            $newDrafts = [];
                            foreach ($result['blocks'] as $block) {
                                // CORRECTION : Calcul de la date réelle basée sur le Lundi + day_index
                                $targetDay = $weekStart->addDays((int)($block['day_index'] ?? 0));
                                
                                // Construction des timestamps complets (Date + Heure)
                                $blockStart = new FrozenTime($targetDay->format('Y-m-d') . ' ' . $block['start']);
                                $blockEnd = new FrozenTime($targetDay->format('Y-m-d') . ' ' . $block['end']);
                                
                                $draft = $DraftRanges->newEmptyEntity();
                                $draft->job_id = $jobId;
                                $draft->user_id = $block['user_id'];
                                $draft->date_start = $blockStart;
                                $draft->date_end = $blockEnd;
                                $draft->offer_id = $block['offer_id']; // Peut être null
                                $draft->source = 'ROTATION'; // Marqueur crucial
                                $draft->is_locked = true;    // Visuel
                                $newDrafts[] = $draft;
                            }
                            
                            if ($debugSolvers) {
                                \Cake\Log\Log::debug("[ROTATION] Nombre d'entités \$newDrafts préparées: " . count($newDrafts));
                            }
                            
                            // Sauvegarde sécurisée avec gestion d'erreur explicite
                            try {
                                if (!empty($newDrafts)) {
                                    if ($debugSolvers) {
                                        \Cake\Log\Log::debug("[ROTATION] Tentative de sauvegarde de " . count($newDrafts) . " drafts...");
                                    }
                                    $saved = $DraftRanges->saveManyOrFail($newDrafts); // Lève une exception si erreur
                                    \Cake\Log\Log::info("[ROTATION] 💾 Sauvegarde réussie de " . count($saved) . " drafts en BDD.");
                                } else {
                                    \Cake\Log\Log::warning("[ROTATION] ⚠️ Aucun draft à sauvegarder (newDrafts est vide après traitement des blocs).");
                                }
                            } catch (\Exception $saveException) {
                                \Cake\Log\Log::error("[ROTATION] ❌ ÉCHEC SAUVEGARDE BDD : " . $saveException->getMessage());
                                // Dump des erreurs de validation si disponibles
                                if (!empty($newDrafts) && method_exists($newDrafts[0], 'getErrors')) {
                                    $errors = $newDrafts[0]->getErrors();
                                    if (!empty($errors)) {
                                        \Cake\Log\Log::error("[ROTATION] Erreurs de validation : " . $this->truncateForLog(json_encode($errors)));
                                    }
                                }
                            }
                        } else {
                            $rotationPass['status'] = (string)($result['status'] ?? 'UNKNOWN');
                            if ($debugSolvers) {
                                \Cake\Log\Log::debug("[ROTATION] Solution non FEASIBLE, status: " . ($result['status'] ?? 'UNKNOWN'));
                            }
                        }
                    } else {
                        $rotationBody = $response->getStringBody();
                        $rotationPass['status'] = 'HTTP_' . $response->getStatusCode();
                        $rotationPass['error'] = is_string($rotationBody) && $rotationBody !== ''
                            ? mb_substr($rotationBody, 0, 500)
                            : 'Erreur HTTP solver Passe 1.5.';
                        if ($debugSolvers) {
                            \Cake\Log\Log::debug("[ROTATION] Status Code != 200, réponse non valide");
                        }
                    }
                } catch (\Exception $e) {
                    \Cake\Log\Log::error('[ROTATION] Erreur appel Solveur Rotation : ' . $e->getMessage());
                    if ($debugSolvers) {
                        \Cake\Log\Log::debug('[ROTATION] Stack: ' . $this->truncateForLog($e->getTraceAsString(), 1000));
                    }
                    // Message générique côté rapport JSON : le détail technique (potentiellement sensible) reste dans les logs.
                    $rotationPass['status'] = 'EXCEPTION';
                    $rotationPass['error'] = 'Erreur technique lors de l\'appel au solveur de rotation (voir logs serveur).';
                    // On ne bloque pas le processus, on continue sans rotation (ou on throw selon sévérité voulue)
                }
            }
        } else {
            if ($ignoreRotation) {
                \Cake\Log\Log::info("[ROTATION] ✅ Passe 1.5 Rotation ignorée (option ignore_rotation activée).");
            } else {
                \Cake\Log\Log::debug("[ROTATION] Solveur de rotation non lancé (shouldRunRotationSolver = false, isFirstJobDay: " . ($isFirstJobDay ? 'true' : 'false') . ", date equals weekStart: " . ($date->equals($weekStart) ? 'true' : 'false') . ")");
            }
        }

// --- INJECTION POUR LA PASSE 2 (CRUCIAL) ---
        
        // 1. On récupère la map [ID => Nom] pour traduire les offres
        // (Car le Python Coverage parle en Noms ("Appels"), pas en IDs (12))
        $offersTable = $this->fetchTable('Offers');
        $offerMapIdName = $offersTable->find('list', ['keyField' => 'id', 'valueField' => 'name'])->toArray();

        // 2. On récupère les drafts de rotation du jour
        $todaysRotationDrafts = $DraftRanges->find()
            ->where([
                'job_id' => $jobId,
                'source' => 'ROTATION',
                'date_start >=' => $date->format('Y-m-d 00:00:00'),
                'date_end <=' => $date->format('Y-m-d 23:59:59'),
            ])
            ->all();

        // Initialisation des listes pour la préparation de la Passe 2
        $rotationFixedWorkPayload = []; // Pour envoyer au Python Coverage

        foreach ($todaysRotationDrafts as $draft) {
            // Conversion dates
            $dateStart = $draft->date_start instanceof \DateTimeInterface 
                ? $draft->date_start 
                : new FrozenTime($draft->date_start);
            $dateEnd = $draft->date_end instanceof \DateTimeInterface 
                ? $draft->date_end 
                : new FrozenTime($draft->date_end);
            
            // Traduction ID -> Nom
            $offerName = $offerMapIdName[$draft->offer_id] ?? null;

            // A. Remplir $rotationBlocks (Existant - on garde pour tes logs/affichage)
            $rotationBlocks[] = [
                'user_id' => $draft->user_id,
                'start' => $dateStart->format('Y-m-d H:i:s'),
                'end' => $dateEnd->format('Y-m-d H:i:s'),
                'offer_id' => $draft->offer_id,
                'offer_name' => $offerName // Ajout utile pour debug
            ];

            // B. Remplir le Payload pour Python (Nouveau format FixedWork)
            // C'est ce tableau qui va forcer la main au solveur Passe 2
            $rotationFixedWorkPayload[] = [
                'user_id' => $draft->user_id,
                'start'   => $dateStart->format('H:i:s'), // Format HH:MM:SS strict pour Python
                'end'     => $dateEnd->format('H:i:s'),
                'offer_name' => $offerName,               // <--- LA CLÉ : On envoie le NOM
                'type'    => 'ROTATION'
            ];
            
            // Log de vérification
            // $output->writeln("[PHP->PYTHON] Lock User {$draft->user_id} sur {$offerName}");
        }

        // Passe 2
        // Initialisation des résultats de la Passe 2 (même si la passe est sautée)
        $schedulePasse2 = [];
        $solverStatus = 'UNKNOWN';
        $solverMessage = null;
        $solverCoverageShortage = null;
        $solverAgentDiagnostics = null;
        $solverPass2Explanation = null;
        $periodEquityOffers = [];

        if (!$ignoreForecastSolver) {
            // EXCLUSION STRICTE : Retirer tous les agents avec rotation de la Passe 2
            $excludedAgentIds = [];
            foreach ($usersForRotation as $user) {
                if (!empty($user->users_rotation_rule)) {
                    $excludedAgentIds[] = (int)$user->id;
                }
            }
            
            if (!empty($excludedAgentIds)) {
                \Cake\Log\Log::debug("[ROTATION] Exclusion Passe 2 : " . count($excludedAgentIds) . " agents avec rotation exclus (IDs: " . json_encode($excludedAgentIds) . ")");
                $updatedAgentsForPasse2 = array_filter($updatedAgentsForPasse2, function($agent) use ($excludedAgentIds) {
                    $agentId = (int)($agent['id'] ?? 0);
                    return !in_array($agentId, $excludedAgentIds, true);
                });
                // Réindexer le tableau après filtrage
                $updatedAgentsForPasse2 = array_values($updatedAgentsForPasse2);
                \Cake\Log\Log::debug("[ROTATION] Nombre d'agents restants pour Passe 2 : " . count($updatedAgentsForPasse2));
            }
            
            // Étape 3: Passe 2 - Planning avec prévisions
            $Jobs->updateAll(
                ['current_step' => 'passe2_planning_previsions', 'modified' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')],
                ['id' => $jobId],
            );

            $forecastableOffers = array_keys($needCurve);
            // Équité "période" : offres equity_enabled, agrégées par bucket de groupe si applicable
            $equityOffers = $Offers->find()
                ->select(['name'])
                ->where(['is_forecastable' => 1, 'equity_enabled' => 1])
                ->all()
                ->extract('name')
                ->map(fn($v) => (string)$v)
                ->toList();

            $periodEquityOffers = [];
            $seenBuckets = [];
            foreach ($forecastableOffers as $offName) {
                $offName = (string)$offName;
                $bucket = (string)($offerEquityBuckets[$offName] ?? $offName);
                if (isset($seenBuckets[$bucket])) {
                    continue;
                }
                $bucketActive = in_array($offName, $equityOffers, true);
                if (!$bucketActive) {
                    foreach ($offerEquityBuckets as $otherOffer => $otherBucket) {
                        if ($otherBucket === $bucket && in_array((string)$otherOffer, $equityOffers, true)) {
                            $bucketActive = true;
                            break;
                        }
                    }
                }
                if ($bucketActive) {
                    $seenBuckets[$bucket] = true;
                    $periodEquityOffers[] = $bucket;
                }
            }

            $periodEquityScores = [];
            foreach ($periodEquityOffers as $bucket) {
                foreach ($updatedAgentsForPasse2 as $ag) {
                    $aid = (int)($ag['id'] ?? 0);
                    if ($aid <= 0) {
                        continue;
                    }
                    $periodEquityScores[$bucket][$aid] = (int)($equityStateForecastables[$bucket][$aid] ?? 0);
                }
            }

            // --- CALCUL DU BESOIN NET (AVEC LOGS DE PREUVE) ---
            // IMPORTANT: Le solveur attend need_curve[offre] = { "HH:MM:SS": valeur, ... } (dict, pas liste)
            $residualNeedCurve = [];
            foreach ($needCurve as $offerName => $curve) {
                // Cloner la courbe en préservant la structure de dictionnaire (clés horaires)
                if (is_array($curve)) {
                    $residualNeedCurve[$offerName] = [];
                    foreach ($curve as $timeKey => $value) {
                        $residualNeedCurve[$offerName][$timeKey] = $value;
                    }
                } else {
                    $residualNeedCurve[$offerName] = $curve;
                }
            }
            
            // Calculer le timestamp de début de journée (date + workdayStart)
            $startDayStr = $date->format('Y-m-d') . ' ' . $workdayStart;
            $startDayTs = strtotime($startDayStr);
            $totalSlotsDeducted = 0;
            $debugSampleShown = false; // Pour montrer un exemple en debug une seule fois
            
            if ($startDayTs === false) {
                \Cake\Log\Log::warning("[ROTATION] Impossible de parser la date de début: {$startDayStr}");
            } else {
                foreach ($rotationBlocks as $block) {
                    if (empty($block['offer_name']) || empty($block['start']) || empty($block['end'])) {
                        continue;
                    }
                    
                    $offerName = $block['offer_name'];
                    if (!isset($residualNeedCurve[$offerName]) || !is_array($residualNeedCurve[$offerName])) {
                        continue;
                    }
                    
                    // Calcul des timestamps
                    $blockStartTs = strtotime($block['start']);
                    $blockEndTs = strtotime($block['end']);
                    
                    // Si le bloc est hors journée ou invalide
                    if ($blockEndTs === false || $blockStartTs === false || $blockEndTs <= $blockStartTs) {
                        continue;
                    }
                    
                    // Générer toutes les clés horaires couvertes par le bloc (slots de 15 min)
                    $currentTs = $blockStartTs;
                    while ($currentTs < $blockEndTs) {
                        // Formater en "HH:MM:SS" pour correspondre aux clés de need_curve
                        $timeKey = date('H:i:s', $currentTs);
                        
                        // Décrémenter le besoin pour ce créneau (sans descendre sous 0)
                        if (isset($residualNeedCurve[$offerName][$timeKey]) && $residualNeedCurve[$offerName][$timeKey] > 0) {
                            // LOG DEBUG : Capture d'écran Avant/Après sur le premier slot modifié
                            if ($debugSolvers && !$debugSampleShown) {
                                $oldVal = $residualNeedCurve[$offerName][$timeKey];
                                $newVal = $oldVal - 1;
                                \Cake\Log\Log::debug("[DEBUG PREUVE] {$timeKey} ({$offerName}) : Besoin Brut {$oldVal} - 1 (Rotation) = Net {$newVal}");
                                $debugSampleShown = true;
                            }
                            
                            $residualNeedCurve[$offerName][$timeKey]--;
                            $totalSlotsDeducted++;
                        }
                        
                        // Passer au slot suivant (15 min = 900 secondes)
                        $currentTs += 900;
                    }
                }
                
                // LOG CONCLUSIF (Mode Normal)
                if ($totalSlotsDeducted > 0) {
                    $hoursSaved = $totalSlotsDeducted * 0.25; // 15 min = 0.25h
                    \Cake\Log\Log::info("[DÉDUCTION] {$totalSlotsDeducted} créneaux ({$hoursSaved} heures) de rotation ont été déduits du besoin initial.");
                } else {
                    \Cake\Log\Log::info("[DÉDUCTION] Aucun créneau de rotation n'a impacté la courbe de besoin (Pas de chevauchement ou offre différente).");
                }
            }

            // =====================================================================
            // ENRICHISSEMENT : INJECTION DES INDISPONIBILITÉS (Ranges)
            // =====================================================================
            // Python a besoin de connaitre les trous (réunions, pauses) explicitement.
            
            foreach ($updatedAgentsForPasse2 as $key => $agentData) {
                $agentId = (int)($agentData['id'] ?? 0);
                if ($agentId <= 0) {
                    continue;
                }
                
                $unavailable = $agentData['unavailable_intervals'] ?? [];
                if (!is_array($unavailable)) {
                    $unavailable = [];
                }
                
                // On récupère les absences/réunions planifiées en BDD
                $dbRanges = $Ranges->find()
                    ->where([
                        'user_id' => $agentId,
                        'date_start <=' => $dateStr . ' 23:59:59',
                        'date_end >=' => $dateStr . ' 00:00:00',
                    ])
                    ->contain(['Offers'])
                    ->all();

                foreach ($dbRanges as $range) {
                    // Seules les vraies absences et reunions (conges, formations...) bloquent la planification.
                    // Le teletravail ou autres types ne doivent pas empecher la Passe 2.
                    $type = strtolower($range->offer->offer_type ?? 'unknown');
                    
                    if ($type === 'absence' || $type === 'meeting') {
                        $unavailable[] = [
                            'start' => $range->date_start->format('H:i:s'),
                            'end' => $range->date_end->format('H:i:s'),
                            'allow_lunch' => true,
                        ];
                    }
                }
                
                // Mise à jour du tableau
                $updatedAgentsForPasse2[$key]['unavailable_intervals'] = !empty($unavailable) ? $unavailable : null;
            }
            // =====================================================================

            $wfmProblemPasse2 = [
                'offers' => array_values($forecastableOffers),
                'need_curve' => $residualNeedCurve, // Besoin net après déduction de la rotation
                'agents' => $updatedAgentsForPasse2,
                'timeout_seconds' => $solverTimeoutPass2,
                // Les agents de rotation étant exclus, fixed_work n'est plus nécessaire
                // 'fixed_work' => $rotationFixedWorkPayload, // SUPPRIMÉ : agents exclus
                'workday_start_time' => $workdayStart,
                'workday_end_time' => $workdayEnd,
                'slot_minutes' => 15,
                'strict_work_hours' => $strictWork,
                // Si fin anticipée autorisée, inciter à finir plus tôt dès qu'il y a de la marge.
                // La couverture reste prioritaire via weight_shortage beaucoup plus élevé côté solver (1000).
                'weight_early_end' => $strictWork ? 0 : 20,
                // TEST (temporaire): activer en dur l'équité intra‑journée (AM/PM) sur toutes les offres forecastables.
                // Objectif: favoriser la rotation matin/après‑midi plutôt que de laisser un agent sur la même offre toute la journée.
                'equity_offers' => array_values($forecastableOffers),
                'weight_equity' => 60,
                // Équité période Passe 2 (minutes): activée uniquement sur les offres configurées.
                'period_equity_offers' => $periodEquityOffers,
                'period_equity_scores' => empty($periodEquityScores) ? (object)[] : $periodEquityScores,
                'weight_period_equity' => !empty($periodEquityOffers) ? 20 : 0,
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
                'relative_gap_limit' => 0.01, // Stop si < 1% d'écart avec l'optimum
            ];
            if (!empty($offerGroupsPayload)) {
                $wfmProblemPasse2['offer_groups'] = array_values($offerGroupsPayload);
            }

            $resp2 = $http->post(
                $solverUrl . '/api/v1/solve-schedule',
                json_encode($wfmProblemPasse2),
                ['type' => 'application/json'],
            );
            $body2 = $resp2->getStringBody();
            $solution = json_decode($body2, true);

            if (is_array($solution)) {
                $solverStatus = (string)($solution['status'] ?? 'UNKNOWN');
                $solverMessage = $solution['message'] ?? $solution['detail'] ?? null;
                if (in_array($solverStatus, ['success', 'FEASIBLE', 'OPTIMAL'], true)) {
                    $schedulePasse2 = $solution['schedule'] ?? [];
                } else {
                    // Échec Passe 2 : on capture les diagnostics bruts du solveur (coverage + agents)
                    // pour permettre l'analyse a posteriori, sans les stocker sur les jours réussis.
                    $solverCoverageShortage = $this->extractCoverageShortage($solution['coverage'] ?? null);
                    $solverAgentDiagnostics = $solution['diagnostics']['agents'] ?? null;
                    // Mode diagnostic : explication d'infaisabilité (second solve OR-Tools côté Python).
                    if ($debugSolvers && isset($solution['diagnostics']['explanation']) && is_array($solution['diagnostics']['explanation'])) {
                        $solverPass2Explanation = $solution['diagnostics']['explanation'];
                    }
                }
                
                // Log de synthèse Passe 2
                $nbAgentsScheduled = 0;
                if (isset($solution['schedules_segments']) && is_array($solution['schedules_segments'])) {
                    // Compter le nombre d'agents uniques via les clés de schedules_segments
                    $nbAgentsScheduled = count($solution['schedules_segments']);
                } elseif (isset($solution['schedule']) && is_array($solution['schedule'])) {
                    // Fallback : compter les agent_id uniques dans schedule
                    // ATTENTION : ne pas nommer cette variable $agentIds (déjà utilisée pour le filtre
                    // de sélection manuelle d'agents), même si elle n'est plus relue après ce point.
                    $passe2ScheduledAgentIds = [];
                    foreach ($solution['schedule'] as $segment) {
                        if (isset($segment['agent_id'])) {
                            $passe2ScheduledAgentIds[(int)$segment['agent_id']] = true;
                        }
                    }
                    $nbAgentsScheduled = count($passe2ScheduledAgentIds);
                }
                
                $icon = (in_array($solverStatus, ['OPTIMAL', 'FEASIBLE', 'success'], true)) ? '✅' : '⚠️';
                \Cake\Log\Log::info("{$icon} [PASSE 2] Couverture : Solution {$solverStatus} | {$nbAgentsScheduled} agents planifiés.");
            } else {
                $solverStatus = 'ERROR';
                $solverMessage = 'Réponse solver non JSON';
                \Cake\Log\Log::warning("⚠️ [PASSE 2] Couverture : Erreur - Réponse solver non JSON.");
            }
        }

        // Segments Passe 1 en format schedule
        $scheduleFromPasse1 = [];
        // Collecter les pauses une seule fois par agent (éviter les doublons)
        // Structure: $breaksByAgent[$agentId]['am_break'] = ['start' => ..., 'end' => ...]
        $breaksByAgent = [];
        
        // Ajouter les blocs de rotation à $scheduleFromPasse1
        foreach ($rotationBlocks as $block) {
            $scheduleFromPasse1[] = [
                'agent_id' => (int)$block['user_id'],
                'start' => $block['start'],
                'end' => $block['end'],
                'label' => 'WORK',
                'offer' => $Offers->get((int)$block['offer_id'])->name ?? '',
            ];
        }
        
        foreach ($fixedActivityAssignments as $assignment) {
            if (!isset($assignment['agent_id'], $assignment['start'], $assignment['end'], $assignment['activity'])) {
                continue;
            }
            
            // Ajouter le segment WORK pour l'activité fixe
            $scheduleFromPasse1[] = [
                'agent_id' => (int)$assignment['agent_id'],
                'start' => (string)$assignment['start'],
                'end' => (string)$assignment['end'],
                'label' => 'WORK',
                'offer' => (string)$assignment['activity'],
            ];
            
            // Collecter les pauses si elles existent (une seule fois par agent)
            if (isset($assignment['breaks']) && is_array($assignment['breaks'])) {
                $agentId = (int)$assignment['agent_id'];
                if (!isset($breaksByAgent[$agentId])) {
                    $breaksByAgent[$agentId] = [];
                }
                
                $breaks = $assignment['breaks'];
                
                // Pause AM
                if (isset($breaks['am_break']) && is_array($breaks['am_break']) && count($breaks['am_break']) >= 2) {
                    if (!isset($breaksByAgent[$agentId]['am_break'])) {
                        $breaksByAgent[$agentId]['am_break'] = [
                            'start' => (string)$breaks['am_break'][0],
                            'end' => (string)$breaks['am_break'][1],
                        ];
                    }
                }
                
                // Pause PM
                if (isset($breaks['pm_break']) && is_array($breaks['pm_break']) && count($breaks['pm_break']) >= 2) {
                    if (!isset($breaksByAgent[$agentId]['pm_break'])) {
                        $breaksByAgent[$agentId]['pm_break'] = [
                            'start' => (string)$breaks['pm_break'][0],
                            'end' => (string)$breaks['pm_break'][1],
                        ];
                    }
                }
                
                // Lunch
                if (isset($breaks['lunch']) && is_array($breaks['lunch']) && count($breaks['lunch']) >= 2) {
                    if (!isset($breaksByAgent[$agentId]['lunch'])) {
                        $breaksByAgent[$agentId]['lunch'] = [
                            'start' => (string)$breaks['lunch'][0],
                            'end' => (string)$breaks['lunch'][1],
                        ];
                    }
                }
            }
        }
        
        // Convertir les pauses collectées en segments de planning
        foreach ($breaksByAgent as $agentId => $breaks) {
            // Pause AM
            if (isset($breaks['am_break']) && isset($breaks['am_break']['start'], $breaks['am_break']['end'])) {
                $scheduleFromPasse1[] = [
                    'agent_id' => $agentId,
                    'start' => $breaks['am_break']['start'],
                    'end' => $breaks['am_break']['end'],
                    'label' => 'AM_BREAK',
                ];
            }
            
            // Pause PM
            if (isset($breaks['pm_break']) && isset($breaks['pm_break']['start'], $breaks['pm_break']['end'])) {
                $scheduleFromPasse1[] = [
                    'agent_id' => $agentId,
                    'start' => $breaks['pm_break']['start'],
                    'end' => $breaks['pm_break']['end'],
                    'label' => 'PM_BREAK',
                ];
            }
            
            // Lunch
            if (isset($breaks['lunch']) && isset($breaks['lunch']['start'], $breaks['lunch']['end'])) {
                $scheduleFromPasse1[] = [
                    'agent_id' => $agentId,
                    'start' => $breaks['lunch']['start'],
                    'end' => $breaks['lunch']['end'],
                    'label' => 'LUNCH',
                ];
            }
        }

        $schedule = array_merge($scheduleFromPasse1, is_array($schedulePasse2) ? $schedulePasse2 : []);

        // Étape 4: Fusion des segments
        $Jobs->updateAll(
            ['current_step' => 'fusion_segments', 'modified' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')],
            ['id' => $jobId],
        );

        // Normaliser les segments pour que les pauses AM/PM/LUNCH remplacent les segments WORK des activités fixes
        $schedule = $this->normalizeScheduleNoOverlap($schedule);

        // Maj équité période forecastables (minutes) à partir de la Passe 2 (segments WORK)
        // Les minutes sont agrégées par bucket de groupe (pas par offre membre/mixte).
        if (!empty($periodEquityOffers) && is_array($schedulePasse2)) {
            foreach ($schedulePasse2 as $seg) {
                if (!is_array($seg) || ($seg['label'] ?? '') !== 'WORK') {
                    continue;
                }
                $off = (string)($seg['offer'] ?? '');
                if ($off === '') {
                    continue;
                }
                $bucket = (string)($offerEquityBuckets[$off] ?? $off);
                if (!in_array($bucket, $periodEquityOffers, true)) {
                    continue;
                }
                $aid = (int)($seg['agent_id'] ?? 0);
                if ($aid <= 0) {
                    continue;
                }
                $s = (string)($seg['start'] ?? '');
                $e = (string)($seg['end'] ?? '');
                if ($s === '' || $e === '') {
                    continue;
                }
                $min = max(0, $this->timeToMinutes($e) - $this->timeToMinutes($s));
                if ($min <= 0) {
                    continue;
                }
                if (!isset($equityStateForecastables[$bucket]) || !is_array($equityStateForecastables[$bucket])) {
                    $equityStateForecastables[$bucket] = [];
                }
                $equityStateForecastables[$bucket][$aid] =
                    (int)($equityStateForecastables[$bucket][$aid] ?? 0) + $min;
            }
        }

        // --- 6) Sauvegarde brouillon (DraftRanges) ---
        // Étape 5: Sauvegarde du brouillon
        $Jobs->updateAll(
            ['current_step' => 'sauvegarde_brouillon', 'modified' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')],
            ['id' => $jobId],
        );

        $DraftRanges->deleteAll([
            'job_id' => $jobId,
            'DATE(date_start)' => $dateStr,
            // CORRECTION CRITIQUE : On ne supprime pas les blocs 'ROTATION' pré-générés en début de semaine
            'OR' => [
                ['source !=' => 'ROTATION'],
                ['source IS' => null],
            ],
        ]);

        $offerMap = $Offers->find('list', ['keyField' => 'name', 'valueField' => 'id'])->toArray();
        $absenceOfferId = $Offers->find()
            ->select(['id'])
            ->where(['offer_type IN' => ['absence', 'meeting']])
            ->first()?->id;
        $pauseOfferId = $settings->pause_offer_id ?? $absenceOfferId;
        $lunchOfferId = $settings->lunch_offer_id ?? $absenceOfferId;

        $entities = [];
        foreach ($schedule as $seg) {
            if (!is_array($seg) || !isset($seg['agent_id'], $seg['start'], $seg['end'], $seg['label'])) {
                continue;
            }

            $label = (string)$seg['label'];
            $offerId = null;

            if ($label === 'WORK') {
                $workOfferName = (string)($seg['offer'] ?? '');
                if ($workOfferName === '') {
                    continue;
                }
                $offerId = $offerMap[$workOfferName] ?? null;
                if ($offerId === null && str_contains($workOfferName, ' - ')) {
                    $base = explode(' - ', $workOfferName, 2)[0];
                    $offerId = $offerMap[$base] ?? null;
                }
                if ($offerId === null) {
                    continue;
                }
            } elseif ($label === 'AM_BREAK' || $label === 'PM_BREAK') {
                $offerId = $pauseOfferId;
            } elseif ($label === 'LUNCH') {
                $offerId = $lunchOfferId;
            } else {
                continue;
            }

            if ($offerId === null) {
                continue;
            }

            $entities[] = $DraftRanges->newEntity([
                'job_id' => $jobId,
                'user_id' => (int)$seg['agent_id'],
                'offer_id' => (int)$offerId,
                'date_start' => $dateStr . ' ' . (string)$seg['start'],
                'date_end' => $dateStr . ' ' . (string)$seg['end'],
                'comment' => 'Brouillon WFM (job #' . $jobId . ')',
            ]);
        }

        if (!empty($entities)) {
            $DraftRanges->saveManyOrFail($entities);
        }

        // Persister l'état d'équité mis à jour (V2 : buckets groupe + cibles cumulées)
        $job->equity_state_json = json_encode([
            'activities' => $equityStateActivities,
            'forecastables' => $equityStateForecastables,
            'cumulative_targets' => $cumulativeTargets,
            EquityBucketsMigrator::VERSION_KEY => $equityBucketsVersion ?? EquityBucketsMigrator::VERSION,
        ], JSON_UNESCAPED_UNICODE);
        $Jobs->saveOrFail($job);

        // Réinitialiser current_step à la fin du traitement du jour
        $Jobs->updateAll(
            ['current_step' => null, 'modified' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')],
            ['id' => $jobId],
        );

        $report = [
            'date' => $dateStr,
            'solver_status' => $solverStatus,
            'solver_message' => $solverMessage,
            'draft_ranges' => count($entities),
            'fixed_activity_assignments' => count($fixedActivityAssignments),
            'fixed_activity_shortfalls' => $fixedActivityShortfalls,
            'fixed_pass1' => $fixedPass1,
            'pass2_period_equity_offers' => $periodEquityOffers ?? [],
            'diagnostics' => $build['diagnostics'] ?? null,
        ];

        $report['passes'] = [
            'pass1' => [
                'label' => 'Passe 1 : activités fixes',
                'attempted' => $fixedPass1['attempted'],
                'status' => $fixedPass1['status'],
                'error' => $fixedPass1['error'],
            ],
            'pass1_5' => [
                'label' => 'Passe 1.5 : rotation',
                'attempted' => $rotationPass['attempted'],
                'status' => $rotationPass['status'],
                'nb_blocks' => $rotationPass['nb_blocks'],
                'error' => $rotationPass['error'],
                'shortfalls' => $rotationPass['shortfalls'] ?? [],
            ],
            'pass2' => [
                'label' => 'Passe 2 : planning prévisionnel',
                'attempted' => !$ignoreForecastSolver,
                'status' => $solverStatus,
                'error' => $solverMessage,
                'coverage_shortage' => $solverCoverageShortage,
                'agent_diagnostics' => $solverAgentDiagnostics,
                'explanation' => $solverPass2Explanation,
            ],
        ];

        // Évaluation fail-fast du statut final : Passe 1 -> Passe 1.5 -> Passe 2,
        // on s'arrête à la première anomalie rencontrée.
        $finalStatus = 'ok';
        $errorMessage = null;

        if ($fixedPass1['attempted'] && $fixedPass1['status'] !== null && !in_array($fixedPass1['status'], ['OPTIMAL', 'FEASIBLE'], true)) {
            $finalStatus = str_starts_with((string)$fixedPass1['status'], 'HTTP_') || $fixedPass1['status'] === 'INVALID'
                ? 'error' : 'infeasible';
            $errorMessage = 'Passe 1 (activités fixes) : ' . $fixedPass1['status']
                . ($fixedPass1['error'] ? ' — ' . $fixedPass1['error'] : '');
        }

        if ($finalStatus === 'ok' && $rotationPass['attempted'] && $rotationPass['status'] !== null && $rotationPass['status'] !== 'FEASIBLE') {
            $finalStatus = ($rotationPass['status'] === 'EXCEPTION' || str_starts_with((string)$rotationPass['status'], 'HTTP_'))
                ? 'error' : 'infeasible';
            $errorMessage = 'Passe 1.5 (rotation) : ' . $rotationPass['status']
                . ($rotationPass['error'] ? ' — ' . $rotationPass['error'] : '');
        }

        if ($finalStatus === 'ok') {
            if ($solverStatus === 'INFEASIBLE') {
                $finalStatus = 'infeasible';
                $errorMessage = 'Passe 2 (planning prévisionnel) infaisable' . ($solverMessage ? ' : ' . $solverMessage : '');
            } elseif ($solverStatus === 'ERROR') {
                $finalStatus = 'error';
                $errorMessage = (string)($solverMessage ?? 'Erreur solver Passe 2');
            }
        }

        return [
            'status' => $finalStatus,
            'report_json' => json_encode($report),
            'error_message' => $errorMessage,
        ];
    }

    /**
     * Ajoute unavailable_intervals aux agents (Passe 2) en fonction des assignations Passe 1.
     *
     * @param array<int, array<string, mixed>> $agents
     * @param array<int, array<string, mixed>> $assignments
     * @return array<int, array<string, mixed>>
     */
    private function updateAgentsAfterFixedActivities(array $agents, array $assignments): array
    {
        $unavailableByAgent = [];
        foreach ($assignments as $assignment) {
            $agentId = (int)($assignment['agent_id'] ?? 0);
            if ($agentId <= 0) {
                continue;
            }
            $start = $assignment['start'] ?? null;
            $end = $assignment['end'] ?? null;
            if (!$start || !$end) {
                continue;
            }
            $unavailableByAgent[$agentId][] = [
                'start' => (string)$start,
                'end' => (string)$end,
                'allow_lunch' => true,
            ];
        }

        $updated = [];
        foreach ($agents as $agent) {
            $aid = (int)($agent['id'] ?? 0);
            $a = $agent;
            $a['unavailable_intervals'] = !empty($unavailableByAgent[$aid]) ? $unavailableByAgent[$aid] : null;
            $updated[] = $a;
        }
        return $updated;
    }

    /**
     * Filtre la structure "coverage" renvoyée par le solveur Passe 2 pour ne garder
     * que les créneaux réellement en déficit (shortage > 0). Évite de stocker toute
     * la grille horaire (majoritairement à 0) dans le rapport JSON.
     *
     * @param array<int, array<string, mixed>>|null $coverage
     * @return array<int, array<string, mixed>>|null
     */
    private function extractCoverageShortage(?array $coverage): ?array
    {
        if (empty($coverage)) {
            return null;
        }

        $result = [];
        foreach ($coverage as $entry) {
            if (!is_array($entry) || empty($entry['offer']) || !is_array($entry['times'] ?? null)) {
                continue;
            }
            $shortTimes = [];
            foreach ($entry['times'] as $t) {
                if (is_array($t) && (int)($t['shortage'] ?? 0) > 0) {
                    $shortTimes[] = [
                        'time' => $t['time'] ?? null,
                        'need' => $t['need'] ?? null,
                        'covered' => $t['covered'] ?? null,
                        'shortage' => $t['shortage'] ?? null,
                    ];
                }
            }
            if (!empty($shortTimes)) {
                $result[] = [
                    'offer' => $entry['offer'],
                    'shortage_slots' => $shortTimes,
                ];
            }
        }

        return !empty($result) ? $result : null;
    }

    /**
     * Enrichit les agents avec les absences manuelles (ranges) et corrige window_end.
     * 
     * @param array<int, array<string, mixed>> $agents
     * @param FrozenTime $date
     * @return array<int, array<string, mixed>>
     */
    private function enrichAgentsWithManualAbsences(array $agents, FrozenTime $date): array
    {
        $Users = $this->fetchTable('Users');
        $Ranges = $this->fetchTable('Ranges');
        $DraftRanges = $this->fetchTable('DraftRanges');
        $agentAvailabilityService = new AgentAvailabilityService();
        $dow = (int)$date->format('N');
        $dateStr = $date->format('Y-m-d');

        $enriched = [];
        foreach ($agents as $k => $agent) {
            $agentId = (int)($agent['id'] ?? 0);
            if ($agentId <= 0) {
                $enriched[] = $agent;
                continue;
            }

            // --- 1. Correction Window ---
            $user = $Users->find()
                ->contain([
                    'UserAvailabilities' => function ($q) use ($dow) {
                        return $q->where(['day_of_week' => $dow]);
                    }
                ])
                ->where(['Users.id' => $agentId])
                ->first();

            if ($user && !empty($user->user_availabilities)) {
                $availability = $user->user_availabilities[0];
                $effective = $agentAvailabilityService->calculateEffectiveAvailability($user, $date, $availability);
                
                if ($effective && !empty($effective['real_window_end'])) {
                    $agent['availability_end_time'] = $effective['real_window_end'];
                }
            }

            // --- 2. Injection Absences Manuelles ---
            $unavailable = $agent['unavailable_intervals'] ?? [];
            if (!is_array($unavailable)) {
                $unavailable = [];
            }

            // Liste des types considérés comme bloquants (Absences/Réunions)
            $blockingTypes = ['absence', 'meeting', 'unavailable', 'conges', 'rtt', 'maladie'];

            // A. Chercher dans les RANGES (Validés)
            $rangesFound = $Ranges->find()
                ->where([
                    'user_id' => $agentId,
                    'date_start <=' => $dateStr . ' 23:59:59',
                    'date_end >=' => $dateStr . ' 00:00:00',
                ])
                ->contain(['Offers'])
                ->all();

            foreach ($rangesFound as $r) {
                $type = $r->offer->offer_type ?? 'unknown';
                if (in_array(strtolower($type), $blockingTypes)) {
                    $isMeeting = (strtolower($type) === 'meeting');
                    $unavailable[] = [
                        'start' => $r->date_start->format('H:i:s'),
                        'end' => $r->date_end->format('H:i:s'),
                        'allow_lunch' => false,
                        'allow_breaks' => false,
                        'forces_lunch' => $isMeeting,
                    ];
                }
            }

            // B. Chercher dans les DRAFTS (Planification Manuelle)
            $draftsFound = $DraftRanges->find()
                ->where([
                    'user_id' => $agentId,
                    'date_start <=' => $dateStr . ' 23:59:59',
                    'date_end >=' => $dateStr . ' 00:00:00',
                ])
                ->contain(['Offers'])
                ->all();

            foreach ($draftsFound as $d) {
                $type = $d->offer->offer_type ?? 'unknown';
                if (in_array(strtolower($type), $blockingTypes)) {
                    $isMeeting = (strtolower($type) === 'meeting');
                    $unavailable[] = [
                        'start' => $d->date_start->format('H:i:s'),
                        'end' => $d->date_end->format('H:i:s'),
                        'allow_lunch' => false,
                        'allow_breaks' => false,
                        'forces_lunch' => $isMeeting,
                    ];
                }
            }

            $agent['unavailable_intervals'] = !empty($unavailable) ? $unavailable : null;
            $enriched[] = $agent;
        }

        return $enriched;
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

    /**
     * Construit currentRealization (agent_id => pool => minutes) à partir de
     * equity_state.activities (comptabilité brute, pauses incluses), qui est la
     * même unité que les cibles d'équité. Aligne ainsi cible et réalisé pour le solveur.
     *
     * @param array<string, array<int, float>> $equityStateActivities base_offer_name => agent_id => minutes (brut)
     * @param array<string, string> $offerNameToEquityGroup map base_offer_name → equity_group
     * @param array<string, string> $groupMode map equity_group → site_mode (per_site|pooled|global)
     * @param array<int, string> $userSiteById map user_id → nom de site
     * @return array<int, array<string, float>>
     */
    private function buildCurrentRealizationFromEquityState(
        array $equityStateActivities,
        array $offerNameToEquityGroup,
        array $groupMode,
        array $userSiteById,
    ): array {
        $realization = [];
        foreach ($equityStateActivities as $baseName => $byAgent) {
            $equityGroup = $offerNameToEquityGroup[$baseName] ?? null;
            if ($equityGroup === null || !is_array($byAgent)) {
                continue;
            }
            $mode = $groupMode[$equityGroup] ?? 'per_site';
            foreach ($byAgent as $userId => $minutes) {
                $userId = (int)$userId;
                $pool = $equityGroup;
                if ($mode === 'per_site') {
                    $pool = $equityGroup . '::' . ($userSiteById[$userId] ?? '');
                }
                $realization[$userId] ??= [];
                $realization[$userId][$pool] = ($realization[$userId][$pool] ?? 0.0) + (float)$minutes;
            }
        }
        return $realization;
    }

    private function truncateForLog(?string $value, int $maxLen = 500): string
    {
        $value = (string)($value ?? '');
        if (strlen($value) <= $maxLen) {
            return $value;
        }

        return substr($value, 0, $maxLen) . '...[tronqué]';
    }

    private function timeToMinutes(string $hhmmss): int
    {
        // Attend "HH:MM[:SS]"
        $parts = explode(':', $hhmmss);
        $h = isset($parts[0]) ? (int)$parts[0] : 0;
        $m = isset($parts[1]) ? (int)$parts[1] : 0;
        return ($h * 60) + $m;
    }

    private function minutesToTime(int $minutes): string
    {
        $m = max(0, $minutes);
        $h = (int)floor($m / 60) % 24;
        $mm = $m % 60;
        return sprintf('%02d:%02d:00', $h, $mm);
    }

    /**
     * Normalise les segments de planning pour éviter les chevauchements.
     * Les pauses/repas (LUNCH/AM_BREAK/PM_BREAK) remplacent les segments WORK (activités fixes).
     *
     * @param array $schedule Tableau de segments
     * @param int $slotMinutes Taille de créneau (15)
     * @return array Planning normalisé en segments
     */
    private function normalizeScheduleNoOverlap(array $schedule, int $slotMinutes = 15): array
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
                $invalid++;
                continue;
            }

            $startMin = $this->timeToMinutes($this->normalizeTime($start, '00:00:00'));
            $endMin = $this->timeToMinutes($this->normalizeTime($end, '00:00:00'));
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
                    // Ne jamais écraser un slot existant (fixe ou pause/repas)
                    if (($existing['label'] ?? null) !== 'WORK') {
                        $conflicts++;
                    }
                    continue;
                }

                // LUNCH/AM_BREAK/PM_BREAK remplacent l'existant (notamment activité fixe)
                $slotsByAgent[$agentId][$m] = [
                    'label' => $label,
                    'offer' => null,
                ];
            }
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
                            'start' => $this->minutesToTime($segStartMin),
                            'end' => $this->minutesToTime($prevMin + $slotMinutes),
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
                    'start' => $this->minutesToTime($segStartMin),
                    'end' => $this->minutesToTime($prevMin + $slotMinutes),
                    'label' => $currentLabel,
                ];
                if ($currentLabel === 'WORK') {
                    $seg['offer'] = (string)($currentOffer ?? '');
                }
                $normalized[] = $seg;
            }
        }

        return $normalized;
    }
}


