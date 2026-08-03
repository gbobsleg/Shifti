<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

class ForecastScenario extends Entity
{
    public const STATUS_QUEUED = 'queued';

    protected array $_accessible = [
        'name' => true,
        'start_date' => true,
        'end_date' => true,
        'settings_snapshot_json' => true,
        'status' => true,
        'prophet_metrics_json' => true,
        'created_by' => true,
        'created' => true,
        'modified' => true,
        'forecast_scenarios_offers' => true,
        'scenario_series' => true,
        'started_at' => true,
        'finished_at' => true,
        'error_message' => true,
        'progress_offer_id' => true,
        'progress_offer_name' => true,
        'progress_date' => true,
        'progress_offers_done' => true,
        'progress_offers_total' => true,
        'progress_days_done' => true,
        'progress_days_total' => true,
    ];
}
