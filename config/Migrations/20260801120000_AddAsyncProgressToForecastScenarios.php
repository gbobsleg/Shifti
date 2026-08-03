<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Colonnes de suivi async pour les scénarios de prévision (worker back-office).
 */
class AddAsyncProgressToForecastScenarios extends BaseMigration
{
    public function change(): void
    {
        if (!$this->hasTable('forecast_scenarios')) {
            return;
        }

        $table = $this->table('forecast_scenarios');

        if (!$table->hasColumn('started_at')) {
            $table->addColumn('started_at', 'datetime', [
                'default' => null,
                'null' => true,
            ]);
        }
        if (!$table->hasColumn('finished_at')) {
            $table->addColumn('finished_at', 'datetime', [
                'default' => null,
                'null' => true,
            ]);
        }
        if (!$table->hasColumn('error_message')) {
            $table->addColumn('error_message', 'text', [
                'default' => null,
                'null' => true,
            ]);
        }
        if (!$table->hasColumn('progress_offer_id')) {
            $table->addColumn('progress_offer_id', 'integer', [
                'default' => null,
                'null' => true,
            ]);
        }
        if (!$table->hasColumn('progress_offer_name')) {
            $table->addColumn('progress_offer_name', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ]);
        }
        if (!$table->hasColumn('progress_date')) {
            $table->addColumn('progress_date', 'date', [
                'default' => null,
                'null' => true,
            ]);
        }
        if (!$table->hasColumn('progress_offers_done')) {
            $table->addColumn('progress_offers_done', 'integer', [
                'default' => 0,
                'null' => false,
            ]);
        }
        if (!$table->hasColumn('progress_offers_total')) {
            $table->addColumn('progress_offers_total', 'integer', [
                'default' => 0,
                'null' => false,
            ]);
        }
        if (!$table->hasColumn('progress_days_done')) {
            $table->addColumn('progress_days_done', 'integer', [
                'default' => 0,
                'null' => false,
            ]);
        }
        if (!$table->hasColumn('progress_days_total')) {
            $table->addColumn('progress_days_total', 'integer', [
                'default' => 0,
                'null' => false,
            ]);
        }

        $table->update();
    }
}
