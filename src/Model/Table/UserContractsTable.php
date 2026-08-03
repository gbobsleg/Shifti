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
                    if (empty($value)) return true;
                    $startDate = $context['data']['start_date'] ?? null;
                    if (!$startDate) return true;
                    return $value > $startDate;
                },
                'message' => 'La date de fin doit être postérieure à la date de début.'
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

        // Règle métier : Empêcher de créer un nouveau contrat si le précédent n'est pas clôturé
        $rules->add(function ($entity, $options) {
            if ($entity->isNew()) {
                $existing = $this->find()
                    ->where([
                        'user_id' => $entity->user_id,
                        'end_date IS' => null,
                    ])
                    ->first();
                return $existing === null;
            }
            return true;
        }, 'noOpenContract', [
            'errorField' => 'start_date',
            'message' => 'Un contrat actif existe déjà. Clôturez-le avant d\'en créer un nouveau.'
        ]);

        return $rules;
    }
}
