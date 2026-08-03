<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

class ScenarioSeries extends Entity
{
    protected array $_accessible = [
        'scenario_id' => true,
        'offer_id' => true,
        'date' => true,
        'type' => true,
        'step_seconds' => true,
        'start_time' => true,
        'end_time' => true,
        'data_json' => true,
        'created' => true,
        'modified' => true,
        'forecast_scenario' => true,
        'offer' => true,
    ];
}


