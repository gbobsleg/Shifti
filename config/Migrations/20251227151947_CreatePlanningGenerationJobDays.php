<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreatePlanningGenerationJobDays extends BaseMigration
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
        if ($this->hasTable('planning_generation_job_days')) {
            return;
        }

        $table = $this->table('planning_generation_job_days');

        $table
            ->addColumn('job_id', 'integer', [
                'null' => false,
            ])
            ->addColumn('date', 'date', [
                'null' => false,
            ])
            ->addColumn('status', 'string', [
                'limit' => 30,
                'null' => false,
                'default' => 'queued',
            ])
            ->addColumn('duration_ms', 'integer', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('error_message', 'text', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('report_json', 'text', [
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
            ->addIndex(['job_id', 'date'], [
                'name' => 'UQ_PGJD_JOB_DATE',
                'unique' => true,
            ])
            ->addIndex(['job_id'], ['name' => 'IDX_PGJD_JOB'])
            ->addIndex(['status'], ['name' => 'IDX_PGJD_STATUS'])
            ->addForeignKey('job_id', 'planning_generation_jobs', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
            ]);

        $table->create();
    }
}
