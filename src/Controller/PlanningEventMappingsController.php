<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * PlanningEventMappings Controller
 *
 * @property \App\Model\Table\PlanningEventMappingsTable $PlanningEventMappings
 * @method \App\Model\Entity\PlanningEventMapping[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class PlanningEventMappingsController extends AppController
{
    public function index()
    {
        $this->Authorization->authorize(new \App\Resource\PlanningEventMappingsResource(), 'index');
        $this->PlanningEventMappings = $this->fetchTable('PlanningEventMappings');
        
        $query = $this->PlanningEventMappings->find()
            ->contain(['Offers'])
            ->order(['PlanningEventMappings.priority' => 'DESC', 'PlanningEventMappings.keywords' => 'ASC']);
        
        $planningEventMappings = $this->paginate($query, ['limit' => 25]);

        $allMappings = $this->PlanningEventMappings->find()->contain(['Offers'])->all();
        $stats = [
            'total' => $allMappings->count(),
            'by_offer' => [],
        ];
        
        foreach ($allMappings as $mapping) {
            $offerName = $mapping->offer->name ?? 'N/A';
            if (!isset($stats['by_offer'][$offerName])) {
                $stats['by_offer'][$offerName] = 0;
            }
            $stats['by_offer'][$offerName]++;
        }

        $this->set(compact('planningEventMappings', 'stats'));
    }

    public function view($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\PlanningEventMappingsResource(), 'view');
        $this->PlanningEventMappings = $this->fetchTable('PlanningEventMappings');
        
        $planningEventMapping = $this->PlanningEventMappings->get($id, [
            'contain' => ['Offers'],
        ]);

        $this->set(compact('planningEventMapping'));
    }

    public function add()
    {
        $this->Authorization->authorize(new \App\Resource\PlanningEventMappingsResource(), 'add');
        $this->PlanningEventMappings = $this->fetchTable('PlanningEventMappings');
        $this->Offers = $this->fetchTable('Offers');
        
        $planningEventMapping = $this->PlanningEventMappings->newEmptyEntity();
        
        if ($this->request->is('post')) {
            $planningEventMapping = $this->PlanningEventMappings->patchEntity($planningEventMapping, $this->request->getData());
            if ($this->PlanningEventMappings->save($planningEventMapping)) {
                $this->Flash->success("Le mapping a été sauvegardé.");
                return $this->redirect(['action' => 'index']);
            }
            // Afficher les erreurs de validation
            if ($planningEventMapping->hasErrors()) {
                $errors = [];
                foreach ($planningEventMapping->getErrors() as $field => $fieldErrors) {
                    foreach ($fieldErrors as $error) {
                        $errors[] = $field . ': ' . $error;
                    }
                }
                $this->Flash->error("Erreurs de validation : " . implode(', ', $errors));
            } else {
            $this->Flash->error("Le mapping n'a pas pu être sauvegardé. Merci d'essayer à nouveau.");
            }
        }
        
        $offers = $this->Offers->find('list')
            ->order(['name' => 'ASC'])
            ->toArray();

        $this->set(compact('planningEventMapping', 'offers'));
    }

    public function edit($id = null)
    {
        $this->Authorization->authorize(new \App\Resource\PlanningEventMappingsResource(), 'edit');
        $this->PlanningEventMappings = $this->fetchTable('PlanningEventMappings');
        $this->Offers = $this->fetchTable('Offers');
        
        $planningEventMapping = $this->PlanningEventMappings->get($id, [
            'contain' => [],
        ]);
        
        if ($this->request->is(['patch', 'post', 'put'])) {
            $planningEventMapping = $this->PlanningEventMappings->patchEntity($planningEventMapping, $this->request->getData());
            if ($this->PlanningEventMappings->save($planningEventMapping)) {
                $this->Flash->success("Le mapping a été sauvegardé.");
                return $this->redirect(['action' => 'index']);
            }
            // Afficher les erreurs de validation
            if ($planningEventMapping->hasErrors()) {
                $errors = [];
                foreach ($planningEventMapping->getErrors() as $field => $fieldErrors) {
                    foreach ($fieldErrors as $error) {
                        $errors[] = $field . ': ' . $error;
                    }
                }
                $this->Flash->error("Erreurs de validation : " . implode(', ', $errors));
            } else {
            $this->Flash->error("Le mapping n'a pas pu être sauvegardé. Merci d'essayer à nouveau.");
            }
        }
        
        $offers = $this->Offers->find('list')
            ->order(['name' => 'ASC'])
            ->toArray();

        $this->set(compact('planningEventMapping', 'offers'));
    }

    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $this->Authorization->authorize(new \App\Resource\PlanningEventMappingsResource(), 'delete');
        $this->PlanningEventMappings = $this->fetchTable('PlanningEventMappings');
        
        $absenceMapping = $this->PlanningEventMappings->get($id);
        if ($this->PlanningEventMappings->delete($absenceMapping)) {
            $this->Flash->success("Le mapping a été supprimé.");
        } else {
            $this->Flash->error("Le mapping n'a pas pu être supprimé. Merci d'essayer à nouveau.");
        }

        return $this->redirect(['action' => 'index']);
    }
}


