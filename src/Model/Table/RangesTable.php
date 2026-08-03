<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Ranges Model
 *
 * @property \App\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Users
 * @property \App\Model\Table\OffersTable&\Cake\ORM\Association\BelongsTo $Offers
 *
 * @method \App\Model\Entity\Range newEmptyEntity()
 * @method \App\Model\Entity\Range newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Range[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Range get($primaryKey, $options = [])
 * @method \App\Model\Entity\Range findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\Range patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Range[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Range|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Range saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Range[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Range[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\Range[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Range[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class RangesTable extends Table
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

        $this->setTable('ranges');
        $this->setDisplayField('id');
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
            ->dateTime('date_start')
            ->requirePresence('date_start', 'create')
            ->notEmptyDateTime('date_start');

        $validator
            ->dateTime('date_end')
            ->requirePresence('date_end', 'create')
            ->notEmptyDateTime('date_end');

        $validator
            ->scalar('comment')
            ->maxLength('comment', 255)
            ->allowEmptyString('comment');

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
        $rules->add($rules->existsIn(['user_id'], 'Users'), ['errorField' => 'user_id']);
        $rules->add($rules->existsIn(['offer_id'], 'Offers'), ['errorField' => 'offer_id']);

        return $rules;
    }

    public function beforeSave($event, $entity, $options) {

    }

    public function findOffers(Query $query, array $offers_id)
    {
        $query->where([
            'offer_id IN' => $offers_id
        ]);

        return $query;
    }

    public function findDayRanges(Query $query, array $options)
    {
        $day_ranges = $options['day_ranges'];

        $query->contain('Offers')
            ->where([
                'date_start <=' => $day_ranges['end'],
                'date_end >=' => $day_ranges['begin']
            ])
            ->where([
                'Offers.offer_type !=' => 'absence'
            ])
            ->select(['id']);

            return $query->find('list');
    }
}
