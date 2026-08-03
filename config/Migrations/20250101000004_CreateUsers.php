<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateUsers extends BaseMigration
{
    public function change(): void
    {
        if (!$this->hasTable('users')) {
            $table = $this->table('users');
        $table->addColumn('user_code', 'string', [
            'default' => null,
            'limit' => 11,
            'null' => false,
        ]);
        $table->addColumn('last_name', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('first_name', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('email', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('password', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('role_id', 'integer', [
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);
        $table->addColumn('site_id', 'integer', [
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);
        $table->addTimestamps('created', 'modified');
        $table->addIndex(['email'], ['name' => 'UNIQUE_EMAIL', 'unique' => true]);
        $table->addIndex(['role_id'], ['name' => 'BY_ROLE_ID']);
        $table->addIndex(['site_id'], ['name' => 'BY_SITE_ID']);
        $table->addForeignKey('role_id', 'roles', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE']);
        $table->addForeignKey('site_id', 'sites', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE']);
        $table->create();
        }
    }
}

