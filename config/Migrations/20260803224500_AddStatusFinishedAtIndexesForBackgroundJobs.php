<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Index composites pour le polling console Jobs (filtre status + finished_at 24h).
 */
class AddStatusFinishedAtIndexesForBackgroundJobs extends BaseMigration
{
    public function change(): void
    {
        $targets = [
            'prophet_tuning_jobs' => 'IDX_PTJ_STATUS_FINISHED_AT',
            'forecast_scenarios' => 'IDX_FS_STATUS_FINISHED_AT',
            'planning_generation_jobs' => 'IDX_PGJ_STATUS_FINISHED_AT',
        ];

        foreach ($targets as $tableName => $indexName) {
            if (!$this->hasTable($tableName)) {
                continue;
            }

            $table = $this->table($tableName);
            if (!$table->hasColumn('status') || !$table->hasColumn('finished_at')) {
                continue;
            }
            if ($table->hasIndexByName($indexName)) {
                continue;
            }

            $table
                ->addIndex(['status', 'finished_at'], ['name' => $indexName])
                ->update();
        }
    }
}
