<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddEquityEnabledToFixedActivityRules extends BaseMigration
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

        if (!$table->hasColumn('equity_enabled')) {
            $table->addColumn('equity_enabled', 'boolean', [
                'default' => 0,
                'null' => false,
                'after' => 'is_splittable',
            ]);
        }

        $table->update();
    }
}
