<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddEquityStrengthToFixedActivityRules extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/4/en/migrations.html#the-change-method
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('fixed_activity_rules');
        $table->addColumn('equity_strength', 'integer', [
            'default' => 0,
            'limit' => 11,
            'null' => true,
        ]);
        $table->update();
    }
}
