<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * DraftRanges Model (planning_range_drafts)
 *
 * Alias: DraftRanges
 * Table: planning_range_drafts
 */
class DraftRangesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('planning_range_drafts');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('PlanningGenerationJobs', [
            'foreignKey' => 'job_id',
            'joinType' => 'INNER',
        ]);

        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
            'joinType' => 'INNER',
        ]);

        $this->belongsTo('Offers', [
            'foreignKey' => 'offer_id',
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
            ->integer('user_id')
            ->requirePresence('user_id', 'create')
            ->notEmptyString('user_id');

        $validator
            ->integer('offer_id')
            ->requirePresence('offer_id', 'create')
            ->notEmptyString('offer_id');

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

        $validator
            ->scalar('source')
            ->maxLength('source', 50)
            ->allowEmptyString('source');

        return $validator;
    }

    /**
     * Filtre par job et plage de dates (chevauchement).
     *
     * @param array{job_id:int, begin:\DateTimeInterface, end:\DateTimeInterface} $options
     */
    public function findForJobAndRange(Query $query, array $options): Query
    {
        $jobId = (int)($options['job_id'] ?? 0);
        $begin = $options['begin'] ?? null;
        $end = $options['end'] ?? null;

        if ($jobId <= 0 || !$begin || !$end) {
            return $query->where(['1 = 0']);
        }

        return $query->where([
            'DraftRanges.job_id' => $jobId,
            'DraftRanges.date_start <=' => $end,
            'DraftRanges.date_end >=' => $begin,
        ]);
    }
}


