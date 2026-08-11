<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Historique par snapshots du planning publié (agent × jour).
 */
class CreatePlanningDayHistories extends BaseMigration
{
    public function change(): void
    {
        if ($this->hasTable('planning_day_histories')) {
            return;
        }

        $table = $this->table('planning_day_histories');

        $table
            ->addColumn('user_id', 'integer', [
                'null' => false,
            ])
            ->addColumn('day', 'date', [
                'null' => false,
            ])
            ->addColumn('snapshot', 'json', [
                'null' => false,
            ])
            ->addColumn('content_hash', 'string', [
                'limit' => 64,
                'null' => false,
            ])
            ->addColumn('source', 'string', [
                'limit' => 32,
                'null' => false,
            ])
            ->addColumn('actor_user_id', 'integer', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('created', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addIndex(['user_id', 'day', 'created'], [
                'name' => 'IDX_PDH_USER_DAY_CREATED',
            ])
            ->addIndex(['user_id', 'day', 'content_hash'], [
                'name' => 'IDX_PDH_USER_DAY_HASH',
            ])
            ->addForeignKey(
                'user_id',
                'users',
                'id',
                [
                    'delete' => 'CASCADE',
                    'update' => 'CASCADE',
                    'constraint' => 'FK_PDH_USER',
                ]
            )
            ->addForeignKey(
                'actor_user_id',
                'users',
                'id',
                [
                    'delete' => 'SET_NULL',
                    'update' => 'CASCADE',
                    'constraint' => 'FK_PDH_ACTOR_USER',
                ]
            );

        $table->create();
    }
}
