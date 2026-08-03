<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddProphetDefaultsToOffers extends BaseMigration
{
    /**
     * Change Method.
     *
     * Ajoute une colonne JSON pour stocker les paramètres Prophet par défaut
     * au niveau de chaque offre.
     *
     * @return void
     */
    public function change(): void
    {
        if ($this->hasTable('offers')) {
            $table = $this->table('offers');

            if (!$table->hasColumn('prophet_default_settings_json')) {
                $table->addColumn('prophet_default_settings_json', 'json', [
                    'default' => null,
                    'null' => true,
                    'comment' => 'Paramètres Prophet par défaut pour cette offre (profil administrateur)',
                ]);
                $table->update();
            }
        }
    }
}


