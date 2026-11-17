<?php
declare(strict_types=1);

use Migrations\BaseMigration;
use Phinx\Db\Adapter\MysqlAdapter;

/**
 * La colonne est déjà en production (longtext) mais absente des migrations.
 * Sans elle, un schéma reconstruit ne peut pas stocker les séries.
 */
class AddDataJsonToScenarioSeries extends BaseMigration
{
    public function change(): void
    {
        if (!$this->hasTable('scenario_series')) {
            return;
        }

        $table = $this->table('scenario_series');
        if ($table->hasColumn('data_json')) {
            return;
        }

        $table
            ->addColumn('data_json', 'text', [
                'limit' => MysqlAdapter::TEXT_LONG,
                'null' => false,
            ])
            ->update();
    }
}
