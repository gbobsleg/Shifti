<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateRoles extends BaseMigration
{
    public function change(): void
    {
        if (!$this->hasTable('roles')) {
            $table = $this->table('roles');
            $table->addColumn('name', 'string', [
                'default' => null,
                'limit' => 255,
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

