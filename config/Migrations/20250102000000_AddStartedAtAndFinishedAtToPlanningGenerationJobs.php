<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddStartedAtAndFinishedAtToPlanningGenerationJobs extends BaseMigration
{
    /**
     * Change Method.
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('planning_generation_jobs');
        
        if (!$table->hasColumn('started_at')) {
            $table->addColumn('started_at', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'created',
            ]);
        }
        
        if (!$table->hasColumn('finished_at')) {
            $table->addColumn('finished_at', 'datetime', [
                'null' => true,
                'default' => null,
                'after' => 'started_at',
            ]);
        }
        
        $table->update();
        
        // Initialiser started_at avec created pour les jobs existants (après création de la colonne)
        $this->execute("UPDATE planning_generation_jobs SET started_at = created WHERE started_at IS NULL");
    }
}
