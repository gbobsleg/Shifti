<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Entity\ProphetTuningJob;
use App\Service\ProphetOptunaConfig;
use Cake\Http\Exception\NotFoundException;
use Cake\Routing\Router;

/**
 * Offers Controller
 *
 * @property \App\Model\Table\OffersTable $Offers
 * @method \App\Model\Entity\Offer[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class OffersController extends AppController
{
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $this->Authorization->authorize(new \App\Resource\OffersResource(), 'index');
        $offers = $this->paginate($this->Offers);

        // Statistiques
        $allOffers = $this->Offers->find('all')->contain(['Skills', 'Ranges']);
        $stats = [
            'total' => $allOffers->count(),
            'with_skills' => 0,
            'without_skills' => 0,
            'total_ranges' => 0
        ];
        
        foreach ($allOffers as $offer) {
            $skillsCount = isset($offer->skills) ? count($offer->skills) : 0;
            if ($skillsCount > 0) {
                $stats['with_skills']++;
            } else {
                $stats['without_skills']++;
            }
            
            $stats['total_ranges'] += isset($offer->ranges) ? count($offer->ranges) : 0;
        }

        $this->set(compact('offers', 'stats'));
    }

    /**
     * View method
     *
     * @param string|null $id Offer id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\OffersResource(), 'view');
        // joinType LEFT obligatoire sur OfferGroups : le belongsTo est INNER par défaut,
        // ce qui exclut l'offre entière si elle n'est pas membre d'un groupe.
        $offer = $this->Offers->get($id, contain: [
            'OfferGroupAsMixed',
            'OfferGroupMember' => [
                'OfferGroups' => ['joinType' => 'LEFT'],
            ],
        ]);

        $prophetDefaults = $this->getProphetDefaultsForOffer($offer);
        $hasOfferProphetProfile = !empty($offer->prophet_default_settings_json);

        // Bornes des données historiques pour cette offre (affichage de la plage disponible)
        $HistoricalData = $this->fetchTable('HistoricalData');
        $q = $HistoricalData->find()
            ->where(['offer_id' => $offer->id]);
        $bounds = $q
            ->select([
                'min_date' => $q->func()->min('datetime_interval', ['datetime']),
                'max_date' => $q->func()->max('datetime_interval', ['datetime']),
            ])
            ->enableHydration(false)
            ->first();
        $historyMinDate = null;
        $historyMaxDate = null;
        if ($bounds && !empty($bounds['min_date']) && !empty($bounds['max_date'])) {
            $minRaw = $bounds['min_date'];
            $maxRaw = $bounds['max_date'];

            if ($minRaw instanceof \DateTimeInterface) {
                $historyMinDate = $minRaw->format('Y-m-d');
            } else {
                $historyMinDate = (new \DateTime((string)$minRaw))->format('Y-m-d');
            }

            if ($maxRaw instanceof \DateTimeInterface) {
                $historyMaxDate = $maxRaw->format('Y-m-d');
            } else {
                $historyMaxDate = (new \DateTime((string)$maxRaw))->format('Y-m-d');
            }

            if ($historyMinDate > $historyMaxDate) {
                $historyMinDate = null;
                $historyMaxDate = null;
            }
        }

        $this->set(compact(
            'offer',
            'prophetDefaults',
            'hasOfferProphetProfile',
            'historyMinDate',
            'historyMaxDate'
        ));
        $this->set('prophetTuning', $this->buildProphetTuningViewData($offer));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $this->Authorization->authorize(new \App\Resource\OffersResource(), 'add');
        $offer = $this->Offers->newEmptyEntity();
        if ($this->request->is('post')) {
            $data = $this->request->getData();
            
            // Valeurs par défaut pour nouveaux champs si non fournis
            if (!isset($data['offer_type'])) {
                $data['offer_type'] = 'normal';
            }
            if (!isset($data['display_order'])) {
                $data['display_order'] = 10;
            }
            if (!isset($data['is_displayed_in_grid'])) {
                $data['is_displayed_in_grid'] = 1;
            }
            if (!isset($data['is_forecastable'])) {
                $data['is_forecastable'] = 1;
            }
            if (!isset($data['default_forecast_method'])) {
                $data['default_forecast_method'] = 'historical';
            }
            
            // Construire les paramètres Prophet par défaut à partir du sous-formulaire
            $prophetDefaults = $this->buildProphetDefaultsFromRequest($data['prophet_defaults'] ?? []);
            // Sur création d'offre, on force la plage historique à "tout l'historique"
            $prophetDefaults['history_start_date'] = null;
            $prophetDefaults['history_end_date'] = null;
            $data['prophet_default_settings_json'] = $prophetDefaults ? json_encode($prophetDefaults) : null;

            $offer = $this->Offers->patchEntity($offer, $data);
            if ($this->Offers->save($offer)) {
                $this->Flash->success("L'offre a été sauvegardée.");

                return $this->redirect(['action' => 'view', $offer->id]);
            }
            $this->Flash->error("L'offre n'a pas pu être sauvegardée. Merci d'essayer à nouveau.");
        }

        $prophetDefaults = $this->getProphetDefaultsForOffer($offer);
        // Bornes globales des données historiques (pour limiter les dates de plage par défaut)
        $HistoricalData = $this->fetchTable('HistoricalData');
        $q = $HistoricalData->find();
        $bounds = $q
            ->select([
                'min_date' => $q->func()->min('datetime_interval', ['datetime']),
                'max_date' => $q->func()->max('datetime_interval', ['datetime']),
            ])
            ->enableHydration(false)
            ->first();
        $historyMinDate = null;
        $historyMaxDate = null;
        if ($bounds && !empty($bounds['min_date']) && !empty($bounds['max_date'])) {
            $minRaw = $bounds['min_date'];
            $maxRaw = $bounds['max_date'];

            // Cake renvoie déjà des objets DateTimeInterface pour les agrégats datetime.
            if ($minRaw instanceof \DateTimeInterface) {
                $historyMinDate = $minRaw->format('Y-m-d');
            } else {
                $historyMinDate = (new \DateTime((string)$minRaw))->format('Y-m-d');
            }

            if ($maxRaw instanceof \DateTimeInterface) {
                $historyMaxDate = $maxRaw->format('Y-m-d');
            } else {
                $historyMaxDate = (new \DateTime((string)$maxRaw))->format('Y-m-d');
            }

            // Si pour une raison quelconque min > max, on neutralise les bornes
            if ($historyMinDate > $historyMaxDate) {
                $historyMinDate = null;
                $historyMaxDate = null;
            }
        }

        // Si des valeurs déjà enregistrées sortent de la plage [min, max], on les remet à null
        if (!empty($prophetDefaults['history_start_date']) && $historyMinDate && $historyMaxDate) {
            $start = $prophetDefaults['history_start_date'];
            if ($start < $historyMinDate || $start > $historyMaxDate) {
                $prophetDefaults['history_start_date'] = null;
            }
        }
        if (!empty($prophetDefaults['history_end_date']) && $historyMinDate && $historyMaxDate) {
            $end = $prophetDefaults['history_end_date'];
            if ($end < $historyMinDate || $end > $historyMaxDate) {
                $prophetDefaults['history_end_date'] = null;
            }
        }

        $this->set(compact('offer', 'prophetDefaults', 'historyMinDate', 'historyMaxDate'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Offer id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\OffersResource(), 'edit');
        $offer = $this->Offers->get($id, [
            'contain' => [],
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $data = $this->request->getData();
            // Construire les paramètres Prophet par défaut à partir du sous-formulaire
            $prophetDefaults = $this->buildProphetDefaultsFromRequest($data['prophet_defaults'] ?? []);
            $data['prophet_default_settings_json'] = $prophetDefaults ? json_encode($prophetDefaults) : null;
            $data['prophet_tuning_enabled'] = !empty($data['prophet_tuning_enabled']);
            unset($data['prophet_defaults']);

            $offer = $this->Offers->patchEntity($offer, $data);
            if ($this->Offers->save($offer)) {
                $this->Flash->success("L'offre a été sauvegardée.");

                return $this->redirect(['action' => 'view', $offer->id]);
            }
            $this->Flash->error("L'offre n'a pas pu être sauvegardée. Merci d'essayer à nouveau.");
        }

        $prophetDefaults = $this->getProphetDefaultsForOffer($offer);
        // Bornes des données historiques pour CETTE offre (pour limiter les dates de plage par défaut)
        $HistoricalData = $this->fetchTable('HistoricalData');
        $q = $HistoricalData->find()
            ->where(['offer_id' => $offer->id]);
        $bounds = $q
            ->select([
                'min_date' => $q->func()->min('datetime_interval', ['datetime']),
                'max_date' => $q->func()->max('datetime_interval', ['datetime']),
            ])
            ->enableHydration(false)
            ->first();
        $historyMinDate = null;
        $historyMaxDate = null;
        if ($bounds && !empty($bounds['min_date']) && !empty($bounds['max_date'])) {
            $minRaw = $bounds['min_date'];
            $maxRaw = $bounds['max_date'];

            if ($minRaw instanceof \DateTimeInterface) {
                $historyMinDate = $minRaw->format('Y-m-d');
            } else {
                $historyMinDate = (new \DateTime((string)$minRaw))->format('Y-m-d');
            }

            if ($maxRaw instanceof \DateTimeInterface) {
                $historyMaxDate = $maxRaw->format('Y-m-d');
            } else {
                $historyMaxDate = (new \DateTime((string)$maxRaw))->format('Y-m-d');
            }

            // Si pour une raison quelconque min > max, on neutralise les bornes
            if ($historyMinDate > $historyMaxDate) {
                $historyMinDate = null;
                $historyMaxDate = null;
            }
        }

        // Si des valeurs déjà enregistrées sortent de la plage [min, max], on les remet à null
        if (!empty($prophetDefaults['history_start_date']) && $historyMinDate && $historyMaxDate) {
            $start = $prophetDefaults['history_start_date'];
            if ($start < $historyMinDate || $start > $historyMaxDate) {
                $prophetDefaults['history_start_date'] = null;
            }
        }
        if (!empty($prophetDefaults['history_end_date']) && $historyMinDate && $historyMaxDate) {
            $end = $prophetDefaults['history_end_date'];
            if ($end < $historyMinDate || $end > $historyMaxDate) {
                $prophetDefaults['history_end_date'] = null;
            }
        }

        $this->set(compact('offer', 'prophetDefaults', 'historyMinDate', 'historyMaxDate'));
        $this->set('prophetTuning', $this->buildProphetTuningViewData($offer));
    }

    /**
     * Met en file un job de tuning Optuna (manuel).
     * Blindage : refuse seulement si cette offre a déjà un job queued/running.
     * Les jobs des autres offres restent en file (le worker les traite en série).
     *
     * @param string|null $id Offer id
     */
    public function tuneStart($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\OffersResource(), 'tuneStart');
        $this->request->allowMethod(['post']);

        $offer = $this->Offers->get((int)$id, contain: []);
        $Jobs = $this->fetchTable('ProphetTuningJobs');

        $busy = $Jobs->find()
            ->select(['id', 'status'])
            ->where([
                'offer_id' => (int)$offer->id,
                'status IN' => [
                    ProphetTuningJob::STATUS_QUEUED,
                    ProphetTuningJob::STATUS_RUNNING,
                ],
            ])
            ->contain([])
            ->first();

        if ($busy) {
            $message = sprintf(
                'Cette offre a déjà un job de tuning %s (job #%d). Attendez la fin ou utilisez Annuler avant d’en relancer un.',
                (string)$busy->status,
                (int)$busy->id
            );
            if ($this->wantsJson()) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => $message,
                ], 409);
            }
            $this->Flash->error($message);

            return $this->redirect(['action' => 'edit', $offer->id]);
        }

        $queueAhead = $Jobs->find()
            ->where([
                'status IN' => [
                    ProphetTuningJob::STATUS_QUEUED,
                    ProphetTuningJob::STATUS_RUNNING,
                ],
            ])
            ->contain([])
            ->count();

        $wfm = $this->fetchTable('WfmSettings')->find()->contain([])->first();
        if (!$wfm) {
            $message = 'Aucun profil WFM trouvé — impossible de lancer le tuning.';
            if ($this->wantsJson()) {
                return $this->jsonResponse(['success' => false, 'message' => $message], 400);
            }
            $this->Flash->error($message);

            return $this->redirect(['action' => 'edit', $offer->id]);
        }
        $optuna = ProphetOptunaConfig::fromStorage($wfm->optuna_settings_json ?? null);

        $identity = $this->request->getAttribute('identity');
        $userId = $identity ? (int)$identity->getIdentifier() : null;

        $job = $Jobs->newEntity([
            'offer_id' => (int)$offer->id,
            'created_by' => $userId ?: null,
            'trigger_type' => ProphetTuningJob::TRIGGER_MANUAL,
            'status' => ProphetTuningJob::STATUS_QUEUED,
            'config_snapshot_json' => $optuna,
            'auto_applied' => false,
            'progress_trials_done' => 0,
            'progress_trials_total' => (int)$optuna['n_trials'],
        ]);

        if (!$Jobs->save($job)) {
            $message = 'Impossible de créer le job de tuning.';
            if ($this->wantsJson()) {
                return $this->jsonResponse(['success' => false, 'message' => $message], 500);
            }
            $this->Flash->error($message);

            return $this->redirect(['action' => 'edit', $offer->id]);
        }

        $message = 'Tuning mis en file d\'attente.';
        if ($queueAhead > 0) {
            $message = sprintf(
                'Tuning mis en file d\'attente (job #%d). %d job(s) déjà en cours/file — exécution séquentielle.',
                (int)$job->id,
                $queueAhead
            );
        }

        $payload = [
            'success' => true,
            'message' => $message,
            'job' => [
                'id' => (int)$job->id,
                'status' => (string)$job->status,
                'progress_trials_done' => 0,
                'progress_trials_total' => (int)$job->progress_trials_total,
            ],
        ];

        if ($this->wantsJson()) {
            return $this->jsonResponse($payload);
        }

        $this->Flash->success($payload['message']);

        return $this->redirect(['action' => 'edit', $offer->id]);
    }

    /**
     * Annule le job Optuna queued/running de cette offre.
     *
     * @param string|null $id Offer id
     */
    public function tuneCancel($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\OffersResource(), 'tuneCancel');
        $this->request->allowMethod(['post']);

        $offer = $this->Offers->get((int)$id, contain: []);
        $Jobs = $this->fetchTable('ProphetTuningJobs');

        $busy = $Jobs->find()
            ->select(['id', 'status'])
            ->where([
                'offer_id' => (int)$offer->id,
                'status IN' => [
                    ProphetTuningJob::STATUS_QUEUED,
                    ProphetTuningJob::STATUS_RUNNING,
                ],
            ])
            ->orderDesc('id')
            ->contain([])
            ->first();

        if (!$busy) {
            $message = 'Aucun job queued/running à annuler pour cette offre.';
            if ($this->wantsJson()) {
                return $this->jsonResponse(['success' => false, 'message' => $message], 409);
            }
            $this->Flash->error($message);

            return $this->redirect(['action' => 'edit', $offer->id]);
        }

        $result = $Jobs->cancelActiveJob(
            (int)$busy->id,
            'Annulé depuis la fiche offre.'
        );

        if ($this->wantsJson()) {
            return $this->jsonResponse([
                'success' => $result['ok'],
                'message' => $result['message'],
                'job_id' => $result['job_id'],
            ], $result['ok'] ? 200 : 409);
        }

        if ($result['ok']) {
            $this->Flash->success($result['message']);
        } else {
            $this->Flash->error($result['message']);
        }

        return $this->redirect(['action' => 'edit', $offer->id]);
    }

    /**
     * Endpoint léger de polling (select exclusif, contain([])).
     *
     * @param string|null $id Offer id
     */
    public function tuneStatus($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\OffersResource(), 'tuneStatus');
        $this->request->allowMethod(['get']);

        $offerId = (int)$id;

        $offer = $this->Offers->find()
            ->select([
                'id',
                'prophet_tuning_enabled',
                'prophet_tuning_draft_json',
                'prophet_tuning_draft_scores_json',
                'prophet_tuning_previous_json',
                'prophet_tuning_last_run_at',
                'prophet_tuning_last_job_id',
            ])
            ->where(['id' => $offerId])
            ->contain([])
            ->first();

        if (!$offer) {
            throw new NotFoundException('Offre introuvable.');
        }

        $Jobs = $this->fetchTable('ProphetTuningJobs');
        $job = $Jobs->find()
            ->select([
                'id',
                'offer_id',
                'status',
                'trigger_type',
                'progress_trials_done',
                'progress_trials_total',
                'best_mae_so_far',
                'auto_applied',
                'error_message',
                'started_at',
                'finished_at',
                'baseline_scores_json',
                'best_scores_json',
            ])
            ->where(['offer_id' => $offerId])
            ->orderDesc('id')
            ->contain([])
            ->first();

        $fmtDt = static function ($value): ?string {
            return ProphetOptunaConfig::formatDateTimeForUi($value);
        };

        $jobPayload = null;
        if ($job) {
            $jobPayload = [
                'id' => (int)$job->id,
                'status' => (string)$job->status,
                'trigger' => (string)$job->trigger_type,
                'progress_trials_done' => (int)($job->progress_trials_done ?? 0),
                'progress_trials_total' => (int)($job->progress_trials_total ?? 0),
                'best_mae_so_far' => $job->best_mae_so_far !== null ? (float)$job->best_mae_so_far : null,
                'auto_applied' => (bool)$job->auto_applied,
                'error_message' => $job->error_message !== null ? (string)$job->error_message : null,
                'started_at' => $fmtDt($job->started_at),
                'finished_at' => $fmtDt($job->finished_at),
                'baseline_scores' => ProphetOptunaConfig::decodeJson($job->baseline_scores_json),
                'best_scores' => ProphetOptunaConfig::decodeJson($job->best_scores_json),
            ];
        }

        return $this->jsonResponse([
            'success' => true,
            'offer' => [
                'id' => (int)$offer->id,
                'prophet_tuning_enabled' => (bool)$offer->prophet_tuning_enabled,
                'draft_params' => ProphetOptunaConfig::decodeJson($offer->prophet_tuning_draft_json),
                'draft_scores' => ProphetOptunaConfig::decodeJson($offer->prophet_tuning_draft_scores_json),
                'has_previous' => ProphetOptunaConfig::decodeJson($offer->prophet_tuning_previous_json) !== null,
                'last_run_at' => $fmtDt($offer->prophet_tuning_last_run_at),
                'last_job_id' => $offer->prophet_tuning_last_job_id !== null
                    ? (int)$offer->prophet_tuning_last_job_id
                    : null,
            ],
            'job' => $jobPayload,
        ]);
    }

    /**
     * Applique le brouillon Optuna comme profil Prophet officiel.
     *
     * @param string|null $id Offer id
     */
    public function tuneApply($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\OffersResource(), 'tuneApply');
        $this->request->allowMethod(['post']);

        $offer = $this->Offers->get((int)$id, contain: []);
        $draft = ProphetOptunaConfig::decodeJson($offer->prophet_tuning_draft_json);
        if ($draft === null) {
            return $this->tuneActionResult($offer->id, false, 'Aucun brouillon Optuna à appliquer.');
        }

        $current = ProphetOptunaConfig::decodeJson($offer->prophet_default_settings_json) ?? [];
        $applied = ProphetOptunaConfig::applyFixedProphetFlags($draft);
        // Préserver history_* du profil courant si absents du brouillon
        if (!array_key_exists('history_start_date', $applied)) {
            $applied['history_start_date'] = $current['history_start_date'] ?? null;
        }
        if (!array_key_exists('history_end_date', $applied)) {
            $applied['history_end_date'] = $current['history_end_date'] ?? null;
        }

        $offer->prophet_tuning_previous_json = $current ?: null;
        $offer->prophet_default_settings_json = $applied;
        $offer->prophet_tuning_draft_json = null;
        $offer->prophet_tuning_draft_scores_json = null;

        if (!$this->Offers->save($offer)) {
            return $this->tuneActionResult($offer->id, false, 'Échec de l\'application du brouillon.');
        }

        return $this->tuneActionResult($offer->id, true, 'Profil Prophet mis à jour depuis le brouillon Optuna.');
    }

    /**
     * Rejette le brouillon Optuna.
     *
     * @param string|null $id Offer id
     */
    public function tuneReject($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\OffersResource(), 'tuneReject');
        $this->request->allowMethod(['post']);

        $offer = $this->Offers->get((int)$id, contain: []);
        $offer->prophet_tuning_draft_json = null;
        $offer->prophet_tuning_draft_scores_json = null;

        if (!$this->Offers->save($offer)) {
            return $this->tuneActionResult($offer->id, false, 'Impossible de rejeter le brouillon.');
        }

        return $this->tuneActionResult($offer->id, true, 'Brouillon Optuna rejeté.');
    }

    /**
     * Rollback 1 niveau : official ← previous.
     *
     * @param string|null $id Offer id
     */
    public function tuneRollback($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\OffersResource(), 'tuneRollback');
        $this->request->allowMethod(['post']);

        $offer = $this->Offers->get((int)$id, contain: []);
        $previous = ProphetOptunaConfig::decodeJson($offer->prophet_tuning_previous_json);
        if ($previous === null) {
            return $this->tuneActionResult($offer->id, false, 'Aucun profil précédent à restaurer.');
        }

        $offer->prophet_default_settings_json = ProphetOptunaConfig::applyFixedProphetFlags($previous);
        $offer->prophet_tuning_previous_json = null;

        if (!$this->Offers->save($offer)) {
            return $this->tuneActionResult($offer->id, false, 'Échec du rollback.');
        }

        return $this->tuneActionResult($offer->id, true, 'Profil Prophet précédent restauré.');
    }

    /**
     * Delete method
     *
     * @param string|null $id Offer id.
     * @return \Cake\Http\Response|null|void Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\OffersResource(), 'delete');
        $this->request->allowMethod(['post', 'delete']);
        $offer = $this->Offers->get($id);
        if ($this->Offers->delete($offer)) {
            $this->Flash->success("L'offre a été supprimée.");
        } else {
            $this->Flash->error("L'offre n'a pas pu être supprimée. Merci d'essayer à nouveau.");
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Construit un tableau de paramètres Prophet par défaut à partir des données du formulaire.
     *
     * @param array $data Sous-tableau prophet_defaults du formulaire
     * @return array Paramètres Prophet normalisés
     */
    private function buildProphetDefaultsFromRequest(array $data): array
    {
        if (empty($data)) {
            return [];
        }

        return [
            // Mode de saisonnalité
            'seasonality_mode' => $data['seasonality_mode'] ?? 'multiplicative',

            // Saisonnalités
            'yearly_seasonality' => !empty($data['yearly_seasonality']),
            'weekly_seasonality' => !empty($data['weekly_seasonality']),
            'monthly_seasonality' => !empty($data['monthly_seasonality']),
            'monthly_fourier_order' => isset($data['monthly_fourier_order']) ? (int)$data['monthly_fourier_order'] : 5,
            'daily_seasonality' => !empty($data['daily_seasonality']),

            // Sensibilités
            'changepoint_prior_scale' => isset($data['changepoint_prior_scale']) ? (float)$data['changepoint_prior_scale'] : 0.1,
            'seasonality_prior_scale' => isset($data['seasonality_prior_scale']) ? (float)$data['seasonality_prior_scale'] : 10.0,

            // Changepoints / tendance
            'growth' => 'linear',
            'n_changepoints' => isset($data['n_changepoints']) ? (int)$data['n_changepoints'] : 25,
            'changepoint_range' => 0.8,

            // Jours fériés
            'use_french_holidays' => !empty($data['use_french_holidays']),

            // Plage historique (par défaut : tout l'historique)
            'history_start_date' => $data['history_start_date'] ?? null,
            'history_end_date' => $data['history_end_date'] ?? null,
        ];
    }

    /**
     * Retourne les paramètres Prophet par défaut pour une offre,
     * en fusionnant le JSON stocké avec les valeurs système par défaut.
     *
     * @param \App\Model\Entity\Offer $offer
     * @return array
     */
    private function getProphetDefaultsForOffer(\App\Model\Entity\Offer $offer): array
    {
        // 1) Défauts système depuis wfm_settings
        /** @var \App\Model\Table\WfmSettingsTable $WfmSettings */
        $WfmSettings = $this->fetchTable('WfmSettings');
        $wfm = $WfmSettings->find()->first();

        $defaults = [
            'seasonality_mode' => 'multiplicative',
            'yearly_seasonality' => true,
            'weekly_seasonality' => true,
            'monthly_seasonality' => true,
            'monthly_fourier_order' => 5,
            'daily_seasonality' => true,
            'changepoint_prior_scale' => 0.1,
            'seasonality_prior_scale' => 10.0,
            'growth' => 'linear',
            'n_changepoints' => 25,
            'changepoint_range' => 0.8,
            'use_french_holidays' => true,
            'history_start_date' => null,
            'history_end_date' => null,
        ];

        if ($wfm && !empty($wfm->prophet_defaults_json)) {
            $rawSystem = $wfm->prophet_defaults_json;
            if (is_string($rawSystem)) {
                $decoded = json_decode($rawSystem, true);
                if (is_array($decoded)) {
                    $defaults = array_merge($defaults, $decoded);
                }
            } elseif (is_array($rawSystem)) {
                $defaults = array_merge($defaults, $rawSystem);
            }
        }

        // 2) Profil spécifique de l'offre (JSON stocké)
        $rawOffer = $offer->prophet_default_settings_json ?? null;
        if (is_string($rawOffer) && $rawOffer !== '') {
            $decodedOffer = json_decode($rawOffer, true);
            if (is_array($decodedOffer)) {
                $defaults = array_merge($defaults, $decodedOffer);
            }
        } elseif (is_array($rawOffer)) {
            $defaults = array_merge($defaults, $rawOffer);
        }

        return $defaults;
    }

    /**
     * Données initiales pour la card Tuning Optuna (edit/view).
     *
     * @return array<string, mixed>
     */
    private function buildProphetTuningViewData(\App\Model\Entity\Offer $offer): array
    {
        $Jobs = $this->fetchTable('ProphetTuningJobs');
        $job = $Jobs->find()
            ->select([
                'id',
                'status',
                'progress_trials_done',
                'progress_trials_total',
                'best_mae_so_far',
                'auto_applied',
                'error_message',
                'baseline_scores_json',
                'best_scores_json',
            ])
            ->where(['offer_id' => (int)$offer->id])
            ->orderDesc('id')
            ->contain([])
            ->first();

        $draftScores = ProphetOptunaConfig::decodeJson($offer->prophet_tuning_draft_scores_json);
        $hasDraft = ProphetOptunaConfig::decodeJson($offer->prophet_tuning_draft_json) !== null;
        $hasPrevious = ProphetOptunaConfig::decodeJson($offer->prophet_tuning_previous_json) !== null;

        return [
            'enabled' => (bool)$offer->prophet_tuning_enabled,
            'has_draft' => $hasDraft,
            'has_previous' => $hasPrevious,
            'draft_scores' => $draftScores,
            'job' => $job ? [
                'id' => (int)$job->id,
                'status' => (string)$job->status,
                'progress_trials_done' => (int)($job->progress_trials_done ?? 0),
                'progress_trials_total' => (int)($job->progress_trials_total ?? 0),
                'best_mae_so_far' => $job->best_mae_so_far !== null ? (float)$job->best_mae_so_far : null,
                'auto_applied' => (bool)$job->auto_applied,
                'error_message' => $job->error_message,
                'baseline_scores' => ProphetOptunaConfig::decodeJson($job->baseline_scores_json),
                'best_scores' => ProphetOptunaConfig::decodeJson($job->best_scores_json),
            ] : null,
            'urls' => [
                'status' => Router::url(['controller' => 'Offers', 'action' => 'tuneStatus', $offer->id]),
                'start' => Router::url(['controller' => 'Offers', 'action' => 'tuneStart', $offer->id]),
                'cancel' => Router::url(['controller' => 'Offers', 'action' => 'tuneCancel', $offer->id]),
                'apply' => Router::url(['controller' => 'Offers', 'action' => 'tuneApply', $offer->id]),
                'reject' => Router::url(['controller' => 'Offers', 'action' => 'tuneReject', $offer->id]),
                'rollback' => Router::url(['controller' => 'Offers', 'action' => 'tuneRollback', $offer->id]),
            ],
        ];
    }

    private function wantsJson(): bool
    {
        if ($this->request->is('ajax')) {
            return true;
        }
        $accept = (string)$this->request->getHeaderLine('Accept');
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        return $this->request->getParam('_ext') === 'json';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonResponse(array $payload, int $status = 200): \Cake\Http\Response
    {
        return $this->response
            ->withStatus($status)
            ->withType('application/json')
            ->withStringBody(json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return \Cake\Http\Response|null
     */
    private function tuneActionResult(int $offerId, bool $success, string $message)
    {
        if ($this->wantsJson()) {
            return $this->jsonResponse([
                'success' => $success,
                'message' => $message,
            ], $success ? 200 : 400);
        }

        if ($success) {
            $this->Flash->success($message);
        } else {
            $this->Flash->error($message);
        }

        return $this->redirect(['action' => 'edit', $offerId]);
    }
}
