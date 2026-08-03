<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * WfmSetting Entity
 *
 * @property int $id
 * @property string $name
 * @property string $service_level_percent
 * @property int $service_level_seconds
 * @property string $shrinkage_percent
 * @property \Cake\I18n\Time|null $day_start_time
 * @property \Cake\I18n\Time|null $day_end_time
 * @property \Cake\I18n\Time $lunch_start_time
 * @property \Cake\I18n\Time $lunch_end_time
 * @property int $lunch_duration_minutes
 * @property int $am_pause_duration_minutes
 * @property \Cake\I18n\Time $am_pause_start_time
 * @property \Cake\I18n\Time $am_pause_end_time
 * @property int $pm_pause_duration_minutes
 * @property \Cake\I18n\Time $pm_pause_start_time
 * @property \Cake\I18n\Time $pm_pause_end_time
 * @property int $min_block_minutes
 * @property int $max_block_minutes
 * @property array|null $prophet_defaults_json
 * @property array|null $optuna_settings_json
 * @property array|null $worked_days_json
 * @property bool|null $strict_work_hours
 * @property bool $enable_am_pm_breaks
 * @property bool $enforce_remote_work_incompatibilities
 */
class WfmSetting extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'name' => true,
        'service_level_percent' => true,
        'service_level_seconds' => true,
        'shrinkage_percent' => true,
        'day_start_time' => true,
        'day_end_time' => true,
        'half_day_pivot' => true,
        'lunch_start_time' => true,
        'lunch_end_time' => true,
        'lunch_duration_minutes' => true,
        'am_pause_duration_minutes' => true,
        'am_pause_start_time' => true,
        'am_pause_end_time' => true,
        'pm_pause_duration_minutes' => true,
        'pm_pause_start_time' => true,
        'pm_pause_end_time' => true,
        'min_block_minutes' => true,
        'max_block_minutes' => true,
        'strict_work_hours' => true,
        'enable_am_pm_breaks' => true,
        'forbid_midday_singletons' => true,
        'prophet_defaults_json' => true,
        'optuna_settings_json' => true,
        'pause_offer_id' => true,
        'lunch_offer_id' => true,
        'pause_offer' => true,
        'lunch_offer' => true,
        'enforce_remote_work_incompatibilities' => true,
        'worked_days_json' => true,
    ];
}
