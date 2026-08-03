<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class DropForecastMethodFromForecastScenarios extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('forecast_scenarios');

        if ($table->hasColumn('forecast_method')) {
            $table->removeColumn('forecast_method');
        }

        // On garde prophet_metrics_json tel quel
        $table->update();
    }

    public function down(): void
    {
        $table = $this->table('forecast_scenarios');

        if (!$table->hasColumn('forecast_method')) {
            $table->addColumn('forecast_method', 'string', [
                'default' => 'historical',
                'limit' => 20,
                'null' => false,
                'comment' => 'Méthode de prévision: historical ou prophet',
            ]);
            $table->addIndex(['forecast_method'], ['name' => 'idx_forecast_method']);
        }

        $table->update();
    }
}




















