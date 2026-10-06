<?php
declare(strict_types=1);

namespace App\Controller;

use App\Controller\Traits\RangeQueryFiltersTrait;
use App\Service\RangeSource;
use Cake\I18n\FrozenTime;

/**
 * RemoteWork Controller
 * Gestion de la configuration du télétravail par agent
 */
class RemoteWorkController extends AppController
{
    use RangeQueryFiltersTrait;

    /**
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Groom');
    }

    /**
     * Index method - Gérer les jours de télétravail (fixe ou flexible)
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $this->Authorization->authorize(new \App\Resource\RemoteWorkResource(), 'index');
        $this->loadComponent('Groom');
        
        $RangesTable = $this->fetchTable('Ranges');
        $UsersTable = $this->fetchTable('Users');
        $OffersTable = $this->fetchTable('Offers');
        $RemoteWorkTable = $this->fetchTable('UserRemoteWorkSettings');
        
        // Récupérer l'offre de télétravail
        $syncService = new \App\Service\RemoteWorkRangesSyncService();
        $remoteWorkOfferId = $syncService->getRemoteWorkOfferId();
        
        if (!$remoteWorkOfferId) {
            $this->Flash->error("L'offre de télétravail n'a pas été trouvée.");
            return $this->redirect(['controller' => 'Pages', 'action' => 'admin']);
        }
        
        $params = $this->request->getQueryParams();
        $rangeType = $this->normalizeRangeType($params['range_type'] ?? null);
        $userIdsWithRemoteWorkRanges = $RangesTable->find()
            ->where(['offer_id' => $remoteWorkOfferId])
            ->all()
            ->extract('user_id')
            ->unique()
            ->toArray();
        
        $users = [];
        if (!empty($userIdsWithRemoteWorkRanges)) {
            $users = $UsersTable->find('list', [
                'keyField' => 'id',
                'valueField' => function ($row) {
                    return $row['last_name'] . ' ' . $row['first_name'];
                },
            ])
            ->where(['Users.id IN' => $userIdsWithRemoteWorkRanges])
            ->order(['Users.last_name' => 'ASC'])
            ->toArray();
        }
        
        $remoteWorkDays = $RangesTable->find()
            ->contain(['Users', 'Offers'])
            ->where($this->remoteWorkConditions($params, (int)$remoteWorkOfferId));
        
        // Pagination
        $this->paginate = ['limit' => 25, 'order' => ['Ranges.date_start' => 'DESC']];
        $remoteWorkDays = $this->paginate($remoteWorkDays);

        $this->set(compact('remoteWorkDays', 'users', 'remoteWorkOfferId', 'rangeType'));
    }

    /**
     * Suppression en masse des jours de télétravail affichés.
     *
     * @return \Cake\Http\Response|null
     */
    public function bulkDelete()
    {
        $this->Authorization->authorize(new \App\Resource\RemoteWorkResource(), 'delete');
        $this->request->allowMethod(['post']);

        $syncService = new \App\Service\RemoteWorkRangesSyncService();
        $remoteWorkOfferId = $syncService->getRemoteWorkOfferId();
        if (!$remoteWorkOfferId) {
            $this->Flash->error("L'offre de télétravail n'a pas été trouvée.");

            return $this->redirect(['action' => 'index']);
        }
        $offerId = (int)$remoteWorkOfferId;

        $Ranges = $this->fetchTable('Ranges');
        if ((string)$this->request->getData('delete_all_matching') === '1') {
            $conditions = $this->remoteWorkConditions($this->request->getQueryParams(), $offerId);
            if (!isset($conditions['Ranges.offer_id'])) {
                $this->Flash->error('Suppression refusée : périmètre télétravail absent.');

                return $this->redirect(['action' => 'index']);
            }
            $deleted = $Ranges->deleteAll($conditions);
            $this->Flash->success($deleted . ' jour(s) de télétravail supprimé(s).');

            return $this->redirect($this->indexUrlWithoutPage());
        }

        $ids = $this->postedIds();
        if ($ids === []) {
            $this->Flash->error('Aucun jour sélectionné.');

            return $this->redirect($this->referer('/', true));
        }

        $deleted = $Ranges->deleteAll([
            'Ranges.id IN' => $ids,
            'Ranges.offer_id' => $offerId,
        ]);
        $this->Flash->success($deleted . ' jour(s) de télétravail supprimé(s).');

        return $this->redirect($this->referer('/', true));
    }

    /**
     * Configure method - Redirige vers la page d'édition de l'utilisateur
     * (La configuration du télétravail est maintenant gérée sur la page utilisateur)
     *
     * @param int|null $userId User ID
     * @return \Cake\Http\Response|null|void Redirects to Users/edit
     */
    public function configure($userId = null)
    {
        $this->Authorization->authorize(new \App\Resource\RemoteWorkResource(), 'configure');
        
        // Rediriger vers la page d'édition de l'utilisateur
        return $this->redirect(['controller' => 'Users', 'action' => 'edit', $userId]);
    }

    /**
     * AJAX - Retourne la configuration d'un agent en JSON
     *
     * @param int|null $userId User ID
     * @return void
     */
    public function ajaxGetUserSettings($userId = null)
    {
        $this->request->allowMethod(['get']);
        $this->Authorization->authorize(new \App\Resource\RemoteWorkResource(), 'ajaxGetUserSettings');
        
        $RemoteWorkTable = $this->fetchTable('UserRemoteWorkSettings');
        
        $setting = $RemoteWorkTable->find()
            ->where(['user_id' => $userId])
            ->first();
        
        $response = [
            'success' => false,
            'data' => null,
        ];
        
        if ($setting && $setting->isEnabled()) {
            $response['success'] = true;
            $response['data'] = [
                'remote_work_type' => $setting->remote_work_type,
                'fixed_days' => $setting->getFixedDays(),
                'time_ranges' => $setting->getTimeRanges(),
                'flexible_days_per_week' => $setting->flexible_days_per_week,
                'notes' => $setting->notes,
            ];
        }
        
        $this->viewBuilder()->setClassName('Json');
        $this->set($response);
        $this->viewBuilder()->setOption('serialize', array_keys($response));
    }

    /**
     * Add method - Ajouter un jour de télétravail (fixe ou flexible)
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise
     */
    public function add()
    {
        $this->Authorization->authorize(new \App\Resource\RemoteWorkResource(), 'addDay');
        $this->loadComponent('Groom');
        
        $RangesTable = $this->fetchTable('Ranges');
        $UsersTable = $this->fetchTable('Users');
        $RemoteWorkTable = $this->fetchTable('UserRemoteWorkSettings');
        
        // Récupérer l'offre de télétravail
        $syncService = new \App\Service\RemoteWorkRangesSyncService();
        $remoteWorkOfferId = $syncService->getRemoteWorkOfferId();
        
        if (!$remoteWorkOfferId) {
            $this->Flash->error("L'offre de télétravail n'a pas été trouvée.");
            return $this->redirect(['action' => 'index']);
        }
        
        $range = $RangesTable->newEmptyEntity();
        
        // Liste des utilisateurs avec config télétravail (fixe ou flexible)
        $usersWithRemoteWork = $RemoteWorkTable->find()
            ->where(['remote_work_type IN' => ['fixed_days', 'flexible']])
            ->all()
            ->extract('user_id')
            ->toArray();
        
        $users = $UsersTable->find('list', [
            'keyField' => 'id',
            'valueField' => function ($row) {
                return $row['last_name'] . ' ' . $row['first_name'];
            },
        ])
        ->where(['Users.id IN' => $usersWithRemoteWork])
        ->order(['last_name' => 'ASC'])
        ->toArray();
        
        if ($this->request->is('post')) {
            $data = $this->request->getData();
            
            // Vérifier que l'agent a une config télétravail (fixe ou flexible)
            $setting = $RemoteWorkTable->find()
                ->where(['user_id' => $data['user_id'], 'remote_work_type IN' => ['fixed_days', 'flexible']])
                ->first();
            
            if (!$setting) {
                $this->Flash->error("L'agent sélectionné n'a pas de configuration de télétravail.");
                $this->set(compact('range', 'users', 'remoteWorkOfferId'));
                return;
            }
            
            $data['date_start'] = $this->parseDateTimeLocal((string)($data['date_start'] ?? ''));
            $data['date_end'] = $this->parseDateTimeLocal((string)($data['date_end'] ?? ''));

            if ($data['date_start'] === null || $data['date_end'] === null) {
                $this->Flash->error('Les dates de début et de fin sont invalides.');
                $this->set(compact('range', 'users', 'remoteWorkOfferId'));

                return;
            }
            
            // Vérifier les dates de validité
            $rangeDate = \Cake\I18n\FrozenDate::parse($data['date_start']->format('Y-m-d'));
            if ($setting->start_date && $rangeDate < $setting->start_date) {
                $this->Flash->error("La date est antérieure à la date de début de validité du télétravail.");
                $this->set(compact('range', 'users', 'remoteWorkOfferId'));
                return;
            }
            if ($setting->end_date && $rangeDate > $setting->end_date) {
                $this->Flash->error("La date est postérieure à la date de fin de validité du télétravail.");
                $this->set(compact('range', 'users', 'remoteWorkOfferId'));
                return;
            }
            
            // Traitement des jours de la semaine si spécifiés
            $dates = [];
            if (!empty($data['days']) && is_array($data['days'])) {
                $dates = $this->Groom->findDayDates($data['days'], [
                    'date_start' => $data['date_start']->i18nFormat('yyyy-MM-dd HH:mm:ss'),
                    'date_end' => $data['date_end']->i18nFormat('yyyy-MM-dd HH:mm:ss'),
                ]);
            }
            
            if (empty($dates)) {
                // Un seul range
                unset($data['days']);
                $data['offer_id'] = $remoteWorkOfferId;
                $entity = $RangesTable->newEntity($data);
                
                if ($RangesTable->save($entity)) {
                    $this->Flash->success("Le jour de télétravail a été sauvegardé.");
                    return $this->redirect(['action' => 'index']);
                }
                $this->Flash->error("Le jour de télétravail n'a pas pu être sauvegardé. Merci d'essayer à nouveau.");
            } else {
                // Plusieurs ranges (jours de la semaine)
                $ranges = [];
                foreach ($dates as $date) {
                    $ranges[] = [
                        'date_start' => $date['date_start'],
                        'date_end' => $date['date_end'],
                        'user_id' => $data['user_id'],
                        'offer_id' => $remoteWorkOfferId,
                        'comment' => $data['comment'] ?? null,
                    ];
                }
                
                $entities = $RangesTable->newEntities($ranges);
                
                if ($RangesTable->saveMany($entities)) {
                    $this->Flash->success('Les jours de télétravail ont été sauvegardés.');
                    return $this->redirect(['action' => 'index']);
                }
                $this->Flash->error("Les jours de télétravail n'ont pas pu être sauvegardés. Merci d'essayer à nouveau.");
            }
        }
        
        $this->set(compact('range', 'users', 'remoteWorkOfferId'));
    }

    /**
     * Delete method - Supprimer la configuration de télétravail
     *
     * @param int|null $id Setting ID
     * @return \Cake\Http\Response|null Redirects to index
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $this->Authorization->authorize(new \App\Resource\RemoteWorkResource(), 'delete');
        
        $RemoteWorkTable = $this->fetchTable('UserRemoteWorkSettings');
        $setting = $RemoteWorkTable->get($id);
        
        if ($RemoteWorkTable->delete($setting)) {
            $this->Flash->success("La configuration du télétravail a été supprimée.");
        } else {
            $this->Flash->error("La configuration n'a pas pu être supprimée.");
        }
        
        return $this->redirect(['action' => 'index']);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function remoteWorkConditions(array $params, int $offerId): array
    {
        $conditions = $this->buildRangeFilters($params);
        if ($offerId <= 0) {
            return $conditions;
        }
        $conditions['Ranges.offer_id'] = $offerId;
        $rangeType = $this->normalizeRangeType($params['range_type'] ?? null);
        if ($rangeType === 'fixed') {
            $conditions['Ranges.source'] = RangeSource::AUTO_TAD;
        } elseif ($rangeType === 'flexible') {
            $conditions['Ranges.source !='] = RangeSource::AUTO_TAD;
        }

        return $conditions;
    }

    private function normalizeRangeType(mixed $value): string
    {
        if (is_string($value) && in_array($value, ['all', 'fixed', 'flexible'], true)) {
            return $value;
        }

        return 'all';
    }

    /**
     * @return array<int>
     */
    private function postedIds(): array
    {
        $raw = $this->request->getData('ids');
        if (!is_array($raw)) {
            return [];
        }
        $ids = [];
        foreach ($raw as $id) {
            $int = $this->rangeFilterPositiveInt($id);
            if ($int !== null) {
                $ids[$int] = $int;
            }
        }

        return array_values($ids);
    }

    /**
     * @return array<string, mixed>
     */
    private function indexUrlWithoutPage(): array
    {
        $query = $this->request->getQueryParams();
        unset($query['page']);
        $url = ['action' => 'index'];
        if ($query !== []) {
            $url['?'] = $query;
        }

        return $url;
    }

    private function parseDateTimeLocal(string $value): ?FrozenTime
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $parsed = FrozenTime::createFromFormat('Y-m-d\TH:i', $value)
            ?: FrozenTime::createFromFormat('Y-m-d\TH:i:s', $value)
            ?: FrozenTime::createFromFormat('Y-m-d H:i:s', str_replace('T', ' ', $value));

        return $parsed ?: null;
    }
}
