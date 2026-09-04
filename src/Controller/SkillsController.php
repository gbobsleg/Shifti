<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Skills Controller
 *
 * @property \App\Model\Table\SkillsTable $Skills
 */
class SkillsController extends AppController
{
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $this->Authorization->authorize(new \App\Resource\SkillsResource(), 'index');
        
        $query = $this->Skills->find()->contain(['Users', 'Offers']);
        $params = $this->request->getQueryParams();

        // Filtre par utilisateur
        if (!empty($params['user_id'])) {
            $query->where(['Skills.user_id' => $params['user_id']]);
        }

        // Filtre par offre/compétence
        if (!empty($params['offer_id'])) {
            $query->where(['Skills.offer_id' => $params['offer_id']]);
        }

        // Filtre par date de validité début
        if (!empty($params['validity_start'])) {
            $validityStart = $params['validity_start'];
            if (is_array($validityStart) && !empty($validityStart['year']) && !empty($validityStart['month']) && !empty($validityStart['day'])) {
                $dateString = sprintf('%04d-%02d-%02d', $validityStart['year'], $validityStart['month'], $validityStart['day']);
                $query->where(['Skills.validity_start >=' => $dateString]);
            }
        }

        // Filtre par date de validité fin
        if (!empty($params['validity_end'])) {
            $validityEnd = $params['validity_end'];
            if (is_array($validityEnd) && !empty($validityEnd['year']) && !empty($validityEnd['month']) && !empty($validityEnd['day'])) {
                $dateString = sprintf('%04d-%02d-%02d', $validityEnd['year'], $validityEnd['month'], $validityEnd['day']);
                $query->where(['Skills.validity_end <=' => $dateString]);
            }
        }

        // Pagination normale
        $this->paginate = ['limit' => 25, 'order' => ['Skills.id' => 'desc']];
        $skills = $this->paginate($query);

        // Données pour le formulaire de recherche
        $users = $this->Skills->Users->find('list', [
            'keyField' => 'id',
            'valueField' => function ($user) {
                return $user->last_name . ' ' . $user->first_name;
            },
            'limit' => 200
        ])->order(['Users.last_name' => 'ASC', 'Users.first_name' => 'ASC'])->toArray();
        
        $offers = $this->Skills->Offers->find('list', ['limit' => 200, 'order' => ['name' => 'ASC']])->toArray();

        $this->set(compact('skills', 'users', 'offers'));
    }

    /**
     * View method
     *
     * @param string|null $id Skill id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\SkillsResource(), 'view');
        $skill = $this->Skills->get($id, [
            'contain' => ['Users', 'Offers']
        ]);
        $this->set(compact('skill'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $this->Authorization->authorize(new \App\Resource\SkillsResource(), 'add');
        $skill = $this->Skills->newEmptyEntity();
        if ($this->request->is('post')) {
            $skill = $this->Skills->patchEntity($skill, $this->request->getData());
            if ($this->Skills->save($skill)) {
                $this->Flash->success("La compétence a été sauvegardée.");

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error("La compétence n'a pas pu être sauvegardée. Merci d'essayer à nouveau.");
        }
        $users = $this->Skills->Users->find('list', [
            'keyField' => 'id',
            'valueField' => function ($user) {
                return $user->last_name . ' ' . $user->first_name;
            },
            'limit' => 200
        ])->order(['Users.last_name' => 'ASC', 'Users.first_name' => 'ASC']);
        $offers = $this->Skills->Offers->find('list', ['limit' => 200, 'order' => ['name' => 'ASC']]);
        $this->set(compact('skill', 'users', 'offers'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Skill id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\SkillsResource(), 'edit');
        $skill = $this->Skills->get($id, [
            'contain' => []
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $skill = $this->Skills->patchEntity($skill, $this->request->getData());
            if ($this->Skills->save($skill)) {
                $this->Flash->success("La compétence a été sauvegardée.");

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error("La compétence n'a pas pu être sauvegardée. Merci d'essayer à nouveau.");
        }
        $users = $this->Skills->Users->find('list', [
            'keyField' => 'id',
            'valueField' => function ($user) {
                return $user->last_name . ' ' . $user->first_name;
            },
            'limit' => 200
        ])->order(['Users.last_name' => 'ASC', 'Users.first_name' => 'ASC']);
        $offers = $this->Skills->Offers->find('list', ['limit' => 200, 'order' => ['name' => 'ASC']]);
        $this->set(compact('skill', 'users', 'offers'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Skill id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $this->Authorization->authorize(new \App\Resource\SkillsResource(), 'delete');
        $skill = $this->Skills->get($id);
        if ($this->Skills->delete($skill)) {
            $this->Flash->success("La compétence a été supprimée.");
        } else {
            $this->Flash->error("La compétence n'a pas pu être supprimée. Merci d'essayer à nouveau.");
        }

        return $this->redirect(['action' => 'index']);
    }
}
