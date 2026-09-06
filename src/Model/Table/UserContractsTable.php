<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * UserContracts Model
 *
 * @property \App\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Users
 *
 * @method \App\Model\Entity\UserContract newEmptyEntity()
 * @method \App\Model\Entity\UserContract newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\UserContract[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\UserContract get($primaryKey, $options = [])
 * @method \App\Model\Entity\UserContract findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\UserContract patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\UserContract[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\UserContract|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\UserContract saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\UserContract[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\UserContract[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\UserContract|false delete(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\UserContract deleteOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class UserContractsTable extends Table
{
    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('user_contracts');
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
            ->requirePresence('user_id', 'create')
            ->notEmptyString('user_id');

        $validator
            ->date('start_date')
            ->requirePresence('start_date', 'create')
            ->notEmptyDate('start_date');

        $validator
            ->date('end_date')
            ->allowEmptyDate('end_date')
            ->add('end_date', 'validEndDate', [
                'rule' => function ($value, $context) {
                    if (empty($value)) {
                        return true;
                    }
                    $startDate = $context['data']['start_date'] ?? null;
                    if (!$startDate && !empty($context['providers']['entity'])) {
                        $startDate = $context['providers']['entity']->start_date ?? null;
                    }
                    if (!$startDate) {
                        return true;
                    }
                    $end = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string)$value;
                    $start = $startDate instanceof \DateTimeInterface ? $startDate->format('Y-m-d') : (string)$startDate;

                    return $end >= $start;
                },
                'message' => 'La date de fin doit être postérieure ou égale à la date de début.'
            ]);

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
        $rules->add($rules->existsIn('user_id', 'Users'), ['errorField' => 'user_id']);

        $rules->add(function ($entity, $options) {
            if (!$entity->isNew()) {
                return true;
            }
            $existing = $this->find()
                ->where([
                    'user_id' => $entity->user_id,
                    'end_date IS' => null,
                ])
                ->first();

            return $existing === null;
        }, 'noOpenContract', [
            'errorField' => 'start_date',
            'message' => 'Un contrat sans date de fin existe déjà. Indiquez-lui une date de fin avant d\'en créer un autre.',
        ]);

        $rules->add(function ($entity, $options) {
            $userId = $entity->user_id ?? null;
            $start = $this->dateString($entity->start_date);
            if (!$userId || $start === null) {
                return true;
            }
            $end = $this->dateString($entity->end_date);
            $query = $this->find()->where(['user_id' => $userId]);
            if (!$entity->isNew() && $entity->id) {
                $query->where(['id IS NOT' => $entity->id]);
            }
            foreach ($query as $other) {
                if ($this->periodsOverlap(
                    $start,
                    $end,
                    $this->dateString($other->start_date),
                    $this->dateString($other->end_date)
                )) {
                    return false;
                }
            }

            return true;
        }, 'noOverlap', [
            'errorField' => 'start_date',
            'message' => 'Ce contrat chevauche une autre période. La nouvelle période doit commencer après la fin de la précédente.',
        ]);

        return $rules;
    }

    private function dateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string)$value;
    }

    private function periodsOverlap(?string $startA, ?string $endA, ?string $startB, ?string $endB): bool
    {
        if ($startA === null || $startB === null) {
            return false;
        }
        $endA = $endA ?? '9999-12-31';
        $endB = $endB ?? '9999-12-31';

        return $startA <= $endB && $startB <= $endA;
    }
}
