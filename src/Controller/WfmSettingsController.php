<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * WfmSettings Controller
 *
 * @property \App\Model\Table\WfmSettingsTable $WfmSettings
 */
class WfmSettingsController extends AppController
{
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $query = $this->WfmSettings->find();
        
        $this->paginate = ['limit' => 25, 'order' => ['WfmSettings.name' => 'ASC']];
        $wfmSettings = $this->paginate($query);

        // Statistiques
        $allSettings = $this->WfmSettings->find()->all();
        $stats = [
            'total' => $allSettings->count(),
            'strict' => 0,
            'flexible' => 0,
            'with_breaks' => 0,
        ];
        
        foreach ($allSettings as $setting) {
            // Compte strict vs flexible
            if ($setting->strict_work_hours === null || $setting->strict_work_hours) {
                $stats['strict']++;
            } else {
                $stats['flexible']++;
            }
            
            // Compte ceux avec pauses AM/PM activées
            if ($setting->enable_am_pm_breaks === null || $setting->enable_am_pm_breaks) {
                $stats['with_breaks']++;
            }
        }

        $this->set(compact('wfmSettings', 'stats'));
    }

    /**
     * View method
     *
     * @param string|null $id Wfm Setting id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        // Charger également les offres liées pour l'affichage (pauses / repas)
        $wfmSetting = $this->WfmSettings->get($id, contain: ['PauseOffers', 'LunchOffers']);
        $this->set('slotMinutes', \App\Service\ScheduleProblemBuilderService::SLOT_MINUTES);
        $this->set(compact('wfmSetting'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $wfmSetting = $this->WfmSettings->newEmptyEntity();
        if ($this->request->is('post')) {
            $data = $this->request->getData();
            $prophetDefaults = $this->buildProphetDefaultsFromRequest($data['prophet_defaults'] ?? []);
            $data['prophet_defaults_json'] = $prophetDefaults ? json_encode($prophetDefaults) : null;
            $wfmSetting = $this->WfmSettings->patchEntity($wfmSetting, $data);
            if ($this->WfmSettings->save($wfmSetting)) {
                $this->Flash->success('Le profil WFM a été enregistré.');

                return $this->redirect(['action' => 'index']);
            }
            $this->flashSaveFailure($wfmSetting);
        }
        
        // Charger les offres pour les sélecteurs
        $offers = $this->fetchTable('Offers')->find('list', [
            'keyField' => 'id',
            'valueField' => 'name'
        ])->all();
        
        $prophetDefaults = $this->getProphetDefaultsForSystem($wfmSetting);

        // Bornes globales des données historiques (pour limiter les dates globales)
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
            $historyMinDate = $this->parseDateToYmd((string)$bounds['min_date']);
            $historyMaxDate = $this->parseDateToYmd((string)$bounds['max_date']);
            // Sécurité : si min > max, on désactive la contrainte
            if ($historyMinDate && $historyMaxDate && $historyMinDate > $historyMaxDate) {
                $historyMinDate = null;
                $historyMaxDate = null;
            }
        }

        // Neutraliser les valeurs déjà stockées qui sortiraient de la plage [min, max]
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

        $this->set('slotMinutes', \App\Service\ScheduleProblemBuilderService::SLOT_MINUTES);
        $this->set(compact('wfmSetting', 'offers', 'prophetDefaults', 'historyMinDate', 'historyMaxDate'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Wfm Setting id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $wfmSetting = $this->WfmSettings->get($id, contain: []);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $data = $this->request->getData();
            $prophetDefaults = $this->buildProphetDefaultsFromRequest($data['prophet_defaults'] ?? []);
            $data['prophet_defaults_json'] = $prophetDefaults ? json_encode($prophetDefaults) : null;
            $wfmSetting = $this->WfmSettings->patchEntity($wfmSetting, $data);
            if ($this->WfmSettings->save($wfmSetting)) {
                $this->Flash->success('Le profil WFM a été enregistré.');

                return $this->redirect(['action' => 'index']);
            }
            $this->flashSaveFailure($wfmSetting);
        }
        
        // Charger les offres pour les sélecteurs
        $offers = $this->fetchTable('Offers')->find('list', [
            'keyField' => 'id',
            'valueField' => 'name'
        ])->all();
        
        $prophetDefaults = $this->getProphetDefaultsForSystem($wfmSetting);

        // Bornes globales des données historiques (pour limiter les dates globales)
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
            $historyMinDate = $this->parseDateToYmd((string)$bounds['min_date']);
            $historyMaxDate = $this->parseDateToYmd((string)$bounds['max_date']);
            // Sécurité : si min > max, on désactive la contrainte
            if ($historyMinDate && $historyMaxDate && $historyMinDate > $historyMaxDate) {
                $historyMinDate = null;
                $historyMaxDate = null;
            }
        }

        // Neutraliser les valeurs déjà stockées qui sortiraient de la plage [min, max]
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

        $this->set('slotMinutes', \App\Service\ScheduleProblemBuilderService::SLOT_MINUTES);
        $this->set(compact('wfmSetting', 'offers', 'prophetDefaults', 'historyMinDate', 'historyMaxDate'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Wfm Setting id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $wfmSetting = $this->WfmSettings->get($id);
        if ($this->WfmSettings->delete($wfmSetting)) {
            $this->Flash->success(__('The wfm setting has been deleted.'));
        } else {
            $this->Flash->error(__('The wfm setting could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Flash d'échec d'enregistrement avec le détail des erreurs de validation.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     * @return void
     */
    private function flashSaveFailure(\Cake\Datasource\EntityInterface $entity): void
    {
        $messages = [];
        foreach ($entity->getErrors() as $fieldErrors) {
            foreach ((array)$fieldErrors as $error) {
                if (is_array($error)) {
                    foreach ($error as $nested) {
                        if (is_string($nested) && $nested !== '') {
                            $messages[] = $nested;
                        }
                    }
                } elseif (is_string($error) && $error !== '') {
                    $messages[] = $error;
                }
            }
        }

        $messages = array_values(array_unique($messages));
        if ($messages === []) {
            $this->Flash->error('Enregistrement impossible. Vérifiez les champs du formulaire.');

            return;
        }

        $this->Flash->error('Enregistrement impossible : ' . implode(' ', $messages));
    }

    /**
     * Construit un tableau de paramètres Prophet par défaut à partir des données du formulaire WFM.
     */
    private function buildProphetDefaultsFromRequest(array $data): array
    {
        if (empty($data)) {
            return [];
        }

        return [
            'seasonality_mode' => $data['seasonality_mode'] ?? 'multiplicative',
            'yearly_seasonality' => !empty($data['yearly_seasonality']),
            'weekly_seasonality' => !empty($data['weekly_seasonality']),
            'monthly_seasonality' => !empty($data['monthly_seasonality']),
            'monthly_fourier_order' => isset($data['monthly_fourier_order']) ? (int)$data['monthly_fourier_order'] : 5,
            'daily_seasonality' => !empty($data['daily_seasonality']),
            'changepoint_prior_scale' => isset($data['changepoint_prior_scale']) ? (float)$data['changepoint_prior_scale'] : 0.1,
            'seasonality_prior_scale' => isset($data['seasonality_prior_scale']) ? (float)$data['seasonality_prior_scale'] : 10.0,
            'growth' => 'linear',
            'n_changepoints' => isset($data['n_changepoints']) ? (int)$data['n_changepoints'] : 25,
            'changepoint_range' => 0.8,
            'use_french_holidays' => !empty($data['use_french_holidays']),
            'history_start_date' => $data['history_start_date'] ?? null,
            'history_end_date' => $data['history_end_date'] ?? null,
        ];
    }

    /**
     * Parse une chaîne de date (format français ou ISO) en Y-m-d.
     * Le locale fr_FR peut renvoyer des dates au format dd/mm/yyyy HH:mm.
     */
    private function parseDateToYmd(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $formats = ['d/m/Y H:i:s', 'd/m/Y H:i', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'];
        foreach ($formats as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $raw);
            if ($dt !== false) {
                return $dt->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * Retourne les défauts Prophet système pour le formulaire WFM.
     */
    private function getProphetDefaultsForSystem(\App\Model\Entity\WfmSetting $setting): array
    {
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

        $raw = $setting->prophet_defaults_json ?? null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $defaults = array_merge($defaults, $decoded);
            }
        } elseif (is_array($raw)) {
            $defaults = array_merge($defaults, $raw);
        }

        return $defaults;
    }
}
