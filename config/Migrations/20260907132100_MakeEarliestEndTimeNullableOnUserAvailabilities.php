<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Aligne le schéma prod (NOT NULL) sur le contrat applicatif :
 * earliest_end_time est optionnel (« Fin la plus tôt » peut rester vide).
 */
class MakeEarliestEndTimeNullableOnUserAvailabilities extends BaseMigration
{
    public function up(): void
    {
        if (!$this->hasTable('user_availabilities')) {
            return;
        }

        $table = $this->table('user_availabilities');
        if (!$table->hasColumn('earliest_end_time')) {
            return;
        }

        $table->changeColumn('earliest_end_time', 'time', [
            'default' => null,
            'null' => true,
        ]);
        $table->update();
    }

    public function down(): void
    {
        if (!$this->hasTable('user_availabilities')) {
            return;
        }

        $table = $this->table('user_availabilities');
        if (!$table->hasColumn('earliest_end_time')) {
            return;
        }

        $this->execute(
            "UPDATE user_availabilities SET earliest_end_time = '00:00:00' WHERE earliest_end_time IS NULL"
        );

        $table->changeColumn('earliest_end_time', 'time', [
            'default' => null,
            'null' => false,
        ]);
        $table->update();
    }
}
