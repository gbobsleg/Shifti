<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddLunchOverlapAllowedToFixedActivityRules extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('fixed_activity_rules');
        if (!$table->hasColumn('lunch_overlap_allowed')) {
            $table
                ->addColumn('lunch_overlap_allowed', 'boolean', [
                    'default' => true,
                    'null' => false,
                    'comment' => 'Autorise (true) ou interdit (false) au repas de recouvrir cette activité fixe',
                ])
                ->update();
        }
    }
}


