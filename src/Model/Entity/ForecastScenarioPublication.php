<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

class ForecastScenarioPublication extends Entity
{
    protected array $_accessible = [
        'scenario_id' => true,
        'date' => true,
        'published_by' => true,
        'published_at' => true,
        'forecast_scenario' => true,
    ];
}


