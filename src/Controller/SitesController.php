<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Sites Controller
 *
 * @property \App\Model\Table\SitesTable $Sites
 * @method \App\Model\Entity\Site[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class SitesController extends AppController
{
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $this->Authorization->authorize(new \App\Resource\SitesResource(), 'index');
        $query = $this->Sites->find()->contain(['Regions', 'Users']);
        $sites = $this->paginate($query);

        // Statistiques
        $allSites = $this->Sites->find('all')->contain(['Regions', 'Users']);
        $stats = [
            'total' => $allSites->count(),
            'total_users' => 0,
            'by_region' => []
        ];
        
        foreach ($allSites as $site) {
            $stats['total_users'] += count($site->users);
            
            $regionName = $site->region ? $site->region->name : 'Sans région';
            if (!isset($stats['by_region'][$regionName])) {
                $stats['by_region'][$regionName] = 0;
            }
            $stats['by_region'][$regionName]++;
        }
        
        // Top 3 régions
        arsort($stats['by_region']);
        $stats['top_regions'] = array_slice($stats['by_region'], 0, 3, true);

        $this->set(compact('sites', 'stats'));
    }

    /**
     * View method
     *
     * @param string|null $id Site id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\SitesResource(), 'view');
        $site = $this->Sites->get($id, [
            'contain' => ['Regions', 'Users'],
        ]);

        // Liste des utilisateurs disponibles (non rattachés à ce site)
        $availableUsers = $this->Sites->Users->find('list', [
            'keyField' => 'id',
            'valueField' => function ($user) {
                return $user->last_name . ' ' . $user->first_name . ' (' . $user->user_code . ')';
            },
        ])
            ->where(['Users.site_id !=' => $id])
            ->order(['Users.last_name' => 'ASC', 'Users.first_name' => 'ASC']);

        $this->set(compact('site', 'availableUsers'));
    }

    /**
     * Assign a user to this site
     *
     * @param string|null $id Site id.
     * @return \Cake\Http\Response|null|void Redirects to view.
     */
    public function assignUser($id = null)
    {
        $this->request->allowMethod(['post']);
        $this->Authorization->authorize(new \App\Resource\SitesResource(), 'edit');

        $userId = $this->request->getData('user_id');
        if (empty($userId)) {
            $this->Flash->error("Aucun utilisateur sélectionné.");
            return $this->redirect(['action' => 'view', $id]);
        }

        $user = $this->Sites->Users->get($userId);
        $user->site_id = (int)$id;

        if ($this->Sites->Users->save($user)) {
            $this->Flash->success("L'utilisateur a été rattaché au site.");
        } else {
            $this->Flash->error("Impossible de rattacher l'utilisateur.");
        }

        return $this->redirect(['action' => 'view', $id]);
    }

    /**
     * Remove a user from this site (move to another site or unassign)
     *
     * @param string|null $id Site id.
     * @param string|null $userId User id.
     * @return \Cake\Http\Response|null|void Redirects to view.
     */
    public function removeUser($id = null, $userId = null)
    {
        $this->request->allowMethod(['post']);
        $this->Authorization->authorize(new \App\Resource\SitesResource(), 'edit');

        // Récupérer le premier autre site pour y déplacer l'utilisateur
        // (car site_id est NOT NULL avec joinType INNER)
        $otherSite = $this->Sites->find()
            ->where(['Sites.id !=' => $id])
            ->first();

        if (!$otherSite) {
            $this->Flash->error("Impossible de retirer l'utilisateur : aucun autre site disponible.");
            return $this->redirect(['action' => 'view', $id]);
        }

        $user = $this->Sites->Users->get($userId);
        $user->site_id = $otherSite->id;

        if ($this->Sites->Users->save($user)) {
            $this->Flash->warning("L'utilisateur a été déplacé vers le site \"{$otherSite->name}\".");
        } else {
            $this->Flash->error("Impossible de retirer l'utilisateur.");
        }

        return $this->redirect(['action' => 'view', $id]);
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $this->Authorization->authorize(new \App\Resource\SitesResource(), 'add');
        $site = $this->Sites->newEmptyEntity();
        if ($this->request->is('post')) {
            $site = $this->Sites->patchEntity($site, $this->request->getData());
            if ($this->Sites->save($site)) {
                $this->Flash->success("Le site a été sauvegardé.");

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error("Le site n'a pas pu être sauvegardé. Merci d'essayer à nouveau.");
        }
        $regions = $this->Sites->Regions->find('list', ['limit' => 200]);
        $this->set(compact('site', 'regions'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Site id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\SitesResource(), 'edit');
        $site = $this->Sites->get($id, [
            'contain' => [],
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $site = $this->Sites->patchEntity($site, $this->request->getData());
            if ($this->Sites->save($site)) {
                $this->Flash->success("Le site a été sauvegardé.");

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error("Le site n'a pas pu être sauvegardé. Merci d'essayer à nouveau.");
        }
        $regions = $this->Sites->Regions->find('list', ['limit' => 200]);
        $this->set(compact('site', 'regions'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Site id.
     * @return \Cake\Http\Response|null|void Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $this->Authorization->authorize(new \App\Resource\SitesResource(), 'delete');
        $site = $this->Sites->get($id);
        if ($this->Sites->delete($site)) {
            $this->Flash->success("Le site a été supprimé.");
        } else {
            $this->Flash->error("Le site n'a pas pu être supprimé. Merci d'essayer à nouveau.");
        }

        return $this->redirect(['action' => 'index']);
    }
}
