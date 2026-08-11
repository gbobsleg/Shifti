<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * PlanningDayHistories Model
 *
 * @property \App\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Users
 * @property \App\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $ActorUsers
 *
 * @method \App\Model\Entity\PlanningDayHistory newEmptyEntity()
 * @method \App\Model\Entity\PlanningDayHistory newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\PlanningDayHistory> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\PlanningDayHistory get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\PlanningDayHistory findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\PlanningDayHistory patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\PlanningDayHistory> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\PlanningDayHistory|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\PlanningDayHistory saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class PlanningDayHistoriesTable extends Table
{
    /**
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('planning_day_histories');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created' => 'new',
                ],
            ],
        ]);

        $this->getSchema()->setColumnType('snapshot', 'json');

        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
            'joinType' => 'INNER',
        ]);

        $this->belongsTo('ActorUsers', [
            'className' => 'Users',
            'foreignKey' => 'actor_user_id',
            'propertyName' => 'actor_user',
            'joinType' => 'LEFT',
        ]);
    }

    /**
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->integer('user_id')
            ->requirePresence('user_id', 'create')
            ->notEmptyString('user_id');

        $validator
            ->date('day')
            ->requirePresence('day', 'create')
            ->notEmptyDate('day');

        $validator
            ->requirePresence('snapshot', 'create')
            ->allowEmptyArray('snapshot');

        $validator
            ->scalar('content_hash')
            ->maxLength('content_hash', 64)
            ->requirePresence('content_hash', 'create')
            ->notEmptyString('content_hash');

        $validator
            ->scalar('source')
            ->maxLength('source', 32)
            ->requirePresence('source', 'create')
            ->notEmptyString('source');

        $validator
            ->integer('actor_user_id')
            ->allowEmptyString('actor_user_id');

        return $validator;
    }

    /**
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['user_id'], 'Users'), ['errorField' => 'user_id']);
        $rules->add($rules->existsIn(['actor_user_id'], 'ActorUsers'), [
            'errorField' => 'actor_user_id',
            'allowNullableNulls' => true,
        ]);

        return $rules;
    }
}
