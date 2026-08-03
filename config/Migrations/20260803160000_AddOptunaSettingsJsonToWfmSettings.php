<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddOptunaSettingsJsonToWfmSettings extends BaseMigration
{
    /**
     * Ajoute la config moteur Optuna (profil système WFM).
     *
     * @return void
     */
    public function change(): void
    {
        if (!$this->hasTable('wfm_settings')) {
            return;
        }

        $table = $this->table('wfm_settings');

        if (!$table->hasColumn('optuna_settings_json')) {
            $table->addColumn('optuna_settings_json', 'json', [
                'default' => null,
                'null' => true,
                'comment' => 'Paramètres moteur Optuna (horizon, trials, cron, auto-apply, bornes de search)',
            ]);
            $table->update();
        }
    }
}
