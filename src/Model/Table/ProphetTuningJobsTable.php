<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\ProphetTuningJob;
use Cake\Database\Expression\QueryExpression;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * ProphetTuningJobs Model
 *
 * @property \App\Model\Table\OffersTable&\Cake\ORM\Association\BelongsTo $Offers
 * @property \App\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Users
 *
 * @method \App\Model\Entity\ProphetTuningJob newEmptyEntity()
 * @method \App\Model\Entity\ProphetTuningJob newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\ProphetTuningJob> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\ProphetTuningJob get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\ProphetTuningJob findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\ProphetTuningJob patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\ProphetTuningJob> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\ProphetTuningJob|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\ProphetTuningJob saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class ProphetTuningJobsTable extends Table
{
    /**
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('prophet_tuning_jobs');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->getSchema()->setColumnType('config_snapshot_json', 'json');
        $this->getSchema()->setColumnType('baseline_params_json', 'json');
        $this->getSchema()->setColumnType('baseline_scores_json', 'json');
        $this->getSchema()->setColumnType('best_params_json', 'json');
        $this->getSchema()->setColumnType('best_scores_json', 'json');

        $this->belongsTo('Offers', [
            'foreignKey' => 'offer_id',
            'joinType' => 'INNER',
        ]);

        $this->belongsTo('Users', [
            'foreignKey' => 'created_by',
            'joinType' => 'LEFT',
        ]);
    }

    /**
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->integer('offer_id')
            ->requirePresence('offer_id', 'create')
            ->notEmptyString('offer_id');

        $validator
            ->integer('created_by')
            ->allowEmptyString('created_by');

        $validator
            ->scalar('trigger_type')
            ->maxLength('trigger_type', 20)
            ->inList('trigger_type', ['manual', 'cron'])
            ->requirePresence('trigger_type', 'create')
            ->notEmptyString('trigger_type');

        $validator
            ->scalar('status')
            ->maxLength('status', 20)
            ->inList('status', ['queued', 'running', 'completed', 'failed', 'cancelled'])
            ->requirePresence('status', 'create')
            ->notEmptyString('status');

        $validator
            ->boolean('auto_applied')
            ->notEmptyString('auto_applied');

        $validator
            ->integer('progress_trials_done')
            ->notEmptyString('progress_trials_done');

        $validator
            ->integer('progress_trials_total')
            ->notEmptyString('progress_trials_total');

        $validator
            ->numeric('best_mae_so_far')
            ->allowEmptyString('best_mae_so_far');

        $validator
            ->scalar('error_message')
            ->allowEmptyString('error_message');

        $validator
            ->dateTime('started_at')
            ->allowEmptyDateTime('started_at');

        $validator
            ->dateTime('finished_at')
            ->allowEmptyDateTime('finished_at');

        return $validator;
    }

    /**
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['offer_id'], 'Offers'), ['errorField' => 'offer_id']);
        $rules->add($rules->existsIn(['created_by'], 'Users'), [
            'errorField' => 'created_by',
            'allowNullableNulls' => true,
        ]);

        return $rules;
    }

    /**
     * Annule un job queued/running. finished_at via UTC_TIMESTAMP() SQL.
     *
     * @return array{ok: bool, message: string, job_id: int|null, previous_status: string|null}
     */
    public function cancelActiveJob(int $jobId, string $reason = 'Annulé depuis l’interface.'): array
    {
        $job = $this->find()
            ->select(['id', 'status'])
            ->where(['id' => $jobId])
            ->contain([])
            ->first();

        if (!$job) {
            return [
                'ok' => false,
                'message' => 'Tâche introuvable.',
                'job_id' => null,
                'previous_status' => null,
            ];
        }

        $previous = (string)$job->status;
        if (!in_array($previous, [
            ProphetTuningJob::STATUS_QUEUED,
            ProphetTuningJob::STATUS_RUNNING,
        ], true)) {
            $previousLabel = match ($previous) {
                ProphetTuningJob::STATUS_QUEUED => 'en file',
                ProphetTuningJob::STATUS_RUNNING => 'en cours',
                ProphetTuningJob::STATUS_COMPLETED => 'terminé',
                ProphetTuningJob::STATUS_FAILED => 'échec',
                ProphetTuningJob::STATUS_CANCELLED => 'annulé',
                default => $previous,
            };

            return [
                'ok' => false,
                'message' => sprintf(
                    'La tâche #%d n’est pas annulable (état « %s »).',
                    $jobId,
                    $previousLabel
                ),
                'job_id' => $jobId,
                'previous_status' => $previous,
            ];
        }

        $statement = $this->updateQuery()
            ->set([
                'status' => ProphetTuningJob::STATUS_CANCELLED,
                'error_message' => mb_substr($reason, 0, 65000),
                'finished_at' => new QueryExpression('UTC_TIMESTAMP()'),
                'modified' => new QueryExpression('UTC_TIMESTAMP()'),
            ])
            ->where([
                'id' => $jobId,
                'status IN' => [
                    ProphetTuningJob::STATUS_QUEUED,
                    ProphetTuningJob::STATUS_RUNNING,
                ],
            ])
            ->execute();

        if ($statement->rowCount() < 1) {
            return [
                'ok' => false,
                'message' => sprintf(
                    'Impossible d’annuler la tâche #%d (état déjà modifié).',
                    $jobId
                ),
                'job_id' => $jobId,
                'previous_status' => $previous,
            ];
        }

        return [
            'ok' => true,
            'message' => sprintf('Tâche #%d annulée.', $jobId),
            'job_id' => $jobId,
            'previous_status' => $previous,
        ];
    }
}
