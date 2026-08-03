<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddSourceToPlanningRangeDrafts extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('planning_range_drafts');
        
        if (!$table->hasColumn('source')) {
            $table->addColumn('source', 'string', [
                'default' => null,
                'limit' => 50,
                'null' => true,
            ]);
            
            $table->addIndex(['job_id', 'source'], ['name' => 'IDX_PRD_JOB_SOURCE']);
            
            $table->update();
        }
    }
}
