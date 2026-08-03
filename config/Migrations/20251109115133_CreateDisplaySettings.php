<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateDisplaySettings extends BaseMigration
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
        if (!$this->hasTable('display_settings')) {
            $table = $this->table('display_settings', ['id' => false, 'primary_key' => ['id']]);
        $table->addColumn('id', 'integer', [
            'autoIncrement' => true,
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);
        $table->addColumn('key', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('value', 'text', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('description', 'text', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('type', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addIndex([
            'key',
        ], [
            'name' => 'UNIQUE_KEY',
            'unique' => true,
        ]);
        $table->addIndex([
            'type',
        ], [
            'name' => 'BY_TYPE',
            'unique' => false,
        ]);
        $table->create();
        }
    }
}
