<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddPauseAndLunchOffersToWfmSettings extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/4/en/migrations.html#the-change-method
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('wfm_settings');
        $table->addColumn('pause_offer_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
        ]);
        $table->addColumn('lunch_offer_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
        ]);
        $table->addIndex([
            'pause_offer_id',
        
            ], [
            'name' => 'BY_PAUSE_OFFER_ID',
            'unique' => false,
        ]);
        $table->addIndex([
            'lunch_offer_id',
        ], [
            'name' => 'BY_LUNCH_OFFER_ID',
            'unique' => false,
        ]);
        $table->addForeignKey('pause_offer_id', 'offers', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE']);
        $table->addForeignKey('lunch_offer_id', 'offers', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE']);
        $table->update();
    }
}
