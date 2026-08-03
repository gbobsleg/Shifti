<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateUsersRotationRules extends BaseMigration
{
    public function change(): void
    {
        if (!$this->hasTable('users_rotation_rules')) {
            $table = $this->table('users_rotation_rules', ['id' => false, 'primary_key' => ['user_id', 'rotation_rule_id']]);
            
            $table->addColumn('user_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ]);
            
            $table->addColumn('rotation_rule_id', 'uuid', [
                'default' => null,
                'null' => false,
            ]);
            
            $table->addColumn('target_count_override', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ]);
            
            $table->addIndex(['user_id'], ['name' => 'IDX_USERS_ROTATION_RULES_USER']);
            $table->addIndex(['rotation_rule_id'], ['name' => 'IDX_USERS_ROTATION_RULES_RULE']);
            $table->addIndex(['user_id', 'rotation_rule_id'], [
                'name' => 'UNIQUE_USER_ROTATION_RULE',
                'unique' => true,
            ]);
            
            $table->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
            ]);
            
            $table->addForeignKey('rotation_rule_id', 'rotation_rules', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
            ]);
            
            $table->create();
        }
    }
}
