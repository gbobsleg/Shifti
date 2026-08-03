<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateUserAvailabilities extends BaseMigration
{
    public function change(): void
    {
        if (!$this->hasTable('user_availabilities')) {
            $table = $this->table('user_availabilities');
        $table->addColumn('user_id', 'integer', [
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);
        $table->addColumn('day_of_week', 'integer', [
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);
        $table->addColumn('availability_start_time', 'time', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('availability_end_time', 'time', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('earliest_end_time', 'time', [
            'default' => null,
            'null' => true,
        ]);
        $table->addIndex(['user_id'], ['name' => 'BY_USER_ID']);
        $table->addIndex(['user_id', 'day_of_week'], ['name' => 'UNIQUE_USER_DAY', 'unique' => true]);
        $table->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->create();
        }
    }
}

