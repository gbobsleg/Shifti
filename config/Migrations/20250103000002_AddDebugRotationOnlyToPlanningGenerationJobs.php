<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddDebugRotationOnlyToPlanningGenerationJobs extends BaseMigration
{
    public function change(): void
    {
        // Sur une BDD neuve, cette migration est antérieure à CreatePlanningGenerationJobs.
        if (!$this->hasTable('planning_generation_jobs')) {
            return;
        }

        $table = $this->table('planning_generation_jobs');
        
        if (!$table->hasColumn('debug_rotation_only')) {
            $table->addColumn('debug_rotation_only', 'boolean', [
                'default' => 0,
                'null' => false,
            ]);
            
            $table->addIndex(['debug_rotation_only'], ['name' => 'IDX_PGJ_DEBUG_ROTATION_ONLY']);
            
            $table->update();
        }
    }
}
