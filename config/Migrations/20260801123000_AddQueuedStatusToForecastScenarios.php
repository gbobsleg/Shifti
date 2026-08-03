<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Ajoute la valeur enum `queued` (file async worker forecast).
 */
class AddQueuedStatusToForecastScenarios extends BaseMigration
{
    public function up(): void
    {
        if (!$this->hasTable('forecast_scenarios')) {
            return;
        }

        // Corriger les lignes déjà corrompues par un UPDATE 'queued' sous l'ancien enum
        $this->execute(
            "UPDATE forecast_scenarios SET status = 'draft' WHERE status = '' OR status IS NULL"
        );

        $this->execute(
            "ALTER TABLE forecast_scenarios
             MODIFY COLUMN status ENUM('draft','queued','running','completed','failed')
             NOT NULL DEFAULT 'draft'"
        );
    }

    public function down(): void
    {
        if (!$this->hasTable('forecast_scenarios')) {
            return;
        }

        $this->execute(
            "UPDATE forecast_scenarios SET status = 'draft' WHERE status = 'queued'"
        );

        $this->execute(
            "ALTER TABLE forecast_scenarios
             MODIFY COLUMN status ENUM('draft','running','completed','failed')
             NOT NULL DEFAULT 'draft'"
        );
    }
}
