<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateOffers extends BaseMigration
{
    public function change(): void
    {
        if (!$this->hasTable('offers')) {
            $table = $this->table('offers');
        $table->addColumn('name', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('start_date', 'date', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('end_date', 'date', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('color', 'string', [
            'default' => null,
            'limit' => 7,
            'null' => false,
        ]);
        $table->addColumn('priority', 'integer', [
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);
        $table->addTimestamps('created', 'modified');
        $table->create();
        }
    }
}

