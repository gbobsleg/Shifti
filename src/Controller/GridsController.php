<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\PlanningDayHistoryService;
use Cake\Event\EventInterface;
use Cake\Log\Log;
use Cake\I18n\FrozenTime;
use DateTime;
use DateTimeInterface;
use Exception;
use Throwable;

class GridsController extends AppController
{
    /**
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Groom');
    }

    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);

        // Désactive le composant Ajax.Ajax uniquement pour les endpoints JSON purs
        // afin d'éviter qu'il force AjaxView et modifie le payload.
        if (in_array($this->request->getParam('action'), ['dayHistory', 'restoreDayHistory'], true)) {
            if ($this->components()->has('Ajax')) {
                $this->components()->unload('Ajax');
            }
        }

        // Déverrouille SecurityComponent pour le POST AJAX de restauration (sinon 403)
        if ($this->request->getParam('action') === 'restoreDayHistory') {
            if ($this->components()->has('Security')) {
                $this->Security->setConfig('unlockedActions', ['restoreDayHistory']);
            }
        }
    }

    /**
     * @return void
     */
    public function plannedSeries()
    {
        $this->Authorization->authorize(new \App\Resource\GridsResource(), 'plannedSeries');
        $this->request->allowMethod(['get']);
        $offerId = (int)$this->request->getQuery('offer_id');
        $dateStr = (string)$this->request->getQuery('date'); // YYYY-MM-DD

        if ($offerId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            $this->viewBuilder()->setClassName('Json');
            $this->set(['success' => false, 'series' => null]);
            $this->viewBuilder()->setOption('serialize', ['success', 'series']);

            return;
        }

        $WfmSettings = $this->fetchTable('WfmSettings')->find()->first();
        $startTime = $this->normalizeTime((string)$WfmSettings->day_start_time, '09:00:00');
        $endTime = $this->normalizeTime((string)$WfmSettings->day_end_time, '17:00:00');

        $start = new FrozenTime($dateStr . ' ' . $startTime);
        $end = new FrozenTime($dateStr . ' ' . $endTime);

        // Init slots
        $data = [];
        for ($t = $start; $t->getTimestamp() < $end->getTimestamp(); $t = $t->addMinutes(15)) {
            $data[$t->format('H:i')] = 0;
        }

        $Ranges = $this->fetchTable('Ranges');
        $rows = $Ranges->find()
            ->where([
                'offer_id' => $offerId,
                'date_start <' => $end,
                'date_end >' => $start,
            ])
            ->select(['date_start', 'date_end'])
            ->all();

        foreach ($rows as $r) {
            $rs = $r->date_start instanceof DateTimeInterface ? new FrozenTime($r->date_start) : new FrozenTime((string)$r->date_start);
            $re = $r->date_end instanceof DateTimeInterface ? new FrozenTime($r->date_end) : new FrozenTime((string)$r->date_end);

            if ($rs->getTimestamp() < $start->getTimestamp()) {
                $rs = $start;
            }
            if ($re->getTimestamp() > $end->getTimestamp()) {
                $re = $end;
            }

            // Arrondis aux quarts d'heure (début: ceil, fin: floor) puis intervalle [start, end)
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

        $this->viewBuilder()->setClassName('Json');
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
     * Retourne la liste des utilisateurs filtrés par site (pour AJAX)
     *
     * @return void
     */
    public function getUsersBySite()
    {
        $this->request->allowMethod(['get']);
        
        $siteId = $this->request->getQuery('site_id');
        $Users = $this->fetchTable('Users');
        
        $query = $Users->find('list', [
            'keyField' => 'id',
            'valueField' => function ($row) {
                return $row['last_name'] . ' ' . $row['first_name'];
            },
        ]);
        
        // Filtrer par site si un site est spécifié
        if (!empty($siteId)) {
            $query->where(['Users.site_id' => $siteId]);
        }
        
        $users = $query->order(['last_name' => 'ASC'])->toArray();
        
        // Convertir en format simple pour JSON
        $result = [];
        foreach ($users as $id => $name) {
            $result[] = ['id' => $id, 'name' => $name];
        }
        
        $this->viewBuilder()->setClassName('Json');
        $this->set(['success' => true, 'users' => $result]);
        $this->viewBuilder()->setOption('serialize', ['success', 'users']);
    }

    /**
     * @param mixed $t
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
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        // Chargement des tables
        $this->Users = $this->fetchTable('Users');
        $this->Offers = $this->fetchTable('Offers');
        $this->Alerts = $this->fetchTable('Alerts');
        $this->Ranges = $this->fetchTable('Ranges');
        $this->Sites = $this->fetchTable('Sites');
        $displaySettingsTable = $this->fetchTable('DisplaySettings');
        
        // Charger les paramètres d'affichage de la grille
        $gridStartHour = $displaySettingsTable->getValue('grid_start_hour', 8);
        $gridEndHour = $displaySettingsTable->getValue('grid_end_hour', 18);

        $params = $this->request->getQueryParams();

        // --- LOGIQUE DE REDIRECTION INITIALE ---
        if (empty($params['date_start'])) {
            return $this->redirect([
                'controller' => 'Grids',
                'action' => 'index',
                '?' => ['date_start' => date('d/m/Y')],
            ]);
        }

        // --- LOGIQUE DE GESTION DES DATES ---
        $dateStart = FrozenTime::createFromFormat('d/m/Y', (string)$params['date_start']);
        if ($dateStart) {
            $params['date_start'] = $dateStart;
        } else {
            $this->Flash->error('Format de date de début invalide.');
            $dateStart = new DateTime(); // Date par défaut
            $params['date_start'] = $dateStart;
        }

        $dateEnd = null;
        if (!empty($params['date_end'])) {
            $dateEnd = FrozenTime::createFromFormat('d/m/Y', (string)$params['date_end']);
            if ($dateEnd) {
                $params['date_end'] = $dateEnd;
                $day_ranges = $this->Groom->findBeginEndDay($dateStart, $dateEnd);
            } else {
                $this->Flash->error('Format de date de fin invalide.');
                $day_ranges = $this->Groom->findBeginEndDay($dateStart);
            }
        } else {
            $day_ranges = $this->Groom->findBeginEndDay($dateStart);
        }

        // --- LOGIQUE DE TRI ---
        // Par défaut: trier par site (alphabétique) puis par nom (alphabétique)
        $sortBy = (string)$this->request->getQuery('sort_by', 'site_name');
        $orderMap = [
            'site_name' => [
                'Sites.name' => 'ASC',
                'Users.last_name' => 'ASC',
                'Users.first_name' => 'ASC',
            ],
            'last_name' => [
                'Users.last_name' => 'ASC',
                'Users.first_name' => 'ASC',
            ],
            'site_id' => [
                'Users.site_id' => 'ASC',
                'Users.last_name' => 'ASC',
            ],
            'user_code' => [
                'Users.user_code' => 'ASC',
            ],
        ];
        $order = $orderMap[$sortBy] ?? $orderMap['site_name'];

        // --- CHARGEMENT DES DONNÉES ---
        // Charger les listes complètes pour les boucles foreach en haut de index.php
        $offers_list = $this->Offers->find('DisplayedInGrid');
        $users_list = $this->Users->find();
        $sites_list = $this->Sites->find();
        $alerts_list = $this->Alerts->find('ThisDay', $day_ranges);
        // Filtrer les alertes pour les utilisateurs simples (priority = 3)
        $identity = $this->request->getAttribute('identity');
        $roleId = null;
        if ($identity && method_exists($identity, 'get')) {
            $roleId = $identity->get('role_id');
        }
        if (!$roleId && $identity && method_exists($identity, 'getOriginalData')) {
            $orig = $identity->getOriginalData();
            if (is_object($orig) && isset($orig->role_id)) {
                $roleId = $orig->role_id;
            }
        }
        $roleId = (int)($roleId ?? 0);
        if ($roleId === 3) {
            $alerts_list->where(['priority' => 3]);
        }

        // Requête principale pour les ranges affichés dans le planning
        $users_ranges_query = $this->Users->find('ThisDay', compact('params', 'day_ranges'));

        // Appliquer l'ordre et exécuter (ou passer la query si toArray() n'est pas nécessaire ici)
        $users_ranges = $users_ranges_query
            ->contain(['Sites', 'Roles', 'UserAvailabilities', 'Ranges.Offers', 'UserRemoteWorkSetting', 'UserContracts'])
            ->leftJoinWith('Sites') // Assure la jointure pour ORDER BY Sites.name
            ->order($order);
        // ->toArray(); // Décommentez si vous avez besoin d'un tableau ici

        // Map des publications: date (Y-m-d) => scenario_id
        $pubsTable = $this->fetchTable('ForecastScenarioPublications');
        $publishedByDate = [];
        $scanDay = clone $day_ranges['begin'];
        $scanEnd = clone $day_ranges['end'];
        while ($scanDay <= $scanEnd) {
            $dateKey = $scanDay->i18nFormat('yyyy-MM-dd');
            $pub = $pubsTable->find()->select(['scenario_id'])->where(['date' => $dateKey])->first();
            if ($pub) {
                $publishedByDate[$dateKey] = (int)$pub->scenario_id;
            }
            $scanDay = $scanDay->addDays(1);
        }

        // Transmettre les variables à la vue
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
        ));
    }

    /**
     * Méthode ADD modifiée pour gérer AJAX
     *
     * @return \Cake\Http\Response|null
     */
    public function add()
    {
        $this->Authorization->authorize(new \App\Resource\GridsResource(), 'add');
        $this->Ranges = $this->fetchTable('Ranges');

        // Initialisation pour éviter les erreurs si ce n'est pas un POST
        $messages = [];
        $responseStatus = 'error';
        $day_ranges = []; // Pour la redirection fallback
        $savedCount = 0;
        $deletedCount = 0;

        if ($this->request->is('post')) {
            $array = $this->request->getData();

            // 1. TRAITEMENT DU CHAMP JSON
            $json_data = $array['planning_data'] ?? null;
            if (empty($json_data) || $json_data === '[]') { // Gère aussi le cas où aucune modif n'est envoyée
                $messages[] = ['message' => __('Aucune modification de planning à enregistrer.'), 'element' => 'flash/info'];
                $responseStatus = 'info'; // Statut pour indiquer qu'il n'y avait rien à faire
                goto handle_response;
            }

            $rangesFromJSON = json_decode($json_data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $messages[] = ['message' => __('Erreur de décodage des données de planning (JSON invalide).'), 'element' => 'flash/error'];
                goto handle_response;
            }

            // 2. GESTION DES DATES (essentiel pour redirection)
            $day_ranges_strings = $array['day_ranges'] ?? [];
            if (!empty($day_ranges_strings['begin'])) {
                $day_ranges['begin'] = DateTime::createFromFormat('d/m/Y H:i', $day_ranges_strings['begin']);
            }
            if (!empty($day_ranges_strings['end'])) {
                $day_ranges['end'] = DateTime::createFromFormat('d/m/Y H:i', $day_ranges_strings['end']);
            }
            if (empty($day_ranges['begin'])) {
                $messages[] = ['message' => __('Erreur: La date de début est manquante.'), 'element' => 'flash/error'];
                goto handle_response;
            }
            // Assurer une date de fin pour la redirection si elle manque
            if (empty($day_ranges['end'])) {
                $day_ranges['end'] = clone $day_ranges['begin']; // Utilise la même date si fin absente
            }

            // --- DEBUT LOGIQUE DE SCISSION V2 ---
            // (Votre logique V2 de traitement des ranges, concaténation, comparaison BDD...)
            // ...
            // Supposons que cette logique aboutisse à :
            // $finalRangesToSave (tableau des entités/données à insérer/màj)
            // $uniqueIdsToDelete (tableau des IDs BDD à supprimer)
            // ... (copiez ici votre logique V2 depuis l'étape 3 jusqu'à 7 inclus) ...

            // 3. Concaténer les actions brutes du JSON (adapté de votre code)
            $rangesByUser = [];
            foreach ($rangesFromJSON as $range) {
                if (!isset($range['user_id'])) {
                    continue;
                } $rangesByUser[$range['user_id']][] = $range;
            }
            $actionRanges = [];
            foreach ($rangesByUser as $ranges) {
                usort($ranges, fn($a, $b) => strcmp($a['date_start'], $b['date_start']));
                $currentRange = array_shift($ranges);
                if (!$currentRange) {
                    continue;
                }
                foreach ($ranges as $nextRange) {
                    if ($currentRange['date_end'] == $nextRange['date_start'] && $currentRange['user_id'] == $nextRange['user_id'] && $currentRange['offer_id'] == $nextRange['offer_id']) {
                        $currentRange['date_end'] = $nextRange['date_end'];
                        if (!empty($nextRange['id'])) {
                            $currentRange['id'] = $nextRange['id'];
                        }
                    } else {
                        $actionRanges[] = $currentRange;
                        $currentRange = $nextRange;
                    }
                } $actionRanges[] = $currentRange;
            }

            // 4. Déterminer zone de travail
            $affectedUserIds = [];
            $minStart = null;
            $maxEnd = null;
            if (empty($actionRanges)) {
                $messages[] = ['message' => __('Aucune modification valide trouvée après traitement.'), 'element' => 'flash/info'];
                $responseStatus = 'info';
                goto handle_response;
            }
            foreach ($actionRanges as $action) {
                $affectedUserIds[] = $action['user_id'];
                $start = new FrozenTime($action['date_start']);
                $end = new FrozenTime($action['date_end']);
                if ($minStart === null || $start < $minStart) {
                    $minStart = $start;
                } if ($maxEnd === null || $end > $maxEnd) {
                    $maxEnd = $end;
                }
            } $affectedUserIds = array_unique($affectedUserIds);

            // 5. Charger état initial BDD
            $initialDBRanges = $this->Ranges->find()->where(['user_id IN' => $affectedUserIds, 'date_end >' => $minStart, 'date_start <' => $maxEnd])->all()->toList();
            $finalIdsToDelete = [];
            $workingRanges = [];
            foreach ($initialDBRanges as $dbRange) {
                $finalIdsToDelete[] = $dbRange->id;
                $workingRanges[] = ['user_id' => $dbRange->user_id, 'offer_id' => $dbRange->offer_id, 'date_start' => $dbRange->date_start, 'date_end' => $dbRange->date_end, 'comment' => $dbRange->comment];
            }

            // 6. Appliquer actions
            foreach ($actionRanges as $actionRange) {
                $actionStart = new FrozenTime($actionRange['date_start']);
                $actionEnd = new FrozenTime($actionRange['date_end']);
                $actionOfferId = $actionRange['offer_id'];
                $actionUserId = $actionRange['user_id'];
                $isDeletion = ($actionOfferId == '0');
                $nextWorkingRanges = [];
                foreach ($workingRanges as $currentRange) {
                    $currentStart = $currentRange['date_start'] instanceof DateTimeInterface ? $currentRange['date_start'] : new FrozenTime($currentRange['date_start']);
                    $currentEnd = $currentRange['date_end'] instanceof DateTimeInterface ? $currentRange['date_end'] : new FrozenTime($currentRange['date_end']);
                    if ($currentRange['user_id'] != $actionUserId || $currentEnd <= $actionStart || $currentStart >= $actionEnd) {
                        $nextWorkingRanges[] = $currentRange;
                        continue;
                    }
                    if ($currentStart < $actionStart) {
                        $nextWorkingRanges[] = ['user_id' => $currentRange['user_id'], 'offer_id' => $currentRange['offer_id'], 'date_start' => $currentStart, 'date_end' => $actionStart, 'comment' => $currentRange['comment']];
                    }
                    if ($currentEnd > $actionEnd) {
                        $nextWorkingRanges[] = ['user_id' => $currentRange['user_id'], 'offer_id' => $currentRange['offer_id'], 'date_start' => $actionEnd, 'date_end' => $currentEnd, 'comment' => $currentRange['comment']];
                    }
                }
                if (!$isDeletion) {
                    unset($actionRange['id']);
                    $actionRange['date_start'] = $actionStart;
                    $actionRange['date_end'] = $actionEnd;
                    $nextWorkingRanges[] = $actionRange;
                }
                $workingRanges = $nextWorkingRanges;
            }

            // 7. Re-concaténer résultat final
            usort($workingRanges, function ($a, $b) {
                if ($a['user_id'] != $b['user_id']) {
                    return $a['user_id'] <=> $b['user_id'];
                } $aStart = $a['date_start'] instanceof DateTimeInterface ? $a['date_start'] : new FrozenTime($a['date_start']);
                $bStart = $b['date_start'] instanceof DateTimeInterface ? $b['date_start'] : new FrozenTime($b['date_start']);

                return $aStart <=> $bStart;
            });
            $finalRangesToSave = [];
            if (!empty($workingRanges)) {
                $currentSaveRange = array_shift($workingRanges);
                foreach ($workingRanges as $nextRange) {
                    $currentEnd = $currentSaveRange['date_end'] instanceof DateTimeInterface ? $currentSaveRange['date_end'] : new FrozenTime($currentSaveRange['date_end']);
                    $nextStart = $nextRange['date_start'] instanceof DateTimeInterface ? $nextRange['date_start'] : new FrozenTime($nextRange['date_start']);
                    if ($currentSaveRange['user_id'] == $nextRange['user_id'] && $currentSaveRange['offer_id'] == $nextRange['offer_id'] && $currentEnd->getTimestamp() == $nextStart->getTimestamp()) {
                            $currentSaveRange['date_end'] = $nextRange['date_end'];
                    } else {
                                $finalRangesToSave[] = $currentSaveRange;
                                $currentSaveRange = $nextRange;
                    }
                } $finalRangesToSave[] = $currentSaveRange;
            }

            // 8. LOGIQUE DE SAUVEGARDE TRANSACTIONNELLE
            $validationErrors = [];
            $uniqueIdsToDelete = array_unique($finalIdsToDelete);

            if (empty($finalRangesToSave) && empty($uniqueIdsToDelete)) {
                $messages[] = ['message' => __('Aucune modification de planning n\'a nécessité de sauvegarde après traitement.'), 'element' => 'flash/info'];
                $responseStatus = 'info';
                goto handle_response;
            }

            $entitiesToSave = $this->Ranges->newEntities($finalRangesToSave);

            try {
                $this->Ranges->getConnection()->transactional(
                    function () use ($entitiesToSave, $uniqueIdsToDelete, &$savedCount, &$deletedCount) {
                        if (!empty($uniqueIdsToDelete)) {
                            $deletedCount = $this->Ranges->deleteAll(['id IN' => $uniqueIdsToDelete]);
                        }
                        if (!empty($entitiesToSave)) {
                            if (!$this->Ranges->saveMany($entitiesToSave)) {
                                $errors = [];
                                foreach ($entitiesToSave as $record) {
                                    if ($record->hasErrors()) {
                                        $errors[] = ['data' => $record->toArray(), 'errors' => $record->getErrors()];
                                    }
                                }
                                throw new Exception(json_encode($errors));
                            } $savedCount = count($entitiesToSave);
                        }
                    },
                );
                // Succès de la transaction
                $messages[] = ['message' => __('Le planning a été sauvegardé. %d nouveaux créneaux créés, %d anciennes plages remplacées.', $savedCount, $deletedCount), 'element' => 'flash/success'];
                $responseStatus = 'success';

                // Historique agent×jour : uniquement après succès de la sauvegarde ranges
                $historyDays = [];
                foreach ($actionRanges as $action) {
                    if (empty($action['date_start'])) {
                        continue;
                    }
                    $historyDays[] = (new FrozenTime($action['date_start']))->format('Y-m-d');
                }
                $identity = $this->request->getAttribute('identity');
                $actorUserId = null;
                if ($identity) {
                    if (method_exists($identity, 'getIdentifier')) {
                        $actorUserId = (int)$identity->getIdentifier();
                    } elseif (method_exists($identity, 'get')) {
                        $actorUserId = (int)$identity->get('id');
                    }
                }
                if ($actorUserId !== null && $actorUserId <= 0) {
                    $actorUserId = null;
                }
                try {
                    (new PlanningDayHistoryService())->recordAffectedUsers(
                        array_values(array_map('intval', $affectedUserIds)),
                        array_values(array_unique($historyDays)),
                        PlanningDayHistoryService::SOURCE_MANUAL,
                        $actorUserId,
                    );
                } catch (Throwable $historyError) {
                    Log::error('PlanningDayHistory (manual) échoué: ' . $historyError->getMessage());
                }
            } catch (Exception $e) {
                // Échec de la transaction
                $validationErrors = json_decode($e->getMessage(), true) ?? $e->getMessage();
                $messages[] = ['message' => __('Échec de la sauvegarde. Erreurs : ') . json_encode($validationErrors), 'element' => 'flash/error'];
                // $responseStatus reste 'error' (valeur par défaut)
                goto handle_response; // Important d'aller à la réponse ici
            }
            // --- FIN LOGIQUE V2 ---
        } // Fin if ($this->request->is('post'))

        handle_response: // Label pour gérer la réponse finale

        // --- GESTION DE LA RÉPONSE (AJAX ou Standard) ---
        if ($this->request->is('ajax') && $this->request->is('post')) {
            // *** RÉPONSE AJAX ***
            $this->autoRender = false; // Ne pas rendre la vue .php
            $this->response = $this->response->withType('application/json');
            $responseData = [
                'status' => $responseStatus, // success, error, ou info
                '_message' => $messages, // Le tableau de messages pour le JS
            ];
            // Ajouter des données supplémentaires si nécessaire (ex: ID créés)
            // if ($responseStatus === 'success') {
            //    $responseData['new_ids'] = $ids_des_nouvelles_ranges;
            // }
            $this->response = $this->response->withStringBody(json_encode($responseData));

            return $this->response;
        } elseif ($this->request->is('post')) {
            // *** RÉPONSE POST STANDARD ***
            // Afficher les messages Flash accumulés
            foreach ($messages as $msg) {
                $flashMethod = 'info'; // Default
                if ($msg['element'] === 'flash/error') {
                    $flashMethod = 'error';
                }
                if ($msg['element'] === 'flash/success') {
                    $flashMethod = 'success';
                }
                $this->Flash->$flashMethod($msg['message']);
            }
            // Rediriger vers l'index avec les bonnes dates
            // S'assure que $day_ranges['begin'] et ['end'] sont des objets DateTime valides
            $dateStartRedirect = $day_ranges['begin'] instanceof DateTimeInterface ? $day_ranges['begin']->i18nFormat('dd/MM/yyyy') : date('d/m/Y');
            $dateEndRedirect = $day_ranges['end'] instanceof DateTimeInterface ? $day_ranges['end']->i18nFormat('dd/MM/yyyy') : $dateStartRedirect; // Fallback

            return $this->redirect([
                'action' => 'index',
                '?' => ['date_start' => $dateStartRedirect, 'date_end' => $dateEndRedirect],
            ]);
        }

        // Si ce n'était pas un POST (ou erreur avant traitement des dates), rediriger vers l'index simple
        // Peut-être afficher un message d'erreur si des messages existent déjà ?
        if (!empty($messages)) {
            foreach ($messages as $msg) {
                $this->Flash->error($msg['message']);
            }
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Liste JSON de l'historique planning publié pour un agent × jour.
     */
    public function dayHistory()
    {
        $this->Authorization->authorize(new \App\Resource\GridsResource(), 'dayHistory');
        $this->request->allowMethod(['get']);

        $userId = (int)$this->request->getQuery('user_id');
        $day = (string)$this->request->getQuery('day');

        $this->viewBuilder()->setClassName('Json');

        if ($userId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            $this->set([
                'success' => false,
                'message' => 'Paramètres invalides (user_id, day=YYYY-MM-DD requis).',
                'versions' => [],
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message', 'versions']);

            return;
        }

        // Récupérer les horaires d'ouverture pour dynamiser les mini-barres
        $wfmSettings = $this->fetchTable('WfmSettings')->find()->first();
        $startTime = 0.0;
        $endTime = 24.0;
        if ($wfmSettings) {
            if (!empty($wfmSettings->day_start_time)) {
                $startTime = $this->parseTimeToFloat((string)$wfmSettings->day_start_time);
            }
            if (!empty($wfmSettings->day_end_time)) {
                $endTime = $this->parseTimeToFloat((string)$wfmSettings->day_end_time);
            }
        }

        $Histories = $this->fetchTable('PlanningDayHistories');
        $rows = $Histories->find()
            ->contain(['ActorUsers'])
            ->where([
                'PlanningDayHistories.user_id' => $userId,
                'PlanningDayHistories.day' => $day,
            ])
            ->orderBy([
                'PlanningDayHistories.created' => 'DESC',
                'PlanningDayHistories.id' => 'DESC',
            ])
            ->all();

        $versions = [];
        foreach ($rows as $row) {
            $actor = $row->actor_user ?? null;
            $actorName = null;
            if ($actor) {
                $first = trim((string)($actor->first_name ?? ''));
                $last = trim((string)($actor->last_name ?? ''));
                $actorName = trim($first . ' ' . $last);
                if ($actorName === '') {
                    $actorName = (string)($actor->email ?? ('#' . (int)$actor->id));
                }
            }

            $created = $row->created;
            $versions[] = [
                'id' => (int)$row->id,
                'created' => $created instanceof DateTimeInterface
                    ? $created->format('Y-m-d H:i:s')
                    : ($created !== null ? (string)$created : null),
                'source' => (string)$row->source,
                'actor_user_id' => $row->actor_user_id !== null ? (int)$row->actor_user_id : null,
                'actor_name' => $actorName,
                'snapshot' => is_array($row->snapshot) ? $row->snapshot : [],
            ];
        }

        $this->set([
            'success' => true,
            'user_id' => $userId,
            'day' => $day,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'versions' => $versions,
        ]);
        $this->viewBuilder()->setOption('serialize', ['success', 'user_id', 'day', 'start_time', 'end_time', 'versions']);
    }

    /**
     * Convertit une chaîne horaire "HH:MM" en float (ex: "08:30" -> 8.5)
     */
    private function parseTimeToFloat(string $time): float
    {
        $parts = explode(':', trim($time));
        $hours = isset($parts[0]) ? (int)$parts[0] : 0;
        $minutes = isset($parts[1]) ? (int)$parts[1] : 0;

        return $hours + ($minutes / 60);
    }

    /**
     * Restaure une version d'historique (JSON).
     */
    public function restoreDayHistory()
    {
        $this->Authorization->authorize(new \App\Resource\GridsResource(), 'restoreDayHistory');
        $this->request->allowMethod(['post']);

        $historyId = (int)($this->request->getData('history_id') ?? $this->request->getQuery('history_id') ?? 0);

        $this->viewBuilder()->setClassName('Json');

        if ($historyId <= 0) {
            $this->set([
                'success' => false,
                'message' => 'Paramètre history_id invalide.',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);

            return;
        }

        $identity = $this->request->getAttribute('identity');
        $actorUserId = null;
        if ($identity) {
            if (method_exists($identity, 'getIdentifier')) {
                $actorUserId = (int)$identity->getIdentifier();
            } elseif (method_exists($identity, 'get')) {
                $actorUserId = (int)$identity->get('id');
            } elseif (method_exists($identity, 'getOriginalData')) {
                $orig = $identity->getOriginalData();
                if (is_object($orig) && isset($orig->id)) {
                    $actorUserId = (int)$orig->id;
                }
            }
        }

        if ($actorUserId === null || $actorUserId <= 0) {
            $this->set([
                'success' => false,
                'message' => 'Utilisateur non identifié.'
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);
            return;
        }

        try {
            (new PlanningDayHistoryService())->restore($historyId, $actorUserId);

            $this->set([
                'success' => true,
                'message' => 'Version restaurée.',
                'history_id' => $historyId,
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message', 'history_id']);
        } catch (Throwable $e) {
            Log::error('PlanningDayHistory restore échoué: ' . $e->getMessage());

            $this->set([
                'success' => false,
                'message' => 'Échec de la restauration.',
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);
        }
    }

}
