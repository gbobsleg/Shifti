<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateRotationRules extends BaseMigration
{
    public function change(): void
    {
        if (!$this->hasTable('rotation_rules')) {
            $table = $this->table('rotation_rules', ['id' => false, 'primary_key' => ['id']]);
            
            $table->addColumn('id', 'uuid', [
                'default' => null,
                'null' => false,
            ]);
            
            $table->addColumn('name', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ]);
            
            $table->addColumn('offer_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ]);
            
            $table->addColumn('period_type', 'string', [
                'default' => null,
                'limit' => 20,
                'null' => false,
            ]);
            
            $table->addColumn('target_count', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ]);
            
            $table->addColumn('shift_duration', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ]);
            
            $table->addColumn('time_window_start', 'time', [
                'default' => null,
                'null' => false,
            ]);
            
            $table->addColumn('time_window_end', 'time', [
                'default' => null,
                'null' => false,
            ]);
            
            $table->addTimestamps('created', 'modified');
            
            $table->addIndex(['offer_id'], ['name' => 'IDX_ROTATION_RULES_OFFER']);
            $table->addIndex(['period_type'], ['name' => 'IDX_ROTATION_RULES_PERIOD_TYPE']);
            
            $table->addForeignKey('offer_id', 'offers', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
            ]);
            
            $table->create();
        }
    }
}
