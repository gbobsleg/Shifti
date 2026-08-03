<?php
declare(strict_types=1);

namespace App\Controller;

class FixedActivityRulesController extends AppController
{
    public function index()
    {
        $this->Authorization->authorize(new \App\Resource\FixedActivityRulesResource(), 'index');

        $query = $this->fetchTable('FixedActivityRules')
            ->find()
            ->contain(['Offers', 'Sites']);

        // Tri par défaut : priorité DESC puis offre ASC
        // Sauf si un tri est déjà spécifié via le Paginator
        if (!$this->request->getQuery('sort')) {
            $query->orderDesc('FixedActivityRules.priority')
                  ->orderAsc('FixedActivityRules.offer_id');
        }

        // Filtres
        if ($this->request->getQuery('offer_id')) {
            $query->where(['FixedActivityRules.offer_id' => $this->request->getQuery('offer_id')]);
        }
        if ($this->request->getQuery('site_mode')) {
            $query->where(['FixedActivityRules.site_mode' => $this->request->getQuery('site_mode')]);
        }
        if ($this->request->getQuery('active') !== null && $this->request->getQuery('active') !== '') {
            $query->where(['FixedActivityRules.active' => (bool)$this->request->getQuery('active')]);
        }

        // Calculer min et max des priorités pour le dégradé dynamique (avant pagination)
        // Créer une requête séparée sans associations pour éviter les erreurs
        $prioritiesQuery = $this->fetchTable('FixedActivityRules')->find();
        
        // Appliquer les mêmes filtres
        if ($this->request->getQuery('offer_id')) {
            $prioritiesQuery->where(['FixedActivityRules.offer_id' => $this->request->getQuery('offer_id')]);
        }
        if ($this->request->getQuery('site_mode')) {
            $prioritiesQuery->where(['FixedActivityRules.site_mode' => $this->request->getQuery('site_mode')]);
        }
        if ($this->request->getQuery('active') !== null && $this->request->getQuery('active') !== '') {
            $prioritiesQuery->where(['FixedActivityRules.active' => (bool)$this->request->getQuery('active')]);
        }
        
        $allRulesForPriority = $prioritiesQuery->select(['priority'])->all();
        $priorities = [];
        foreach ($allRulesForPriority as $rule) {
            if ($rule->priority !== null) {
                $priorities[] = (int)$rule->priority;
            }
        }
        $priorityMin = !empty($priorities) ? min($priorities) : null;
        $priorityMax = !empty($priorities) ? max($priorities) : null;

        $this->paginate = ['limit' => 25];
        $rules = $this->paginate($query);

        // Stats
        $allRules = $this->fetchTable('FixedActivityRules')->find()->all();
        $total = $allRules->count();
        $active = 0;
        $inactive = 0;
        $perSite = 0;
        $pooled = 0;
        $global = 0;
        
        foreach ($allRules as $rule) {
            if ($rule->active) {
                $active++;
            } else {
                $inactive++;
            }
            
            $mode = $rule->site_mode ?? 'per_site';
            if ($mode === 'per_site') {
                $perSite++;
            } elseif ($mode === 'pooled') {
                $pooled++;
            } elseif ($mode === 'global') {
                $global++;
            }
        }
        
        $stats = [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'per_site' => $perSite,
            'pooled' => $pooled,
            'global' => $global,
        ];

        // Options pour les filtres
        $offers = $this->fetchTable('Offers')->find('list')->order(['name' => 'ASC'])->toArray();

        $this->set(compact('rules', 'stats', 'offers', 'priorityMin', 'priorityMax'));
    }

    public function add()
    {
        $this->Authorization->authorize(new \App\Resource\FixedActivityRulesResource(), 'add');

        $table = $this->fetchTable('FixedActivityRules');
        $rule = $table->newEmptyEntity([
            'associated' => ['FixedActivityBlocks', 'IncompatibleOffers'],
        ]);
        $offers = $this->fetchTable('Offers')->find('list')->toArray();
        $sites = $this->fetchTable('Sites')->find('list')->toArray();
        $daysOptions = [1=>'Lundi',2=>'Mardi',3=>'Mercredi',4=>'Jeudi',5=>'Vendredi',6=>'Samedi',7=>'Dimanche'];
        $selectedDays = [];

        if ($this->request->is('post')) {
            $data = $this->request->getData();
            $days = array_values(array_filter(array_map('intval', (array)($data['days_of_week_selected'] ?? [])), function($v){return $v>=1 && $v<=7;}));
            $data['days_of_week'] = json_encode($days);
            unset($data['days_of_week_selected']);
            // Nettoyer les blocs vides ou partiellement remplis:
            // on ne conserve que les blocs avec début ET fin renseignés
            if (!empty($data['fixed_activity_blocks']) && is_array($data['fixed_activity_blocks'])) {
                $cleanBlocks = [];
                foreach ($data['fixed_activity_blocks'] as $block) {
                    $start = $block['start_time'] ?? null;
                    $end = $block['end_time'] ?? null;
                    if ($start && $end) {
                        $cleanBlocks[] = $block;
                    }
                }
                $data['fixed_activity_blocks'] = $cleanBlocks;
            }
            
            // Normaliser priority : convertir string vide en null, sinon en int
            if (isset($data['priority'])) {
                if ($data['priority'] === '' || $data['priority'] === null) {
                    $data['priority'] = null;
                } else {
                    $data['priority'] = (int)$data['priority'];
                }
            }
            
            $rule = $table->patchEntity($rule, $data, [
                'associated' => ['Sites', 'FixedActivityBlocks', 'IncompatibleOffers']
            ]);
            if ($table->save($rule)) {
                $this->Flash->success('Règle créée.');
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error('Échec de sauvegarde.');
            $selectedDays = $days;
        }
        $this->set(compact('rule', 'offers', 'sites', 'daysOptions', 'selectedDays'));
    }

    public function edit($id)
    {
        $this->Authorization->authorize(new \App\Resource\FixedActivityRulesResource(), 'edit');

        $table = $this->fetchTable('FixedActivityRules');
        $rule = $table->get($id, ['contain' => ['Sites', 'FixedActivityBlocks', 'IncompatibleOffers']]);
        $offers = $this->fetchTable('Offers')->find('list')->toArray();
        $sites = $this->fetchTable('Sites')->find('list')->toArray();
        $daysOptions = [1=>'Lundi',2=>'Mardi',3=>'Mercredi',4=>'Jeudi',5=>'Vendredi',6=>'Samedi',7=>'Dimanche'];
        $selectedDays = [];
        if (!empty($rule->days_of_week)) {
            $decoded = is_string($rule->days_of_week) ? json_decode($rule->days_of_week, true) : (array)$rule->days_of_week;
            if (is_array($decoded)) { $selectedDays = array_map('intval', $decoded); }
        }

        if ($this->request->is(['post', 'put', 'patch'])) {
            $data = $this->request->getData();
            $days = array_values(array_filter(array_map('intval', (array)($data['days_of_week_selected'] ?? [])), function($v){return $v>=1 && $v<=7;}));
            $data['days_of_week'] = json_encode($days);
            unset($data['days_of_week_selected']);
            // Nettoyer les blocs vides ou partiellement remplis:
            // on ne conserve que les blocs avec début ET fin renseignés
            if (!empty($data['fixed_activity_blocks']) && is_array($data['fixed_activity_blocks'])) {
                $cleanBlocks = [];
                foreach ($data['fixed_activity_blocks'] as $block) {
                    $start = $block['start_time'] ?? null;
                    $end = $block['end_time'] ?? null;
                    if ($start && $end) {
                        $cleanBlocks[] = $block;
                    }
                }
                $data['fixed_activity_blocks'] = $cleanBlocks;
            }
            
            // Normaliser priority : convertir string vide en null, sinon en int
            if (isset($data['priority'])) {
                if ($data['priority'] === '' || $data['priority'] === null) {
                    $data['priority'] = null;
                } else {
                    $data['priority'] = (int)$data['priority'];
                }
            }
            
            $rule = $table->patchEntity($rule, $data, [
                'associated' => ['Sites', 'FixedActivityBlocks', 'IncompatibleOffers']
            ]);
            if ($table->save($rule)) {
                $this->Flash->success('Règle mise à jour.');
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error('Échec de sauvegarde.');
            $selectedDays = $days;
        }
        $this->set(compact('rule', 'offers', 'sites', 'daysOptions', 'selectedDays'));
    }

    public function view($id)
    {
        $this->Authorization->authorize(new \App\Resource\FixedActivityRulesResource(), 'view');

        $table = $this->fetchTable('FixedActivityRules');
        $rule = $table->get($id, ['contain' => ['Offers', 'Sites', 'IncompatibleOffers']]);
        $this->set(compact('rule'));
    }

    public function toggle($id)
    {
        $this->Authorization->authorize(new \App\Resource\FixedActivityRulesResource(), 'edit');
        $this->request->allowMethod(['post']);
        
        $table = $this->fetchTable('FixedActivityRules');
        $rule = $table->get($id);
        
        $rule->active = !$rule->active;
        
        if ($table->save($rule)) {
            $status = $rule->active ? 'activée' : 'désactivée';
            $this->Flash->success("Règle {$status}.");
        } else {
            $this->Flash->error('Échec de la mise à jour.');
        }
        
        return $this->redirect(['action' => 'index']);
    }

    public function delete($id)
    {
        $this->Authorization->authorize(new \App\Resource\FixedActivityRulesResource(), 'delete');
        $this->request->allowMethod(['post', 'delete']);
        $table = $this->fetchTable('FixedActivityRules');
        $rule = $table->get($id);
        if ($table->delete($rule)) {
            $this->Flash->success('Règle supprimée.');
        } else {
            $this->Flash->error('Suppression impossible.');
        }
        return $this->redirect(['action' => 'index']);
    }
}


