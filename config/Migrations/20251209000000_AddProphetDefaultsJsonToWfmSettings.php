<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddProphetDefaultsJsonToWfmSettings extends BaseMigration
{
    public function change(): void
    {
        if ($this->hasTable('wfm_settings')) {
            $table = $this->table('wfm_settings');

            if (!$table->hasColumn('prophet_defaults_json')) {
                $table->addColumn('prophet_defaults_json', 'json', [
                    'default' => null,
                    'null' => true,
                    'comment' => 'Paramètres Prophet système par défaut (profil global WFM)',
                ]);
                $table->update();
            }
        }
    }
}



















