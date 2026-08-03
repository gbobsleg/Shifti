<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateWfmSettings extends BaseMigration
{
    public function change(): void
    {
        if (!$this->hasTable('wfm_settings')) {
            $table = $this->table('wfm_settings');
        $table->addColumn('name', 'string', [
            'default' => null,
            'limit' => 100,
            'null' => false,
        ]);
        $table->addColumn('service_level_percent', 'decimal', [
            'default' => null,
            'precision' => 5,
            'scale' => 2,
            'null' => false,
        ]);
        $table->addColumn('service_level_seconds', 'integer', [
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);
        $table->addColumn('shrinkage_percent', 'decimal', [
            'default' => null,
            'precision' => 5,
            'scale' => 2,
            'null' => false,
        ]);
        $table->addColumn('lunch_start_time', 'time', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('lunch_end_time', 'time', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('lunch_duration_minutes', 'integer', [
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);
        $table->addColumn('am_pause_duration_minutes', 'integer', [
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);
        $table->addColumn('am_pause_start_time', 'time', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('am_pause_end_time', 'time', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('pm_pause_duration_minutes', 'integer', [
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);
        $table->addColumn('pm_pause_start_time', 'time', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('pm_pause_end_time', 'time', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('min_block_minutes', 'integer', [
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);
        $table->addColumn('max_block_minutes', 'integer', [
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);
        $table->addColumn('strict_work_hours', 'boolean', [
            'default' => null,
            'null' => true,
        ]);
        $table->create();
        }
    }
}

