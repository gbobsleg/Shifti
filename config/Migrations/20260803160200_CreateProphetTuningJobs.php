<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateProphetTuningJobs extends BaseMigration
{
    /**
     * File d'attente des jobs de tuning Optuna (par offre).
     *
     * @return void
     */
    public function change(): void
    {
        if ($this->hasTable('prophet_tuning_jobs')) {
            return;
        }

        $table = $this->table('prophet_tuning_jobs');

        $table
            ->addColumn('offer_id', 'integer', [
                'null' => false,
                'signed' => true,
                'comment' => 'Offre tunée',
            ])
            ->addColumn('created_by', 'integer', [
                'null' => true,
                'default' => null,
                'signed' => true,
                'comment' => 'User ayant lancé le job (null si cron)',
            ])
            ->addColumn('trigger_type', 'string', [
                'limit' => 20,
                'null' => false,
                'default' => 'manual',
                'comment' => 'manual | cron',
            ])
            ->addColumn('status', 'string', [
                'limit' => 20,
                'null' => false,
                'default' => 'queued',
                'comment' => 'queued | running | completed | failed | cancelled',
            ])
            ->addColumn('config_snapshot_json', 'json', [
                'null' => true,
                'default' => null,
                'comment' => 'Snapshot des settings Optuna au lancement',
            ])
            ->addColumn('baseline_params_json', 'json', [
                'null' => true,
                'default' => null,
                'comment' => 'Params Prophet du profil actuel au lancement',
            ])
            ->addColumn('baseline_scores_json', 'json', [
                'null' => true,
                'default' => null,
                'comment' => 'Scores backtest du profil actuel',
            ])
            ->addColumn('best_params_json', 'json', [
                'null' => true,
                'default' => null,
                'comment' => 'Meilleurs params trouvés par Optuna',
            ])
            ->addColumn('best_scores_json', 'json', [
                'null' => true,
                'default' => null,
                'comment' => 'Scores backtest du meilleur essai',
            ])
            ->addColumn('auto_applied', 'boolean', [
                'null' => false,
                'default' => false,
                'comment' => 'True si le profil officiel a été mis à jour automatiquement',
            ])
            ->addColumn('progress_trials_done', 'integer', [
                'null' => false,
                'default' => 0,
                'signed' => true,
            ])
            ->addColumn('progress_trials_total', 'integer', [
                'null' => false,
                'default' => 0,
                'signed' => true,
            ])
            ->addColumn('best_mae_so_far', 'float', [
                'null' => true,
                'default' => null,
                'signed' => true,
            ])
            ->addColumn('error_message', 'text', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('started_at', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('finished_at', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('created', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('modified', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addIndex(['status', 'created'], [
                'name' => 'IDX_PTJ_STATUS_CREATED',
            ])
            ->addIndex(['offer_id', 'created'], [
                'name' => 'IDX_PTJ_OFFER_CREATED',
            ])
            ->addForeignKey('offer_id', 'offers', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
                'constraint' => 'FK_PTJ_OFFER',
            ])
            ->addForeignKey('created_by', 'users', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE',
                'constraint' => 'FK_PTJ_CREATED_BY',
            ]);

        $table->create();
    }
}
