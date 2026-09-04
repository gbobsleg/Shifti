<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * RemoteWork Controller
 * Gestion de la configuration du télétravail par agent
 */
class RemoteWorkController extends AppController
{
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
        
        // Filtre par intervalle [date_start, date_end] : ranges qui chevauchent (comme Ranges)
        $filterStart = null;
        $filterEnd = null;
        if (!empty($params['date_start'])) {
            $dateStart = $params['date_start'];
            if (is_array($dateStart) && !empty($dateStart['year']) && !empty($dateStart['month']) && !empty($dateStart['day'])) {
                $filterStart = sprintf('%04d-%02d-%02d', $dateStart['year'], $dateStart['month'], $dateStart['day']) . ' 00:00:00';
            } elseif (is_string($dateStart) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStart)) {
                $filterStart = $dateStart . ' 00:00:00';
            }
        }
        if (!empty($params['date_end'])) {
            $dateEnd = $params['date_end'];
            if (is_array($dateEnd) && !empty($dateEnd['year']) && !empty($dateEnd['month']) && !empty($dateEnd['day'])) {
                $filterEnd = sprintf('%04d-%02d-%02d', $dateEnd['year'], $dateEnd['month'], $dateEnd['day']) . ' 23:59:59';
            } elseif (is_string($dateEnd) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEnd)) {
                $filterEnd = $dateEnd . ' 23:59:59';
            }
        }
        
        // Liste des utilisateurs ayant au moins un range télétravail (pour le filtre Agent)
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
        
        // Requête pour les ranges de télétravail
        $remoteWorkDays = $RangesTable->find()
            ->where(['offer_id' => $remoteWorkOfferId])
            ->contain(['Users', 'Offers']);
        
        // Filtre par type (fixe/flexible)
        $rangeType = $params['range_type'] ?? 'all';
        if ($rangeType === 'fixed') {
            // Uniquement les ranges auto-créés (jours fixes)
            $remoteWorkDays->where(['comment LIKE' => '[AUTO-TAD]%']);
        } elseif ($rangeType === 'flexible') {
            // Uniquement les ranges manuels (flexibles)
            $remoteWorkDays->where(function ($exp) {
                return $exp->or([
                    'comment IS' => null,
                    'comment NOT LIKE' => '[AUTO-TAD]%',
                ]);
            });
        }
        // Si 'all', pas de filtre supplémentaire (affiche tous les ranges)
        
        // Chevauchement : range chevauche [filterStart, filterEnd] ssi date_start <= filterEnd ET date_end >= filterStart
        if ($filterStart !== null && $filterEnd !== null) {
            $remoteWorkDays->where([
                'Ranges.date_start <=' => $filterEnd,
                'Ranges.date_end >=' => $filterStart
            ]);
        } elseif ($filterStart !== null) {
            $remoteWorkDays->where(['Ranges.date_end >=' => $filterStart]);
        } elseif ($filterEnd !== null) {
            $remoteWorkDays->where(['Ranges.date_start <=' => $filterEnd]);
        }
        if (!empty($params['user_id'])) {
            $remoteWorkDays->where(['Ranges.user_id' => $params['user_id']]);
        }
        
        // Pagination
        $this->paginate = ['limit' => 25, 'order' => ['Ranges.date_start' => 'DESC']];
        $remoteWorkDays = $this->paginate($remoteWorkDays);

        $this->set(compact('remoteWorkDays', 'users', 'remoteWorkOfferId', 'rangeType'));
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
            
            // Parser les dates
            $data['date_start'] = \Cake\I18n\FrozenTime::createFromFormat('d/m/Y, H:i', (string)$data['date_start']);
            $data['date_end'] = \Cake\I18n\FrozenTime::createFromFormat('d/m/Y, H:i', (string)$data['date_end']);
            
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
}
