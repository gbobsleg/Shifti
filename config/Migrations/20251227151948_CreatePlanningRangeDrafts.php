<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreatePlanningRangeDrafts extends BaseMigration
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
        if ($this->hasTable('planning_range_drafts')) {
            return;
        }

        $table = $this->table('planning_range_drafts');

        $table
            ->addColumn('job_id', 'integer', [
                'null' => false,
            ])
            ->addColumn('user_id', 'integer', [
                'null' => false,
            ])
            ->addColumn('offer_id', 'integer', [
                'null' => false,
            ])
            ->addColumn('date_start', 'datetime', [
                'null' => false,
            ])
            ->addColumn('date_end', 'datetime', [
                'null' => false,
            ])
            ->addColumn('comment', 'string', [
                'limit' => 255,
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
            ->addIndex(['job_id'], ['name' => 'IDX_PRD_JOB'])
            ->addIndex(['user_id'], ['name' => 'IDX_PRD_USER'])
            ->addIndex(['offer_id'], ['name' => 'IDX_PRD_OFFER'])
            ->addIndex(['job_id', 'date_start'], ['name' => 'IDX_PRD_JOB_DATESTART'])
            ->addIndex(['job_id', 'user_id', 'date_start'], ['name' => 'IDX_PRD_JOB_USER_DATESTART'])
            ->addForeignKey('job_id', 'planning_generation_jobs', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
            ])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
            ])
            ->addForeignKey('offer_id', 'offers', 'id', [
                'delete' => 'RESTRICT',
                'update' => 'CASCADE',
            ]);

        $table->create();
    }
}
