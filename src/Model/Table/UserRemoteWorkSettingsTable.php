<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * UserRemoteWorkSettings Model
 *
 * @property \App\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Users
 *
 * @method \App\Model\Entity\UserRemoteWorkSetting newEmptyEntity()
 * @method \App\Model\Entity\UserRemoteWorkSetting newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\UserRemoteWorkSetting> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\UserRemoteWorkSetting get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\UserRemoteWorkSetting findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\UserRemoteWorkSetting patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\UserRemoteWorkSetting> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\UserRemoteWorkSetting|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\UserRemoteWorkSetting saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\UserRemoteWorkSetting>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\UserRemoteWorkSetting>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\UserRemoteWorkSetting>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\UserRemoteWorkSetting> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\UserRemoteWorkSetting>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\UserRemoteWorkSetting>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\UserRemoteWorkSetting>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\UserRemoteWorkSetting> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class UserRemoteWorkSettingsTable extends Table
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

        $this->setTable('user_remote_work_settings');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

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
            ->scalar('remote_work_type')
            ->inList('remote_work_type', ['none', 'fixed_days', 'flexible'])
            ->requirePresence('remote_work_type', 'create')
            ->notEmptyString('remote_work_type');

        $validator
            ->date('start_date')
            ->allowEmptyDate('start_date');

        $validator
            ->date('end_date')
            ->allowEmptyDate('end_date');

        $validator
            ->scalar('fixed_days_json')
            ->allowEmptyString('fixed_days_json');

        $validator
            ->integer('flexible_days_per_week')
            ->requirePresence('flexible_days_per_week', 'create')
            ->notEmptyString('flexible_days_per_week');

        $validator
            ->scalar('notes')
            ->allowEmptyString('notes');

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
        $rules->add($rules->isUnique(['user_id']), ['errorField' => 'user_id']);
        $rules->add($rules->existsIn(['user_id'], 'Users'), ['errorField' => 'user_id']);

        return $rules;
    }

    /**
     * Finder pour les utilisateurs avec télétravail actif
     */
    public function findWithActiveRemoteWork(Query $query, array $options): Query
    {
        return $query->where(['remote_work_type !=' => 'none']);
    }
}
