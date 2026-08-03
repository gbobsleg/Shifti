<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

class ForecastScenariosOffer extends Entity
{
    protected array $_accessible = [
        'scenario_id' => true,
        'offer_id' => true,
        'forecast_method' => true,
        'prophet_settings_json' => true,
        'created' => true,
        'modified' => true,
        'forecast_scenario' => true,
        'offer' => true,
    ];
}


