<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddHalfDayPivotToWfmSettings extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Ajoute une colonne 'half_day_pivot' de type TIME à la table wfm_settings
     * pour gérer la bascule Matin/Après-midi sans trou de pause déjeuner.
     */
    public function change(): void
    {
        $table = $this->table('wfm_settings');
        $table->addColumn('half_day_pivot', 'time', [
            'default' => '13:00:00',
            'null' => false,
            'after' => 'day_end_time',
        ]);
        $table->update();
    }
}
