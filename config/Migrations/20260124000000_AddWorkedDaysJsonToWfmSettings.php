<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddWorkedDaysJsonToWfmSettings extends BaseMigration
{
    public function change(): void
    {
        if ($this->hasTable('wfm_settings')) {
            $table = $this->table('wfm_settings');

            if (!$table->hasColumn('worked_days_json')) {
                $table->addColumn('worked_days_json', 'json', [
                    'default' => null,
                    'null' => true,
                    'comment' => 'Jours travaillés pour le calcul des plages (tableau 1=Lundi à 7=Dimanche)',
                ]);
                $table->update();
            }
        }
    }
}
