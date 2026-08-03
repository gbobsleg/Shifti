<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateHistoricalData extends BaseMigration
{
    public function change(): void
    {
        if (!$this->hasTable('historical_data')) {
            $table = $this->table('historical_data');
        $table->addColumn('offer_id', 'integer', [
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);
        $table->addColumn('datetime_interval', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('call_volume', 'integer', [
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);
        $table->addColumn('avg_handle_time_seconds', 'integer', [
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);
        $table->addIndex(['offer_id'], ['name' => 'BY_OFFER_ID']);
        $table->addIndex(['datetime_interval'], ['name' => 'BY_DATETIME_INTERVAL']);
        $table->addForeignKey('offer_id', 'offers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->create();
        }
    }
}

