<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateSites extends BaseMigration
{
    public function change(): void
    {
        if (!$this->hasTable('sites')) {
            $table = $this->table('sites');
            $table->addColumn('name', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ]);
            $table->addColumn('number', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ]);
            $table->addColumn('region_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ]);
            $table->addIndex(['region_id'], ['name' => 'BY_REGION_ID']);
            $table->addForeignKey('region_id', 'regions', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE']);
            $table->create();
        }
    }
}

