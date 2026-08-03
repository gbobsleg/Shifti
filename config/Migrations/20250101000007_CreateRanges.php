<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateRanges extends BaseMigration
{
    public function change(): void
    {
        if (!$this->hasTable('ranges')) {
            $table = $this->table('ranges');
        $table->addColumn('user_id', 'integer', [
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);
        $table->addColumn('offer_id', 'integer', [
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);
        $table->addColumn('date_start', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('date_end', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('comment', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $table->addTimestamps('created', 'modified');
        $table->addIndex(['user_id'], ['name' => 'BY_USER_ID']);
        $table->addIndex(['offer_id'], ['name' => 'BY_OFFER_ID']);
        $table->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->addForeignKey('offer_id', 'offers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->create();
        }
    }
}

