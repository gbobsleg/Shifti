<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Skills Model
 *
 * @property \App\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Users
 * @property \App\Model\Table\OffersTable&\Cake\ORM\Association\BelongsTo $Offers
 *
 * @method \App\Model\Entity\Skill newEmptyEntity()
 * @method \App\Model\Entity\Skill newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Skill[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Skill get($primaryKey, $options = [])
 * @method \App\Model\Entity\Skill findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\Skill patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Skill[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Skill|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Skill saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Skill[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Skill[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\Skill[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Skill[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class SkillsTable extends Table
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

        $this->setTable('skills');
        $this->setDisplayField('id'); // ID is the primary field for a join table
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Offers', [
            'foreignKey' => 'offer_id',
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
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->date('validity_start')
            ->allowEmptyDate('validity_start'); // Date can be nullable (NULL)

        $validator
            ->date('validity_end')
            ->allowEmptyDate('validity_end'); // Date can be nullable (NULL)

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
        // Rule 1: user_id and offer_id must exist in their respective tables.
        $rules->add($rules->existsIn(['user_id'], 'Users'), ['errorField' => 'user_id']);
        $rules->add($rules->existsIn(['offer_id'], 'Offers'), ['errorField' => 'offer_id']);

        // Rule 2: Prevents duplicate entries (the UNIQUE KEY constraint in SQL).
        $rules->add($rules->isUnique(['user_id', 'offer_id']), ['errorField' => 'user_id', 'message' => 'This user already has this skill/offer.']);

        return $rules;
    }
}