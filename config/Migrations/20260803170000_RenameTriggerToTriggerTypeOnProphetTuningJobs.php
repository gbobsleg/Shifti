<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * `trigger` est un mot réservé MySQL → renommer en trigger_type.
 */
class RenameTriggerToTriggerTypeOnProphetTuningJobs extends BaseMigration
{
    public function change(): void
    {
        if (!$this->hasTable('prophet_tuning_jobs')) {
            return;
        }

        $table = $this->table('prophet_tuning_jobs');

        if ($table->hasColumn('trigger') && !$table->hasColumn('trigger_type')) {
            $table->renameColumn('trigger', 'trigger_type');
            $table->update();
        }
    }
}
