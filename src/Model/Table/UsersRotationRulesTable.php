<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * UsersRotationRules Model
 *
 * @property \App\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Users
 * @property \App\Model\Table\RotationRulesTable&\Cake\ORM\Association\BelongsTo $RotationRules
 *
 * @method \App\Model\Entity\UsersRotationRule newEmptyEntity()
 * @method \App\Model\Entity\UsersRotationRule newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\UsersRotationRule[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\UsersRotationRule get($primaryKey, $options = [])
 * @method \App\Model\Entity\UsersRotationRule findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\UsersRotationRule patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\UsersRotationRule[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\UsersRotationRule|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\UsersRotationRule saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\UsersRotationRule[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\UsersRotationRule[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\UsersRotationRule[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\UsersRotationRule[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class UsersRotationRulesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('users_rotation_rules');
        $this->setDisplayField('user_id');
        $this->setPrimaryKey(['user_id', 'rotation_rule_id']);

        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
            'joinType' => 'INNER',
        ]);

        $this->belongsTo('RotationRules', [
            'foreignKey' => 'rotation_rule_id',
            'joinType' => 'INNER',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('user_id')
            ->requirePresence('user_id', 'create')
            ->notEmptyString('user_id');

        $validator
            ->uuid('rotation_rule_id')
            ->requirePresence('rotation_rule_id', 'create')
            ->notEmptyString('rotation_rule_id');

        $validator
            ->integer('target_count_override')
            ->allowEmptyString('target_count_override')
            ->greaterThanOrEqual('target_count_override', 1);

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['user_id'], 'Users'), ['errorField' => 'user_id']);
        $rules->add($rules->existsIn(['rotation_rule_id'], 'RotationRules'), ['errorField' => 'rotation_rule_id']);

        return $rules;
    }
}
