<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class ReplacePriorityWithAllowShortfall extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('fixed_activity_rules');

        if (!$table->hasColumn('allow_shortfall')) {
            $table->addColumn('allow_shortfall', 'boolean', [
                'default' => false,
                'null' => false,
                'after' => 'priority',
            ]);
            $table->update();
        }

        // Backfill : les activités de priorité 0 (ex: Cellule d'appui) deviennent optionnelles.
        $this->execute('UPDATE fixed_activity_rules SET allow_shortfall = 1 WHERE priority = 0');

        $table = $this->table('fixed_activity_rules');
        if ($table->hasColumn('priority')) {
            $table->removeColumn('priority');
        }
        if ($table->hasColumn('equity_strength')) {
            $table->removeColumn('equity_strength');
        }
        $table->update();
    }

    public function down(): void
    {
        $table = $this->table('fixed_activity_rules');

        if (!$table->hasColumn('priority')) {
            $table->addColumn('priority', 'integer', [
                'default' => null,
                'null' => true,
                'after' => 'quantity',
            ]);
        }
        if (!$table->hasColumn('equity_strength')) {
            $table->addColumn('equity_strength', 'integer', [
                'default' => 0,
                'limit' => 11,
                'null' => true,
            ]);
        }
        $table->update();

        // Restauration approximative : priorité 0 pour les activités optionnelles, 100 sinon.
        $this->execute('UPDATE fixed_activity_rules SET priority = CASE WHEN allow_shortfall = 1 THEN 0 ELSE 100 END');

        $table = $this->table('fixed_activity_rules');
        if ($table->hasColumn('allow_shortfall')) {
            $table->removeColumn('allow_shortfall');
        }
        $table->update();
    }
}
