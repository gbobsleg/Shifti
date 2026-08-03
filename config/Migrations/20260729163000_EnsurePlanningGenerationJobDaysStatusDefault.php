<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Garantit que planning_generation_job_days.status est NOT NULL avec DEFAULT 'queued'.
 *
 * Nécessaire car un UPDATE explicite avec status = NULL (ex. relance de job)
 * échoue si la colonne est NOT NULL sans valeur de remplacement cohérente côté applicatif.
 * La valeur métier attendue à la création / réinitialisation est toujours 'queued'.
 */
class EnsurePlanningGenerationJobDaysStatusDefault extends BaseMigration
{
    public function up(): void
    {
        if (!$this->hasTable('planning_generation_job_days')) {
            return;
        }

        // Remplacer d'éventuels NULL résiduels avant de renforcer la contrainte
        $this->execute(
            "UPDATE planning_generation_job_days SET status = 'queued' WHERE status IS NULL OR status = ''"
        );

        $this->execute(
            "ALTER TABLE planning_generation_job_days
             MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'queued'"
        );
    }

    public function down(): void
    {
        // Pas de retour arrière utile : le DEFAULT 'queued' est la définition nominale.
    }
}
