<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class RenameSettingsOverrideToProphetSettingsOnForecastScenariosOffers extends BaseMigration
{
    public function change(): void
    {
        if ($this->hasTable('forecast_scenarios_offers')) {
            $table = $this->table('forecast_scenarios_offers');

            if ($table->hasColumn('settings_override_json') && !$table->hasColumn('prophet_settings_json')) {
                $table->renameColumn('settings_override_json', 'prophet_settings_json');
                $table->update();
            }
        }
    }
}



















