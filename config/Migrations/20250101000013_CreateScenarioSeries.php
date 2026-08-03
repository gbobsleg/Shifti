<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateScenarioSeries extends BaseMigration
{
    public function change(): void
    {
        if (!$this->hasTable('scenario_series')) {
            $table = $this->table('scenario_series');
        $table->addColumn('scenario_id', 'integer', [
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);
        $table->addColumn('offer_id', 'integer', [
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);
        $table->addColumn('date', 'date', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('type', 'string', [
            'default' => null,
            'limit' => 50,
            'null' => false,
        ]);
        $table->addColumn('step_seconds', 'integer', [
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);
        $table->addColumn('start_time', 'time', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('end_time', 'time', [
            'default' => null,
            'null' => false,
        ]);
        $table->addTimestamps('created', 'modified');
        $table->addIndex(['scenario_id'], ['name' => 'BY_SCENARIO_ID']);
        $table->addIndex(['offer_id'], ['name' => 'BY_OFFER_ID']);
        $table->addIndex(['date'], ['name' => 'BY_DATE']);
        $table->addForeignKey('scenario_id', 'forecast_scenarios', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->addForeignKey('offer_id', 'offers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->create();
        }
    }
}

