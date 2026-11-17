<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Range scan offre + intervalle pour l'export annuel.
 * Les index séparés BY_OFFER_ID et BY_DATETIME_INTERVAL restent en place.
 */
class AddOfferDatetimeIndexToHistoricalData extends BaseMigration
{
    public function change(): void
    {
        if (!$this->hasTable('historical_data')) {
            return;
        }

        $table = $this->table('historical_data');
        if ($table->hasIndexByName('BY_OFFER_DATETIME')) {
            return;
        }

        $table
            ->addIndex(['offer_id', 'datetime_interval'], ['name' => 'BY_OFFER_DATETIME'])
            ->update();
    }
}
