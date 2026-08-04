<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreatePlanningGenerationJobs extends BaseMigration
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
        // Si la migration a échoué en cours de route (ex: FK incompatible), la table peut exister partiellement.
        // Vu que c'est une table nouvelle, on la recrée proprement.
        if ($this->hasTable('planning_generation_jobs')) {
            $this->table('planning_generation_jobs')->drop()->save();
        }

        $table = $this->table('planning_generation_jobs');

        $table
            ->addColumn('user_id', 'integer', [
                'null' => false,
            ])
            ->addColumn('start_date', 'date', [
                'null' => false,
            ])
            ->addColumn('end_date', 'date', [
                'null' => false,
            ])
            ->addColumn('wfm_setting_id', 'integer', [
                'null' => false,
            ])
            ->addColumn('scenario_id', 'integer', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('options_json', 'text', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('status', 'string', [
                'limit' => 40,
                'null' => false,
                'default' => 'queued',
            ])
            ->addColumn('total_days', 'integer', [
                'null' => false,
                'default' => 0,
            ])
            ->addColumn('processed_days', 'integer', [
                'null' => false,
                'default' => 0,
            ])
            ->addColumn('current_day', 'date', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('current_step', 'string', [
                'limit' => 50,
                'null' => true,
                'default' => null,
            ])
            ->addColumn('eta_seconds', 'integer', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('equity_state_json', 'text', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('report_json', 'text', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('error_message', 'text', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('created', 'datetime', [
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
            ->addColumn('modified', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('debug_rotation_only', 'boolean', [
                'default' => false,
                'null' => false,
            ])
            ->addIndex(['status'], ['name' => 'IDX_PGJ_STATUS'])
            ->addIndex(['user_id'], ['name' => 'IDX_PGJ_USER'])
            ->addIndex(['created'], ['name' => 'IDX_PGJ_CREATED'])
            ->addIndex(['debug_rotation_only'], ['name' => 'IDX_PGJ_DEBUG_ROTATION_ONLY'])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
            ])
            ->addForeignKey('wfm_setting_id', 'wfm_settings', 'id', [
                'delete' => 'RESTRICT',
                'update' => 'CASCADE',
            ]);

        $table->create();
    }
}
