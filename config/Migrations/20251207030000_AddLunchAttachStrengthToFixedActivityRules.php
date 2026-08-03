<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddLunchAttachStrengthToFixedActivityRules extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('fixed_activity_rules');
        if (!$table->hasColumn('lunch_attach_strength')) {
            $table
                ->addColumn('lunch_attach_strength', 'integer', [
                    'default' => 0,
                    'null' => false,
                    'comment' => 'Force de la préférence de repas (0=aucune, 1..5=faible→très forte)',
                ])
                ->update();
        }
    }
}


