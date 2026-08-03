<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Alerts Model
 *
 * @method \App\Model\Entity\Alert newEmptyEntity()
 * @method \App\Model\Entity\Alert newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Alert[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Alert get($primaryKey, $options = [])
 * @method \App\Model\Entity\Alert findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\Alert patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Alert[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Alert|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Alert saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Alert[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Alert[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\Alert[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Alert[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class AlertsTable extends Table
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

        $this->setTable('alerts');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');
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
            ->scalar('content')
            ->maxLength('content', 255)
            ->requirePresence('content', 'create')
            ->notEmptyString('content');

        $validator
            ->integer('priority')
            ->requirePresence('priority', 'create')
            ->notEmptyString('priority');

        return $validator;
    }

    public function findThisDay(Query $query, array $day_ranges)
    {
        $query->where([
            ['date_start <=' => $day_ranges['end'], 'date_end >=' => $day_ranges['begin']],
        ]);

        return $query;
    }
}
