<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateFixedActivityBlocks extends BaseMigration
{
    public function change(): void
    {
        if ($this->hasTable('fixed_activity_blocks')) {
            return;
        }

        $table = $this->table('fixed_activity_blocks');
        $table
            ->addColumn('fixed_activity_rule_id', 'integer', [
                'default' => null,
                'null' => false,
            ])
            ->addColumn('start_time', 'time', [
                'default' => null,
                'null' => false,
            ])
            ->addColumn('end_time', 'time', [
                'default' => null,
                'null' => false,
            ])
            ->addColumn('position', 'integer', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'null' => true,
            ])
            ->addIndex(['fixed_activity_rule_id'], ['name' => 'BY_RULE_ID'])
            ->addForeignKey(
                'fixed_activity_rule_id',
                'fixed_activity_rules',
                'id',
                ['delete' => 'CASCADE', 'update' => 'CASCADE']
            )
            ->create();
    }
}


