<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\NotFoundException;
use Cake\I18n\FrozenDate;
use Cake\I18n\FrozenTime;
use Cake\Routing\Router;

class PlanningGenerationJobsController extends AppController
{
    public function index()
    {
        $this->Authorization->authorize(new \App\Resource\PlanningGenerationJobsResource(), 'index');

        $Jobs = $this->fetchTable('PlanningGenerationJobs');

        $query = $Jobs->find()
            ->contain(['Users', 'WfmSettings']);

        $params = $this->request->getQueryParams();

        // Filtre par statut
        if (!empty($params['status'])) {
            $status = $params['status'];
            if ($status === 'finished') {
                $query->where(['PlanningGenerationJobs.status IN' => ['finished', 'finished_with_errors']]);
            } elseif ($status === 'running') {
                $query->where(['PlanningGenerationJobs.status IN' => ['running', 'queued']]);
            } elseif ($status === 'error') {
                $query->where(['PlanningGenerationJobs.status IN' => ['error', 'infeasible']]);
            } else {
                $query->where(['PlanningGenerationJobs.status' => $status]);
            }
        }

        // Filtre par période (détection de chevauchement)
        $dateStart = $params['date_start'] ?? null;
        $dateEnd = $params['date_end'] ?? null;
        
        if (!empty($dateStart) && !empty($dateEnd)) {
            // Si les deux dates sont définies : jobs qui chevauchent la plage
            // Un job chevauche si : start_date <= date_end ET end_date >= date_start
            $query->where([
                'PlanningGenerationJobs.start_date <=' => $dateEnd,
                'PlanningGenerationJobs.end_date >=' => $dateStart,
            ]);
        } elseif (!empty($dateStart)) {
            // Si seulement date_start : jobs dont la période commence à partir de cette date
            $query->where(['PlanningGenerationJobs.start_date >=' => $dateStart]);
        } elseif (!empty($dateEnd)) {
            // Si seulement date_end : jobs dont la période se termine avant ou à cette date
            $query->where(['PlanningGenerationJobs.end_date <=' => $dateEnd]);
        }

        // Filtre par période (date de création)
        if (!empty($params['created_from'])) {
            $createdFrom = $params['created_from'];
            $query->where(['PlanningGenerationJobs.created >=' => $createdFrom . ' 00:00:00']);
        }

        if (!empty($params['created_to'])) {
            $createdTo = $params['created_to'];
            $query->where(['PlanningGenerationJobs.created <=' => $createdTo . ' 23:59:59']);
        }

        $jobs = $query
            ->orderDesc('PlanningGenerationJobs.created')
            ->limit(50)
            ->all();

        $this->set(compact('jobs'));
    }

    public function delete(int $id)
    {
        $this->Authorization->authorize(new \App\Resource\PlanningGenerationJobsResource(), 'delete');
        $this->request->allowMethod(['post', 'delete']);

        $Jobs = $this->fetchTable('PlanningGenerationJobs');
        $job = $Jobs->get($id);

        if ((string)$job->status === 'running') {
            $this->Flash->error('Suppression interdite : le job est en cours (running).');
            return $this->redirect(['action' => 'index']);
        }

        $Jobs->deleteOrFail($job);
        $this->Flash->success('Job supprimé (jours + brouillon supprimés automatiquement).');

        return $this->redirect(['action' => 'index']);
    }

    public function bulkDelete()
    {
        $this->Authorization->authorize(new \App\Resource\PlanningGenerationJobsResource(), 'delete');
        $this->request->allowMethod(['post']);

        $Jobs = $this->fetchTable('PlanningGenerationJobs');
        $ids = $this->request->getData('ids', []);

        if (empty($ids) || !is_array($ids)) {
            $this->Flash->error('Aucun job sélectionné.');
            $redirectUrl = ['action' => 'index'];
            $queryParams = $this->request->getQueryParams();
            if (!empty($queryParams)) {
                $redirectUrl['?'] = $queryParams;
            }
            return $this->redirect($redirectUrl);
        }

        $ids = array_map('intval', $ids);
        $jobs = $Jobs->find()->where(['id IN' => $ids])->all();

        $deletedCount = 0;
        $skippedCount = 0;

        foreach ($jobs as $job) {
            if ((string)$job->status === 'running') {
                $skippedCount++;
                continue;
            }

            $Jobs->delete($job);
            $deletedCount++;
        }

        if ($deletedCount > 0) {
            $this->Flash->success($deletedCount . ' job(s) supprimé(s).');
        }

        if ($skippedCount > 0) {
            $this->Flash->warning($skippedCount . ' job(s) ignoré(s) (en cours).');
        }

        if ($deletedCount === 0 && $skippedCount === 0) {
            $this->Flash->error('Aucun job n\'a pu être supprimé.');
        }

        $redirectUrl = ['action' => 'index'];
        $queryParams = $this->request->getQueryParams();
        if (!empty($queryParams)) {
            $redirectUrl['?'] = $queryParams;
        }
        return $this->redirect($redirectUrl);
    }

    public function retry(int $id)
    {
        $this->Authorization->authorize(new \App\Resource\PlanningGenerationJobsResource(), 'delete');
        $this->request->allowMethod(['post']);

        $Jobs = $this->fetchTable('PlanningGenerationJobs');
        $job = $Jobs->get($id);

        $Days = $this->fetchTable('PlanningGenerationJobDays');
        $DraftRanges = $this->fetchTable('DraftRanges');

        // Supprimer tous les anciens drafts du job
        $DraftRanges->deleteAll(['job_id' => $id]);

        // Réinitialiser le job
        $job->status = 'queued';
        $job->processed_days = 0;
        $job->current_step = null;
        $job->current_day = null;
        $job->eta_seconds = null;
        $job->error_message = null;
        $job->started_at = new \Cake\I18n\FrozenTime();
        $job->finished_at = null;
        $Jobs->saveOrFail($job);

        // Réinitialiser tous les jours associés (status = queued pour reprise par le worker)
        $Days->updateAll(
            [
                'status' => 'queued',
                'report_json' => null,
                'error_message' => null,
                'duration_ms' => null,
            ],
            ['job_id' => $id]
        );

        $this->Flash->success('Job relancé. Il sera traité par le worker dès qu\'il sera disponible.');

        // Rediriger vers l'index si on vient de là, sinon vers la page view
        $referer = $this->request->getHeaderLine('Referer');
        if ($referer && strpos($referer, '/planning-generation-jobs') !== false && strpos($referer, '/view/') === false) {
            // On vient de l'index, préserver les filtres
            $redirectUrl = ['action' => 'index'];
            $queryParams = $this->request->getQueryParams();
            if (!empty($queryParams)) {
                $redirectUrl['?'] = $queryParams;
            }
            return $this->redirect($redirectUrl);
        }

        return $this->redirect(['action' => 'view', $id]);
    }

    /**
     * Charge les listes de référence utilisées par les formulaires add()/edit().
     *
     * @return array{0: iterable, 1: array, 2: iterable}
     */
    private function loadFormReferenceData(): array
    {
        $WfmSettings = $this->fetchTable('WfmSettings');
        $wfmSettingsList = $WfmSettings->find('list', [
            'keyField' => 'id',
            'valueField' => 'name',
        ])->all();

        $ForecastScenarios = $this->fetchTable('ForecastScenarios');
        $scenariosList = $ForecastScenarios->find('list', [
                'keyField' => 'id',
                'valueField' => function ($row) {
                    return sprintf('%s (%s → %s)', (string)$row->name, (string)$row->start_date, (string)$row->end_date);
                },
            ])
            ->where(['ForecastScenarios.status' => 'completed'])
            ->orderDesc('ForecastScenarios.modified')
            ->toArray();

        $sites = $this->fetchTable('Sites')->find()
            ->contain(['Users' => function ($q) {
                return $q->order(['Users.last_name' => 'ASC']);
            }])
            ->orderAsc('Sites.name')
            ->all();

        return [$wfmSettingsList, $scenariosList, $sites];
    }

    /**
     * Nettoie la liste d'IDs d'agents postée depuis le formulaire (sélection manuelle).
     *
     * @param array<string, mixed> $data
     * @return array<int, int>
     */
    private function sanitizeAgentIds(array $data): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', (array)($data['agent_ids'] ?? [])),
            fn (int $id): bool => $id !== 0,
        )));
    }

    /**
     * Construit le tableau complet des options du job (flags + sélection d'agents) à partir des données postées.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildJobOptions(array $data): array
    {
        $options = [
            'ignore_fixed_activities' => !empty($data['ignore_fixed_activities'] ?? false),
            'ignore_rotation' => !empty($data['ignore_rotation'] ?? false),
            'ignore_forecast_solver' => !empty($data['ignore_forecast_solver'] ?? false),
            'debug_solvers' => !empty($data['debug_solvers'] ?? false),
            'debug_rotation_only' => !empty($data['debug_rotation_only'] ?? false),
        ];

        $agentIds = $this->sanitizeAgentIds($data);
        if (!empty($agentIds)) {
            $options['agent_ids'] = $agentIds;
        }

        return $options;
    }

    public function add()
    {
        $this->Authorization->authorize(new \App\Resource\PlanningGenerationJobsResource(), 'add');

        $Jobs = $this->fetchTable('PlanningGenerationJobs');
        $Days = $this->fetchTable('PlanningGenerationJobDays');

        [$wfmSettingsList, $scenariosList, $sites] = $this->loadFormReferenceData();

        $job = $Jobs->newEmptyEntity();

        if ($this->request->is('post')) {
            $identity = $this->request->getAttribute('identity');
            $userId = (int)($identity?->get('id') ?? 0);
            if ($userId <= 0) {
                throw new NotFoundException('Utilisateur non identifié.');
            }

            $data = $this->request->getData();
            $start = new FrozenDate((string)($data['start_date'] ?? ''));
            $end = new FrozenDate((string)($data['end_date'] ?? ''));
            $settingsId = (int)($data['wfm_setting_id'] ?? 0);
            $scenarioId = (int)($data['scenario_id'] ?? 0);

            if ($settingsId <= 0) {
                $this->Flash->error('Profil WFM invalide.');
                return;
            }
            if ($end < $start) {
                $this->Flash->error('La date de fin doit être >= à la date de début.');
                return;
            }

            $options = $this->buildJobOptions($data);

            // Jours ouvrés uniquement (cohérent avec la grille qui saute les weekends)
            $day = $start;
            $jobDays = [];
            while ($day <= $end) {
                if (!$day->isWeekend()) {
                    $jobDays[] = $day;
                }
                $day = $day->addDays(1);
            }

            $job = $Jobs->newEntity([
                'user_id' => $userId,
                'start_date' => $start,
                'end_date' => $end,
                'wfm_setting_id' => $settingsId,
                'scenario_id' => $scenarioId > 0 ? $scenarioId : null,
                'options_json' => json_encode($options),
                'debug_rotation_only' => !empty($data['debug_rotation_only'] ?? false),
                'status' => 'queued',
                'total_days' => count($jobDays),
                'processed_days' => 0,
                'current_day' => null,
                'current_step' => null,
                'eta_seconds' => null,
                'equity_state_json' => json_encode([]),
                'report_json' => null,
                'error_message' => null,
                'started_at' => new \Cake\I18n\FrozenTime(),
                'finished_at' => null,
            ]);

            $Jobs->saveOrFail($job);

            foreach ($jobDays as $d) {
                $Days->saveOrFail($Days->newEntity([
                    'job_id' => (int)$job->id,
                    'date' => $d,
                    'status' => 'queued',
                    'duration_ms' => null,
                    'error_message' => null,
                    'report_json' => null,
                ]));
            }

            $this->Flash->success("Job créé. Tu peux suivre l'avancement.", [
                'params' => [
                    'auto-dismiss' => 5000
                ]
            ]);
            return $this->redirect(['action' => 'view', (int)$job->id]);
        }

        $this->set(compact('job', 'wfmSettingsList', 'scenariosList', 'sites'));
    }

    public function edit($id)
    {
        $this->Authorization->authorize(new \App\Resource\PlanningGenerationJobsResource(), 'edit');

        $Jobs = $this->fetchTable('PlanningGenerationJobs');
        $Days = $this->fetchTable('PlanningGenerationJobDays');
        $DraftRanges = $this->fetchTable('DraftRanges');

        $job = $Jobs->get($id);

        if ((string)$job->status === 'running') {
            $this->Flash->error('Modification interdite : le job est en cours (running).');
            return $this->redirect(['action' => 'view', $id]);
        }

        [$wfmSettingsList, $scenariosList, $sites] = $this->loadFormReferenceData();

        $options = [];
        if (!empty($job->options_json)) {
            $decoded = json_decode((string)$job->options_json, true);
            if (is_array($decoded)) {
                $options = $decoded;
            }
        }
        $selectedAgentIds = $options['agent_ids'] ?? [];
        $ignoreFixedActivities = !empty($options['ignore_fixed_activities'] ?? false);
        $ignoreRotation = !empty($options['ignore_rotation'] ?? false);
        $ignoreForecastSolver = !empty($options['ignore_forecast_solver'] ?? false);
        $debugSolvers = !empty($options['debug_solvers'] ?? false);

        if ($this->request->is(['post', 'put'])) {
            $data = $this->request->getData();
            $start = new FrozenDate((string)($data['start_date'] ?? ''));
            $end = new FrozenDate((string)($data['end_date'] ?? ''));
            $settingsId = (int)($data['wfm_setting_id'] ?? 0);
            $scenarioId = (int)($data['scenario_id'] ?? 0);

            if ($settingsId <= 0) {
                $this->Flash->error('Profil WFM invalide.');
            } elseif ($end < $start) {
                $this->Flash->error('La date de fin doit être >= à la date de début.');
            } else {
                $newOptions = $this->buildJobOptions($data);

                // Jours ouvrés uniquement (même logique que add(), cohérente avec la grille qui saute les weekends)
                $day = $start;
                $newDates = [];
                while ($day <= $end) {
                    if (!$day->isWeekend()) {
                        $newDates[] = $day->format('Y-m-d');
                    }
                    $day = $day->addDays(1);
                }

                $job = $Jobs->patchEntity($job, [
                    'start_date' => $start,
                    'end_date' => $end,
                    'wfm_setting_id' => $settingsId,
                    'scenario_id' => $scenarioId > 0 ? $scenarioId : null,
                    'options_json' => json_encode($newOptions),
                    'debug_rotation_only' => !empty($data['debug_rotation_only'] ?? false),
                    'status' => 'queued',
                    'total_days' => count($newDates),
                    'processed_days' => 0,
                    'current_day' => null,
                    'current_step' => null,
                    'eta_seconds' => null,
                    'error_message' => null,
                    'started_at' => new \Cake\I18n\FrozenTime(),
                    'finished_at' => null,
                ]);

                try {
                    $Jobs->getConnection()->transactional(function () use ($Jobs, $Days, $DraftRanges, $job, $id, $newDates) {
                        // 1. Supprime les jours obsolètes (hors nouvelle plage de dates)
                        $Days->deleteAll([
                            'job_id' => $id,
                            'date NOT IN' => $newDates,
                        ]);

                        // 2. Insère les jours manquants (fallback si le jour n'existe pas déjà)
                        $existingDates = $Days->find()
                            ->select(['date'])
                            ->where(['job_id' => $id])
                            ->all()
                            ->extract('date')
                            ->map(fn ($d) => $d->format('Y-m-d'))
                            ->toArray();

                        foreach ($newDates as $dateStr) {
                            if (in_array($dateStr, $existingDates, true)) {
                                continue;
                            }
                            $Days->saveOrFail($Days->newEntity([
                                'job_id' => $id,
                                'date' => $dateStr,
                                'status' => 'queued',
                                'duration_ms' => null,
                                'error_message' => null,
                                'report_json' => null,
                            ]));
                        }

                        // 3. Purge complète des brouillons existants (paramètres modifiés = drafts obsolètes)
                        $DraftRanges->deleteAll(['job_id' => $id]);

                        // 4 + 5. Sauvegarde du job avec les nouveaux paramètres
                        $Jobs->saveOrFail($job);

                        // 6. Réinitialise tous les jours restants pour reprise complète par le worker
                        $Days->updateAll(
                            [
                                'status' => 'queued',
                                'report_json' => null,
                                'error_message' => null,
                                'duration_ms' => null,
                            ],
                            ['job_id' => $id]
                        );
                    });

                    $this->Flash->success("Job modifié et remis en file d'attente.", [
                        'params' => [
                            'auto-dismiss' => 5000
                        ]
                    ]);

                    return $this->redirect(['action' => 'view', $id]);
                } catch (\Cake\ORM\Exception\PersistenceFailedException $e) {
                    $this->Flash->error('Veuillez corriger les erreurs de saisie.');
                } catch (\Exception $e) {
                    $this->Flash->error('Erreur lors de la modification : ' . $e->getMessage());
                }
            }
        }

        $isEdit = true;
        $this->set(compact(
            'job',
            'wfmSettingsList',
            'scenariosList',
            'sites',
            'isEdit',
            'selectedAgentIds',
            'ignoreFixedActivities',
            'ignoreRotation',
            'ignoreForecastSolver',
            'debugSolvers'
        ));

        $this->render('add');
    }

    public function view(int $id)
    {
        $this->Authorization->authorize(new \App\Resource\PlanningGenerationJobsResource(), 'view');

        $Jobs = $this->fetchTable('PlanningGenerationJobs');
        $job = $Jobs->get($id, ['contain' => ['Users', 'WfmSettings']]);

        $Days = $this->fetchTable('PlanningGenerationJobDays');
        $firstProcessedDay = $Days->find()
            ->where([
                'job_id' => $id,
                'status IN' => ['ok', 'infeasible', 'error'],
            ])
            ->orderAsc('modified')
            ->first();

        $firstDayProcessedAt = $firstProcessedDay ? $firstProcessedDay->modified : null;

        // Liste légère des jours (badges / timeline rapide)
        $daysLight = $Days->find()
            ->select(['id', 'date', 'status'])
            ->where(['job_id' => $id])
            ->orderAsc('date')
            ->all();

        $status = (string)$job->status;
        $tab = (string)($this->request->getQuery('tab') ?? '');
        if (!in_array($tab, ['planning', 'qualite', 'technique'], true)) {
            if (in_array($status, ['queued', 'running'], true)) {
                $tab = 'planning';
            } elseif ($status === 'finished_with_errors') {
                $tab = 'qualite';
            } else {
                $tab = 'planning';
            }
        }

        $workspaceSection = (string)($this->request->getQuery('section') ?? '');
        $isFinished = in_array($status, ['finished', 'finished_with_errors', 'error', 'infeasible'], true);

        // Rapport pour KPI/badges si onglet qualité/technique, ou job terminé
        if (in_array($tab, ['qualite', 'technique'], true) || $isFinished) {
            $this->loadReportViewVars($id);
        }

        // Équité uniquement sur l'onglet Qualité
        if ($tab === 'qualite') {
            $this->loadEquityViewVars($id);
        }

        // Conformité : onglet Qualité, ou job terminé (badge nav)
        if ($tab === 'qualite' || $isFinished) {
            $this->loadComplianceViewVars($id);
        }

        $workspaceTab = $tab;
        $this->set(compact(
            'job',
            'firstDayProcessedAt',
            'daysLight',
            'workspaceTab',
            'workspaceSection',
        ));
    }

    /**
     * Charge le contrôle de conformité fixes/rotations pour l'onglet Qualité.
     */
    private function loadComplianceViewVars(int $id): void
    {
        try {
            $service = new \App\Service\Planning\DraftComplianceService();
            $result = $service->analyze($id);
            $complianceFixed = $result['fixed'] ?? [];
            $complianceRotation = $result['rotation'] ?? [];
            $complianceSummary = $result['summary'] ?? [
                'fixed_ok' => 0,
                'fixed_ko' => 0,
                'fixed_total' => 0,
                'rotation_ok' => 0,
                'rotation_ko' => 0,
                'rotation_total' => 0,
                'ko_total' => 0,
            ];
        } catch (\Throwable $e) {
            $complianceFixed = [];
            $complianceRotation = [];
            $complianceSummary = [
                'fixed_ok' => 0,
                'fixed_ko' => 0,
                'fixed_total' => 0,
                'rotation_ok' => 0,
                'rotation_ko' => 0,
                'rotation_total' => 0,
                'ko_total' => 0,
            ];
        }

        $this->set(compact('complianceFixed', 'complianceRotation', 'complianceSummary'));
    }

    public function status(int $id)
    {
        $this->Authorization->authorize(new \App\Resource\PlanningGenerationJobsResource(), 'status');
        $this->request->allowMethod(['get']);

        $Jobs = $this->fetchTable('PlanningGenerationJobs');
        $job = $Jobs->get($id);

        // Récupérer la date de traitement du premier jour traité pour le calcul de vitesse
        $Days = $this->fetchTable('PlanningGenerationJobDays');
        $firstProcessedDay = $Days->find()
            ->where([
                'job_id' => $id,
                'status IN' => ['ok', 'infeasible', 'error']
            ])
            ->orderAsc('modified')
            ->first();
        
        $firstDayProcessedAtTimestamp = null;
        if ($firstProcessedDay && $firstProcessedDay->modified) {
            if ($firstProcessedDay->modified instanceof \Cake\I18n\FrozenTime || $firstProcessedDay->modified instanceof \Cake\I18n\FrozenDate) {
                $firstDayProcessedAtTimestamp = $firstProcessedDay->modified->getTimestamp();
            } elseif ($firstProcessedDay->modified instanceof \DateTimeInterface) {
                $firstDayProcessedAtTimestamp = $firstProcessedDay->modified->getTimestamp();
            }
        }

        // Récupérer finished_at si le job est terminé
        $finishedAtTimestamp = null;
        if ($job->finished_at) {
            if ($job->finished_at instanceof \Cake\I18n\FrozenTime || $job->finished_at instanceof \Cake\I18n\FrozenDate) {
                $finishedAtTimestamp = $job->finished_at->getTimestamp();
            } elseif ($job->finished_at instanceof \DateTimeInterface) {
                $finishedAtTimestamp = $job->finished_at->getTimestamp();
            }
        }

        $this->viewBuilder()->setClassName('Json');
        $this->set([
            'success' => true,
            'job' => [
                'id' => (int)$job->id,
                'status' => (string)$job->status,
                'total_days' => (int)$job->total_days,
                'processed_days' => (int)$job->processed_days,
                'current_day' => $job->current_day ? (string)$job->current_day : null,
                'current_step' => $job->current_step ? (string)$job->current_step : null,
                'eta_seconds' => $job->eta_seconds !== null ? (int)$job->eta_seconds : null,
                'first_day_processed_at_timestamp' => $firstDayProcessedAtTimestamp,
                'finished_at_timestamp' => $finishedAtTimestamp,
            ],
        ]);
        $this->viewBuilder()->setOption('serialize', ['success', 'job']);
    }

    public function report(int $id)
    {
        $this->Authorization->authorize(new \App\Resource\PlanningGenerationJobsResource(), 'report');

        return $this->redirect(['action' => 'view', $id, '?' => ['tab' => 'qualite']]);
    }

    /**
     * Charge les variables de vue du rapport (KPI, jours, diagnostics, performance).
     */
    private function loadReportViewVars(int $id): void
    {
        $Jobs = $this->fetchTable('PlanningGenerationJobs');
        $Days = $this->fetchTable('PlanningGenerationJobDays');
        $DraftRanges = $this->fetchTable('DraftRanges');
        $Offers = $this->fetchTable('Offers');

        $job = $Jobs->get($id, ['contain' => ['Users', 'WfmSettings']]);
        $days = $Days->find()->where(['job_id' => $id])->orderAsc('date')->all();

        // Récupérer les IDs des offres pause/lunch pour les exclure du comptage
        // Utiliser les IDs depuis les paramètres WFM du job, sinon chercher par type
        $excludedOfferIds = [];
        if ($job->wfm_setting) {
            if ($job->wfm_setting->pause_offer_id) {
                $excludedOfferIds[] = (int)$job->wfm_setting->pause_offer_id;
            }
            if ($job->wfm_setting->lunch_offer_id) {
                $excludedOfferIds[] = (int)$job->wfm_setting->lunch_offer_id;
            }
        }
        // Fallback : chercher par type si pas défini dans WFM
        if (empty($excludedOfferIds)) {
            $pauseOfferIds = $Offers->find('ByType', ['type' => 'pause'])->extract('id')->toList();
            $lunchOfferIds = $Offers->find('ByType', ['type' => 'lunch'])->extract('id')->toList();
            $excludedOfferIds = array_merge($pauseOfferIds, $lunchOfferIds);
        }

        // === CALCUL DES STATISTIQUES GLOBALES ===
        $stats = [
            'total_days' => 0,
            'days_ok' => 0,
            'days_infeasible' => 0,
            'days_error' => 0,
            'days_queued' => 0,
            'days_running' => 0,
            'total_duration_ms' => 0,
            'avg_duration_ms' => 0,
            'total_segments' => 0,
            'total_excluded_agents' => 0,
            'total_warnings' => 0,
        ];

        // Agrégation des diagnostics
        $excludedAgentsById = [];
        $allWarnings = [];
        $allPreSolverDiagnostics = [];
        $daysData = [];
        $durationData = [];
        $statusTimeline = [];

        foreach ($days as $d) {
            $stats['total_days']++;
            $status = (string)$d->status;
            
            if ($status === 'ok') $stats['days_ok']++;
            elseif ($status === 'infeasible') $stats['days_infeasible']++;
            elseif ($status === 'error') $stats['days_error']++;
            elseif ($status === 'queued') $stats['days_queued']++;
            elseif ($status === 'running') $stats['days_running']++;

            if ($d->duration_ms !== null) {
                $stats['total_duration_ms'] += (int)$d->duration_ms;
                // Conserver l'objet FrozenDate pour utiliser i18nFormat
                $dateObj = $d->date instanceof \Cake\I18n\FrozenDate 
                    ? $d->date 
                    : new \Cake\I18n\FrozenDate((string)$d->date);
                $durationData[] = [
                    'date' => $dateObj,
                    'duration' => (int)$d->duration_ms / 1000, // en secondes
                ];
            }

            $statusTimeline[] = [
                'date' => (string)$d->date,
                'status' => $status,
            ];

            // Parser le report_json
            $rep = [];
            if (!empty($d->report_json)) {
                $decoded = json_decode((string)$d->report_json, true);
                $rep = is_array($decoded) ? $decoded : [];
            }

            // Extraire les diagnostics
            $diag = is_array($rep['diagnostics'] ?? null) ? $rep['diagnostics'] : [];
            $preSolverDiag = is_array($rep['pre_solver_diagnostics'] ?? null) ? $rep['pre_solver_diagnostics'] : [];

            // Compter les agents exclus et warnings
            $excludedAgents = is_array($diag['excluded_agents'] ?? null) ? $diag['excluded_agents'] : [];
            $warnings = is_array($diag['warnings'] ?? null) ? $diag['warnings'] : [];

            $stats['total_excluded_agents'] += count($excludedAgents);
            $stats['total_warnings'] += count($warnings);

            // Agrégation exhaustive des agents exclus (jours + raisons)
            foreach ($excludedAgents as $ex) {
                if (!is_array($ex) || !isset($ex['id'])) {
                    continue;
                }
                $agentId = (int)$ex['id'];
                $reason = (string)($ex['reason'] ?? 'Raison inconnue');
                $dateStr = (string)$d->date;

                if (!isset($excludedAgentsById[$agentId])) {
                    $excludedAgentsById[$agentId] = [
                        'id' => $agentId,
                        'name' => (string)($ex['name'] ?? 'Nom inconnu'),
                        'site' => (string)($ex['site'] ?? 'Site inconnu'),
                        'days' => [],
                        'reason_counts' => [],
                    ];
                }
                $excludedAgentsById[$agentId]['days'][] = [
                    'date' => $dateStr,
                    'reason' => $reason,
                ];
                if (!isset($excludedAgentsById[$agentId]['reason_counts'][$reason])) {
                    $excludedAgentsById[$agentId]['reason_counts'][$reason] = 0;
                }
                $excludedAgentsById[$agentId]['reason_counts'][$reason]++;
            }

            // Agrégation des warnings (par message)
            foreach ($warnings as $w) {
                $msg = is_array($w) ? ($w['message'] ?? json_encode($w)) : (string)$w;
                $msgKey = md5($msg);
                if (!isset($allWarnings[$msgKey])) {
                    $allWarnings[$msgKey] = [
                        'message' => $msg,
                        'count' => 0,
                        'dates' => [],
                    ];
                }
                $allWarnings[$msgKey]['count']++;
                $allWarnings[$msgKey]['dates'][] = (string)$d->date;
            }

            // Compter les segments générés (WORK uniquement, depuis DraftRanges)
            $scheduleCount = 0;
            if ($status === 'ok') {
                // Compter les DraftRanges WORK pour ce jour (exclure pauses/repas)
                $dateStr = $d->date instanceof \Cake\I18n\FrozenDate 
                    ? $d->date->format('Y-m-d') 
                    : (string)$d->date;
                $dayStart = new \Cake\I18n\FrozenTime($dateStr . ' 00:00:00');
                $dayEnd = new \Cake\I18n\FrozenTime($dateStr . ' 23:59:59');
                
                $query = $DraftRanges->find()
                    ->where([
                        'job_id' => $id,
                        'date_start <' => $dayEnd,
                        'date_end >' => $dayStart,
                    ]);
                
                if (!empty($excludedOfferIds)) {
                    $query->where(['offer_id NOT IN' => $excludedOfferIds]);
                }
                
                $scheduleCount = $query->count();
            }
            
            // Alternative : utiliser draft_ranges du report_json si disponible
            if ($scheduleCount === 0 && isset($rep['draft_ranges'])) {
                // draft_ranges inclut tous les segments (WORK + pauses/repas)
                // On ne peut pas distinguer, donc on utilise cette valeur comme approximation
                $scheduleCount = (int)($rep['draft_ranges'] ?? 0);
            }
            
            $stats['total_segments'] += $scheduleCount;

            // Stocker les données du jour
            $daysData[] = [
                'day' => $d,
                'report' => $rep,
                'diagnostics' => $diag,
                'pre_solver_diagnostics' => $preSolverDiag,
                'schedule_count' => $scheduleCount,
                'excluded_count' => count($excludedAgents),
                'warnings_count' => count($warnings),
            ];

            // Fusionner les pre_solver_diagnostics
            if (!empty($preSolverDiag)) {
                foreach ($preSolverDiag as $key => $value) {
                    if (!isset($allPreSolverDiagnostics[$key])) {
                        $allPreSolverDiagnostics[$key] = [];
                    }
                    if (is_array($value)) {
                        $allPreSolverDiagnostics[$key] = array_merge($allPreSolverDiagnostics[$key], $value);
                    }
                }
            }
        }

        // Calculer la durée moyenne
        $daysWithDuration = array_filter($days->toArray(), fn($d) => $d->duration_ms !== null);
        if (count($daysWithDuration) > 0) {
            $stats['avg_duration_ms'] = $stats['total_duration_ms'] / count($daysWithDuration);
        }

        // Taux de succès
        $successRate = $stats['total_days'] > 0 
            ? round(($stats['days_ok'] / $stats['total_days']) * 100, 1) 
            : 0;

        // Finaliser agents exclus : catégories, synthèse par raison, tris
        $excludedByReason = [];
        $excludedAgentsList = [];
        $agentsActionable = 0;
        $agentsExpected = 0;

        foreach ($excludedAgentsById as $agentId => $agent) {
            $reasonCounts = $agent['reason_counts'];
            arsort($reasonCounts);
            $reasons = array_keys($reasonCounts);
            $categories = [];
            $hasActionable = false;
            foreach ($reasons as $reason) {
                $cat = $this->classifyExclusionReason($reason);
                $categories[$cat] = true;
                if ($cat === 'actionable') {
                    $hasActionable = true;
                }

                if (!isset($excludedByReason[$reason])) {
                    $excludedByReason[$reason] = [
                        'reason' => $reason,
                        'category' => $cat,
                        'agent_count' => 0,
                        'day_count' => 0,
                        'agent_ids' => [],
                    ];
                }
                $excludedByReason[$reason]['agent_count']++;
                $excludedByReason[$reason]['day_count'] += (int)$reasonCounts[$reason];
                $excludedByReason[$reason]['agent_ids'][$agentId] = true;
            }

            $primaryCategory = $hasActionable ? 'actionable' : 'expected';
            if ($primaryCategory === 'actionable') {
                $agentsActionable++;
            } else {
                $agentsExpected++;
            }

            // Raison dominante (compat ancien format)
            $primaryReason = $reasons[0] ?? 'Raison inconnue';
            $dayCount = count($agent['days']);
            $dates = array_column($agent['days'], 'date');

            $excludedAgentsList[] = [
                'id' => (int)$agent['id'],
                'name' => (string)$agent['name'],
                'site' => (string)$agent['site'],
                'days' => $agent['days'],
                'reason_counts' => $reasonCounts,
                'reasons' => $reasons,
                'categories' => array_keys($categories),
                'primary_category' => $primaryCategory,
                'day_count' => $dayCount,
                // Compat report.php déprécié
                'reason' => $primaryReason,
                'count' => $dayCount,
                'dates' => $dates,
            ];
        }

        usort($excludedAgentsList, static function (array $a, array $b): int {
            if ($a['primary_category'] !== $b['primary_category']) {
                return $a['primary_category'] === 'actionable' ? -1 : 1;
            }
            if ($a['day_count'] !== $b['day_count']) {
                return $b['day_count'] <=> $a['day_count'];
            }
            return strcasecmp((string)$a['name'], (string)$b['name']);
        });

        $excludedByReason = array_values($excludedByReason);
        usort($excludedByReason, static function (array $a, array $b): int {
            if ($a['category'] !== $b['category']) {
                return $a['category'] === 'actionable' ? -1 : 1;
            }
            if ($a['agent_count'] !== $b['agent_count']) {
                return $b['agent_count'] <=> $a['agent_count'];
            }
            return strcasecmp((string)$a['reason'], (string)$b['reason']);
        });
        foreach ($excludedByReason as &$reasonRow) {
            unset($reasonRow['agent_ids']);
        }
        unset($reasonRow);

        $excludedSummary = [
            'agents_total' => count($excludedAgentsList),
            'agents_actionable' => $agentsActionable,
            'agents_expected' => $agentsExpected,
            'day_agent_total' => (int)$stats['total_excluded_agents'],
        ];

        // Compat anciens templates (report.php déprécié)
        $allExcludedAgents = $excludedAgentsList;
        $topExcludedAgents = array_slice($excludedAgentsList, 0, 10);

        // Top warnings
        usort($allWarnings, fn($a, $b) => $b['count'] <=> $a['count']);
        $topWarnings = array_slice($allWarnings, 0, 10);

        // Score de santé (0-100)
        $healthScore = 100;
        if ($stats['total_days'] > 0) {
            $healthScore -= (($stats['days_infeasible'] / $stats['total_days']) * 50);
            $healthScore -= (($stats['days_error'] / $stats['total_days']) * 30);
            $healthScore -= min(20, ($stats['total_warnings'] / $stats['total_days']) * 2);
        }
        $healthScore = max(0, round($healthScore));

        $this->set(compact(
            'job',
            'days',
            'stats',
            'successRate',
            'healthScore',
            'topExcludedAgents',
            'topWarnings',
            'allExcludedAgents',
            'excludedAgentsList',
            'excludedByReason',
            'excludedSummary',
            'allWarnings',
            'allPreSolverDiagnostics',
            'daysData',
            'durationData',
            'statusTimeline'
        ));
    }

    /**
     * Classe une raison d'exclusion pré-solveur : attendu (congés) vs à corriger (données).
     */
    private function classifyExclusionReason(string $reason): string
    {
        if ($reason === 'Agent en congé complet pour cette date') {
            return 'expected';
        }
        return 'actionable';
    }

    public function equityReport(int $id)
    {
        $this->Authorization->authorize(new \App\Resource\PlanningGenerationJobsResource(), 'equityReport');

        return $this->redirect(['action' => 'view', $id, '?' => ['tab' => 'qualite', 'section' => 'equity']]);
    }

    /**
     * Charge les variables de vue du rapport d'équité.
     * En cas d'indisponibilité des données, pose des valeurs vides (pas de redirect).
     */
    private function loadEquityViewVars(int $id): void
    {
        $Jobs = $this->fetchTable('PlanningGenerationJobs');
        $Days = $this->fetchTable('PlanningGenerationJobDays');
        $DraftRanges = $this->fetchTable('DraftRanges');
        $Ranges = $this->fetchTable('Ranges');
        $Offers = $this->fetchTable('Offers');
        $Users = $this->fetchTable('Users');
        $Sites = $this->fetchTable('Sites');
        $FixedActivityRules = $this->fetchTable('FixedActivityRules');

        $job = $Jobs->get($id, ['contain' => ['Users', 'WfmSettings']]);

        $okDays = $Days->find()
            ->where(['job_id' => $id, 'status' => 'ok'])
            ->orderAsc('date')
            ->all()
            ->toList();

        if (empty($okDays)) {
            $this->setEmptyEquityViewVars($job);
            return;
        }

        $settings = $job->wfm_setting;
        $dayStartTime = $this->normalizeTime($settings?->day_start_time ?? null, '09:00:00');
        $dayEndTime = $this->normalizeTime($settings?->day_end_time ?? null, '17:00:00');

        $dayStartMin = $this->timeToMinutes($dayStartTime);
        $dayEndMin = $this->timeToMinutes($dayEndTime);
        if ($dayEndMin <= $dayStartMin) {
            $this->setEmptyEquityViewVars($job);
            return;
        }

        $minutesPerDay = $dayEndMin - $dayStartMin;
        $okDates = [];
        foreach ($okDays as $d) {
            $date = $d->date instanceof FrozenDate ? $d->date : new FrozenDate((string)$d->date);
            $okDates[] = $date->format('Y-m-d');
        }
        $okDates = array_values(array_unique($okDates));
        sort($okDates);

        $theoreticalMinutesTotal = $minutesPerDay * count($okDates);

        $periodStart = new FrozenTime($okDates[0] . ' ' . $dayStartTime);
        $periodEnd = new FrozenTime($okDates[count($okDates) - 1] . ' ' . $dayEndTime);

        // Construire les fenêtres jour par jour (timestamps) pour les overlaps
        $dayWindows = [];
        foreach ($okDates as $dateStr) {
            $ws = new FrozenTime($dateStr . ' ' . $dayStartTime);
            $we = new FrozenTime($dateStr . ' ' . $dayEndTime);
            $dayWindows[$dateStr] = [$ws->getTimestamp(), $we->getTimestamp()];
        }

        // Récupérer les brouillons (DraftRanges) sur la période OK
        $draftRows = $DraftRanges->find()
            ->where([
                'DraftRanges.job_id' => $id,
                'DraftRanges.date_start <' => $periodEnd,
                'DraftRanges.date_end >' => $periodStart,
            ])
            ->select(['id', 'user_id', 'offer_id', 'date_start', 'date_end'])
            ->all()
            ->toList();

        if (empty($draftRows)) {
            $this->setEmptyEquityViewVars($job);
            return;
        }

        $q = $this->request->getQueryParams();
        $filterSiteId = (int)($q['site_id'] ?? 0);
        $filterUserId = (int)($q['user_id'] ?? 0);
        $filterEquityGroup = trim((string)($q['equity_group'] ?? ''));

        // Offre -> type + name
        $offerIds = array_values(array_unique(array_map(fn($r) => (int)$r->offer_id, $draftRows)));
        $offersById = $Offers->find()
            ->where(['Offers.id IN' => $offerIds])
            ->select(['id', 'name', 'offer_type'])
            ->all()
            ->indexBy('id')
            ->toArray();

        // Offres "protégées" (absence / télétravail) pour dénominateurs
        $absenceOfferIds = $Offers->find()
            ->select(['id'])
            ->where(['offer_type' => 'absence'])
            ->all()
            ->extract('id')
            ->map(fn($v) => (int)$v)
            ->toList();
        $remoteWorkOfferIds = $Offers->find()
            ->select(['id'])
            ->where(['offer_type' => 'remote_work'])
            ->all()
            ->extract('id')
            ->map(fn($v) => (int)$v)
            ->toList();

        // Agents concernés (ceux qui ont du brouillon) : base "non filtrée" pour alimenter les dropdowns
        $allUserIdsInScope = array_values(array_unique(array_map(fn($r) => (int)$r->user_id, $draftRows)));

        $allUsersForLists = $Users->find()
            ->where(['Users.id IN' => $allUserIdsInScope])
            ->contain(['Sites'])
            ->select(['Users.id', 'Users.first_name', 'Users.last_name', 'Users.site_id', 'Sites.id', 'Sites.name'])
            ->orderAsc('Users.last_name')
            ->orderAsc('Users.first_name')
            ->all()
            ->toList();

        // Listes filtres (toujours complètes pour permettre de changer de site sans "perdre" les options)
        $usersList = [];
        $siteIds = [];
        foreach ($allUsersForLists as $u) {
            $usersList[(int)$u->id] = trim((string)$u->last_name . ' ' . (string)$u->first_name);
            if (!empty($u->site_id)) {
                $siteIds[] = (int)$u->site_id;
            }
        }
        asort($usersList);
        $siteIds = array_values(array_unique($siteIds));
        $sitesList = [];
        if (!empty($siteIds)) {
            $sitesList = $Sites->find('list', ['keyField' => 'id', 'valueField' => 'name'])
                ->where(['Sites.id IN' => $siteIds])
                ->orderAsc('Sites.name')
                ->toArray();
        }

        // Appliquer filtres (site / user) pour le calcul du rapport
        $userIds = $allUserIdsInScope;
        if ($filterUserId > 0) {
            $userIds = array_values(array_intersect($userIds, [$filterUserId]));
        }

        $usersQuery = $Users->find()
            ->where(['Users.id IN' => $userIds])
            ->contain(['Sites'])
            ->select(['Users.id', 'Users.first_name', 'Users.last_name', 'Users.site_id', 'Sites.id', 'Sites.name'])
            ->orderAsc('Users.last_name')
            ->orderAsc('Users.first_name');

        if ($filterSiteId > 0) {
            $usersQuery->where(['Users.site_id' => $filterSiteId]);
        }

        $users = $usersQuery
            ->all()
            ->indexBy('id')
            ->toArray();

        $userIds = array_keys($users);
        if (empty($userIds)) {
            $this->Flash->warning('Aucun agent ne correspond aux filtres (site/agent).');
            $this->setEmptyEquityViewVars($job, $sitesList, $usersList, $filterSiteId, $filterUserId, $filterEquityGroup);
            return;
        }

        // Absences + remote_work (ranges publiés) pour calculer les dénominateurs
        $protectedRanges = [];
        $allProtectedOfferIds = array_values(array_unique(array_merge($absenceOfferIds, $remoteWorkOfferIds)));
        if (!empty($allProtectedOfferIds)) {
            $protectedRanges = $Ranges->find()
                ->where([
                    'Ranges.user_id IN' => $userIds,
                    'Ranges.offer_id IN' => $allProtectedOfferIds,
                    'Ranges.date_start <' => $periodEnd,
                    'Ranges.date_end >' => $periodStart,
                ])
                ->select(['user_id', 'offer_id', 'date_start', 'date_end'])
                ->all()
                ->toList();
        }

        $absenceIntervals = []; // [user_id][dateStr] => [[sTs,eTs], ...]
        $remoteIntervals = [];  // [user_id][dateStr] => [[sTs,eTs], ...]

        foreach ($protectedRanges as $pr) {
            $uid = (int)$pr->user_id;
            $offerId = (int)$pr->offer_id;
            $isAbsence = in_array($offerId, $absenceOfferIds, true);
            $isRemote = in_array($offerId, $remoteWorkOfferIds, true);
            if (!$isAbsence && !$isRemote) {
                continue;
            }

            $rs = $pr->date_start instanceof \DateTimeInterface ? (new FrozenTime($pr->date_start)) : new FrozenTime((string)$pr->date_start);
            $re = $pr->date_end instanceof \DateTimeInterface ? (new FrozenTime($pr->date_end)) : new FrozenTime((string)$pr->date_end);
            $sTs = $rs->getTimestamp();
            $eTs = $re->getTimestamp();
            if ($eTs <= $sTs) {
                continue;
            }

            foreach ($dayWindows as $dateStr => [$ws, $we]) {
                $os = max($sTs, $ws);
                $oe = min($eTs, $we);
                if ($oe > $os) {
                    if ($isAbsence) {
                        $absenceIntervals[$uid][$dateStr][] = [$os, $oe];
                    } elseif ($isRemote) {
                        $remoteIntervals[$uid][$dateStr][] = [$os, $oe];
                    }
                }
            }
        }

        $unionMinutesByUser = function (array $intervalsByUser) use ($userIds): array {
            $minutesByUser = [];
            foreach ($userIds as $uid) {
                $total = 0;
                $perDay = $intervalsByUser[$uid] ?? [];
                foreach ($perDay as $intervals) {
                    usort($intervals, fn($a, $b) => $a[0] <=> $b[0]);
                    $curS = null;
                    $curE = null;
                    foreach ($intervals as [$s, $e]) {
                        if ($curS === null) {
                            $curS = $s; $curE = $e;
                            continue;
                        }
                        if ($s <= $curE) {
                            $curE = max($curE, $e);
                        } else {
                            $total += (int)round(($curE - $curS) / 60);
                            $curS = $s; $curE = $e;
                        }
                    }
                    if ($curS !== null) {
                        $total += (int)round(($curE - $curS) / 60);
                    }
                }
                $minutesByUser[$uid] = $total;
            }
            return $minutesByUser;
        };

        $absenceMinutesByUser = $unionMinutesByUser($absenceIntervals);
        $remoteMinutesByUser = $unionMinutesByUser($remoteIntervals);

        // Minutes par agent/offre (brouillon) sur jours OK, offre de base (offer_id)
        // - Work = tout sauf absence/remote_work/pause/lunch
        // - Pause/Lunch = affichés à part (ne comptent pas dans les %)
        $workMinutes = []; // [uid][offerId] => minutes
        $pauseMinutesByUser = []; // [uid] => minutes
        $lunchMinutesByUser = []; // [uid] => minutes
        $offersUsed = [];  // [offerId] => true

        foreach ($draftRows as $dr) {
            $uid = (int)$dr->user_id;
            if (!in_array($uid, $userIds, true)) {
                continue;
            }
            $offerId = (int)$dr->offer_id;
            $offer = $offersById[$offerId] ?? null;
            if (!$offer) {
                continue;
            }

            $type = (string)($offer->offer_type ?? '');
            $isProtected = in_array($type, ['absence', 'remote_work'], true);
            $isPause = ($type === 'pause');
            $isLunch = ($type === 'lunch');

            $rs = $dr->date_start instanceof \DateTimeInterface ? (new FrozenTime($dr->date_start)) : new FrozenTime((string)$dr->date_start);
            $re = $dr->date_end instanceof \DateTimeInterface ? (new FrozenTime($dr->date_end)) : new FrozenTime((string)$dr->date_end);
            $sTs = $rs->getTimestamp();
            $eTs = $re->getTimestamp();
            if ($eTs <= $sTs) {
                continue;
            }

            // Découper par jour OK (au cas où) et clipper à la fenêtre WFM
            foreach ($dayWindows as $dateStr => [$ws, $we]) {
                $os = max($sTs, $ws);
                $oe = min($eTs, $we);
                if ($oe > $os) {
                    $min = (int)round(($oe - $os) / 60);
                    if ($min > 0) {
                        if ($isPause) {
                            $pauseMinutesByUser[$uid] = (int)($pauseMinutesByUser[$uid] ?? 0) + $min;
                            continue;
                        }
                        if ($isLunch) {
                            $lunchMinutesByUser[$uid] = (int)($lunchMinutesByUser[$uid] ?? 0) + $min;
                            continue;
                        }
                        if ($isProtected) {
                            // Absence / Télétravail : pas dans les % (déjà utilisé au dénominateur dispo)
                            continue;
                        }

                        $workMinutes[$uid][$offerId] = (int)($workMinutes[$uid][$offerId] ?? 0) + $min;
                        $offersUsed[$offerId] = true;
                    }
                }
            }
        }

        $offersUsedIds = array_keys($offersUsed);
        sort($offersUsedIds);

        // Map offer_id → equity_group (activités couplées) pour agréger les colonnes
        $offerIdToEquityGroup = [];
        if (!empty($offersUsedIds)) {
            $rules = $FixedActivityRules->find()
                ->where([
                    'FixedActivityRules.offer_id IN' => $offersUsedIds,
                    'FixedActivityRules.active' => 1,
                ])
                ->select(['offer_id', 'equity_group_id'])
                ->all();
            foreach ($rules as $r) {
                $oid = (int)$r->offer_id;
                $g = $r->equity_group_id !== null && (string)$r->equity_group_id !== ''
                    ? (string)$r->equity_group_id
                    : (string)($offersById[$oid]->name ?? '#' . $oid);
                if (!isset($offerIdToEquityGroup[$oid]) || ($offerIdToEquityGroup[$oid] === '#' . $oid)) {
                    $offerIdToEquityGroup[$oid] = $g;
                }
            }
            foreach ($offersUsedIds as $oid) {
                if (!isset($offerIdToEquityGroup[$oid])) {
                    $offerIdToEquityGroup[$oid] = (string)($offersById[$oid]->name ?? '#' . $oid);
                }
            }
        }

        // Colonnes par groupe d'équité (une colonne par groupe, libellé = groupe ou noms d'offres)
        $groupToOfferIds = [];
        foreach ($offersUsedIds as $oid) {
            $g = $offerIdToEquityGroup[$oid] ?? (string)($offersById[$oid]->name ?? '#' . $oid);
            $groupToOfferIds[$g][] = $oid;
        }
        $equityGroupsColumns = [];
        foreach ($groupToOfferIds as $groupKey => $oids) {
            $labels = array_map(fn($id) => (string)($offersById[$id]->name ?? '#' . $id), $oids);
            $label = count($labels) > 1 ? $groupKey . ' (' . implode(', ', $labels) . ')' : ($labels[0] ?? $groupKey);
            $equityGroupsColumns[] = ['key' => $groupKey, 'label' => $label, 'offer_ids' => $oids];
        }
        usort($equityGroupsColumns, fn($a, $b) => strcasecmp($a['key'], $b['key']));

        // Filtre par offre planifiée (groupe d'équité) : ne garder que les agents avec au moins une minute sur ce groupe
        if ($filterEquityGroup !== '') {
            $matchingCol = null;
            foreach ($equityGroupsColumns as $col) {
                if ($col['key'] === $filterEquityGroup) {
                    $matchingCol = $col;
                    break;
                }
            }
            if ($matchingCol !== null) {
                $offerIdsForGroup = $matchingCol['offer_ids'];
                $userIds = array_values(array_filter($userIds, function ($uid) use ($workMinutes, $offerIdsForGroup) {
                    foreach ($offerIdsForGroup as $oid) {
                        if (($workMinutes[$uid][$oid] ?? 0) > 0) {
                            return true;
                        }
                    }
                    return false;
                }));
            }
        }

        if (empty($userIds)) {
            $this->Flash->warning('Aucun agent ne correspond aux filtres (site/agent/offre).');
            $this->setEmptyEquityViewVars($job, $sitesList, $usersList, $filterSiteId, $filterUserId, $filterEquityGroup);
            return;
        }

        // Recharger $users pour la liste restreinte (après filtre offre)
        $users = $Users->find()
            ->where(['Users.id IN' => $userIds])
            ->contain(['Sites'])
            ->select(['Users.id', 'Users.first_name', 'Users.last_name', 'Users.site_id', 'Sites.id', 'Sites.name'])
            ->orderAsc('Users.last_name')
            ->orderAsc('Users.first_name')
            ->all()
            ->indexBy('id')
            ->toArray();

        $userIds = array_keys($users);

        // Construire les lignes du tableau (avec minutes par groupe d'équité)
        $rows = [];
        foreach ($userIds as $uid) {
            $u = $users[$uid] ?? null;
            $name = $u ? trim((string)$u->last_name . ' ' . (string)$u->first_name) : ('#' . $uid);

            $absenceMin = (int)($absenceMinutesByUser[$uid] ?? 0);
            $remoteMin = (int)($remoteMinutesByUser[$uid] ?? 0);
            // Disponible = temps théorique - absences (le télétravail n'est pas une indisponibilité)
            $availableMin = max(0, $theoreticalMinutesTotal - $absenceMin);

            $offersMin = $workMinutes[$uid] ?? [];
            $totalWork = 0;
            foreach ($offersMin as $m) {
                $totalWork += (int)$m;
            }

            $groupMinutes = [];
            foreach ($equityGroupsColumns as $col) {
                $sum = 0;
                foreach ($col['offer_ids'] as $oid) {
                    $sum += (int)($offersMin[$oid] ?? 0);
                }
                $groupMinutes[$col['key']] = $sum;
            }

            $rows[] = [
                'user_id' => $uid,
                'name' => $name,
                'absence_minutes' => $absenceMin,
                'remote_minutes' => $remoteMin,
                'pause_minutes' => (int)($pauseMinutesByUser[$uid] ?? 0),
                'lunch_minutes' => (int)($lunchMinutesByUser[$uid] ?? 0),
                'theoretical_minutes' => $theoreticalMinutesTotal,
                'available_minutes' => $availableMin,
                'work_minutes_total' => $totalWork,
                'offers_minutes' => $offersMin,
                'group_minutes' => $groupMinutes,
            ];
        }

        $equityAvailable = true;
        $this->set(compact(
            'job',
            'okDates',
            'minutesPerDay',
            'theoreticalMinutesTotal',
            'offersUsedIds',
            'offersById',
            'equityGroupsColumns',
            'rows',
            'sitesList',
            'usersList',
            'filterSiteId',
            'filterUserId',
            'filterEquityGroup',
            'equityAvailable',
        ));
    }

    /**
     * Variables d'équité vides (section indisponible ou filtres trop restrictifs).
     */
    private function setEmptyEquityViewVars(
        mixed $job,
        array $sitesList = [],
        array $usersList = [],
        int $filterSiteId = 0,
        int $filterUserId = 0,
        string $filterEquityGroup = '',
    ): void {
        $this->set([
            'job' => $job,
            'okDates' => [],
            'minutesPerDay' => 0,
            'theoreticalMinutesTotal' => 0,
            'offersUsedIds' => [],
            'offersById' => [],
            'equityGroupsColumns' => [],
            'rows' => [],
            'sitesList' => $sitesList,
            'usersList' => $usersList,
            'filterSiteId' => $filterSiteId,
            'filterUserId' => $filterUserId,
            'filterEquityGroup' => $filterEquityGroup,
            'equityAvailable' => false,
        ]);
    }

    public function publish(int $id)
    {
        $this->Authorization->authorize(new \App\Resource\PlanningGenerationJobsResource(), 'publish');
        $this->request->allowMethod(['post']);

        $Jobs = $this->fetchTable('PlanningGenerationJobs');
        $Days = $this->fetchTable('PlanningGenerationJobDays');
        $DraftRanges = $this->fetchTable('DraftRanges');
        $Ranges = $this->fetchTable('Ranges');
        $Offers = $this->fetchTable('Offers');

        $job = $Jobs->get($id);

        $data = $this->request->getData();
        $rangeStart = !empty($data['publish_start']) ? new FrozenDate((string)$data['publish_start']) : $job->start_date;
        $rangeEnd = !empty($data['publish_end']) ? new FrozenDate((string)$data['publish_end']) : $job->end_date;
        if ($rangeEnd < $rangeStart) {
            $this->Flash->error('Plage de publication invalide (fin < début).');
            return $this->redirect(['action' => 'view', $id, '?' => ['tab' => 'qualite']]);
        }

        // Jours OK dans la plage demandée
        $okDays = $Days->find()
            ->where([
                'job_id' => $id,
                'status' => 'ok',
                'date >=' => $rangeStart->format('Y-m-d'),
                'date <=' => $rangeEnd->format('Y-m-d'),
            ])
            ->orderAsc('date')
            ->all()
            ->toList();

        if (empty($okDays)) {
            $this->Flash->warning('Aucun jour publiable (aucun jour OK dans la plage).');
            return $this->redirect(['action' => 'view', $id, '?' => ['tab' => 'qualite']]);
        }

        $protectedOfferIds = $Offers->find()
            ->select(['id'])
            ->where(['offer_type IN' => ['absence', 'remote_work']])
            ->all()
            ->extract('id')
            ->map(fn($v) => (int)$v)
            ->toList();

        $connection = $Ranges->getConnection();
        $publishedDays = 0;
        $skippedDraftRanges = 0;

        $connection->begin();
        try {
            foreach ($okDays as $dayRow) {
                $date = $dayRow->date instanceof FrozenDate ? $dayRow->date : new FrozenDate((string)$dayRow->date);
                $dateStr = $date->format('Y-m-d');
                $dayStart = new FrozenTime($dateStr . ' 00:00:00');
                $dayEnd = new FrozenTime($dateStr . ' 23:59:59');

                // Supprimer les ranges existants (hors absences/télétravail), et tout ce qui a été généré avant.
                $deleteConditions = [
                    'OR' => [
                        ['comment LIKE' => 'Généré par WFM%'],
                        ['offer_id NOT IN' => $protectedOfferIds],
                    ],
                    'DATE(date_start)' => $dateStr,
                ];
                if (empty($protectedOfferIds)) {
                    $deleteConditions = ['DATE(date_start)' => $dateStr];
                }
                $Ranges->deleteAll($deleteConditions);

                // Charger ranges protégés du jour (pour éviter de publier par-dessus)
                $protectedRangesByUser = [];
                if (!empty($protectedOfferIds)) {
                    $protectedRows = $Ranges->find()
                        ->where([
                            'offer_id IN' => $protectedOfferIds,
                            'date_start <=' => $dayEnd,
                            'date_end >=' => $dayStart,
                        ])
                        ->select(['user_id', 'date_start', 'date_end'])
                        ->all();
                    foreach ($protectedRows as $pr) {
                        $uid = (int)$pr->user_id;
                        $protectedRangesByUser[$uid][] = [$pr->date_start, $pr->date_end];
                    }
                }

                $draftRows = $DraftRanges->find()
                    ->where([
                        'job_id' => $id,
                        'date_start <=' => $dayEnd,
                        'date_end >=' => $dayStart,
                    ])
                    ->all()
                    ->toList();

                if (empty($draftRows)) {
                    continue;
                }

                $toInsert = [];
                foreach ($draftRows as $dr) {
                    $uid = (int)$dr->user_id;
                    $ds = $dr->date_start;
                    $de = $dr->date_end;
                    $blocked = false;

                    foreach (($protectedRangesByUser[$uid] ?? []) as [$ps, $pe]) {
                        // chevauchement ?
                        if ($ds < $pe && $de > $ps) {
                            $blocked = true;
                            break;
                        }
                    }

                    if ($blocked) {
                        $skippedDraftRanges++;
                        continue;
                    }

                    $toInsert[] = $Ranges->newEntity([
                        'user_id' => $uid,
                        'offer_id' => (int)$dr->offer_id,
                        'date_start' => $ds,
                        'date_end' => $de,
                        'comment' => 'Publié depuis brouillon (job #' . $id . ')',
                    ]);
                }

                if (!empty($toInsert)) {
                    $Ranges->saveManyOrFail($toInsert);
                    $publishedDays++;
                }
            }

            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollback();
            $this->Flash->error('Erreur publication: ' . $e->getMessage());
            return $this->redirect(['action' => 'view', $id, '?' => ['tab' => 'qualite']]);
        }

        $msg = "Publication effectuée: {$publishedDays} jour(s) publiés.";
        if ($skippedDraftRanges > 0) {
            $msg .= " {$skippedDraftRanges} segment(s) brouillon ignoré(s) (conflit absences/télétravail).";
        }
        $this->Flash->success($msg);

        return $this->redirect(['controller' => 'Grids', 'action' => 'index', '?' => [
            'date_start' => (new \DateTime($job->start_date->format('Y-m-d')))->format('d/m/Y'),
            'date_end' => (new \DateTime($job->end_date->format('Y-m-d')))->format('d/m/Y'),
        ]]);
    }

    /**
     * Supprime le brouillon (sans toucher au planning publié).
     */
    public function clearDraft(int $id)
    {
        $this->Authorization->authorize(new \App\Resource\PlanningGenerationJobsResource(), 'publish');
        $this->request->allowMethod(['post']);

        $DraftRanges = $this->fetchTable('DraftRanges');
        $deleted = $DraftRanges->deleteAll(['job_id' => $id]);

        $this->Flash->success("Brouillon supprimé ({$deleted} segment(s)).");
        return $this->redirect(['action' => 'view', $id, '?' => ['tab' => 'qualite']]);
    }

    /**
     * Affiche le brouillon dans la même vue que la grille principale.
     */
    public function draft(int $id)
    {
        $this->Authorization->authorize(new \App\Resource\PlanningGenerationJobsResource(), 'draft');

        $q = $this->request->getQueryParams();
        $embed = $q['embed'] ?? null;
        if (empty($embed)) {
            $redirectQuery = $q;
            $redirectQuery['tab'] = 'planning';
            unset($redirectQuery['embed']);
            return $this->redirect(['action' => 'view', $id, '?' => $redirectQuery]);
        }

        $Jobs = $this->fetchTable('PlanningGenerationJobs');
        $Days = $this->fetchTable('PlanningGenerationJobDays');
        $job = $Jobs->get($id, ['contain' => ['WfmSettings']]);

        // Mode embed : grille brouillon dans le workspace (iframe)
        // Layout « embed » (pas ajax) : CSS/JS requis pour la grille
        $this->viewBuilder()->setLayout('embed');

        // --- Par défaut: afficher le premier jour déjà "généré" (ok/infeasible/error).
        // Si l'utilisateur filtre via date_start/date_end, on respecte sa sélection.
        $dateStartStr = (string)($q['date_start'] ?? '');
        $dateEndStr = (string)($q['date_end'] ?? '');

        $beginDay = null;
        $endDay = null;
        if ($dateStartStr !== '') {
            $beginDay = FrozenDate::createFromFormat('d/m/Y', $dateStartStr) ?: null;
        }
        if ($dateEndStr !== '') {
            $endDay = FrozenDate::createFromFormat('d/m/Y', $dateEndStr) ?: null;
        }

        if (!$beginDay) {
            $firstDone = $Days->find()
                ->where(['job_id' => $id, 'status IN' => ['ok', 'infeasible', 'error']])
                ->orderAsc('date')
                ->first();
            $beginDay = $firstDone && $firstDone->date ? $firstDone->date : $job->start_date;
        }
        if (!$endDay) {
            // Afficher un seul jour par défaut
            $endDay = $beginDay;
        }

        // Clamp à la période du job
        if ($beginDay < $job->start_date) {
            $beginDay = $job->start_date;
        }
        if ($endDay > $job->end_date) {
            $endDay = $job->end_date;
        }

        $begin = new FrozenTime($beginDay->format('Y-m-d') . ' 00:00:00');
        $end = new FrozenTime($endDay->format('Y-m-d') . ' 23:59:59');
        $day_ranges = ['begin' => $begin, 'end' => $end];

        // Charger les données nécessaires à templates/Grids/index.php
        $Users = $this->fetchTable('Users');
        $Offers = $this->fetchTable('Offers');
        $Sites = $this->fetchTable('Sites');
        $Alerts = $this->fetchTable('Alerts');
        $displaySettingsTable = $this->fetchTable('DisplaySettings');

        $gridStartHour = (int)$displaySettingsTable->getValue('grid_start_hour', 8);
        $gridEndHour = (int)$displaySettingsTable->getValue('grid_end_hour', 18);

        $offers_list = $Offers->find('DisplayedInGrid');
        $users_list = $Users->find();
        $sites_list = $Sites->find();
        $alerts_list = $Alerts->find('ThisDay', $day_ranges);

        $params = $this->request->getQueryParams();
        $params['date_start'] = $begin;
        $params['date_end'] = $end;
        $sortBy = 'site_name';
        $order = ['Sites.name' => 'ASC', 'Users.last_name' => 'ASC', 'Users.first_name' => 'ASC'];

        // Users + DraftRanges (job) + Ranges (absences/remote_work) pour contexte et télétravail
        $usersQuery = $Users->find()
            ->contain(['Sites', 'Roles', 'UserAvailabilities', 'UserRemoteWorkSetting', 'UserContracts'])
            ->contain('DraftRanges', function ($q) use ($id, $day_ranges) {
                return $q
                    ->where([
                        'DraftRanges.job_id' => $id,
                        'DraftRanges.date_start <=' => $day_ranges['end'],
                        'DraftRanges.date_end >=' => $day_ranges['begin'],
                    ])
                    ->contain(['Offers']);
            })
            ->contain('Ranges', function ($q) use ($day_ranges) {
                // On ne charge que ce qui sert de contexte (absences + télétravail) pour éviter de surcharger.
                return $q
                    ->where([
                        'Ranges.date_start <=' => $day_ranges['end'],
                        'Ranges.date_end >=' => $day_ranges['begin'],
                    ])
                    ->contain(['Offers'])
                    ->matching('Offers', function ($q2) {
                        return $q2->where(['Offers.offer_type IN' => ['absence', 'remote_work']]);
                    });
            })
            ->leftJoinWith('Sites')
            ->order($order);

        // Filtres: site / user (côté Users), offer (côté DraftRanges)
        $siteId = (int)($q['site_id'] ?? 0);
        if ($siteId > 0) {
            $usersQuery->where(['Users.site_id' => $siteId]);
        }
        $userId = (int)($q['user_id'] ?? 0);
        if ($userId > 0) {
            $usersQuery->where(['Users.id' => $userId]);
        }
        $offerIdRaw = $q['offer_id'] ?? null;
        $offerIds = is_array($offerIdRaw)
            ? array_values(array_filter(array_map('intval', $offerIdRaw)))
            : ((int)$offerIdRaw > 0 ? [(int)$offerIdRaw] : []);
        if (!empty($offerIds)) {
            $usersQuery->innerJoinWith('DraftRanges', function ($q2) use ($id, $offerIds, $day_ranges) {
                return $q2->where([
                    'DraftRanges.job_id' => $id,
                    'DraftRanges.offer_id IN' => $offerIds,
                    'DraftRanges.date_start <=' => $day_ranges['end'],
                    'DraftRanges.date_end >=' => $day_ranges['begin'],
                ]);
            });
            $usersQuery->distinct(true);
        }

        $users_ranges = $usersQuery;

        // Publication de scénario: utiliser le scenario_id du job pour permettre l'affichage du graphique de besoin
        $publishedByDate = [];
        if ($job->scenario_id && $job->scenario_id > 0) {
            $scanDay = clone $beginDay;
            while ($scanDay <= $endDay) {
                // Ignorer les weekends comme dans la logique de génération du job
                if (!$scanDay->isWeekend()) {
                    $dateKey = $scanDay->i18nFormat('yyyy-MM-dd');
                    $publishedByDate[$dateKey] = (int)$job->scenario_id;
                }
                $scanDay = $scanDay->addDays(1);
            }
        }

        // Réutiliser la vue Grids/index.php
        $this->viewBuilder()->setTemplatePath('Grids');
        $this->viewBuilder()->setTemplate('index');

        $saveUrl = ['controller' => 'PlanningGenerationJobs', 'action' => 'saveDraft', $id];
        $plannedSeriesBaseUrl = Router::url(['controller' => 'PlanningGenerationJobs', 'action' => 'draftPlannedSeries', '_ext' => 'json']);
        $plannedSeriesExtraQuery = '&job_id=' . $id;
        // Conserver embed=1 pour que les filtres de la grille restent dans l'iframe
        $searchUrl = ['controller' => 'PlanningGenerationJobs', 'action' => 'draft', $id, '?' => ['embed' => '1']];

        $rangesProperty = 'draft_ranges';

        $embedMode = true;
        $this->set(compact(
            'users_ranges',
            'offers_list',
            'users_list',
            'sites_list',
            'alerts_list',
            'day_ranges',
            'params',
            'sortBy',
            'publishedByDate',
            'gridStartHour',
            'gridEndHour',
            'saveUrl',
            'plannedSeriesBaseUrl',
            'plannedSeriesExtraQuery',
            'searchUrl',
            'rangesProperty',
            'job',
            'embedMode',
        ));
    }

    public function draftPlannedSeries()
    {
        $this->Authorization->authorize(new \App\Resource\PlanningGenerationJobsResource(), 'draft');
        $this->request->allowMethod(['get']);

        $jobId = (int)$this->request->getQuery('job_id');
        $offerId = (int)$this->request->getQuery('offer_id');
        $dateStr = (string)$this->request->getQuery('date'); // YYYY-MM-DD

        $this->viewBuilder()->setClassName('Json');
        if ($jobId <= 0 || $offerId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            $this->set(['success' => false, 'series' => null]);
            $this->viewBuilder()->setOption('serialize', ['success', 'series']);
            return;
        }

        $WfmSettings = $this->fetchTable('WfmSettings')->find()->first();
        $startTime = $this->normalizeTime((string)$WfmSettings->day_start_time, '09:00:00');
        $endTime = $this->normalizeTime((string)$WfmSettings->day_end_time, '17:00:00');

        $start = new FrozenTime($dateStr . ' ' . $startTime);
        $end = new FrozenTime($dateStr . ' ' . $endTime);

        $data = [];
        for ($t = $start; $t->getTimestamp() < $end->getTimestamp(); $t = $t->addMinutes(15)) {
            $data[$t->format('H:i')] = 0;
        }

        $DraftRanges = $this->fetchTable('DraftRanges');
        $rows = $DraftRanges->find()
            ->where([
                'job_id' => $jobId,
                'offer_id' => $offerId,
                'date_start <' => $end,
                'date_end >' => $start,
            ])
            ->select(['date_start', 'date_end'])
            ->all();

        foreach ($rows as $r) {
            $rs = $r->date_start instanceof \DateTimeInterface ? new FrozenTime($r->date_start) : new FrozenTime((string)$r->date_start);
            $re = $r->date_end instanceof \DateTimeInterface ? new FrozenTime($r->date_end) : new FrozenTime((string)$r->date_end);

            if ($rs->getTimestamp() < $start->getTimestamp()) {
                $rs = $start;
            }
            if ($re->getTimestamp() > $end->getTimestamp()) {
                $re = $end;
            }

            // Arrondis aux quarts d'heure comme GridsController::plannedSeries
            $rsMin = (int)$rs->format('i');
            $rsSec = (int)$rs->format('s');
            $extra = $rsMin % 15 === 0 && $rsSec === 0 ? 0 : (15 - ($rsMin % 15)) % 15;
            if ($extra > 0 || $rsSec !== 0) {
                $rs = $rs->addMinutes($extra)->setTime((int)$rs->format('H'), (int)$rs->format('i'), 0);
            }

            $reMin = (int)$re->format('i');
            $reSec = (int)$re->format('s');
            if ($reSec !== 0) {
                $re = $re->setTime((int)$re->format('H'), $reMin, 0);
            }
            $re = $re->subSeconds(1);

            for ($t = $rs; $t->getTimestamp() < $re->getTimestamp(); $t = $t->addMinutes(15)) {
                $key = $t->format('H:i');
                if (array_key_exists($key, $data)) {
                    $data[$key]++;
                }
            }
        }

        $this->set([
            'success' => true,
            'series' => [
                'stepSeconds' => 900,
                'startTime' => $startTime,
                'endTime' => $endTime,
                'data' => $data,
            ],
        ]);
        $this->viewBuilder()->setOption('serialize', ['success', 'series']);
    }

    /**
     * Sauvegarde (AJAX) les modifications manuelles du brouillon, au même format que GridsController::add().
     */
    public function saveDraft(int $id)
    {
        $this->Authorization->authorize(new \App\Resource\PlanningGenerationJobsResource(), 'saveDraft');
        $this->request->allowMethod(['post']);

        $DraftRanges = $this->fetchTable('DraftRanges');

        // Réutiliser la structure de réponse JSON de GridsController::add()
        $messages = [];
        $responseStatus = 'error';

        $array = $this->request->getData();
        $json_data = $array['planning_data'] ?? null;
        if (empty($json_data) || $json_data === '[]') {
            $messages[] = ['message' => __('Aucune modification de planning à enregistrer.'), 'element' => 'flash/info'];
            $responseStatus = 'info';
            goto handle_response;
        }

        $rangesFromJSON = json_decode((string)$json_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $messages[] = ['message' => __('Erreur de décodage des données de planning (JSON invalide).'), 'element' => 'flash/error'];
            goto handle_response;
        }

        // Gestion des dates (obligatoire pour la logique de fusion)
        $day_ranges_strings = $array['day_ranges'] ?? [];
        $day_ranges = [];
        if (!empty($day_ranges_strings['begin'])) {
            $day_ranges['begin'] = new FrozenTime((string)$day_ranges_strings['begin']);
        }
        if (!empty($day_ranges_strings['end'])) {
            $day_ranges['end'] = new FrozenTime((string)$day_ranges_strings['end']);
        }
        if (empty($day_ranges['begin']) || empty($day_ranges['end'])) {
            $messages[] = ['message' => __('Erreur: La plage de dates est manquante.'), 'element' => 'flash/error'];
            goto handle_response;
        }

        // Concaténer les actions brutes du JSON (même logique que GridsController)
        $rangesByUser = [];
        foreach ($rangesFromJSON as $range) {
            if (!isset($range['user_id'])) {
                continue;
            }
            $rangesByUser[$range['user_id']][] = $range;
        }

        $actionRanges = [];
        foreach ($rangesByUser as $ranges) {
            usort($ranges, fn($a, $b) => strcmp((string)$a['date_start'], (string)$b['date_start']));
            $currentRange = array_shift($ranges);
            if (!$currentRange) {
                continue;
            }
            foreach ($ranges as $nextRange) {
                if (
                    ($currentRange['date_end'] == $nextRange['date_start'])
                    && ($currentRange['user_id'] == $nextRange['user_id'])
                    && ($currentRange['offer_id'] == $nextRange['offer_id'])
                ) {
                    $currentRange['date_end'] = $nextRange['date_end'];
                    if (!empty($nextRange['id'])) {
                        $currentRange['id'] = $nextRange['id'];
                    }
                } else {
                    $actionRanges[] = $currentRange;
                    $currentRange = $nextRange;
                }
            }
            $actionRanges[] = $currentRange;
        }

        if (empty($actionRanges)) {
            $messages[] = ['message' => __('Aucune modification valide trouvée après traitement.'), 'element' => 'flash/info'];
            $responseStatus = 'info';
            goto handle_response;
        }

        // Déterminer zone de travail
        $affectedUserIds = [];
        $minStart = null;
        $maxEnd = null;
        foreach ($actionRanges as $action) {
            $affectedUserIds[] = $action['user_id'];
            $start = new FrozenTime((string)$action['date_start']);
            $end = new FrozenTime((string)$action['date_end']);
            if ($minStart === null || $start < $minStart) {
                $minStart = $start;
            }
            if ($maxEnd === null || $end > $maxEnd) {
                $maxEnd = $end;
            }
        }
        $affectedUserIds = array_values(array_unique($affectedUserIds));

        // Charger état initial BDD (scopé job_id)
        $initialDBRanges = $DraftRanges->find()
            ->where([
                'job_id' => $id,
                'user_id IN' => $affectedUserIds,
                'date_end >' => $minStart,
                'date_start <' => $maxEnd,
            ])
            ->all()
            ->toList();

        $finalIdsToDelete = [];
        $workingRanges = [];
        foreach ($initialDBRanges as $dbRange) {
            $finalIdsToDelete[] = (int)$dbRange->id;
            $workingRanges[] = [
                'job_id' => (int)$dbRange->job_id,
                'user_id' => (int)$dbRange->user_id,
                'offer_id' => (int)$dbRange->offer_id,
                'date_start' => $dbRange->date_start,
                'date_end' => $dbRange->date_end,
                'comment' => $dbRange->comment,
            ];
        }

        // Appliquer actions
        foreach ($actionRanges as $actionRange) {
            $actionStart = new FrozenTime((string)$actionRange['date_start']);
            $actionEnd = new FrozenTime((string)$actionRange['date_end']);
            $actionOfferId = (int)$actionRange['offer_id'];
            $actionUserId = (int)$actionRange['user_id'];
            $isDeletion = ($actionOfferId === 0);

            $nextWorkingRanges = [];
            foreach ($workingRanges as $currentRange) {
                $currentStart = $currentRange['date_start'] instanceof \DateTimeInterface
                    ? $currentRange['date_start']
                    : new FrozenTime((string)$currentRange['date_start']);
                $currentEnd = $currentRange['date_end'] instanceof \DateTimeInterface
                    ? $currentRange['date_end']
                    : new FrozenTime((string)$currentRange['date_end']);

                if ($currentRange['user_id'] !== $actionUserId || $currentEnd <= $actionStart || $currentStart >= $actionEnd) {
                    $nextWorkingRanges[] = $currentRange;
                    continue;
                }

                if ($currentStart < $actionStart) {
                    $nextWorkingRanges[] = [
                        'job_id' => $id,
                        'user_id' => $currentRange['user_id'],
                        'offer_id' => $currentRange['offer_id'],
                        'date_start' => $currentStart,
                        'date_end' => $actionStart,
                        'comment' => $currentRange['comment'],
                    ];
                }

                if ($currentEnd > $actionEnd) {
                    $nextWorkingRanges[] = [
                        'job_id' => $id,
                        'user_id' => $currentRange['user_id'],
                        'offer_id' => $currentRange['offer_id'],
                        'date_start' => $actionEnd,
                        'date_end' => $currentEnd,
                        'comment' => $currentRange['comment'],
                    ];
                }
            }

            if (!$isDeletion) {
                $nextWorkingRanges[] = [
                    'job_id' => $id,
                    'user_id' => $actionUserId,
                    'offer_id' => $actionOfferId,
                    'date_start' => $actionStart,
                    'date_end' => $actionEnd,
                    'comment' => 'Brouillon WFM (job #' . $id . ')',
                ];
            }

            $workingRanges = $nextWorkingRanges;
        }

        // Supprimer anciens ranges de la zone (scopé job)
        if (!empty($finalIdsToDelete)) {
            $DraftRanges->deleteAll(['id IN' => $finalIdsToDelete]);
        }

        // Sauver nouveaux ranges
        $entities = [];
        foreach ($workingRanges as $r) {
            $entities[] = $DraftRanges->newEntity($r);
        }

        if (!empty($entities)) {
            $DraftRanges->saveManyOrFail($entities);
        }

        $messages[] = ['message' => __('Brouillon enregistré.'), 'element' => 'flash/success'];
        $responseStatus = 'success';

        handle_response:
        $this->viewBuilder()->setClassName('Json');
        $this->set([
            'status' => $responseStatus,
            '_message' => $messages,
        ]);
        $this->viewBuilder()->setOption('serialize', ['status', '_message']);
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

    private function timeToMinutes(string $hhmmss): int
    {
        $t = $this->normalizeTime($hhmmss, '00:00:00');
        $parts = explode(':', $t);
        $h = isset($parts[0]) ? (int)$parts[0] : 0;
        $m = isset($parts[1]) ? (int)$parts[1] : 0;
        return ($h * 60) + $m;
    }
}


