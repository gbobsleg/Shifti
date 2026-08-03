<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateForecastScenariosOffers extends BaseMigration
{
    public function change(): void
    {
        if (!$this->hasTable('forecast_scenarios_offers')) {
            $table = $this->table('forecast_scenarios_offers');
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
        $table->addTimestamps('created', 'modified');
        $table->addIndex(['scenario_id'], ['name' => 'BY_SCENARIO_ID']);
        $table->addIndex(['offer_id'], ['name' => 'BY_OFFER_ID']);
        $table->addForeignKey('scenario_id', 'forecast_scenarios', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->addForeignKey('offer_id', 'offers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->create();
        }
    }
}

