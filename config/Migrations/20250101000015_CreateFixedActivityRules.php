<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateFixedActivityRules extends BaseMigration
{
    public function change(): void
    {
        if (!$this->hasTable('fixed_activity_rules')) {
            $table = $this->table('fixed_activity_rules');
        $table->addColumn('offer_id', 'integer', [
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);
        $table->addColumn('start_time', 'time', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('end_time', 'time', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('quantity', 'integer', [
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);
        $table->addColumn('priority', 'boolean', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('active', 'boolean', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('is_splittable', 'boolean', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('site_mode', 'string', [
            'default' => null,
            'limit' => 50,
            'null' => false,
        ]);
        $table->addIndex(['offer_id'], ['name' => 'BY_OFFER_ID']);
        $table->addForeignKey('offer_id', 'offers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->create();
        }
    }
}

