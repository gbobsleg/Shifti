<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddForecastMethodToForecastScenariosOffers extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('forecast_scenarios_offers');

        if (!$table->hasColumn('forecast_method')) {
            $table->addColumn('forecast_method', 'string', [
                'default' => 'historical',
                'limit' => 20,
                'null' => false,
                'comment' => 'Méthode de prévision pour cette offre: historical ou prophet',
                'after' => 'offer_id',
            ]);
        }

        $table->update();
    }

    public function down(): void
    {
        $table = $this->table('forecast_scenarios_offers');

        if ($table->hasColumn('forecast_method')) {
            $table->removeColumn('forecast_method');
        }

        $table->update();
    }
}




















