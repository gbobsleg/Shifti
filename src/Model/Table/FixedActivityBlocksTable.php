<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class FixedActivityBlocksTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('fixed_activity_blocks');
        $this->setPrimaryKey('id');
        $this->setDisplayField('id');

        $this->belongsTo('FixedActivityRules', [
            'foreignKey' => 'fixed_activity_rule_id',
            'joinType' => 'INNER',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('fixed_activity_rule_id')->notEmptyString('fixed_activity_rule_id')
            ->time('start_time')->notEmptyTime('start_time')
            ->time('end_time')->notEmptyTime('end_time')
            ->integer('position')->allowEmptyString('position');

        return $validator;
    }
}


