<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * PlanningGenerationJob Entity
 *
 * @property int $id
 * @property int $user_id
 * @property \Cake\I18n\FrozenDate $start_date
 * @property \Cake\I18n\FrozenDate $end_date
 * @property int $wfm_setting_id
 * @property int|null $scenario_id
 * @property string|null $options_json
 * @property string $status
 * @property int $total_days
 * @property int $processed_days
 * @property \Cake\I18n\FrozenDate|null $current_day
 * @property string|null $current_step
 * @property int|null $eta_seconds
 * @property string|null $equity_state_json
 * @property string|null $report_json
 * @property string|null $error_message
 * @property \Cake\I18n\FrozenTime|null $created
 * @property \Cake\I18n\FrozenTime|null $modified
 * @property \Cake\I18n\FrozenTime|null $started_at
 * @property \Cake\I18n\FrozenTime|null $finished_at
 *
 * @property \App\Model\Entity\User $user
 * @property \App\Model\Entity\WfmSetting $wfm_setting
 * @property \App\Model\Entity\PlanningGenerationJobDay[] $planning_generation_job_days
 * @property \App\Model\Entity\DraftRange[] $draft_ranges
 */
class PlanningGenerationJob extends Entity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'user_id' => true,
        'start_date' => true,
        'end_date' => true,
        'wfm_setting_id' => true,
        'scenario_id' => true,
        'options_json' => true,
        'status' => true,
        'total_days' => true,
        'processed_days' => true,
        'current_day' => true,
        'current_step' => true,
        'eta_seconds' => true,
        'equity_state_json' => true,
        'report_json' => true,
        'error_message' => true,
        'created' => true,
        'modified' => true,
        'started_at' => true,
        'finished_at' => true,
        'debug_rotation_only' => true,
        'user' => true,
        'wfm_setting' => true,
        'planning_generation_job_days' => true,
        'draft_ranges' => true,
    ];
}


