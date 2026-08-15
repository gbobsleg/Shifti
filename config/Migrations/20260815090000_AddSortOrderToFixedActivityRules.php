<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddSortOrderToFixedActivityRules extends BaseMigration
{
    /**
     * Ajoute la colonne sort_order pour ordonner la résolution des pools d'équité.
     *
     * @return void
     */
    public function change(): void
    {
        if (!$this->hasTable('fixed_activity_rules')) {
            return;
        }

        $table = $this->table('fixed_activity_rules');

        if (!$table->hasColumn('sort_order')) {
            $table->addColumn('sort_order', 'integer', [
                'default' => 0,
                'null' => false,
                'comment' => 'Ordre de résolution des pools d\'équité (1 = priorité la plus haute)',
            ]);
            $table->update();
        }
    }
}
