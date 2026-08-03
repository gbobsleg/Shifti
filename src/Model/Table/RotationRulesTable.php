<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * RotationRules Model
 *
 * @property \App\Model\Table\OffersTable&\Cake\ORM\Association\BelongsTo $Offers
 * @property \App\Model\Table\UsersRotationRulesTable&\Cake\ORM\Association\HasMany $UsersRotationRules
 *
 * @method \App\Model\Entity\RotationRule newEmptyEntity()
 * @method \App\Model\Entity\RotationRule newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\RotationRule[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\RotationRule get($primaryKey, $options = [])
 * @method \App\Model\Entity\RotationRule findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\RotationRule patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\RotationRule[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\RotationRule|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\RotationRule saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\RotationRule[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\RotationRule[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\RotationRule[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\RotationRule[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class RotationRulesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('rotation_rules');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Offers', [
            'foreignKey' => 'offer_id',
            'joinType' => 'LEFT',
        ]);

        $this->hasMany('UsersRotationRules', [
            'foreignKey' => 'rotation_rule_id',
            'dependent' => true,
            'cascadeCallbacks' => true,
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->integer('offer_id')
            ->allowEmptyString('offer_id');

        $validator
            ->scalar('period_type')
            ->maxLength('period_type', 20)
            ->inList('period_type', ['WEEKLY', 'MONTHLY'])
            ->requirePresence('period_type', 'create')
            ->notEmptyString('period_type');

        $validator
            ->integer('target_count')
            ->requirePresence('target_count', 'create')
            ->notEmptyString('target_count')
            ->greaterThanOrEqual('target_count', 1);

        $validator
            ->integer('shift_duration')
            ->requirePresence('shift_duration', 'create')
            ->notEmptyString('shift_duration')
            ->greaterThanOrEqual('shift_duration', 1);

        $validator
            ->time('time_window_start')
            ->requirePresence('time_window_start', 'create')
            ->notEmptyTime('time_window_start');

        $validator
            ->time('time_window_end')
            ->requirePresence('time_window_end', 'create')
            ->notEmptyTime('time_window_end');

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['offer_id'], 'Offers'), ['errorField' => 'offer_id']);

        return $rules;
    }
}
