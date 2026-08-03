<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Utility\Text;

class RotationRulesController extends AppController
{
    public function index()
    {
        $this->Authorization->authorize(new \App\Resource\RotationRulesResource(), 'index');

        $query = $this->fetchTable('RotationRules')
            ->find()
            ->contain(['Offers'])
            ->orderDesc('RotationRules.modified');

        // Filtres
        if ($this->request->getQuery('offer_id')) {
            $query->where(['RotationRules.offer_id' => $this->request->getQuery('offer_id')]);
        }
        if ($this->request->getQuery('period_type')) {
            $query->where(['RotationRules.period_type' => $this->request->getQuery('period_type')]);
        }

        $this->paginate = ['limit' => 25];
        $rules = $this->paginate($query);

        // Stats
        $allRules = $this->fetchTable('RotationRules')->find()->all();
        $total = $allRules->count();
        $weekly = 0;
        $monthly = 0;
        
        foreach ($allRules as $rule) {
            if ($rule->period_type === 'WEEKLY') {
                $weekly++;
            } elseif ($rule->period_type === 'MONTHLY') {
                $monthly++;
            }
        }
        
        $stats = [
            'total' => $total,
            'weekly' => $weekly,
            'monthly' => $monthly,
        ];

        // Options pour les filtres
        $offers = $this->fetchTable('Offers')->find('list')->order(['name' => 'ASC'])->toArray();

        $this->set(compact('rules', 'stats', 'offers'));
    }

    public function add()
    {
        $this->Authorization->authorize(new \App\Resource\RotationRulesResource(), 'add');

        $table = $this->fetchTable('RotationRules');
        $rule = $table->newEmptyEntity();
        $offers = $this->fetchTable('Offers')->find('list', [
            'keyField' => 'id',
            'valueField' => 'name',
        ])->order(['name' => 'ASC'])->toArray();

        // Récupérer les paramètres WFM pour les valeurs par défaut
        $wfmSettings = $this->fetchTable('WfmSettings')->find()->first();
        $defaultTimeWindowStart = $wfmSettings?->day_start_time ? (string)$wfmSettings->day_start_time : '09:00';
        $defaultTimeWindowEnd = $wfmSettings?->day_end_time ? (string)$wfmSettings->day_end_time : '18:00';

        if ($this->request->is('post')) {
            $data = $this->request->getData();
            
            // Générer un UUID si non fourni
            if (empty($data['id'])) {
                $data['id'] = Text::uuid();
            }
            
            $rule = $table->patchEntity($rule, $data);
            if ($table->save($rule)) {
                $this->Flash->success('Règle de rotation créée.');
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error('Échec de sauvegarde.');
        }
        
        $this->set(compact('rule', 'offers', 'defaultTimeWindowStart', 'defaultTimeWindowEnd'));
    }

    public function edit(string $id)
    {
        $this->Authorization->authorize(new \App\Resource\RotationRulesResource(), 'edit');

        $table = $this->fetchTable('RotationRules');
        $rule = $table->get($id, ['contain' => ['Offers']]);
        $offers = $this->fetchTable('Offers')->find('list', [
            'keyField' => 'id',
            'valueField' => 'name',
        ])->order(['name' => 'ASC'])->toArray();

        if ($this->request->is(['post', 'put', 'patch'])) {
            $data = $this->request->getData();
            $rule = $table->patchEntity($rule, $data);
            if ($table->save($rule)) {
                $this->Flash->success('Règle de rotation mise à jour.');
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error('Échec de sauvegarde.');
        }
        
        $this->set(compact('rule', 'offers'));
    }

    public function view(string $id)
    {
        $this->Authorization->authorize(new \App\Resource\RotationRulesResource(), 'view');

        $table = $this->fetchTable('RotationRules');
        $rule = $table->get($id, [
            'contain' => ['Offers', 'UsersRotationRules.Users.Sites'],
        ]);
        
        $this->set(compact('rule'));
    }

    public function delete(string $id)
    {
        $this->Authorization->authorize(new \App\Resource\RotationRulesResource(), 'delete');
        $this->request->allowMethod(['post', 'delete']);
        
        $table = $this->fetchTable('RotationRules');
        $rule = $table->get($id);
        
        if ($table->delete($rule)) {
            $this->Flash->success('Règle de rotation supprimée.');
        } else {
            $this->Flash->error('Suppression impossible.');
        }
        
        return $this->redirect(['action' => 'index']);
    }
}
