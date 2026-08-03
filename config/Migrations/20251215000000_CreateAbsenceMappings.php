<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateAbsenceMappings extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('absence_mappings');
        $table->addColumn('excel_pattern', 'string', [
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('offer_id', 'integer', [
            'null' => false,
        ]);
        $table->addColumn('priority', 'integer', [
            'default' => 0,
            'null' => false,
        ]);
        $table->addTimestamps();
        $table->addIndex(['excel_pattern'], ['unique' => true]);
        $table->addForeignKey('offer_id', 'offers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->create();
    }
}


