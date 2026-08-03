<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class DropLunchAttachStrengthFromFixedActivityRules extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('fixed_activity_rules');
        if ($table->hasColumn('lunch_attach_strength')) {
            $table->removeColumn('lunch_attach_strength')->update();
        }
    }
}


