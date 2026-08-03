<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Service\ScheduleProblemBuilderService;
use Cake\I18n\DateTime;
use Cake\I18n\Time;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use DateTimeInterface;

/**
 * WfmSettings Model
 *
 * @method \App\Model\Entity\WfmSetting newEmptyEntity()
 * @method \App\Model\Entity\WfmSetting newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\WfmSetting> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\WfmSetting get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\WfmSetting findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\WfmSetting patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\WfmSetting> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\WfmSetting|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\WfmSetting saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\WfmSetting>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\WfmSetting>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\WfmSetting>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\WfmSetting> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\WfmSetting>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\WfmSetting>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\WfmSetting>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\WfmSetting> deleteManyOrFail(iterable $entities, array $options = [])
 */
class WfmSettingsTable extends Table
{
    /**
     * Initialize method
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('wfm_settings');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->getSchema()->setColumnType('prophet_defaults_json', 'json');
        $this->getSchema()->setColumnType('optuna_settings_json', 'json');
        $this->getSchema()->setColumnType('worked_days_json', 'json');

        $this->belongsTo('PauseOffers', [
            'className' => 'Offers',
            'foreignKey' => 'pause_offer_id',
            'joinType' => 'LEFT',
        ]);

        $this->belongsTo('LunchOffers', [
            'className' => 'Offers',
            'foreignKey' => 'lunch_offer_id',
            'joinType' => 'LEFT',
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
        $slot = ScheduleProblemBuilderService::SLOT_MINUTES;

        $validator
            ->scalar('name')
            ->maxLength('name', 100)
            ->notEmptyString('name');

        $validator
            ->time('day_start_time')
            ->notEmptyTime('day_start_time', 'L\'heure de début de journée doit être renseignée dans le profil WFM.');

        $validator
            ->time('day_end_time')
            ->notEmptyTime('day_end_time', 'L\'heure de fin de journée doit être renseignée dans le profil WFM.');

        $validator
            ->decimal('service_level_percent')
            ->notEmptyString('service_level_percent');

        $validator
            ->integer('service_level_seconds')
            ->notEmptyString('service_level_seconds');

        $validator
            ->decimal('shrinkage_percent')
            ->notEmptyString('shrinkage_percent');

        $validator
            ->time('lunch_start_time')
            ->notEmptyTime('lunch_start_time');

        $validator
            ->time('lunch_end_time')
            ->notEmptyTime('lunch_end_time');

        $validator
            ->integer('lunch_duration_minutes')
            ->greaterThan('lunch_duration_minutes', 0)
            ->notEmptyString('lunch_duration_minutes')
            ->add('lunch_duration_minutes', 'multipleOfSlot', [
                'rule' => function ($value) use ($slot) {
                    return $this->isMultipleOfSlot($value, $slot);
                },
                'message' => sprintf(
                    'La durée du repas doit être un multiple de %d minutes (pas de grille).',
                    $slot
                ),
            ]);

        $validator
            ->integer('am_pause_duration_minutes')
            ->greaterThan('am_pause_duration_minutes', 0)
            ->notEmptyString('am_pause_duration_minutes')
            ->add('am_pause_duration_minutes', 'multipleOfSlot', [
                'rule' => function ($value) use ($slot) {
                    return $this->isMultipleOfSlot($value, $slot);
                },
                'message' => sprintf(
                    'La durée de la pause AM doit être un multiple de %d minutes (pas de grille).',
                    $slot
                ),
            ]);

        $validator
            ->time('am_pause_start_time')
            ->notEmptyTime('am_pause_start_time');

        $validator
            ->time('am_pause_end_time')
            ->notEmptyTime('am_pause_end_time');

        $validator
            ->integer('pm_pause_duration_minutes')
            ->greaterThan('pm_pause_duration_minutes', 0)
            ->notEmptyString('pm_pause_duration_minutes')
            ->add('pm_pause_duration_minutes', 'multipleOfSlot', [
                'rule' => function ($value) use ($slot) {
                    return $this->isMultipleOfSlot($value, $slot);
                },
                'message' => sprintf(
                    'La durée de la pause PM doit être un multiple de %d minutes (pas de grille).',
                    $slot
                ),
            ]);

        $validator
            ->time('pm_pause_start_time')
            ->notEmptyTime('pm_pause_start_time');

        $validator
            ->time('pm_pause_end_time')
            ->notEmptyTime('pm_pause_end_time');

        $validator
            ->integer('min_block_minutes')
            ->greaterThan('min_block_minutes', 0)
            ->notEmptyString('min_block_minutes')
            ->add('min_block_minutes', 'multipleOfSlot', [
                'rule' => function ($value) use ($slot) {
                    return $this->isMultipleOfSlot($value, $slot);
                },
                'message' => sprintf(
                    'La durée minimale de bloc doit être un multiple de %d minutes (pas de grille).',
                    $slot
                ),
            ]);

        $validator
            ->integer('max_block_minutes')
            ->greaterThan('max_block_minutes', 0)
            ->notEmptyString('max_block_minutes')
            ->add('max_block_minutes', 'multipleOfSlot', [
                'rule' => function ($value) use ($slot) {
                    return $this->isMultipleOfSlot($value, $slot);
                },
                'message' => sprintf(
                    'La durée maximale de bloc doit être un multiple de %d minutes (pas de grille).',
                    $slot
                ),
            ]);

        $validator
            ->boolean('strict_work_hours')
            ->allowEmptyString('strict_work_hours');

        $validator
            ->boolean('forbid_midday_singletons')
            ->allowEmptyString('forbid_midday_singletons');

        $validator
            ->boolean('enforce_remote_work_incompatibilities')
            ->allowEmptyString('enforce_remote_work_incompatibilities');

        $validator
            ->add('worked_days_json', 'isArray', [
                'rule' => function ($value) {
                    return $value === null || is_array($value);
                },
                'message' => 'Les jours travaillés doivent être un tableau.',
            ])
            ->allowEmptyArray('worked_days_json');

        return $validator;
    }

    /**
     * Rules checking for cross-field time windows and block durations.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $slot = ScheduleProblemBuilderService::SLOT_MINUTES;

        $rules->add(function ($entity) {
            $start = $this->asDateTime($entity->day_start_time);
            $end = $this->asDateTime($entity->day_end_time);
            if ($start === null || $end === null) {
                return true;
            }

            return $start->lessThan($end);
        }, 'dayWindowOrder', [
            'errorField' => 'day_end_time',
            'message' => 'La fin de journée doit être strictement après le début de journée.',
        ]);

        $rules->add(function ($entity) {
            $start = $this->asDateTime($entity->lunch_start_time);
            $end = $this->asDateTime($entity->lunch_end_time);
            if ($start === null || $end === null) {
                return true;
            }

            return $start->lessThan($end);
        }, 'lunchWindowOrder', [
            'errorField' => 'lunch_end_time',
            'message' => 'La fin de la fenêtre repas doit être strictement après son début.',
        ]);

        $rules->add(function ($entity) {
            $start = $this->asDateTime($entity->am_pause_start_time);
            $end = $this->asDateTime($entity->am_pause_end_time);
            if ($start === null || $end === null) {
                return true;
            }

            return $start->lessThan($end);
        }, 'amPauseWindowOrder', [
            'errorField' => 'am_pause_end_time',
            'message' => 'La fin de la fenêtre pause AM doit être strictement après son début.',
        ]);

        $rules->add(function ($entity) {
            $start = $this->asDateTime($entity->pm_pause_start_time);
            $end = $this->asDateTime($entity->pm_pause_end_time);
            if ($start === null || $end === null) {
                return true;
            }

            return $start->lessThan($end);
        }, 'pmPauseWindowOrder', [
            'errorField' => 'pm_pause_end_time',
            'message' => 'La fin de la fenêtre pause PM doit être strictement après son début.',
        ]);

        // Retourner une string = échec + message (RuleInvoker). Éviter setError manuel :
        // avec errorField, Cake réécrirait ensuite la clé de règle avec "invalid".
        $rules->add(function ($entity) use ($slot) {
            $amEnd = $this->asDateTime($entity->am_pause_end_time);
            $lunchStart = $this->asDateTime($entity->lunch_start_time);
            if ($amEnd === null || $lunchStart === null) {
                return true;
            }

            $limit = $lunchStart->subMinutes($slot);
            if ($amEnd->lessThanOrEquals($limit)) {
                return true;
            }

            return sprintf(
                'Fin saisie : %s. Maximum autorisé : %s (début de la fenêtre repas %s moins %d min).',
                $amEnd->format('H:i'),
                $limit->format('H:i'),
                $lunchStart->format('H:i'),
                $slot
            );
        }, 'amPauseBeforeLunchMinusSlot', [
            'errorField' => 'am_pause_end_time',
        ]);

        $rules->add(function ($entity) {
            $pmStart = $this->asDateTime($entity->pm_pause_start_time);
            $lunchEnd = $this->asDateTime($entity->lunch_end_time);
            if ($pmStart === null || $lunchEnd === null) {
                return true;
            }

            if ($pmStart->greaterThanOrEquals($lunchEnd)) {
                return true;
            }

            return sprintf(
                'Début saisi : %s. Minimum autorisé : %s (fin de la fenêtre repas).',
                $pmStart->format('H:i'),
                $lunchEnd->format('H:i')
            );
        }, 'pmPauseAfterLunch', [
            'errorField' => 'pm_pause_start_time',
        ]);

        $rules->add(function ($entity) {
            if ($entity->min_block_minutes === null || $entity->max_block_minutes === null) {
                return true;
            }

            return (int)$entity->max_block_minutes >= (int)$entity->min_block_minutes;
        }, 'maxBlockGteMinBlock', [
            'errorField' => 'max_block_minutes',
            'message' => 'La durée maximale de bloc doit être supérieure ou égale à la durée minimale.',
        ]);

        return $rules;
    }

    /**
     * @param mixed $value
     */
    private function isMultipleOfSlot(mixed $value, int $slot): bool
    {
        if (!is_numeric($value)) {
            return false;
        }
        $intVal = (int)$value;
        if ($intVal <= 0 || $slot <= 0) {
            return false;
        }

        return ($intVal % $slot) === 0;
    }

    /**
     * Normalise un champ horaire hydraté (Time/DateTime) en DateTime Chronos
     * pour disposer de subMinutes() / comparaisons natives.
     *
     * @param mixed $value
     * @return \Cake\I18n\DateTime|null
     */
    private function asDateTime(mixed $value): ?DateTime
    {
        if ($value instanceof DateTime) {
            return DateTime::parse('1970-01-01 ' . $value->format('H:i:s'));
        }
        if ($value instanceof Time) {
            return DateTime::parse('1970-01-01 ' . $value->format('H:i:s'));
        }
        if ($value instanceof DateTimeInterface) {
            return DateTime::parse('1970-01-01 ' . $value->format('H:i:s'));
        }
        if (is_string($value) && $value !== '') {
            return DateTime::parse('1970-01-01 ' . $value);
        }

        return null;
    }

    /**
     * Normalise un champ horaire hydraté (Time/DateTime) pour comparaisons Chronos.
     *
     * @param mixed $value
     * @return \Cake\I18n\Time|null
     */
    private function asTime(mixed $value): ?Time
    {
        if ($value instanceof Time) {
            return $value;
        }
        if ($value instanceof DateTime || $value instanceof DateTimeInterface) {
            return Time::parse($value->format('H:i:s'));
        }
        if (is_string($value) && $value !== '') {
            return Time::parse($value);
        }

        return null;
    }
}
