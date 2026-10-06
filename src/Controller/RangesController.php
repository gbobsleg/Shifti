<?php
declare(strict_types=1);

namespace App\Controller;

use App\Controller\Traits\RangeQueryFiltersTrait;

/**
 * Ranges Controller
 *
 * @property \App\Model\Table\RangesTable $Ranges
 * @method \App\Model\Entity\Range[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class RangesController extends AppController
{
    use RangeQueryFiltersTrait;

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $this->Authorization->authorize(new \App\Resource\RangesResource(), 'index');
        
        $params = $this->request->getQueryParams();
        $query = $this->Ranges->find()->contain(['Users', 'Offers']);
        $conditions = $this->rangeSearchConditions($params);
        if ($conditions !== []) {
            $query->where($conditions);
        }

        // Pagination normale
        $this->paginate = ['limit' => 25, 'order' => ['Ranges.id' => 'desc']];
        $ranges = $this->paginate($query);

        // Données pour le formulaire de recherche
        $users = $this->Ranges->Users->find('list', [
            'keyField' => 'id',
            'valueField' => function ($row) {
                return $row['last_name'] . ' ' . $row['first_name'];
            },
        ])->order(['last_name' => 'ASC'])->toArray();
        
        $offers = $this->Ranges->Offers->find('list', ['limit' => 200])->toArray();

        $this->set(compact('ranges', 'users', 'offers'));
    }

    /**
     * View method
     *
     * @param string|null $id Range id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\RangesResource(), 'view');
        $range = $this->Ranges->get($id, [
            'contain' => ['Users', 'Offers'],
        ]);

        $this->set(compact('range'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $this->Authorization->authorize(new \App\Resource\RangesResource(), 'add');
        $range = $this->Ranges->newEmptyEntity();
        if ($this->request->is('post')) {
            $range = $this->Ranges->patchEntity($range, $this->request->getData());
            if ($this->Ranges->save($range)) {
                $this->Flash->success("La plage a été sauvegardée.");

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error("La plage n'a pas pu être sauvegardée. Merci d'essayer à nouveau.");
        }
        $users = $this->Ranges->Users->find('list', [
            'keyField' => 'id',
            'valueField' => function ($row) {
                return $row['last_name'] . ' ' . $row['first_name'];
            },
        ])->order(['last_name' => 'ASC'])->toArray();
        $offers = $this->Ranges->Offers->find('list', ['limit' => 200])->toArray();
        $this->set(compact('range', 'users', 'offers'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Range id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\RangesResource(), 'edit');
        $range = $this->Ranges->get($id, [
            'contain' => [],
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $range = $this->Ranges->patchEntity($range, $this->request->getData());
            if ($this->Ranges->save($range)) {
                $this->Flash->success("La plage a été sauvegardée.");

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error("La plage n'a pas pu être sauvegardée. Merci d'essayer à nouveau.");
        }
        $users = $this->Ranges->Users->find('list', [
            'keyField' => 'id',
            'valueField' => function ($row) {
                return $row['last_name'] . ' ' . $row['first_name'];
            },
        ])->order(['last_name' => 'ASC'])->toArray();
        $offers = $this->Ranges->Offers->find('list', ['limit' => 200])->toArray();
        $this->set(compact('range', 'users', 'offers'));
    }

    /**
     * Suppression en masse de plages
     *
     * @return \Cake\Http\Response|null
     */
    public function bulkDelete()
    {
        $this->Authorization->authorize(new \App\Resource\RangesResource(), 'delete');
        $this->request->allowMethod(['post']);

        if ((string)$this->request->getData('delete_all_matching') === '1') {
            $conditions = $this->rangeSearchConditions($this->request->getQueryParams());
            if ($conditions === []) {
                if ((string)$this->request->getData('confirm_purge_all') !== '1') {
                    $this->Flash->error('Suppression de toutes les plages refusée : confirmation de purge absente.');

                    return $this->redirect($this->indexUrlWithoutPage());
                }
                $conditions = ['Ranges.id IS NOT' => null];
            }
            $deleted = $this->Ranges->deleteAll($conditions);
            $this->Flash->success($deleted . ' plage(s) supprimée(s).');

            return $this->redirect($this->indexUrlWithoutPage());
        }

        $ids = $this->postedIds();
        if ($ids === []) {
            $this->Flash->error('Aucune plage sélectionnée.');

            return $this->redirect($this->referer('/', true));
        }

        $deleted = $this->Ranges->deleteAll(['Ranges.id IN' => $ids]);
        $this->Flash->success($deleted . ' plage(s) supprimée(s).');

        return $this->redirect($this->referer('/', true));
    }

    /**
     * Delete method
     *
     * @param string|null $id Range id.
     * @return \Cake\Http\Response|null|void Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\RangesResource(), 'delete');
        $this->request->allowMethod(['post', 'delete']);
        $range = $this->Ranges->get($id);
        if ($this->Ranges->delete($range)) {
            $this->Flash->success("La plage a été supprimée.");
        } else {
            $this->Flash->error("La plage n'a pas pu être supprimée. Merci d'essayer à nouveau.");
        }

//        return $this->redirect(['action' => 'index']);
        return $this->redirect($this->referer());
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function rangeSearchConditions(array $params): array
    {
        $conditions = $this->buildRangeFilters($params);
        $offerId = $this->rangeFilterPositiveInt($params['offer_id'] ?? null);
        if ($offerId !== null) {
            $conditions['Ranges.offer_id'] = $offerId;
        }
        if (isset($params['comment']) && is_string($params['comment']) && $params['comment'] !== '') {
            $conditions['Ranges.comment LIKE'] = '%' . $params['comment'] . '%';
        }

        return $conditions;
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
}
