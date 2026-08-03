<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddEquityGroupIdToFixedActivityRules extends BaseMigration
{
    /**
     * Ajoute la colonne equity_group_id (string, nullable, indexée) pour les meta-groupes d'équité V2.
     */
    public function change(): void
    {
        $table = $this->table('fixed_activity_rules');
        if (!$table->hasColumn('equity_group_id')) {
            $table->addColumn('equity_group_id', 'string', [
                'default' => null,
                'limit' => 64,
                'null' => true,
            ]);
            $table->addIndex(['equity_group_id'], ['name' => 'IDX_FAR_EQUITY_GROUP_ID']);
            $table->update();
        }
    }
}
