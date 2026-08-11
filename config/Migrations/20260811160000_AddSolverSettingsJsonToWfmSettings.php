<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddSolverSettingsJsonToWfmSettings extends BaseMigration
{
    /**
     * Ajoute la configuration des timeouts solveurs (profil système WFM).
     *
     * @return void
     */
    public function change(): void
    {
        if (!$this->hasTable('wfm_settings')) {
            return;
        }

        $table = $this->table('wfm_settings');

        if (!$table->hasColumn('solver_settings_json')) {
            $table->addColumn('solver_settings_json', 'json', [
                'default' => null,
                'null' => true,
                'comment' => 'Timeouts solveurs CP-SAT (global, pass1, pass1_5, pass2)',
            ]);
            $table->update();
        }
    }
}
