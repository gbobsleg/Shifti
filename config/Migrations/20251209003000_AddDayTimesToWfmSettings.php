<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddDayTimesToWfmSettings extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('wfm_settings');

        if (!$table->hasColumn('day_start_time')) {
            $table->addColumn('day_start_time', 'time', [
                'default' => '09:00:00',
                'null' => false,
                'after' => 'shrinkage_percent',
            ]);
        }

        if (!$table->hasColumn('day_end_time')) {
            $table->addColumn('day_end_time', 'time', [
                'default' => '17:00:00',
                'null' => false,
                'after' => 'day_start_time',
            ]);
        }

        $table->update();
    }

    public function down(): void
    {
        $table = $this->table('wfm_settings');

        if ($table->hasColumn('day_start_time')) {
            $table->removeColumn('day_start_time');
        }

        if ($table->hasColumn('day_end_time')) {
            $table->removeColumn('day_end_time');
        }

        $table->update();
    }
}




















