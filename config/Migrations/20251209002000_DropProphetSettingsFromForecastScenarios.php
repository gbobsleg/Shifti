<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class DropProphetSettingsFromForecastScenarios extends BaseMigration
{
    public function change(): void
    {
        if ($this->hasTable('forecast_scenarios')) {
            $table = $this->table('forecast_scenarios');

            if ($table->hasColumn('prophet_settings_json')) {
                $table->removeColumn('prophet_settings_json');
                $table->update();
            }
        }
    }
}



















