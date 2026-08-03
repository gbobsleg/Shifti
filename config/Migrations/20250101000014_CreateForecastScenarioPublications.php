<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateForecastScenarioPublications extends BaseMigration
{
    public function change(): void
    {
        if (!$this->hasTable('forecast_scenario_publications')) {
            $table = $this->table('forecast_scenario_publications');
        $table->addColumn('scenario_id', 'integer', [
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);
        $table->addColumn('date', 'date', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('published_at', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        $table->addTimestamps('created', 'modified');
        $table->addIndex(['scenario_id'], ['name' => 'BY_SCENARIO_ID']);
        $table->addIndex(['date'], ['name' => 'UNIQUE_DATE', 'unique' => true]);
        $table->addForeignKey('scenario_id', 'forecast_scenarios', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->create();
        }
    }
}

