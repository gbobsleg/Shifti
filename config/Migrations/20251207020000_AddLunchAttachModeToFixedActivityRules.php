<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddLunchAttachModeToFixedActivityRules extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('fixed_activity_rules');
        if (!$table->hasColumn('lunch_attach_mode')) {
            $table
                ->addColumn('lunch_attach_mode', 'string', [
                    'limit' => 16,
                    'default' => 'none',
                    'null' => false,
                    'comment' => 'Position préférée du repas par rapport à cette activité: none|before|after',
                ])
                ->update();
        }
    }
}


