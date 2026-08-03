<?php
declare(strict_types=1);

namespace App\Controller;

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
        $offer = $this->Offers->get($id, [
            'contain' => [],
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
}
