<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateSkills extends BaseMigration
{
    public function change(): void
    {
        if (!$this->hasTable('skills')) {
            $table = $this->table('skills');
        $table->addColumn('user_id', 'integer', [
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);
        $table->addColumn('offer_id', 'integer', [
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);
        $table->addColumn('validity_start', 'date', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('validity_end', 'date', [
            'default' => null,
            'null' => true,
        ]);
        $table->addTimestamps('created', 'modified');
        $table->addIndex(['user_id'], ['name' => 'BY_USER_ID']);
        $table->addIndex(['offer_id'], ['name' => 'BY_OFFER_ID']);
        $table->addIndex(['user_id', 'offer_id'], ['name' => 'UNIQUE_USER_OFFER', 'unique' => true]);
        $table->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->addForeignKey('offer_id', 'offers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->create();
        }
    }
}

