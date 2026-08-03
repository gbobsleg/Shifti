<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * PlanningGenerationJobs Model
 *
 * @property \App\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Users
 * @property \App\Model\Table\WfmSettingsTable&\Cake\ORM\Association\BelongsTo $WfmSettings
 * @property \App\Model\Table\PlanningGenerationJobDaysTable&\Cake\ORM\Association\HasMany $PlanningGenerationJobDays
 * @property \App\Model\Table\DraftRangesTable&\Cake\ORM\Association\HasMany $DraftRanges
 */
class PlanningGenerationJobsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('planning_generation_jobs');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
            'joinType' => 'INNER',
        ]);

        $this->belongsTo('WfmSettings', [
            'foreignKey' => 'wfm_setting_id',
            'joinType' => 'INNER',
        ]);

        $this->hasMany('PlanningGenerationJobDays', [
            'foreignKey' => 'job_id',
            'dependent' => true,
            'cascadeCallbacks' => true,
        ]);

        $this->hasMany('DraftRanges', [
            'className' => 'DraftRanges',
            'foreignKey' => 'job_id',
            'dependent' => true,
            'cascadeCallbacks' => true,
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

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
            ->requirePresence('end_date', 'create')
            ->notEmptyDate('end_date');

        $validator
            ->integer('wfm_setting_id')
            ->requirePresence('wfm_setting_id', 'create')
            ->notEmptyString('wfm_setting_id');

        $validator
            ->scalar('status')
            ->maxLength('status', 40)
            ->requirePresence('status', 'create')
            ->notEmptyString('status');

        return $validator;
    }
}


