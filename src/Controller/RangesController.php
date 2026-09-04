<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Ranges Controller
 *
 * @property \App\Model\Table\RangesTable $Ranges
 * @method \App\Model\Entity\Range[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class RangesController extends AppController
{
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $this->Authorization->authorize(new \App\Resource\RangesResource(), 'index');
        
        $query = $this->Ranges->find()->contain(['Users', 'Offers']);
        $params = $this->request->getQueryParams();

        // Filtre par intervalle [date_start, date_end] : affiche les ranges qui chevauchent cet intervalle
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

        // Chevauchement : range chevauche [filterStart, filterEnd] ssi range.date_start <= filterEnd ET range.date_end >= filterStart
        if ($filterStart !== null && $filterEnd !== null) {
            $query->where([
                'Ranges.date_start <=' => $filterEnd,
                'Ranges.date_end >=' => $filterStart
            ]);
        } elseif ($filterStart !== null) {
            $query->where(['Ranges.date_end >=' => $filterStart]);
        } elseif ($filterEnd !== null) {
            $query->where(['Ranges.date_start <=' => $filterEnd]);
        }

        // Filtre par utilisateur
        if (!empty($params['user_id'])) {
            $query->where(['Ranges.user_id' => $params['user_id']]);
        }

        // Filtre par offre
        if (!empty($params['offer_id'])) {
            $query->where(['Ranges.offer_id' => $params['offer_id']]);
        }

        // Filtre par commentaire
        if (!empty($params['comment'])) {
            $query->where(['Ranges.comment LIKE' => '%' . $params['comment'] . '%']);
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

        $ids = $this->request->getData('ids', []);

        if (empty($ids) || !is_array($ids)) {
            $this->Flash->error('Aucune plage sélectionnée.');
            return $this->redirect($this->referer('/', true));
        }

        $ids = array_map('intval', $ids);
        $ranges = $this->Ranges->find()->where(['id IN' => $ids])->all();

        $deletedCount = 0;
        foreach ($ranges as $range) {
            $this->Ranges->delete($range);
            $deletedCount++;
        }

        if ($deletedCount > 0) {
            $this->Flash->success($deletedCount . ' plage(s) supprimée(s).');
        } else {
            $this->Flash->error('Aucune plage n\'a pu être supprimée.');
        }

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
}
