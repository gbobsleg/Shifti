<?php
namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class ForecastScenariosTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('forecast_scenarios');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->hasMany('ForecastScenariosOffers', [
            'foreignKey' => 'scenario_id',
            'dependent' => true,
        ]);

        $this->hasMany('ScenarioSeries', [
            'foreignKey' => 'scenario_id',
            'dependent' => true,
        ]);

        $this->hasMany('ForecastScenarioPublications', [
            'foreignKey' => 'scenario_id',
            'dependent' => true,
        ]);

        $this->belongsToMany('Offers', [
            'through' => 'ForecastScenariosOffers',
            'foreignKey' => 'scenario_id',
            'targetForeignKey' => 'offer_id',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->requirePresence('name', 'create')
            ->notEmptyString('name')
            ->requirePresence('start_date', 'create')
            ->date('start_date')
            ->requirePresence('end_date', 'create')
            ->date('end_date');

        $validator
            ->dateTime('started_at')
            ->allowEmptyDateTime('started_at')
            ->dateTime('finished_at')
            ->allowEmptyDateTime('finished_at')
            ->scalar('error_message')
            ->allowEmptyString('error_message')
            ->integer('progress_offer_id')
            ->allowEmptyString('progress_offer_id')
            ->scalar('progress_offer_name')
            ->maxLength('progress_offer_name', 255)
            ->allowEmptyString('progress_offer_name')
            ->date('progress_date')
            ->allowEmptyDate('progress_date')
            ->integer('progress_offers_done')
            ->allowEmptyString('progress_offers_done')
            ->integer('progress_offers_total')
            ->allowEmptyString('progress_offers_total')
            ->integer('progress_days_done')
            ->allowEmptyString('progress_days_done')
            ->integer('progress_days_total')
            ->allowEmptyString('progress_days_total');

        return $validator;
    }
}




