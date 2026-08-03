<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateAlerts extends BaseMigration
{
    public function change(): void
    {
        if (!$this->hasTable('alerts')) {
            $table = $this->table('alerts');
        $table->addColumn('date_start', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('date_end', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('content', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('priority', 'integer', [
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);
        $table->addIndex(['date_start', 'date_end'], ['name' => 'BY_DATE_RANGE']);
        $table->create();
        }
    }
}

