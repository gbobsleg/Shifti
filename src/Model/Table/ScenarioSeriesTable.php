<?php
namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class ScenarioSeriesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('scenario_series');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('ForecastScenarios', [
            'foreignKey' => 'scenario_id',
        ]);

        $this->belongsTo('Offers', [
            'foreignKey' => 'offer_id',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->requirePresence('date', 'create')
            ->date('date')
            ->requirePresence('type', 'create')
            ->inList('type', ['forecast', 'need', 'planned'])
            ->integer('step_seconds')
            ->requirePresence('start_time', 'create')
            ->time('start_time')
            ->requirePresence('end_time', 'create')
            ->time('end_time');

        return $validator;
    }
}




