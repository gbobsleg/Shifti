<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateForecastScenarios extends BaseMigration
{
    public function change(): void
    {
        if (!$this->hasTable('forecast_scenarios')) {
            $table = $this->table('forecast_scenarios');
        $table->addColumn('name', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('start_date', 'date', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('end_date', 'date', [
            'default' => null,
            'null' => false,
        ]);
        $table->addTimestamps('created', 'modified');
        $table->create();
        }
    }
}

