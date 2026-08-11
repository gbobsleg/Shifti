<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Event\EventInterface;
use Authentication\PasswordHasher\DefaultPasswordHasher;

/**
 * Users Controller
 *
 * @property \App\Model\Table\UsersTable $Users
 * @method \App\Model\Entity\User[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class UsersController extends AppController
{
    /**
     * Configuration des actions autorisées sans authentification
     */
    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);
        // Autoriser les actions login et logout sans authentification
        $this->Authentication->addUnauthenticatedActions(['login', 'logout']);
        $action = (string)$this->request->getParam('action');
        // Forcer le layout dédié pour la page de connexion
        if ($action === 'login') {
            $this->viewBuilder()->setLayout('login');
        }
        // Utiliser le layout dashtron pour les pages compte et changement de mot de passe
        if (in_array($action, ['account', 'changePassword'], true)) {
            $this->viewBuilder()->setLayout('TwitterBootstrap/dashtron');
        }
    }

    /**
     * Méthode de connexion
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function login()
    {
        $this->request->allowMethod(['get', 'post']);
        
        $result = $this->Authentication->getResult();
        
        // Si l'utilisateur est authentifié
        if ($result->isValid()) {
            $redirectUrl = $this->Authentication->getLoginRedirect() ?? ['controller' => 'Grids', 'action' => 'index'];
            return $this->redirect($redirectUrl);
        }
        
        // Afficher les erreurs d'authentification
        if ($this->request->is('post') && !$result->isValid()) {
            $this->Flash->error('Email ou mot de passe invalide.');
        }
    }

    /**
     * Méthode de déconnexion
     *
     * @return \Cake\Http\Response|null|void Redirects to login
     */
    public function logout()
    {
        $result = $this->Authentication->getResult();
        
        if ($result->isValid()) {
            $this->Authentication->logout();
            $this->Flash->success('Vous avez été déconnecté.', ['params' => ['auto-dismiss' => 5000]]);
        }
        
        return $this->redirect(['controller' => 'Users', 'action' => 'login']);
    }

    /**
     * Changer son mot de passe (utilisateur connecté)
     */
    public function changePassword()
    {
        $this->request->allowMethod(['get', 'post', 'patch', 'put']);

        $identity = $this->Authentication->getIdentity();
        if (!$identity) {
            return $this->redirect(['action' => 'login']);
        }

        $userId = method_exists($identity, 'getIdentifier') ? $identity->getIdentifier() : $identity->get('id');
        $user = $this->Users->get($userId);

        if ($this->request->is(['post', 'patch', 'put'])) {
            $data = (array)$this->request->getData();
            $current = (string)($data['current_password'] ?? '');
            $new = (string)($data['new_password'] ?? '');
            $confirm = (string)($data['confirm_password'] ?? '');

            $hasher = new DefaultPasswordHasher();

            if (!$hasher->check($current, (string)$user->password)) {
                $this->Flash->error("Le mot de passe actuel est incorrect.");
            } elseif (strlen($new) < 8) {
                $this->Flash->error("Le nouveau mot de passe doit contenir au moins 8 caractères.");
            } elseif ($new !== $confirm) {
                $this->Flash->error("La confirmation ne correspond pas au nouveau mot de passe.");
            } else {
                $user = $this->Users->patchEntity($user, ['password' => $new], [
                    'fields' => ['password'],
                ]);
                if ($this->Users->save($user)) {
                    $this->Flash->success('Mot de passe mis à jour.', ['params' => ['auto-dismiss' => 5000]]);
                    return $this->redirect(['controller' => 'Grids', 'action' => 'index']);
                }
                $this->Flash->error("Le mot de passe n'a pas pu être mis à jour. Merci d'essayer à nouveau.");
            }
        }

        $this->set(compact('user'));
    }

    /**
     * Mon compte (affichage des infos de base)
     */
    public function account()
    {
        $this->request->allowMethod(['get']);

        $identity = $this->Authentication->getIdentity();
        if (!$identity) {
            return $this->redirect(['action' => 'login']);
        }

        $userId = method_exists($identity, 'getIdentifier') ? $identity->getIdentifier() : $identity->get('id');
        $user = $this->Users->get($userId, [
            'contain' => ['Roles', 'Sites']
        ]);

        $this->set(compact('user'));
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $this->Authorization->authorize(new \App\Resource\UsersResource(), 'index');
        
        $session = $this->request->getSession();
        $sessionKey = 'Users.index.filters';
        $sortSessionKey = 'Users.index.sort';
        
        // Si demande explicite de reset des filtres
        if ($this->request->getQuery('reset') === '1') {
            $session->delete($sessionKey);
            $session->delete($sortSessionKey);
            return $this->redirect(['action' => 'index']);
        }
        
        // Récupérer les filtres depuis la query string ou la session
        $queryParams = $this->request->getQueryParams();
        $hasQueryFilters = !empty($queryParams['search_name']) || 
                          !empty($queryParams['search_firstname']) || 
                          !empty($queryParams['role_id']) || 
                          !empty($queryParams['site_id']);
        
        if ($hasQueryFilters) {
            // Nouveaux filtres appliqués → sauvegarder en session
            $filters = [
                'search_name' => $queryParams['search_name'] ?? '',
                'search_firstname' => $queryParams['search_firstname'] ?? '',
                'role_id' => $queryParams['role_id'] ?? '',
                'site_id' => $queryParams['site_id'] ?? '',
            ];
            $session->write($sessionKey, $filters);
        } else {
            // Pas de filtres dans l'URL → restaurer depuis la session si disponible
            $filters = $session->read($sessionKey) ?? [
                'search_name' => '',
                'search_firstname' => '',
                'role_id' => '',
                'site_id' => '',
            ];
        }

        // Le tri (colonne cliquée) est mémorisé en session pour survivre à une
        // redirection (ex: retour sur la liste après modification d'un utilisateur)
        if (!empty($queryParams['sort'])) {
            $session->write($sortSessionKey, [
                'sort' => $queryParams['sort'],
                'direction' => $queryParams['direction'] ?? 'asc',
            ]);
        } else {
            $storedSort = $session->read($sortSessionKey);
            if ($storedSort) {
                $queryParams['sort'] = $storedSort['sort'];
                $queryParams['direction'] = $storedSort['direction'];
                $this->setRequest($this->request->withQueryParams($queryParams));
            }
        }
        
        $query = $this->Users->find()->contain(['Roles', 'Sites']);
        
        // Appliquer les filtres
        if (!empty($filters['search_name'])) {
            $query->where(['Users.last_name LIKE' => '%' . $filters['search_name'] . '%']);
        }
        
        if (!empty($filters['search_firstname'])) {
            $query->where(['Users.first_name LIKE' => '%' . $filters['search_firstname'] . '%']);
        }
        
        if (!empty($filters['role_id'])) {
            $query->where(['Users.role_id' => $filters['role_id']]);
        }
        
        if (!empty($filters['site_id'])) {
            $query->where(['Users.site_id' => $filters['site_id']]);
        }
        
        // Pagination normale
        $this->paginate = ['limit' => 25, 'order' => ['Users.last_name' => 'asc']];
        $users = $this->paginate($query);
        
        // Données pour le formulaire de recherche
        $roles = $this->Users->Roles->find('list', ['limit' => 200])->toArray();
        $sites = $this->Users->Sites->find('list', ['limit' => 200])->toArray();

        // Statistiques
        $allUsers = $this->Users->find()->contain(['Roles'])->all();
        $stats = [
            'total' => $allUsers->count(),
            'active' => $allUsers->filter(function($u) { return $u->active ?? true; })->count(),
            'inactive' => $allUsers->filter(function($u) { return !($u->active ?? true); })->count(),
        ];
        
        // Stats par rôle (top 3)
        $roleStats = [];
        foreach ($allUsers as $user) {
            if ($user->role) {
                $roleId = $user->role->id;
                if (!isset($roleStats[$roleId])) {
                    $roleStats[$roleId] = ['name' => $user->role->name, 'count' => 0];
                }
                $roleStats[$roleId]['count']++;
            }
        }
        arsort($roleStats);
        $stats['roles'] = array_slice($roleStats, 0, 3, true);

        $this->set(compact('users', 'roles', 'sites', 'stats', 'filters'));
    }

    /**
     * View method
     *
     * @param string|null $id User id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\UsersResource(), 'view');
        $user = $this->Users->get($id, [
            'contain' => ['Roles', 'Sites', 'Skills' => ['Offers'], 'UserAvailabilities', 'UserRemoteWorkSetting', 'UserContracts'],
        ]);

        // Charger la règle de rotation avec ses relations si elle existe
        $UsersRotationRules = $this->fetchTable('UsersRotationRules');
        $userRotationRuleEntity = $UsersRotationRules->find()
            ->where(['user_id' => $id])
            ->contain(['RotationRules.Offers'])
            ->first();
        
        if ($userRotationRuleEntity) {
            $user->users_rotation_rule = $userRotationRuleEntity;
        }

        $this->set(compact('user'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $this->Authorization->authorize(new \App\Resource\UsersResource(), 'add');
        $user = $this->Users->newEmptyEntity();
        $days = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];
        $daysOfWeek = $days;
        $offers = $this->Users->Offers->find('list', [
            'order' => ['name' => 'ASC']
        ])->where([
            'Offers.offer_type NOT IN' => ['pause', 'lunch', 'remote_work', 'absence', 'meeting']
        ]);
        $userSkills = [];
        $contractsData = null;

        $RemoteWorkTable = $this->fetchTable('UserRemoteWorkSettings');
        $remoteWorkSetting = $RemoteWorkTable->newEmptyEntity();
        $remoteWorkSetting->remote_work_type = 'none';
        $fixedDays = [];
        $timeStart = '09:00';
        $timeEnd = '17:00';
        $startDate = null;
        $endDate = null;

        $buildAvailabilitiesForForm = function (array $postedAvailabilities = []) use ($user) {
            $finalAvailabilities = [];
            for ($i = 1; $i <= 7; $i++) {
                $index = $i - 1;
                $row = $postedAvailabilities[$index] ?? [];

                // Valeurs par défaut : lundi-vendredi (1-5) = 09h-17h, samedi-dimanche (6-7) = 00h-00h
                $isWeekday = $i >= 1 && $i <= 5;
                $defaultStart = $isWeekday ? '09:00:00' : '00:00:00';
                $defaultEnd = $isWeekday ? '17:00:00' : '00:00:00';
                $defaultEarliestEnd = $isWeekday ? '16:30:00' : '00:00:00';

                $finalAvailabilities[] = $this->Users->UserAvailabilities->newEntity([
                    'id' => $row['id'] ?? null,
                    'day_of_week' => $i,
                    'availability_start_time' => $row['availability_start_time'] ?? $defaultStart,
                    'availability_end_time' => $row['availability_end_time'] ?? $defaultEnd,
                    'earliest_end_time' => $row['earliest_end_time'] ?? $defaultEarliestEnd,
                ], ['validate' => false]);
            }

            $user->set('user_availabilities', $finalAvailabilities);
        };

        if ($this->request->is('post')) {
            $data = $this->request->getData();
            $availabilitiesData = $data['user_availabilities'] ?? [];
            $skillsData = $data['skills'] ?? null;
            $remoteWorkData = $data['remote_work'] ?? null;
            $rotationRuleData = $data['rotation_rule'] ?? null;
            $contractsData = $data['contracts'] ?? null;
            unset($data['skills'], $data['remote_work'], $data['rotation_rule'], $data['contracts']);

            $user = $this->Users->patchEntity($user, $data, [
                'associated' => ['UserAvailabilities']
            ]);

            $connection = $this->Users->getConnection();
            $success = $connection->transactional(function () use ($user, $skillsData, $remoteWorkData, $rotationRuleData, $contractsData, $RemoteWorkTable) {
                if (!$this->Users->save($user)) {
                    return false;
                }

                if (!empty($contractsData) && is_array($contractsData)) {
                    $UserContracts = $this->fetchTable('UserContracts');
                    foreach ($contractsData as $contractData) {
                        if (empty($contractData['start_date'])) {
                            continue;
                        }
                        $contract = $UserContracts->newEntity($contractData);
                        $contract->user_id = $user->id;
                        if (!$UserContracts->save($contract)) {
                            return false;
                        }
                    }
                }

                if (is_array($skillsData) && !empty($skillsData)) {
                    $skillEntities = [];
                    foreach ($skillsData as $offerId => $skillData) {
                        if (!empty($skillData['selected'])) {
                            $skillEntities[] = $this->Users->Skills->newEntity([
                                'user_id' => $user->id,
                                'offer_id' => (int)$offerId,
                                'validity_start' => !empty($skillData['validity_start']) ? $skillData['validity_start'] : null,
                                'validity_end' => !empty($skillData['validity_end']) ? $skillData['validity_end'] : null,
                            ]);
                        }
                    }

                    if (!empty($skillEntities) && !$this->Users->Skills->saveMany($skillEntities)) {
                        return false;
                    }
                }

                if (is_array($remoteWorkData) && !empty($remoteWorkData)) {
                    $remoteWorkData['user_id'] = $user->id;

                    if (!isset($remoteWorkData['remote_work_type']) || empty($remoteWorkData['remote_work_type'])) {
                        $remoteWorkData['remote_work_type'] = 'none';
                    }

                    if ($remoteWorkData['remote_work_type'] === 'fixed_days') {
                        $fixedDays = [];
                        $timeRanges = [];

                        if (isset($remoteWorkData['fixed_days']) && is_array($remoteWorkData['fixed_days'])) {
                            $fixedDays = array_map('intval', $remoteWorkData['fixed_days']);
                        }

                        if (isset($remoteWorkData['time_start']) && isset($remoteWorkData['time_end'])) {
                            $timeRanges[] = [
                                'start' => $remoteWorkData['time_start'],
                                'end' => $remoteWorkData['time_end'],
                            ];
                        }

                        $jsonData = [
                            'days' => $fixedDays,
                            'time_ranges' => $timeRanges,
                        ];
                        $remoteWorkData['fixed_days_json'] = json_encode($jsonData);
                        $remoteWorkData['flexible_days_per_week'] = 0;
                    } elseif ($remoteWorkData['remote_work_type'] === 'flexible') {
                        $remoteWorkData['fixed_days_json'] = null;
                        $remoteWorkData['flexible_days_per_week'] = (int)($remoteWorkData['flexible_days_per_week'] ?? 0);
                    } else {
                        $remoteWorkData['fixed_days_json'] = null;
                        $remoteWorkData['flexible_days_per_week'] = 0;
                    }

                    if (isset($remoteWorkData['start_date']) && (empty($remoteWorkData['start_date']) || trim((string)$remoteWorkData['start_date']) === '')) {
                        $remoteWorkData['start_date'] = null;
                    }
                    if (isset($remoteWorkData['end_date']) && (empty($remoteWorkData['end_date']) || trim((string)$remoteWorkData['end_date']) === '')) {
                        $remoteWorkData['end_date'] = null;
                    }

                    $jsonString = null;
                    if (isset($remoteWorkData['fixed_days_json']) && is_string($remoteWorkData['fixed_days_json'])) {
                        $jsonString = $remoteWorkData['fixed_days_json'];
                    } elseif (isset($remoteWorkData['fixed_days_json']) && is_array($remoteWorkData['fixed_days_json'])) {
                        $jsonString = json_encode($remoteWorkData['fixed_days_json']);
                    }
                    unset($remoteWorkData['fixed_days_json']);

                    if (empty($remoteWorkData['id'])) {
                        unset($remoteWorkData['id']);
                        $remoteWorkSetting = $RemoteWorkTable->newEmptyEntity();
                    } else {
                        $remoteWorkSetting = $RemoteWorkTable->newEmptyEntity();
                    }

                    $remoteWorkSetting = $RemoteWorkTable->patchEntity($remoteWorkSetting, $remoteWorkData);
                    if ($jsonString !== null) {
                        $remoteWorkSetting->set('fixed_days_json', $jsonString);
                    }

                    if (!$RemoteWorkTable->save($remoteWorkSetting)) {
                        return false;
                    }

                    // Synchroniser les ranges pour le télétravail fixe / none
                    if ($remoteWorkSetting->isFixedDays() || $remoteWorkSetting->remote_work_type === 'none') {
                        $syncService = new \App\Service\RemoteWorkRangesSyncService();
                        $syncService->syncUserRemoteWorkRanges((int)$user->id, $remoteWorkSetting);
                    }
                }

                // Gestion de la règle de rotation
                if (is_array($rotationRuleData) && !empty($rotationRuleData['rotation_rule_id'])) {
                    $UsersRotationRules = $this->fetchTable('UsersRotationRules');
                    $rotationRuleEntity = $UsersRotationRules->newEntity([
                        'user_id' => $user->id,
                        'rotation_rule_id' => $rotationRuleData['rotation_rule_id'],
                        'target_count_override' => !empty($rotationRuleData['target_count_override']) 
                            ? (int)$rotationRuleData['target_count_override'] 
                            : null,
                    ]);
                    if (!$UsersRotationRules->save($rotationRuleEntity)) {
                        return false;
                    }
                }

                return true;
            });

            if ($success) {
                $this->Flash->success("L'utilisateur a été sauvegardé.", ['params' => ['auto-dismiss' => 5000]]);

                return $this->redirect(['action' => 'index']);
            }

            $buildAvailabilitiesForForm($availabilitiesData);
            // Reconstituer les variables pour ré-afficher correctement le formulaire (skills + télétravail)
            if (is_array($skillsData) && !empty($skillsData)) {
                $userSkills = [];
                foreach ($skillsData as $offerId => $skillData) {
                    if (!empty($skillData['selected'])) {
                        $userSkills[(int)$offerId] = [
                            'validity_start' => !empty($skillData['validity_start']) ? (string)$skillData['validity_start'] : null,
                            'validity_end' => !empty($skillData['validity_end']) ? (string)$skillData['validity_end'] : null,
                        ];
                    }
                }
            }

            if (is_array($remoteWorkData) && !empty($remoteWorkData)) {
                // On prépare l'entity et les variables d'affichage comme dans edit()
                $remoteWorkSetting = $RemoteWorkTable->newEmptyEntity();
                $remoteWorkSetting = $RemoteWorkTable->patchEntity($remoteWorkSetting, $remoteWorkData);

                if (!isset($remoteWorkData['remote_work_type']) || empty($remoteWorkData['remote_work_type'])) {
                    $remoteWorkSetting->remote_work_type = 'none';
                }

                $daysOfWeek = $days;
                $fixedDays = [];
                $timeStart = '09:00';
                $timeEnd = '17:00';
                $startDate = null;
                $endDate = null;

                if (($remoteWorkData['remote_work_type'] ?? 'none') === 'fixed_days') {
                    $fixedDays = array_map('intval', $remoteWorkData['fixed_days'] ?? []);
                    $timeStart = (string)($remoteWorkData['time_start'] ?? $timeStart);
                    $timeEnd = (string)($remoteWorkData['time_end'] ?? $timeEnd);
                }

                if (!empty($remoteWorkData['start_date'])) {
                    $startDate = (string)$remoteWorkData['start_date'];
                }
                if (!empty($remoteWorkData['end_date'])) {
                    $endDate = (string)$remoteWorkData['end_date'];
                }
            }
            $this->Flash->error("L'utilisateur n'a pas pu être sauvegardé. Merci d'essayer à nouveau.");
        } else {
            $buildAvailabilitiesForForm();
        }
        $roles = $this->Users->Roles->find('list', ['limit' => 200]);
        $sites = $this->Users->Sites->find('list', ['limit' => 200]);
        
        // Charger les règles de rotation disponibles
        $rotationRules = $this->fetchTable('RotationRules')
            ->find('list', [
                'keyField' => 'id',
                'valueField' => 'name'
            ])
            ->order(['name' => 'ASC'])
            ->toArray();
        
        // Valeurs par défaut pour la rotation (nouvel utilisateur)
        $selectedRotationRuleId = null;
        $rotationTargetOverride = null;
        $userContracts = [];
        if ($this->request->is('post') && !empty($contractsData) && is_array($contractsData)) {
            $UserContracts = $this->fetchTable('UserContracts');
            foreach ($contractsData as $contractData) {
                if (empty($contractData['start_date'])) {
                    continue;
                }
                $userContracts[] = $UserContracts->newEntity($contractData, ['validate' => false]);
            }
        }

        $this->set(compact('user', 'roles', 'sites', 'days', 'offers', 'userSkills', 'remoteWorkSetting', 'fixedDays', 'timeStart', 'timeEnd', 'startDate', 'endDate', 'daysOfWeek', 'rotationRules', 'selectedRotationRuleId', 'rotationTargetOverride', 'userContracts'));
    }

    /**
     * Edit method
     *
     * @param string|null $id User id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\UsersResource(), 'edit');
        $this->loadComponent('Groom');
        
        $user = $this->Users->get($id, [
            'contain' => ['UserAvailabilities', 'Skills', 'UserRemoteWorkSetting', 'UserContracts'],
        ]);
        
        // Charger la règle de rotation avec ses relations si elle existe
        $UsersRotationRules = $this->fetchTable('UsersRotationRules');
        $userRotationRuleEntity = $UsersRotationRules->find()
            ->where(['user_id' => $id])
            ->contain(['RotationRules.Offers'])
            ->first();
        
        if ($userRotationRuleEntity) {
            $user->users_rotation_rule = $userRotationRuleEntity;
        }

        $days = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];
        $availabilities = [];

        // Met les dispo existantes dans un tableau facile à lire (clé = 1 pour Lundi, etc.)
        foreach ($user->user_availabilities as $availability) {
            $availabilities[$availability->day_of_week] = $availability;
        }

        // --- CORRECTION ---
        // On crée un NOUVEAU tableau 0-indexé pour le FormHelper
        $finalAvailabilities = [];

        for ($i = 1; $i <= 7; $i++) { // On boucle de Lundi (1) à Dimanche (7)
            if (!isset($availabilities[$i])) {
                // Crée l'entité vide si elle n'existe pas en BDD
                $availabilities[$i] = $this->Users->UserAvailabilities->newEntity([
                    'user_id' => $user->id,
                    'day_of_week' => $i,
                    'availability_start_time' => '00:00:00',
                    'availability_end_time' => '00:00:00',
                ]);
            }
            // On ajoute l'entité (existante ou nouvelle) au tableau final
            $finalAvailabilities[] = $availabilities[$i];
        }

        // On ré-assigne à $user un tableau propre, 0-indexé (0=Lundi, 1=Mardi, ...)
        $user->user_availabilities = $finalAvailabilities;

        // Gérer UserRemoteWorkSetting
        $RemoteWorkTable = $this->fetchTable('UserRemoteWorkSettings');
        if (isset($user->user_remote_work_setting) && $user->user_remote_work_setting) {
            $remoteWorkSetting = $user->user_remote_work_setting;
        } else {
            $remoteWorkSetting = $RemoteWorkTable->newEmptyEntity();
            $remoteWorkSetting->user_id = $user->id;
            $remoteWorkSetting->remote_work_type = 'none';
        }

        if ($this->request->is(['patch', 'post', 'put'])) {
            $data = $this->request->getData();
            if (isset($data['password']) && trim((string)$data['password']) === '') {
                unset($data['password']);
            }

            // Gestion des compétences (skills)
            $skillsData = $data['skills'] ?? null;
            if ($skillsData !== null) {
                // Supprimer toutes les compétences existantes
                $this->Users->Skills->deleteAll(['user_id' => $user->id]);
                
                // Créer les nouvelles compétences
                foreach ($skillsData as $offerId => $skillData) {
                    if (!empty($skillData['selected'])) {
                        $skill = $this->Users->Skills->newEntity([
                            'user_id' => $user->id,
                            'offer_id' => $offerId,
                            'validity_start' => !empty($skillData['validity_start']) ? $skillData['validity_start'] : null,
                            'validity_end' => !empty($skillData['validity_end']) ? $skillData['validity_end'] : null,
                        ]);
                        $this->Users->Skills->save($skill);
                    }
                }
            }

            // Gestion des contrats
            if (!empty($data['contracts'])) {
                $contractsData = $data['contracts'];
                $UserContracts = $this->fetchTable('UserContracts');
                
                foreach ($contractsData as $contractData) {
                    if (!empty($contractData['id'])) {
                        // Mise a jour contrat existant
                        $contract = $UserContracts->get($contractData['id']);
                        $contract = $UserContracts->patchEntity($contract, $contractData);
                    } elseif (!empty($contractData['start_date'])) {
                        // Nouveau contrat
                        $contract = $UserContracts->newEntity($contractData);
                        $contract->user_id = $user->id;
                    } else {
                        continue;
                    }
                    
                    if (!$UserContracts->save($contract)) {
                        $this->Flash->error('Erreur lors de la sauvegarde du contrat.');
                    }
                }
            }
            
            // Traiter le télétravail
            $remoteWorkData = $data['remote_work'] ?? null;
            if ($remoteWorkData !== null && is_array($remoteWorkData) && !empty($remoteWorkData)) {
                $remoteWorkData['user_id'] = $user->id;
                
                // Initialiser remote_work_type à 'none' si non défini
                if (!isset($remoteWorkData['remote_work_type']) || empty($remoteWorkData['remote_work_type'])) {
                    $remoteWorkData['remote_work_type'] = 'none';
                }
                
                // Traiter les données du formulaire pour le télétravail
                if ($remoteWorkData['remote_work_type'] === 'fixed_days') {
                    // Construire le JSON pour les jours fixes
                    $fixedDays = [];
                    $timeRanges = [];
                    
                    if (isset($remoteWorkData['fixed_days']) && is_array($remoteWorkData['fixed_days'])) {
                        $fixedDays = array_map('intval', $remoteWorkData['fixed_days']);
                    }
                    
                    if (isset($remoteWorkData['time_start']) && isset($remoteWorkData['time_end'])) {
                        $timeRanges[] = [
                            'start' => $remoteWorkData['time_start'],
                            'end' => $remoteWorkData['time_end'],
                        ];
                    }
                    
                    // Encoder en JSON
                    $jsonData = [
                        'days' => $fixedDays,
                        'time_ranges' => $timeRanges,
                    ];
                    $remoteWorkData['fixed_days_json'] = json_encode($jsonData);
                    $remoteWorkData['flexible_days_per_week'] = 0;
                } elseif ($remoteWorkData['remote_work_type'] === 'flexible') {
                    $remoteWorkData['fixed_days_json'] = null;
                    $remoteWorkData['flexible_days_per_week'] = (int)($remoteWorkData['flexible_days_per_week'] ?? 0);
                } else {
                    // none
                    $remoteWorkData['fixed_days_json'] = null;
                    $remoteWorkData['flexible_days_per_week'] = 0;
                }
                
                // Gérer les dates de début/fin
                if (isset($remoteWorkData['start_date'])) {
                    if (empty($remoteWorkData['start_date']) || $remoteWorkData['start_date'] === '' || trim($remoteWorkData['start_date']) === '') {
                        $remoteWorkData['start_date'] = null;
                    }
                } else {
                    $remoteWorkData['start_date'] = null;
                }
                
                if (isset($remoteWorkData['end_date'])) {
                    if (empty($remoteWorkData['end_date']) || $remoteWorkData['end_date'] === '' || trim($remoteWorkData['end_date']) === '') {
                        $remoteWorkData['end_date'] = null;
                    }
                } else {
                    $remoteWorkData['end_date'] = null;
                }
                
                // Retirer fixed_days_json des données pour patchEntity
                $jsonString = null;
                if (isset($remoteWorkData['fixed_days_json']) && is_string($remoteWorkData['fixed_days_json'])) {
                    $jsonString = $remoteWorkData['fixed_days_json'];
                } elseif (isset($remoteWorkData['fixed_days_json']) && is_array($remoteWorkData['fixed_days_json'])) {
                    $jsonString = json_encode($remoteWorkData['fixed_days_json']);
                }
                unset($remoteWorkData['fixed_days_json']);
                
                // Si l'ID est vide, créer une nouvelle entité
                if (empty($remoteWorkData['id'])) {
                    unset($remoteWorkData['id']);
                    $remoteWorkSetting = $RemoteWorkTable->newEmptyEntity();
                }
                
                $remoteWorkSetting = $RemoteWorkTable->patchEntity($remoteWorkSetting, $remoteWorkData);
                
                // Définir la valeur JSON directement après patchEntity
                if ($jsonString !== null) {
                    $remoteWorkSetting->set('fixed_days_json', $jsonString);
                }
            }
            
            // Gestion de la règle de rotation
            $rotationRuleData = $data['rotation_rule'] ?? null;
            if ($rotationRuleData !== null && is_array($rotationRuleData)) {
                $UsersRotationRules = $this->fetchTable('UsersRotationRules');
                
                // Supprimer l'ancienne assignation si elle existe
                $UsersRotationRules->deleteAll(['user_id' => $user->id]);
                
                // Créer la nouvelle assignation si une règle est sélectionnée
                if (!empty($rotationRuleData['rotation_rule_id'])) {
                    $rotationRuleEntity = $UsersRotationRules->newEntity([
                        'user_id' => $user->id,
                        'rotation_rule_id' => $rotationRuleData['rotation_rule_id'],
                        'target_count_override' => !empty($rotationRuleData['target_count_override']) 
                            ? (int)$rotationRuleData['target_count_override'] 
                            : null,
                    ]);
                    $UsersRotationRules->save($rotationRuleEntity);
                }
            }
            
            // Ne pas patcher ces champs "hors user" sur l'entité User
            unset($data['skills'], $data['remote_work'], $data['rotation_rule']);

            $user = $this->Users->patchEntity($user, $data, [
                'associated' => ['UserAvailabilities']
            ]);
            
            $saveSuccess = $this->Users->save($user);
            
            // Sauvegarder le télétravail si les données ont été fournies
            if ($saveSuccess && $remoteWorkData !== null && is_array($remoteWorkData) && !empty($remoteWorkData)) {
                if ($RemoteWorkTable->save($remoteWorkSetting)) {
                    // Synchroniser les ranges pour le télétravail fixe
                    if ($remoteWorkSetting->isFixedDays()) {
                        $syncService = new \App\Service\RemoteWorkRangesSyncService();
                        $stats = $syncService->syncUserRemoteWorkRanges((int)$user->id, $remoteWorkSetting);
                    } elseif ($remoteWorkSetting->remote_work_type === 'none') {
                        // Supprimer tous les ranges auto-créés si télétravail désactivé
                        $syncService = new \App\Service\RemoteWorkRangesSyncService();
                        $stats = $syncService->syncUserRemoteWorkRanges((int)$user->id, $remoteWorkSetting);
                    }
                }
            }
            
            if ($saveSuccess) {
                $this->Flash->success("L'utilisateur a été sauvegardé.", ['params' => ['auto-dismiss' => 5000]]);

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error("L'utilisateur n'a pas pu être sauvegardé. Merci d'essayer à nouveau.");
        }
        
        $roles = $this->Users->Roles->find('list', ['limit' => 200]);
        $sites = $this->Users->Sites->find('list', ['limit' => 200]);
        $offers = $this->Users->Offers->find('list', [
            'order' => ['name' => 'ASC']
        ])->where([
            'Offers.offer_type NOT IN' => ['pause', 'lunch', 'remote_work', 'absence', 'meeting']
        ]);
        
        // Charger les règles de rotation disponibles
        $rotationRules = $this->fetchTable('RotationRules')
            ->find('list', [
                'keyField' => 'id',
                'valueField' => 'name',
            ])
            ->order(['name' => 'ASC'])
            ->toArray();
        
        // Préparer les données de rotation pour le formulaire
        $userRotationRule = $user->users_rotation_rule ?? null;
        $selectedRotationRuleId = $userRotationRule ? $userRotationRule->rotation_rule_id : null;
        $rotationTargetOverride = $userRotationRule && $userRotationRule->target_count_override !== null 
            ? $userRotationRule->target_count_override 
            : null;
        
        // Préparer les compétences existantes pour le formulaire
        $userSkills = [];
        foreach ($user->skills as $skill) {
            $userSkills[$skill->offer_id] = [
                'validity_start' => $skill->validity_start,
                'validity_end' => $skill->validity_end,
            ];
        }
        
        // Préparer les données pour le formulaire de télétravail
        $fixedDays = [];
        $timeStart = '09:00';
        $timeEnd = '17:00';
        $startDate = null;
        $endDate = null;
        $daysOfWeek = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];
        
        if ($remoteWorkSetting->isFixedDays()) {
            $decoded = $remoteWorkSetting->getFixedDaysJsonDecoded();
            if ($decoded && isset($decoded['days'])) {
                $fixedDays = $decoded['days'];
            }
            $timeRanges = $remoteWorkSetting->getTimeRanges();
            if (!empty($timeRanges)) {
                $timeStart = $timeRanges[0]['start'] ?? '09:00';
                $timeEnd = $timeRanges[0]['end'] ?? '17:00';
            }
        }
        
        if ($remoteWorkSetting->start_date) {
            $startDate = $remoteWorkSetting->start_date->format('Y-m-d');
        }
        if ($remoteWorkSetting->end_date) {
            $endDate = $remoteWorkSetting->end_date->format('Y-m-d');
        }
        
        // Contrats de l'utilisateur
        $userContracts = $user->user_contracts ?? [];
        
        $this->set(compact('days', 'user', 'roles', 'sites', 'offers', 'userSkills', 'remoteWorkSetting', 'fixedDays', 'timeStart', 'timeEnd', 'startDate', 'endDate', 'daysOfWeek', 'rotationRules', 'selectedRotationRuleId', 'rotationTargetOverride', 'userContracts'));
    }

    /**
     * Clôturer un contrat
     */
    public function closeContract(int $contractId)
    {
        $this->request->allowMethod(['post', 'put', 'patch']);
        $this->Authorization->authorize(new \App\Resource\UsersResource(), 'edit');
        
        $UserContracts = $this->fetchTable('UserContracts');
        $contract = $UserContracts->get($contractId);
        
        $endDate = $this->request->getData('end_date') ?? date('Y-m-d');
        $contract->end_date = $endDate;
        
        if ($UserContracts->save($contract)) {
            $this->Flash->success('Contrat clôturé avec succès.');
        } else {
            $this->Flash->error('Erreur lors de la clôture du contrat.');
        }
        
        return $this->redirect($this->referer());
    }

    /**
     * Delete method
     *
     * @param string|null $id User id.
     * @return \Cake\Http\Response|null|void Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\UsersResource(), 'delete');
        $this->request->allowMethod(['post', 'delete']);
        $user = $this->Users->get($id);
        if ($this->Users->delete($user)) {
            $this->Flash->success("L'utilisateur a été supprimé.", ['params' => ['auto-dismiss' => 5000]]);
        } else {
            $this->Flash->error("L'utilisateur n'a pas pu être supprimé. Merci d'essayer à nouveau.");
        }

        return $this->redirect(['action' => 'index']);
    }
}
