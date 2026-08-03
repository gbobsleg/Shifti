<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateFixedActivityRulesSites extends BaseMigration
{
    public function change(): void
    {
        if (!$this->hasTable('fixed_activity_rules_sites')) {
            $table = $this->table('fixed_activity_rules_sites');
        $table->addColumn('rule_id', 'integer', [
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);
        $table->addColumn('site_id', 'integer', [
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);
        $table->addIndex(['rule_id'], ['name' => 'BY_RULE_ID']);
        $table->addIndex(['site_id'], ['name' => 'BY_SITE_ID']);
        $table->addIndex(['rule_id', 'site_id'], ['name' => 'UNIQUE_RULE_SITE', 'unique' => true]);
        $table->addForeignKey('rule_id', 'fixed_activity_rules', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->addForeignKey('site_id', 'sites', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE']);
        $table->create();
        }
    }
}

