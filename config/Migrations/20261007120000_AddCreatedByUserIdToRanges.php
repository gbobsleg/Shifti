<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddCreatedByUserIdToRanges extends BaseMigration
{
    public function up(): void
    {
        if (!$this->hasTable('ranges')) {
            return;
        }

        $table = $this->table('ranges');
        if ($table->hasColumn('created_by_user_id')) {
            return;
        }

        $table
            ->addColumn('created_by_user_id', 'integer', [
                'null' => true,
                'default' => null,
                'after' => 'source',
            ])
            ->addIndex(['user_id', 'date_start'], [
                'name' => 'idx_ranges_user_date_start',
            ])
            ->addForeignKey('created_by_user_id', 'users', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE',
                'constraint' => 'FK_RANGES_CREATED_BY_USER',
            ])
            ->update();
    }

    public function down(): void
    {
        if (!$this->hasTable('ranges')) {
            return;
        }

        $table = $this->table('ranges');
        if (!$table->hasColumn('created_by_user_id')) {
            return;
        }

        $table
            ->dropForeignKey('created_by_user_id')
            ->removeIndex(['user_id', 'date_start'])
            ->removeColumn('created_by_user_id')
            ->update();
    }
}
