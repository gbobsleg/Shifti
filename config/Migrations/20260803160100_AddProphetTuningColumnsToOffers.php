<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddProphetTuningColumnsToOffers extends BaseMigration
{
    /**
     * Ajoute les colonnes de tuning Optuna / brouillon Prophet par offre.
     *
     * @return void
     */
    public function change(): void
    {
        if (!$this->hasTable('offers')) {
            return;
        }

        $table = $this->table('offers');
        $updated = false;

        if (!$table->hasColumn('prophet_tuning_enabled')) {
            $table->addColumn('prophet_tuning_enabled', 'boolean', [
                'default' => false,
                'null' => false,
                'comment' => 'Inclure cette offre dans le tuning Optuna (manuel / cron)',
            ]);
            $updated = true;
        }

        if (!$table->hasColumn('prophet_tuning_draft_json')) {
            $table->addColumn('prophet_tuning_draft_json', 'json', [
                'default' => null,
                'null' => true,
                'comment' => 'Brouillon de paramètres Prophet proposés par Optuna',
            ]);
            $updated = true;
        }

        if (!$table->hasColumn('prophet_tuning_draft_scores_json')) {
            $table->addColumn('prophet_tuning_draft_scores_json', 'json', [
                'default' => null,
                'null' => true,
                'comment' => 'Scores du brouillon Optuna (baseline vs proposé)',
            ]);
            $updated = true;
        }

        if (!$table->hasColumn('prophet_tuning_previous_json')) {
            $table->addColumn('prophet_tuning_previous_json', 'json', [
                'default' => null,
                'null' => true,
                'comment' => 'Profil Prophet officiel avant le dernier apply (rollback 1 niveau)',
            ]);
            $updated = true;
        }

        if (!$table->hasColumn('prophet_tuning_last_run_at')) {
            $table->addColumn('prophet_tuning_last_run_at', 'datetime', [
                'default' => null,
                'null' => true,
                'comment' => 'Horodatage du dernier job de tuning terminé',
            ]);
            $updated = true;
        }

        if (!$table->hasColumn('prophet_tuning_last_job_id')) {
            $table->addColumn('prophet_tuning_last_job_id', 'integer', [
                'default' => null,
                'null' => true,
                'signed' => true,
                'comment' => 'ID du dernier prophet_tuning_jobs associé',
            ]);
            $updated = true;
        }

        if ($updated) {
            $table->update();
        }
    }
}
