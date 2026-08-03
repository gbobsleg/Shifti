<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * PlanningGenerationJobDays Model
 *
 * @property \App\Model\Table\PlanningGenerationJobsTable&\Cake\ORM\Association\BelongsTo $PlanningGenerationJobs
 */
class PlanningGenerationJobDaysTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('planning_generation_job_days');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('PlanningGenerationJobs', [
            'foreignKey' => 'job_id',
            'joinType' => 'INNER',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->integer('job_id')
            ->requirePresence('job_id', 'create')
            ->notEmptyString('job_id');

        $validator
            ->date('date')
            ->requirePresence('date', 'create')
            ->notEmptyDate('date');

        $validator
            ->scalar('status')
            ->maxLength('status', 30)
            ->requirePresence('status', 'create')
            ->notEmptyString('status');

        return $validator;
    }
}


