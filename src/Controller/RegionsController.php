<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Regions Controller
 *
 * @property \App\Model\Table\RegionsTable $Regions
 * @method \App\Model\Entity\Region[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class RegionsController extends AppController
{
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $this->Authorization->authorize(new \App\Resource\RegionsResource(), 'index');
        $query = $this->Regions->find()->contain(['Sites']);
        $regions = $this->paginate($query);

        // Statistiques
        $allRegions = $this->Regions->find('all')->contain(['Sites']);
        $stats = [
            'total' => $allRegions->count(),
            'with_sites' => 0,
            'without_sites' => 0,
            'total_sites' => 0
        ];
        
        foreach ($allRegions as $region) {
            $siteCount = count($region->sites);
            $stats['total_sites'] += $siteCount;
            if ($siteCount > 0) {
                $stats['with_sites']++;
            } else {
                $stats['without_sites']++;
            }
        }

        $this->set(compact('regions', 'stats'));
    }

    /**
     * View method
     *
     * @param string|null $id Region id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\RegionsResource(), 'view');
        $region = $this->Regions->get($id, [
            'contain' => ['Sites'],
        ]);

        $this->set(compact('region'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $this->Authorization->authorize(new \App\Resource\RegionsResource(), 'add');
        $region = $this->Regions->newEmptyEntity();
        if ($this->request->is('post')) {
            $region = $this->Regions->patchEntity($region, $this->request->getData());
            if ($this->Regions->save($region)) {
                $this->Flash->success("La région a été sauvegardée.");

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error("La région n'a pas pu être sauvegardée. Merci d'essayer à nouveau.");
        }
        $this->set(compact('region'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Region id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\RegionsResource(), 'edit');
        $region = $this->Regions->get($id, [
            'contain' => [],
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $region = $this->Regions->patchEntity($region, $this->request->getData());
            if ($this->Regions->save($region)) {
                $this->Flash->success("La région a été sauvegardée.");

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error("La région n'a pas pu être sauvegardée. Merci d'essayer à nouveau.");
        }
        $this->set(compact('region'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Region id.
     * @return \Cake\Http\Response|null|void Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $this->Authorization->authorize(new \App\Resource\RegionsResource(), 'delete');
        $region = $this->Regions->get($id);
        if ($this->Regions->delete($region)) {
            $this->Flash->success("La région a été supprimée.");
        } else {
            $this->Flash->error("La région n'a pas pu être supprimée. Merci d'essayer à nouveau.");
        }

        return $this->redirect(['action' => 'index']);
    }
}
