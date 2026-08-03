<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\Event\EventInterface;
use ArrayObject;

/**
 * UserAvailabilities Model
 *
 * @property \App\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Users
 *
 * @method \App\Model\Entity\UserAvailability newEmptyEntity()
 * @method \App\Model\Entity\UserAvailability newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\UserAvailability> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\UserAvailability get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\UserAvailability findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\UserAvailability patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\UserAvailability> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\UserAvailability|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\UserAvailability saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\UserAvailability>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\UserAvailability>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\UserAvailability>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\UserAvailability> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\UserAvailability>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\UserAvailability>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\UserAvailability>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\UserAvailability> deleteManyOrFail(iterable $entities, array $options = [])
 */
class UserAvailabilitiesTable extends Table
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

        $this->setTable('user_availabilities');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
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
            ->integer('user_id')
            ->notEmptyString('user_id');

        $validator
            ->integer('day_of_week')
            ->requirePresence('day_of_week', 'create')
            ->notEmptyString('day_of_week');

        $validator
            ->time('availability_start_time')
            ->requirePresence('availability_start_time', 'create')
            ->notEmptyTime('availability_start_time');

        $validator
            ->time('availability_end_time')
            ->requirePresence('availability_end_time', 'create')
            ->notEmptyTime('availability_end_time');

        $validator
            ->time('earliest_end_time')
            ->allowEmptyTime('earliest_end_time');

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
        $rules->add($rules->isUnique(['user_id', 'day_of_week']), ['errorField' => 'user_id']);
        $rules->add($rules->existsIn(['user_id'], 'Users'), ['errorField' => 'user_id']);

        return $rules;
    }

    /**
     * Normalize empty strings to NULL for optional time fields.
     * Ensures MySQL TIME column accepts NULL instead of ''.
     */
    public function beforeMarshal(EventInterface $event, ArrayObject $data, ArrayObject $options): void
    {
        // ArrayObject supports isset()/offsetExists; avoid array_key_exists() type error
        if (isset($data['earliest_end_time']) && $data['earliest_end_time'] === '') {
            $data['earliest_end_time'] = null;
        }
    }
}
