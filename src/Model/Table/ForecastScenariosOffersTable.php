<?php
namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class ForecastScenariosOffersTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('forecast_scenarios_offers');
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
            ->integer('offer_id')
            ->requirePresence('offer_id', 'create')
            ->notEmptyString('offer_id');

        return $validator;
    }
}




