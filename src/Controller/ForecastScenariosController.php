<?php
namespace App\Controller;

use App\Model\Entity\ForecastScenario;
use App\Service\WfmScenarioService;

class ForecastScenariosController extends AppController
{
    protected WfmScenarioService $WfmScenarioService;

    public function initialize(): void
    {
        parent::initialize();
        $forecastService = new \App\Service\ForecastService();
        $calculatorService = new \App\Service\WfmCalculatorService($forecastService);
        $this->WfmScenarioService = new WfmScenarioService($forecastService, $calculatorService);
    }

    public function index()
    {
        $this->Authorization->authorize(new \App\Resource\ForecastScenariosResource(), 'index');
        $query = $this->fetchTable('ForecastScenarios')->find()
            ->contain(['ForecastScenariosOffers' => ['Offers'], 'ForecastScenarioPublications'])
            ->orderDesc('ForecastScenarios.created');
        
        // Filtres de recherche
        if ($this->request->getQuery('search_name')) {
            $query->where(['ForecastScenarios.name LIKE' => '%' . $this->request->getQuery('search_name') . '%']);
        }
        
        if ($this->request->getQuery('status')) {
            $query->where(['ForecastScenarios.status' => $this->request->getQuery('status')]);
        }
        
        // Pagination normale
        $this->paginate = ['limit' => 25];
        $scenarios = $this->paginate($query);
        
        // Calculer les statistiques (sur TOUS les scénarios, pas seulement la page courante)
        $Scenarios = $this->fetchTable('ForecastScenarios');
        $stats = [
            'total' => $Scenarios->find()->count(),
            'draft' => $Scenarios->find()->where(['status' => 'draft'])->count(),
            'queued' => $Scenarios->find()->where(['status' => ForecastScenario::STATUS_QUEUED])->count(),
            'running' => $Scenarios->find()->where(['status' => 'running'])->count(),
            'completed' => $Scenarios->find()->where(['status' => 'completed'])->count(),
            'failed' => $Scenarios->find()->where(['status' => 'failed'])->count(),
            'published' => 0,
            'prophet' => 0,
        ];

        $allScenarios = $Scenarios->find()->contain(['ForecastScenarioPublications']);
        
        // Compter les scénarios publiés
        foreach ($allScenarios as $s) {
            if (!empty($s->forecast_scenario_publications)) {
                $stats['published']++;
            }
        }

        // Compter les scénarios qui utilisent au moins une offre en Prophet
        $Links = $this->fetchTable('ForecastScenariosOffers');
        $stats['prophet'] = $Links->find()
            ->select('scenario_id')
            ->where(['forecast_method' => 'prophet'])
            ->distinct()
            ->count();
        
        $this->set(compact('scenarios', 'stats'));
    }

    public function add()
    {
        $this->Authorization->authorize(new \App\Resource\ForecastScenariosResource(), 'add');
        $scenario = $this->fetchTable('ForecastScenarios')->newEmptyEntity();
        $offers = $this->fetchTable('Offers')
            ->find('forecastable')
            ->find('list')
            ->order(['Offers.display_order' => 'ASC', 'Offers.name' => 'ASC'])
            ->toArray();
        $defaultMethodsByOffer = $this->fetchTable('Offers')
            ->find('forecastable')
            ->find('list', keyField: 'id', valueField: 'default_forecast_method')
            ->toArray();
        $WfmSettings = $this->fetchTable('WfmSettings');
        $wfm = $WfmSettings->find()->first();
        $wfmSettingsList = $WfmSettings->find('list', [
            'keyField' => 'id',
            'valueField' => 'name',
        ])->toArray();

        if ($this->request->is('post')) {
            $data = $this->request->getData();

            $name = (string)($data['name'] ?? 'Scenario');
            $startDate = new \DateTime($data['start_date']);
            $endDate = new \DateTime($data['end_date']);
            $offerIds = array_map('intval', (array)($data['offer_ids'] ?? []));
            $methodsByOffer = (array)($data['offer_methods'] ?? []);

            $chosenWfm = $wfm;
            $chosenId = (int)($data['wfm_setting_id'] ?? 0);
            if ($chosenId > 0) {
                $chosenWfm = $WfmSettings->get($chosenId);
            }
            $settingsSnapshot = [
                'day_start_time' => (string)$chosenWfm->day_start_time,
                'day_end_time' => (string)$chosenWfm->day_end_time,
                'shrinkage_percent' => (float)$chosenWfm->shrinkage_percent,
                'service_level_percent' => (float)$chosenWfm->service_level_percent,
                'service_level_seconds' => (int)$chosenWfm->service_level_seconds,
            ];

            $scenarioId = $this->WfmScenarioService->createScenario(
                $name,
                $startDate,
                $endDate,
                $offerIds,
                $settingsSnapshot,
                $this->request->getAttribute('identity')->get('id') ?? null,
                [],
                $methodsByOffer
            );

            $this->Flash->success(__('Scénario créé.'));
            return $this->redirect(['action' => 'view', $scenarioId]);
        }

        $this->set(compact('scenario', 'offers', 'defaultMethodsByOffer', 'wfm', 'wfmSettingsList'));
    }

    public function run($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\ForecastScenariosResource(), 'run');

        $Scenarios = $this->fetchTable('ForecastScenarios');
        $scenario = $Scenarios->get($id);
        $status = (string)$scenario->status;

        if (in_array($status, [ForecastScenario::STATUS_QUEUED, 'running'], true)) {
            $this->Flash->warning(__('Ce scénario est déjà en file d\'attente ou en cours de calcul.'));

            return $this->redirect(['action' => 'view', $scenario->id]);
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $Scenarios->updateAll(
            [
                'status' => ForecastScenario::STATUS_QUEUED,
                'error_message' => null,
                'started_at' => null,
                'finished_at' => null,
                'progress_offer_id' => null,
                'progress_offer_name' => null,
                'progress_date' => null,
                'progress_offers_done' => 0,
                'progress_offers_total' => 0,
                'progress_days_done' => 0,
                'progress_days_total' => 0,
                'modified' => $now,
            ],
            ['id' => (int)$scenario->id],
        );

        $this->Flash->success(__('Scénario mis en file d\'attente. Le calcul démarre automatiquement.'));

        return $this->redirect(['action' => 'view', $scenario->id]);
    }

    /**
     * Endpoint léger de polling (Blindage 3 : select exclusif, aucune jointure).
     */
    public function status($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\ForecastScenariosResource(), 'status');
        $this->request->allowMethod(['get']);

        $row = $this->fetchTable('ForecastScenarios')->find()
            ->select([
                'id',
                'status',
                'error_message',
                'started_at',
                'finished_at',
                'progress_offer_id',
                'progress_offer_name',
                'progress_date',
                'progress_offers_done',
                'progress_offers_total',
                'progress_days_done',
                'progress_days_total',
            ])
            ->where(['id' => (int)$id])
            ->contain([])
            ->first();

        if (!$row) {
            throw new \Cake\Http\Exception\NotFoundException(__('Scénario introuvable.'));
        }

        $fmtDateTime = static function ($value): ?string {
            if ($value === null || $value === '') {
                return null;
            }
            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d H:i:s');
            }

            return (string)$value;
        };
        $fmtDate = static function ($value): ?string {
            if ($value === null || $value === '') {
                return null;
            }
            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d');
            }

            return (string)$value;
        };

        $this->viewBuilder()->setClassName('Json');
        $this->set([
            'success' => true,
            'scenario' => [
                'id' => (int)$row->id,
                'status' => (string)$row->status,
                'error_message' => $row->error_message !== null ? (string)$row->error_message : null,
                'started_at' => $fmtDateTime($row->started_at),
                'finished_at' => $fmtDateTime($row->finished_at),
                'progress_offer_id' => $row->progress_offer_id !== null ? (int)$row->progress_offer_id : null,
                'progress_offer_name' => $row->progress_offer_name !== null ? (string)$row->progress_offer_name : null,
                'progress_date' => $fmtDate($row->progress_date),
                'progress_offers_done' => (int)($row->progress_offers_done ?? 0),
                'progress_offers_total' => (int)($row->progress_offers_total ?? 0),
                'progress_days_done' => (int)($row->progress_days_done ?? 0),
                'progress_days_total' => (int)($row->progress_days_total ?? 0),
            ],
        ]);
        $this->viewBuilder()->setOption('serialize', ['success', 'scenario']);
    }

    public function view($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\ForecastScenariosResource(), 'view');
        $Scenarios = $this->fetchTable('ForecastScenarios');
        $scenario = $Scenarios->get($id, [
            'contain' => ['ForecastScenariosOffers' => ['Offers'], 'ForecastScenarioPublications'],
        ]);

        $snapshot = [];
        $raw = $scenario->settings_snapshot_json;
        if (is_string($raw)) { $snapshot = json_decode($raw, true) ?: []; }
        elseif (is_array($raw)) { $snapshot = $raw; }

        $current = $this->fetchTable('WfmSettings')->find()->first();

        $this->set(compact('scenario', 'snapshot', 'current'));
    }

    public function delete($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\ForecastScenariosResource(), 'delete');
        $this->request->allowMethod(['post', 'delete']);
        $table = $this->fetchTable('ForecastScenarios');
        $scenario = $table->get($id);
        $table->delete($scenario);
        $this->Flash->success(__('Scénario supprimé.'));
        return $this->redirect(['action' => 'index']);
    }

    public function series($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\ForecastScenariosResource(), 'view');
        $this->request->allowMethod(['get']);
        $offerId = (int)$this->request->getQuery('offer_id');
        $date = new \DateTime((string)$this->request->getQuery('date'));
        $type = (string)$this->request->getQuery('type');

        $res = $this->WfmScenarioService->getSeries((int)$id, $offerId, $date, $type);
        $this->viewBuilder()->setClassName('Json');
        $this->set(['success' => (bool)$res, 'series' => $res]);
        $this->viewBuilder()->setOption('serialize', ['success', 'series']);
    }

    public function publish($id)
    {
        $this->Authorization->authorize(new \App\Resource\ForecastScenariosResource(), 'publish');
        $Scenarios = $this->fetchTable('ForecastScenarios');
        $Pubs = $this->fetchTable('ForecastScenarioPublications');
        $scenario = $Scenarios->get($id);
        if ($scenario->status !== 'completed') {
            $this->Flash->error("Le scénario doit être 'completed' pour être publié.");
            return $this->redirect(['action' => 'view', $id]);
        }

        $start = $this->toDateTime($scenario->start_date);
        $end = $this->toDateTime($scenario->end_date);
        $userId = $this->request->getAttribute('identity')->get('id') ?? null;

        for ($d = clone $start; $d <= $end; $d->modify('+1 day')) {
            // Replace existing publication for this date
            $existing = $Pubs->find()->where(['date' => $d->format('Y-m-d')])->first();
            if ($existing) {
                $Pubs->delete($existing);
            }
            $pub = $Pubs->newEntity([
                'scenario_id' => (int)$scenario->id,
                'date' => $d->format('Y-m-d'),
                'published_by' => $userId,
            ]);
            $Pubs->save($pub); // tolère collisions liées à la boucle
        }

        $this->Flash->success('Scénario publié sur la plage de dates.');
        return $this->redirect(['action' => 'view', $id]);
    }

    public function unpublish($id)
    {
        $this->Authorization->authorize(new \App\Resource\ForecastScenariosResource(), 'unpublish');
        $Scenarios = $this->fetchTable('ForecastScenarios');
        $Pubs = $this->fetchTable('ForecastScenarioPublications');
        $scenario = $Scenarios->get($id);
        $start = $this->toDateTime($scenario->start_date);
        $end = $this->toDateTime($scenario->end_date);

        $Pubs->deleteAll([
            'scenario_id' => (int)$scenario->id,
            'date >=' => $start->format('Y-m-d'),
            'date <=' => $end->format('Y-m-d'),
        ]);

        $this->Flash->success('Scénario dépublié sur la plage de dates.');
        return $this->redirect(['action' => 'view', $id]);
    }

    public function edit($id)
    {
        $Scenarios = $this->fetchTable('ForecastScenarios');
        $Pubs = $this->fetchTable('ForecastScenarioPublications');
        $Offers = $this->fetchTable('Offers');
        $WfmSettings = $this->fetchTable('WfmSettings');

        $scenario = $Scenarios->get($id, [
            'contain' => ['ForecastScenariosOffers' => ['Offers']]
        ]);

        // Block edit if any publication exists in range
        $pubExists = $Pubs->exists([
            'scenario_id' => (int)$scenario->id,
        ]);
        if ($pubExists) {
            $this->Flash->error("Ce scénario est publié. Dépublie-le avant modification.");
            return $this->redirect(['action' => 'view', $id]);
        }

        $wfmSettingsList = $WfmSettings->find('list', [
            'keyField' => 'id',
            'valueField' => 'name',
        ])->toArray();
        $selectedOfferIds = array_map(fn($l) => (int)$l->offer_id, (array)$scenario->forecast_scenarios_offers);

        // Offres affichées à l'édition :
        // - toutes les offres forecastables
        // - + les offres déjà présentes dans le scénario (même si elles ne sont plus forecastables),
        //   afin d'éviter de les retirer involontairement lors d'un enregistrement.
        $offersQuery = $Offers->find();
        $orConditions = [
            ['Offers.is_forecastable' => true],
        ];
        if (!empty($selectedOfferIds)) {
            $orConditions[] = ['Offers.id IN' => $selectedOfferIds];
        }
        $offersQuery->where(['OR' => $orConditions]);
        $offers = $offersQuery
            ->find('list')
            ->order(['Offers.display_order' => 'ASC', 'Offers.name' => 'ASC'])
            ->toArray();

        // Map id => méthode par défaut (mêmes offres que le formulaire : forecastables + déjà liées)
        $defaultMethodsQuery = $Offers->find();
        $defaultMethodsQuery->where(['OR' => $orConditions]);
        $defaultMethodsByOffer = $defaultMethodsQuery
            ->find('list', keyField: 'id', valueField: 'default_forecast_method')
            ->toArray();

        // Calculer la volatilité pour chaque offre sélectionnée
        $offerVolatility = [];
        $conn = \Cake\Datasource\ConnectionManager::get('default');
        foreach ($selectedOfferIds as $offerId) {
            $sql = "SELECT 
                        STDDEV(call_volume) as vol_stddev,
                        AVG(call_volume) as vol_avg
                    FROM historical_data
                    WHERE offer_id = :offer_id
                    AND call_volume > 0";
            $stmt = $conn->execute($sql, ['offer_id' => $offerId]);
            $row = $stmt->fetch('assoc');
            
            if ($row && $row['vol_avg'] > 0) {
                $cv = ($row['vol_stddev'] / $row['vol_avg']) * 100;
                $offerVolatility[$offerId] = [
                    'coefficient' => round($cv, 1),
                    'level' => $cv >= 60 ? 'high' : ($cv >= 50 ? 'medium' : 'low'),
                    'label' => $cv >= 60 ? '🔴 Très volatile' : ($cv >= 50 ? '🟠 Moyen' : '🟢 Stable')
                ];
            } else {
                $offerVolatility[$offerId] = [
                    'coefficient' => 0,
                    'level' => 'unknown',
                    'label' => '⚪ Données insuffisantes'
                ];
            }
        }

        // Decode snapshot
        $snapshot = is_string($scenario->settings_snapshot_json)
            ? (json_decode($scenario->settings_snapshot_json, true) ?: [])
            : ((array)$scenario->settings_snapshot_json ?: []);

        if ($this->request->is(['post', 'put', 'patch'])) {
            $data = $this->request->getData();
            $name = (string)($data['name'] ?? $scenario->name);
            $startDate = $this->toDateTime($data['start_date'] ?? (string)$scenario->start_date);
            $endDate = $this->toDateTime($data['end_date'] ?? (string)$scenario->end_date);
            $offerIds = array_map('intval', (array)($data['offer_ids'] ?? $selectedOfferIds));
            $methodsByOffer = (array)($data['offer_methods'] ?? []);
            // Snapshot depuis un profil WFM sélectionné, sinon on conserve l'existant
            $newSnapshot = $snapshot;
            $chosenId = (int)($data['wfm_setting_id'] ?? 0);
            if ($chosenId > 0) {
                $chosen = $WfmSettings->get($chosenId);
                $newSnapshot = [
                    'day_start_time' => (string)$chosen->day_start_time,
                    'day_end_time' => (string)$chosen->day_end_time,
                    'shrinkage_percent' => (float)$chosen->shrinkage_percent,
                    'service_level_percent' => (float)$chosen->service_level_percent,
                    'service_level_seconds' => (int)$chosen->service_level_seconds,
                ];
            }

            $this->WfmScenarioService->updateScenario(
                (int)$scenario->id,
                $name,
                $startDate,
                $endDate,
                $offerIds,
                $newSnapshot,
                $methodsByOffer
            );

            $this->Flash->success('Scénario mis à jour. Statut repassé à draft. Lance un recalcul.');
            return $this->redirect(['action' => 'view', $scenario->id]);
        }

        $this->set(compact('scenario', 'offers', 'selectedOfferIds', 'snapshot', 'wfmSettingsList', 'offerVolatility', 'defaultMethodsByOffer'));
    }
    private function toDateTime($value): \DateTime
    {
        if ($value instanceof \DateTimeInterface) {
            return new \DateTime($value->format('Y-m-d'));
        }
        $s = (string)$value;
        if (strpos($s, '/') !== false) {
            $dt = \DateTime::createFromFormat('d/m/Y', $s);
            if ($dt instanceof \DateTime) {
                return $dt;
            }
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return new \DateTime($s);
        }
        return new \DateTime($s);
    }
}


