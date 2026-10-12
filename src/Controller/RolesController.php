<?php
declare(strict_types=1);

namespace App\Controller;

use App\Authorization\CapabilityCatalog;
use App\Authorization\RoleCapabilities;

/**
 * Roles Controller
 *
 * @property \App\Model\Table\RolesTable $Roles
 * @method \App\Model\Entity\Role[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class RolesController extends AppController
{
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $this->Authorization->authorize(new \App\Resource\RolesResource(), 'index');
        $query = $this->Roles->find()->contain(['Users']);
        $roles = $this->paginate($query);

        $matrixRoles = $this->Roles->find()
            ->select(['id', 'name', 'code', 'priority'])
            ->orderBy(['Roles.priority' => 'ASC', 'Roles.id' => 'ASC'])
            ->all()
            ->toList();
        $capabilityGroups = CapabilityCatalog::groups();
        $grantedByRole = [];
        foreach ($matrixRoles as $matrixRole) {
            $grantedByRole[(int)$matrixRole->id] = RoleCapabilities::forCode($matrixRole->code);
        }

        $this->set(compact('roles', 'matrixRoles', 'capabilityGroups', 'grantedByRole'));
    }

    /**
     * View method
     *
     * @param string|null $id Role id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\RolesResource(), 'view');
        $role = $this->Roles->get($id, [
            'contain' => ['Users'],
        ]);

        $capabilityGroups = CapabilityCatalog::groups();
        $granted = RoleCapabilities::forCode($role->code);

        $this->set(compact('role', 'capabilityGroups', 'granted'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $this->Authorization->authorize(new \App\Resource\RolesResource(), 'add');
        $role = $this->Roles->newEmptyEntity();
        if ($this->request->is('post')) {
            $role = $this->Roles->patchEntity($role, $this->request->getData());
            if ($this->Roles->save($role)) {
                $this->Flash->success("Le rôle a été sauvegardé.");

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error("Le rôle n'a pas pu être sauvegardé. Merci d'essayer à nouveau.");
        }
        $this->set(compact('role'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Role id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\RolesResource(), 'edit');
        $role = $this->Roles->get($id, [
            'contain' => [],
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $data = $this->request->getData();
            // La priorité d'un rôle codé fixe la hiérarchie d'attribution (PermissionService).
            if ($role->code !== null) {
                unset($data['priority']);
            }
            $role = $this->Roles->patchEntity($role, $data);
            if ($this->Roles->save($role)) {
                $this->Flash->success("Le rôle a été sauvegardé.");

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error("Le rôle n'a pas pu être sauvegardé. Merci d'essayer à nouveau.");
        }
        $this->set(compact('role'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Role id.
     * @return \Cake\Http\Response|null|void Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $this->Authorization->authorize(new \App\Resource\RolesResource(), 'delete');
        $role = $this->Roles->get($id);
        if ($this->Roles->delete($role)) {
            $this->Flash->success("Le rôle a été supprimé.");
        } else {
            $this->Flash->error("Le rôle n'a pas pu être supprimé. Merci d'essayer à nouveau.");
        }

        return $this->redirect(['action' => 'index']);
    }
}
