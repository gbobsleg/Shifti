<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\I18n\DateTime;

/**
 * Alerts Controller
 *
 * @property \App\Model\Table\AlertsTable $Alerts
 * @method \App\Model\Entity\Alert[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class AlertsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Groom');
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $this->Authorization->authorize(new \App\Resource\AlertsResource(), 'index');
        
        $alerts = $this->Alerts->find();
        $params = $this->request->getQueryParams();

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
        if ($filterStart !== null && $filterEnd !== null) {
            $alerts->where([
                'Alerts.date_start <=' => $filterEnd,
                'Alerts.date_end >=' => $filterStart,
            ]);
        } elseif ($filterStart !== null) {
            $alerts->where(['Alerts.date_end >=' => $filterStart]);
        } elseif ($filterEnd !== null) {
            $alerts->where(['Alerts.date_start <=' => $filterEnd]);
        }

        // Filtre par contenu
        if (!empty($params['content'])) {
            $alerts->where(['Alerts.content LIKE' => '%' . $params['content'] . '%']);
        }

        // Filtre par priorité
        if (!empty($params['priority'])) {
            $alerts->where(['Alerts.priority' => $params['priority']]);
        }

        // Pagination normale
        $this->paginate = ['limit' => 25, 'order' => ['Alerts.date_start' => 'desc']];
        $alerts = $this->paginate($alerts);

        $this->set(compact('alerts'));
    }

    /**
     * View method
     *
     * @param string|null $id Alert id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\AlertsResource(), 'view');
        $alert = $this->Alerts->get($id, [
            'contain' => [],
        ]);

        $this->set(compact('alert'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $this->Authorization->authorize(new \App\Resource\AlertsResource(), 'add');
        $alert = $this->Alerts->newEmptyEntity();

        if ($this->request->is('post')) {
//            debug($this->request->getData());
            $alert = $this->request->getData();
//            debug($alert); exit;

            foreach ($alert as $k => $v) {
                if ($k == 'date_start') {
                    $date_start = DateTime::createFromFormat('Y-m-d', $v);
                    $day_ranges = $this->Groom->findBeginEndDay($date_start);
                    $alert[$k] = $day_ranges['begin'];
                }
                if ($k == 'date_end') {
                    $date_end = DateTime::createFromFormat('Y-m-d', $v);
                    $day_ranges = $this->Groom->findBeginEndDay($date_end);
                    $alert[$k] = $day_ranges['end'];
                }
            }

            $alert = $this->Alerts->newEntity($alert);

            if ($this->Alerts->save($alert)) {
                $this->Flash->success("L'alerte a été sauvegardée.");

                return $this->redirect($this->referer());
            }
            $this->Flash->error("L'alerte n'a pas pu être sauvegardée. Merci d'essayer à nouveau.");
        }
        $this->set(compact('alert'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Alert id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\AlertsResource(), 'edit');
        $alert = $this->Alerts->get($id, [
            'contain' => [],
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $alert = $this->Alerts->patchEntity($alert, $this->request->getData());
            if ($this->Alerts->save($alert)) {
                $this->Flash->success("L'alerte a été sauvegardée.");

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error("L'alerte n'a pas pu être sauvegardée. Merci d'essayer à nouveau.");
        }
        $this->set(compact('alert'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Alert id.
     * @return \Cake\Http\Response|null|void Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\AlertsResource(), 'delete');
        $this->request->allowMethod(['post', 'delete']);
        $alert = $this->Alerts->get($id);
        if ($this->Alerts->delete($alert)) {
            $this->Flash->success("L'alerte a été supprimée.");
        } else {
            $this->Flash->error("L'alerte n'a pas pu être supprimée. Merci d'essayer à nouveau.");
        }

        return $this->redirect($this->referer());
    }

    /**
     * @return \Cake\Http\Response|null
     */
    public function bulkDelete()
    {
        $this->Authorization->authorize(new \App\Resource\AlertsResource(), 'delete');
        $this->request->allowMethod(['post']);

        $ids = $this->request->getData('ids', []);
        if (empty($ids) || !is_array($ids)) {
            $this->Flash->error('Aucune alerte sélectionnée.');
            return $this->redirect($this->referer('/', true));
        }

        $ids = array_map('intval', $ids);
        $toDelete = $this->Alerts->find()->where(['id IN' => $ids])->all();

        $deletedCount = 0;
        foreach ($toDelete as $alert) {
            if ($this->Alerts->delete($alert)) {
                $deletedCount++;
            }
        }

        if ($deletedCount > 0) {
            $this->Flash->success($deletedCount . ' alerte(s) supprimée(s).');
        } else {
            $this->Flash->error('Aucune alerte n\'a pu être supprimée.');
        }

        return $this->redirect($this->referer('/', true));
    }
}
