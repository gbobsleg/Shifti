<?php
namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class ForecastScenarioPublicationsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('forecast_scenario_publications');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'published_at' => 'new',
                ],
            ],
        ]);

        $this->belongsTo('ForecastScenarios', [
            'foreignKey' => 'scenario_id',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->date('date');
        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['date'], 'Un scénario est déjà publié pour cette date.'));
        $rules->add($rules->existsIn(['scenario_id'], 'ForecastScenarios'));
        return $rules;
    }
}


