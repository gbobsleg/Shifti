<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateFixedActivityRulesIncompatibleOffers extends BaseMigration
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
        $table = $this->table('fixed_activity_rules_incompatible_offers', ['id' => false, 'primary_key' => ['fixed_activity_rule_id', 'offer_id']]);
        
        $table
            ->addColumn('fixed_activity_rule_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('offer_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addIndex(
                [
                    'fixed_activity_rule_id',
                ]
            )
            ->addIndex(
                [
                    'offer_id',
                ]
            )
            ->addForeignKey(
                'fixed_activity_rule_id',
                'fixed_activity_rules',
                'id',
                [
                    'update' => 'NO_ACTION',
                    'delete' => 'CASCADE'
                ]
            )
            ->addForeignKey(
                'offer_id',
                'offers',
                'id',
                [
                    'update' => 'NO_ACTION',
                    'delete' => 'CASCADE'
                ]
            )
            ->create();
    }
}
