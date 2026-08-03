<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class RenameCurrentDateToCurrentDayInPlanningGenerationJobs extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/4/en/migrations.html#the-change-method
     *
     * @return void
     */
    public function change(): void
    {
        if (!$this->hasTable('planning_generation_jobs')) {
            return;
        }

        $table = $this->table('planning_generation_jobs');

        // MySQL: CURRENT_DATE est réservé. On renomme la colonne pour éviter les erreurs SQL
        // quand quoteIdentifiers=false.
        if ($table->hasColumn('current_date') && !$table->hasColumn('current_day')) {
            $table->renameColumn('current_date', 'current_day');
            $table->update();
        }
    }
}
