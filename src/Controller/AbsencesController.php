<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\I18n\FrozenTime;

/**
 * Absences Controller
 */
class AbsencesController extends AppController
{
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

        // Filtre par intervalle [date_start, date_end] : ranges qui chevauchent (comme Ranges)
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

        // Chevauchement : range chevauche [filterStart, filterEnd] ssi date_start <= filterEnd ET date_end >= filterStart
        if ($filterStart !== null && $filterEnd !== null) {
            $absences->where([
                'Ranges.date_start <=' => $filterEnd,
                'Ranges.date_end >=' => $filterStart
            ]);
        } elseif ($filterStart !== null) {
            $absences->where(['Ranges.date_end >=' => $filterStart]);
        } elseif ($filterEnd !== null) {
            $absences->where(['Ranges.date_start <=' => $filterEnd]);
        }
        if (!empty($params['user_id'])) {
            $absences->where(['Ranges.user_id' => $params['user_id']]);
        }
        if (!empty($params['offer_id'])) {
            $absences->where(['Ranges.offer_id' => $params['offer_id']]);
        }

        // Pagination normale
        $this->paginate = ['limit' => 25, 'order' => ['Ranges.id' => 'desc']];
        $absences = $this->paginate($absences);

        // Statistiques
        $allAbsences = $this->Ranges->find('Offers', array_flip($offers))
            ->contain(['Users', 'Offers'])->all();
        $stats = [
            'total' => $allAbsences->count(),
            'this_month' => $allAbsences->filter(function($abs) {
                $now = new \Cake\I18n\FrozenTime();
                return $abs->date_start && $abs->date_start->month == $now->month && $abs->date_start->year == $now->year;
            })->count(),
        ];

        $this->set(compact('absences', 'users', 'offers', 'stats'));
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

            $datas['date_start'] = FrozenTime::createFromFormat('d/m/Y, H:i', (string)$datas['date_start']);
            $datas['date_end'] = FrozenTime::createFromFormat('d/m/Y, H:i', (string)$datas['date_end']);

            $dates = $this->Groom->findDayDates($datas['days'], [
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
}
