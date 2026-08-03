<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddEquityEnabledToOffersAndMakeFixedRuleEquityNullable extends BaseMigration
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
        if ($this->hasTable('offers')) {
            $offers = $this->table('offers');
            if (!$offers->hasColumn('equity_enabled')) {
                $offers->addColumn('equity_enabled', 'boolean', [
                    'default' => 0,
                    'null' => false,
                    'after' => 'is_forecastable',
                ]);
            }
            $offers->update();
        }

        if ($this->hasTable('fixed_activity_rules')) {
            $rules = $this->table('fixed_activity_rules');
            if ($rules->hasColumn('equity_enabled')) {
                // Tri-état: null = hérite de l'offre
                $rules->changeColumn('equity_enabled', 'boolean', [
                    'null' => true,
                    'default' => null,
                ]);
                $rules->update();
            }
        }
    }
}
