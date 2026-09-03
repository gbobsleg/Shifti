<?php
declare(strict_types=1);

namespace App\Model\Table;

use ArrayObject;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class RotationRuleLinesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('rotation_rule_lines');
        $this->setDisplayField('line_type');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->getSchema()->setColumnType('days_of_week', 'json');

        $this->belongsTo('RotationRules', [
            'foreignKey' => 'rotation_rule_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Offers', [
            'foreignKey' => 'offer_id',
            'joinType' => 'LEFT',
        ]);
        $this->hasMany('RotationRuleLineSlots', [
            'foreignKey' => 'rotation_rule_line_id',
            'dependent' => true,
            'cascadeCallbacks' => true,
            'saveStrategy' => 'replace',
            'sort' => ['position' => 'ASC'],
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('rotation_rule_id')
            ->allowEmptyString('rotation_rule_id')
            ->scalar('line_type')
            ->inList('line_type', ['quota', 'coverage'])
            ->requirePresence('line_type', 'create')
            ->integer('offer_id')->allowEmptyString('offer_id')
            ->integer('sort_order')->greaterThanOrEqual('sort_order', 1)
            ->integer('target_count')->allowEmptyString('target_count')
            ->integer('shift_duration')->allowEmptyString('shift_duration')
            ->time('time_window_start')->allowEmptyTime('time_window_start')
            ->time('time_window_end')->allowEmptyTime('time_window_end')
            ->boolean('fit_need_curve')
            ->integer('quantity')->allowEmptyString('quantity')
            ->boolean('equity_enabled')
            ->boolean('same_person_day_slots');

        $validator->add('line_type', 'quotaFields', [
            'rule' => function ($value, $context) {
                if ($value !== 'quota') {
                    return true;
                }
                $data = $context['data'] ?? [];
                $target = (int)($data['target_count'] ?? 0);
                $duration = (int)($data['shift_duration'] ?? 0);

                return $target >= 1 && $duration >= 1;
            },
            'message' => 'Une ligne quota exige une cible et une durée.',
        ]);

        $validator->add('line_type', 'coverageQuantity', [
            'rule' => function ($value, $context) {
                if ($value !== 'coverage') {
                    return true;
                }
                $data = $context['data'] ?? [];
                $qty = (int)($data['quantity'] ?? 0);

                return $qty >= 1;
            },
            'message' => 'Une ligne couverture exige un effectif visé ≥ 1.',
        ]);

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['rotation_rule_id'], 'RotationRules'), [
            'errorField' => 'rotation_rule_id',
            'allowNullable' => true,
        ]);
        $rules->add($rules->existsIn(['offer_id'], 'Offers'), [
            'errorField' => 'offer_id',
            'allowNullable' => true,
        ]);

        return $rules;
    }

    public function beforeSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        if ($entity->get('line_type') === 'quota') {
            $entity->set('quota_flag', 1);
        } else {
            $entity->set('quota_flag', null);
        }
    }
}
