<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddForbidMiddaySingletonsToWfmSettings extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('wfm_settings');
        if (!$table->hasColumn('forbid_midday_singletons')) {
            $table
                ->addColumn('forbid_midday_singletons', 'boolean', [
                    'default' => false,
                    'null' => false,
                    'comment' => 'Interdit les blocs isolés de 15 minutes entre 12h et 14h (peut augmenter la pénurie)',
                ])
                ->update();
        }
    }
}


