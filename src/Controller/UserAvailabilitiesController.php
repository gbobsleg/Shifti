<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * UserAvailabilities Controller
 *
 * @property \App\Model\Table\UserAvailabilitiesTable $UserAvailabilities
 */
class UserAvailabilitiesController extends AppController
{
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $this->Authorization->authorize(new \App\Resource\UserAvailabilitiesResource(), 'index');
        
        $params = $this->request->getQueryParams();
        
        $query = $this->UserAvailabilities->find()
            ->contain(['Users']);
        
        // Filtre par utilisateur
        if (!empty($params['user_id'])) {
            $query->where(['UserAvailabilities.user_id' => $params['user_id']]);
        }
        
        // Filtre par jour de la semaine
        if (!empty($params['day_of_week'])) {
            $query->where(['UserAvailabilities.day_of_week' => $params['day_of_week']]);
        }
        
        $userAvailabilities = $this->paginate($query);

        // Liste des utilisateurs pour les filtres
        $users = $this->UserAvailabilities->Users->find('list', [
            'keyField' => 'id',
            'valueField' => function ($row) {
                return $row['last_name'] . ' ' . $row['first_name'];
            },
            'order' => ['Users.last_name' => 'ASC']
        ])->toArray();

        $this->set(compact('userAvailabilities', 'users'));
    }

    /**
     * View method
     *
     * @param string|null $id User Availability id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\UserAvailabilitiesResource(), 'view');
        $userAvailability = $this->UserAvailabilities->get($id, contain: ['Users']);
        $this->set(compact('userAvailability'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $this->Authorization->authorize(new \App\Resource\UserAvailabilitiesResource(), 'add');
        $userAvailability = $this->UserAvailabilities->newEmptyEntity();
        if ($this->request->is('post')) {
            $userAvailability = $this->UserAvailabilities->patchEntity($userAvailability, $this->request->getData());
            if ($this->UserAvailabilities->save($userAvailability)) {
                $this->Flash->success(__('The user availability has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The user availability could not be saved. Please, try again.'));
        }
        $users = $this->UserAvailabilities->Users->find('list', [
            'keyField' => 'id',
            'valueField' => function ($row) {
                return $row['last_name'] . ' ' . $row['first_name'];
            },
            'order' => ['Users.last_name' => 'ASC'],
            'limit' => 200
        ])->toArray();
        $this->set(compact('userAvailability', 'users'));
    }

    /**
     * Edit method
     *
     * @param string|null $id User Availability id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\UserAvailabilitiesResource(), 'edit');
        $userAvailability = $this->UserAvailabilities->get($id, contain: ['Users']);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $userAvailability = $this->UserAvailabilities->patchEntity($userAvailability, $this->request->getData());
            if ($this->UserAvailabilities->save($userAvailability)) {
                $this->Flash->success(__('The user availability has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The user availability could not be saved. Please, try again.'));
        }
        $users = $this->UserAvailabilities->Users->find('list', [
            'keyField' => 'id',
            'valueField' => function ($row) {
                return $row['last_name'] . ' ' . $row['first_name'];
            },
            'order' => ['Users.last_name' => 'ASC'],
            'limit' => 200
        ])->toArray();
        $this->set(compact('userAvailability', 'users'));
    }

    /**
     * Delete method
     *
     * @param string|null $id User Availability id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $this->Authorization->authorize(new \App\Resource\UserAvailabilitiesResource(), 'delete');
        $userAvailability = $this->UserAvailabilities->get($id);
        if ($this->UserAvailabilities->delete($userAvailability)) {
            $this->Flash->success(__('The user availability has been deleted.'));
        } else {
            $this->Flash->error(__('The user availability could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}
