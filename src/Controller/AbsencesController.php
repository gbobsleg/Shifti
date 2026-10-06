<?php
declare(strict_types=1);

namespace App\Controller;

use App\Controller\Traits\RangeQueryFiltersTrait;
use Cake\I18n\FrozenTime;

/**
 * Absences Controller
 */
class AbsencesController extends AppController
{
    use RangeQueryFiltersTrait;

    /**
     * @return void
     */
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
        $this->Authorization->authorize(new \App\Resource\AbsencesResource(), 'index');
        $this->Ranges = $this->fetchTable('Ranges');
        $this->Users = $this->fetchTable('Users');
        $this->Offers = $this->fetchTable('Offers');

        $params = $this->request->getQueryParams();

        $offers = $this->Offers->find('list')
            ->where(['offer_type IN' => ['absence', 'meeting']])
            ->toArray();
        $absenceOfferIds = array_keys($offers);

        // Liste des utilisateurs ayant au moins une absence (pour le filtre Agent)
        $users = [];
        if (!empty($absenceOfferIds)) {
            $userIdsWithAbsences = $this->Ranges->find()
                ->where(['offer_id IN' => $absenceOfferIds])
                ->all()
                ->extract('user_id')
                ->unique()
                ->toArray();
            if (!empty($userIdsWithAbsences)) {
                $users = $this->Users->find('list', [
                    'keyField' => 'id',
                    'valueField' => function ($row) {
                        return $row['last_name'] . ' ' . $row['first_name'];
                    },
                ])
                ->where(['Users.id IN' => $userIdsWithAbsences])
                ->order(['Users.last_name' => 'ASC'])
                ->toArray();
            }
        }

        $offers = $this->Offers->find('list')
            ->where([
                'offer_type IN' => ['absence', 'meeting'],
                ])
            ->toArray();

        $absences = $this->Ranges->find('Offers', array_flip($offers))
            ->contain(['Users', 'Offers']);
        $filters = $this->buildRangeFilters($params);
        $offerId = $this->rangeFilterPositiveInt($params['offer_id'] ?? null);
        if ($offerId !== null) {
            $filters['Ranges.offer_id'] = $offerId;
        }
        if ($filters !== []) {
            $absences->where($filters);
        }

        // Pagination normale
        $this->paginate = ['limit' => 25, 'order' => ['Ranges.id' => 'desc']];
        $absences = $this->paginate($absences);

        $this->set(compact('absences', 'users', 'offers'));
    }

    /**
     * @return \Cake\Http\Response|null
     */
    public function bulkDelete()
    {
        $this->Authorization->authorize(new \App\Resource\AbsencesResource(), 'delete');
        $this->request->allowMethod(['post']);

        $scopeIds = $this->absenceOfferIds();
        if ($scopeIds === []) {
            $this->Flash->error('Aucune absence à supprimer.');

            return $this->redirect(['action' => 'index']);
        }

        if ((string)$this->request->getData('delete_all_matching') === '1') {
            $deleted = $this->Ranges->deleteAll($this->absenceSearchConditions($this->request->getQueryParams(), $scopeIds));
            $this->Flash->success($deleted . ' absence(s) supprimée(s).');

            return $this->redirect($this->indexUrlWithoutPage());
        }

        $ids = $this->postedIds();
        if ($ids === []) {
            $this->Flash->error('Aucune absence sélectionnée.');

            return $this->redirect($this->referer('/', true));
        }

        $deleted = $this->Ranges->deleteAll([
            'Ranges.id IN' => $ids,
            'Ranges.offer_id IN' => $scopeIds,
        ]);
        $this->Flash->success($deleted . ' absence(s) supprimée(s).');

        return $this->redirect($this->referer('/', true));
    }

    /**
     * @param string|null $id Range id.
     * @return \Cake\Http\Response|null
     */
    public function delete($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\AbsencesResource(), 'delete');
        $this->request->allowMethod(['post', 'delete']);

        $scopeIds = $this->absenceOfferIds();
        $rangeId = $this->rangeFilterPositiveInt($id);
        if ($scopeIds === [] || $rangeId === null) {
            $this->Flash->error("L'absence n'a pas pu être supprimée.");

            return $this->redirect($this->referer('/', true));
        }

        $deleted = $this->Ranges->deleteAll([
            'Ranges.id' => $rangeId,
            'Ranges.offer_id IN' => $scopeIds,
        ]);
        if ($deleted > 0) {
            $this->Flash->success("L'absence a été supprimée.");
        } else {
            $this->Flash->error("L'absence n'a pas pu être supprimée.");
        }

        return $this->redirect($this->referer('/', true));
    }

    /**
     * @return \Cake\Http\Response|null
     */
    public function add()
    {
        $this->Authorization->authorize(new \App\Resource\AbsencesResource(), 'add');
        $this->Ranges = $this->fetchTable('Ranges');
        $this->Offers = $this->fetchTable('Offers');
        $this->Users = $this->fetchTable('Users');

        $range = $this->Ranges->newEmptyEntity();

        $offers = $this->Offers->find('list')
            ->where([
                'offer_type IN' => ['absence', 'meeting'],
            ])
            ->toArray();

        $users = $this->Users->find('list', [
            'keyField' => 'id',
            'valueField' => function ($row) {
                return $row['last_name'] . ' ' . $row['first_name'];
            },
        ])
            ->order(['last_name' => 'ASC'])
            ->toArray();

        if ($this->request->is('post')) {
            $datas = $this->request->getData();

            $datas['date_start'] = $this->parseDateTimeLocal((string)($datas['date_start'] ?? ''));
            $datas['date_end'] = $this->parseDateTimeLocal((string)($datas['date_end'] ?? ''));

            if ($datas['date_start'] === null || $datas['date_end'] === null) {
                $this->Flash->error('Les dates de début et de fin sont invalides.');
                $this->set(compact('range', 'users', 'offers'));

                return;
            }

            $dates = $this->Groom->findDayDates($datas['days'] ?? [], [
                'date_start' => $datas['date_start']->i18nFormat('yyyy-MM-dd HH:mm:ss'),
                'date_end' => $datas['date_end']->i18nFormat('yyyy-MM-dd HH:mm:ss'),
            ]);

            if (empty($dates)) {
                unset($datas['days']);

                $entity = $this->Ranges->newEntity($datas);

                if ($this->Ranges->save($entity)) {
                    $this->Flash->success("L'absence a été sauvegardée.");

                    return $this->redirect($this->referer());
                }
                $this->Flash->error("L'absence n'a pas pu être sauvegardée. Merci d'essayer à nouveau.");

                return $this->redirect($this->referer());
            }

            $datesCount = count($dates);
            for ($i = 0; $i < $datesCount; $i++) {
                $ranges[$i]['date_start'] = $dates[$i]['date_start'];
                $ranges[$i]['date_end'] = $dates[$i]['date_end'];
                $ranges[$i]['user_id'] = $datas['user_id'];
                $ranges[$i]['offer_id'] = $datas['offer_id'];
                $ranges[$i]['comment'] = $datas['comment'];
            }

            $entities_ranges = $this->Ranges->newEntities($ranges);

            if ($this->Ranges->saveMany($entities_ranges)) {
                $this->Flash->success('Les absences ont été sauvegardées.');

                return $this->redirect($this->referer());
            }
            $this->Flash->error("Les absences n'ont pas pu être sauvegardées. Merci d'essayer à nouveau.");
        }

        $this->set(compact('range', 'users', 'offers'));
    }

    private function parseDateTimeLocal(string $value): ?FrozenTime
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $parsed = FrozenTime::createFromFormat('Y-m-d\TH:i', $value)
            ?: FrozenTime::createFromFormat('Y-m-d\TH:i:s', $value)
            ?: FrozenTime::createFromFormat('Y-m-d H:i:s', str_replace('T', ' ', $value));

        return $parsed ?: null;
    }

    /**
     * @return array<int>
     */
    private function absenceOfferIds(): array
    {
        $offers = $this->fetchTable('Offers')->find('list')
            ->where(['offer_type IN' => ['absence', 'meeting']])
            ->toArray();
        $ids = [];
        foreach (array_keys($offers) as $id) {
            $int = $this->rangeFilterPositiveInt($id);
            if ($int !== null) {
                $ids[$int] = $int;
            }
        }

        return array_values($ids);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<int> $scopeIds
     * @return array<string, mixed>
     */
    private function absenceSearchConditions(array $params, array $scopeIds): array
    {
        $conditions = $this->buildRangeFilters($params);
        $conditions['Ranges.offer_id IN'] = $scopeIds;
        $offerId = $this->rangeFilterPositiveInt($params['offer_id'] ?? null);
        if ($offerId !== null) {
            $conditions['Ranges.offer_id'] = $offerId;
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
