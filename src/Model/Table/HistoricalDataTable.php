<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * HistoricalData Model
 *
 * @property \App\Model\Table\OffersTable&\Cake\ORM\Association\BelongsTo $Offers
 *
 * @method \App\Model\Entity\HistoricalData newEmptyEntity()
 * @method \App\Model\Entity\HistoricalData newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\HistoricalData> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\HistoricalData get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\HistoricalData findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\HistoricalData patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\HistoricalData> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\HistoricalData|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\HistoricalData saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\HistoricalData>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\HistoricalData>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\HistoricalData>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\HistoricalData> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\HistoricalData>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\HistoricalData>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\HistoricalData>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\HistoricalData> deleteManyOrFail(iterable $entities, array $options = [])
 */
class HistoricalDataTable extends Table
{
    /**
     * Initialize method
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('historical_data');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('Offers', [
            'foreignKey' => 'offer_id',
            'joinType' => 'INNER',
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('offer_id')
            ->notEmptyString('offer_id');

        $validator
            ->dateTime('datetime_interval')
            ->requirePresence('datetime_interval', 'create')
            ->notEmptyDateTime('datetime_interval');

        $validator
            ->integer('call_volume')
            ->notEmptyString('call_volume');

        $validator
            ->integer('avg_handle_time_seconds')
            ->notEmptyString('avg_handle_time_seconds');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['offer_id'], 'Offers'), ['errorField' => 'offer_id']);

        return $rules;
    }
}
