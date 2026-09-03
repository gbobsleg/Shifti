<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class RotationRuleLineSlotsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('rotation_rule_line_slots');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('RotationRuleLines', [
            'foreignKey' => 'rotation_rule_line_id',
            'joinType' => 'INNER',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('rotation_rule_line_id')
            ->allowEmptyString('rotation_rule_line_id')
            ->time('start_time')->notEmptyTime('start_time')
            ->time('end_time')->notEmptyTime('end_time')
            ->integer('position')->allowEmptyString('position');

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['rotation_rule_line_id'], 'RotationRuleLines'), [
            'errorField' => 'rotation_rule_line_id',
            'allowNullable' => true,
        ]);

        return $rules;
    }
}
