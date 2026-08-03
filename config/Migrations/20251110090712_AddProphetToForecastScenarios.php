<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddProphetToForecastScenarios extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/4/en/migrations.html#the-change-method
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('forecast_scenarios');
        
        $table->addColumn('forecast_method', 'string', [
            'default' => 'historical',
            'limit' => 20,
            'null' => false,
            'comment' => 'Méthode de prévision: historical ou prophet'
        ]);
        
        $table->addColumn('prophet_settings_json', 'json', [
            'default' => null,
            'null' => true,
            'comment' => 'Paramètres Prophet si méthode=prophet'
        ]);
        
        $table->addColumn('prophet_metrics_json', 'json', [
            'default' => null,
            'null' => true,
            'comment' => 'Métriques de performance (MAPE, MAE, RMSE)'
        ]);
        
        $table->addIndex(['forecast_method'], ['name' => 'idx_forecast_method']);
        
        $table->update();
    }
}
