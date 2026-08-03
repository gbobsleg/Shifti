<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateRegions extends BaseMigration
{
    public function change(): void
    {
        if (!$this->hasTable('regions')) {
            $table = $this->table('regions');
            $table->addColumn('name', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ]);
            $table->addColumn('number', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ]);
            $table->create();
        }
    }
}

