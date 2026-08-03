<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * DisplaySettings Controller
 *
 * @property \App\Model\Table\DisplaySettingsTable $DisplaySettings
 * @property \Authorization\Controller\Component\AuthorizationComponent $Authorization
 */
class DisplaySettingsController extends AppController
{
    /**
     * Initialize controller
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Authorization.Authorization');
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $this->Authorization->authorize(new \App\Resource\DisplaySettingsResource());
        $query = $this->DisplaySettings->find();
        $displaySettings = $this->paginate($query);

        $this->set(compact('displaySettings'));
    }

    /**
     * View method
     *
     * @param string|null $id Display Setting id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\DisplaySettingsResource());
        $displaySetting = $this->DisplaySettings->get($id, contain: []);
        $this->set(compact('displaySetting'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $this->Authorization->authorize(new \App\Resource\DisplaySettingsResource());
        $displaySetting = $this->DisplaySettings->newEmptyEntity();
        if ($this->request->is('post')) {
            $displaySetting = $this->DisplaySettings->patchEntity($displaySetting, $this->request->getData());
            if ($this->DisplaySettings->save($displaySetting)) {
                $this->Flash->success('Le paramètre a été enregistré.');

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error('Le paramètre n\'a pas pu être enregistré. Veuillez réessayer.');
        }
        $this->set(compact('displaySetting'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Display Setting id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\DisplaySettingsResource());
        $displaySetting = $this->DisplaySettings->get($id, contain: []);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $displaySetting = $this->DisplaySettings->patchEntity($displaySetting, $this->request->getData());
            if ($this->DisplaySettings->save($displaySetting)) {
                $this->Flash->success('Le paramètre a été modifié.');

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error('Le paramètre n\'a pas pu être modifié. Veuillez réessayer.');
        }
        $this->set(compact('displaySetting'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Display Setting id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $this->Authorization->authorize(new \App\Resource\DisplaySettingsResource());
        $displaySetting = $this->DisplaySettings->get($id);
        if ($this->DisplaySettings->delete($displaySetting)) {
            $this->Flash->success('Le paramètre a été supprimé.');
        } else {
            $this->Flash->error('Le paramètre n\'a pas pu être supprimé. Veuillez réessayer.');
        }

        return $this->redirect(['action' => 'index']);
    }
}
