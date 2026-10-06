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
        $conditions = $this->alertSearchConditions($this->request->getQueryParams());
        if ($conditions !== []) {
            $alerts->where($conditions);
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

        if ((string)$this->request->getData('delete_all_matching') === '1') {
            $conditions = $this->alertSearchConditions($this->request->getQueryParams());
            if ($conditions === []) {
                if ((string)$this->request->getData('confirm_purge_all') !== '1') {
                    $this->Flash->error('Suppression de toutes les alertes refusée : confirmation de purge absente.');

                    return $this->redirect($this->indexUrlWithoutPage());
                }
                $conditions = ['Alerts.id IS NOT' => null];
            }
            $deleted = $this->Alerts->deleteAll($conditions);
            $this->Flash->success($deleted . ' alerte(s) supprimée(s).');

            return $this->redirect($this->indexUrlWithoutPage());
        }

        $ids = $this->postedIds();
        if ($ids === []) {
            $this->Flash->error('Aucune alerte sélectionnée.');

            return $this->redirect($this->referer('/', true));
        }

        $deleted = $this->Alerts->deleteAll(['Alerts.id IN' => $ids]);
        $this->Flash->success($deleted . ' alerte(s) supprimée(s).');

        return $this->redirect($this->referer('/', true));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function alertSearchConditions(array $params): array
    {
        $conditions = [];
        $filterStart = $this->alertFilterBound($params['date_start'] ?? null, '00:00:00');
        $filterEnd = $this->alertFilterBound($params['date_end'] ?? null, '23:59:59');
        if ($filterStart !== null && $filterEnd !== null) {
            $conditions['Alerts.date_start <='] = $filterEnd;
            $conditions['Alerts.date_end >='] = $filterStart;
        } elseif ($filterStart !== null) {
            $conditions['Alerts.date_end >='] = $filterStart;
        } elseif ($filterEnd !== null) {
            $conditions['Alerts.date_start <='] = $filterEnd;
        }
        if (isset($params['content']) && is_string($params['content']) && $params['content'] !== '') {
            $conditions['Alerts.content LIKE'] = '%' . $params['content'] . '%';
        }
        $priority = $this->alertPositiveInt($params['priority'] ?? null);
        if ($priority !== null) {
            $conditions['Alerts.priority'] = $priority;
        }

        return $conditions;
    }

    private function alertFilterBound(mixed $value, string $time): ?string
    {
        if (is_array($value) && !empty($value['year']) && !empty($value['month']) && !empty($value['day'])) {
            $date = sprintf('%04d-%02d-%02d', $value['year'], $value['month'], $value['day']);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
                return $date . ' ' . $time;
            }

            return null;
        }
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value . ' ' . $time;
        }

        return null;
    }

    private function alertPositiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (!is_string($value) || $value === '' || !ctype_digit($value)) {
            return null;
        }
        $int = (int)$value;

        return $int > 0 ? $int : null;
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
            $int = $this->alertPositiveInt($id);
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
