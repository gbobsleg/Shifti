<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * ProphetTuningJob Entity
 *
 * @property int $id
 * @property int $offer_id
 * @property int|null $created_by
 * @property string $trigger_type
 * @property string $status
 * @property array|null $config_snapshot_json
 * @property array|null $baseline_params_json
 * @property array|null $baseline_scores_json
 * @property array|null $best_params_json
 * @property array|null $best_scores_json
 * @property bool $auto_applied
 * @property int $progress_trials_done
 * @property int $progress_trials_total
 * @property float|null $best_mae_so_far
 * @property string|null $error_message
 * @property \Cake\I18n\DateTime|null $started_at
 * @property \Cake\I18n\DateTime|null $finished_at
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\Offer $offer
 * @property \App\Model\Entity\User|null $user
 */
class ProphetTuningJob extends Entity
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const TRIGGER_MANUAL = 'manual';
    public const TRIGGER_CRON = 'cron';

    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'offer_id' => true,
        'created_by' => true,
        'trigger_type' => true,
        'status' => true,
        'config_snapshot_json' => true,
        'baseline_params_json' => true,
        'baseline_scores_json' => true,
        'best_params_json' => true,
        'best_scores_json' => true,
        'auto_applied' => true,
        'progress_trials_done' => true,
        'progress_trials_total' => true,
        'best_mae_so_far' => true,
        'error_message' => true,
        'started_at' => true,
        'finished_at' => true,
        'created' => true,
        'modified' => true,
        'offer' => true,
        'user' => true,
    ];
}
