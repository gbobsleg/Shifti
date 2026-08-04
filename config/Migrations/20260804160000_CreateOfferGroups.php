<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Groupes d'offres (membres + profil mixte) pour la passe 2.
 */
class CreateOfferGroups extends BaseMigration
{
    public function change(): void
    {
        if (!$this->hasTable('offer_groups')) {
            $table = $this->table('offer_groups');
            $table->addColumn('name', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ]);
            $table->addColumn('mixed_offer_id', 'integer', [
                'default' => null,
                'null' => false,
            ]);
            $table->addColumn('forecast_source', 'string', [
                'default' => 'members',
                'limit' => 16,
                'null' => false,
            ]);
            $table->addColumn('prefer_mixed', 'boolean', [
                'default' => true,
                'null' => false,
            ]);
            $table->addTimestamps('created', 'modified');
            $table->addIndex(['name'], [
                'unique' => true,
                'name' => 'UQ_OFFER_GROUPS_NAME',
            ]);
            $table->addIndex(['mixed_offer_id'], [
                'unique' => true,
                'name' => 'UQ_OFFER_GROUPS_MIXED_OFFER_ID',
            ]);
            $table->addForeignKey(
                'mixed_offer_id',
                'offers',
                'id',
                ['delete' => 'RESTRICT', 'update' => 'CASCADE', 'constraint' => 'FK_OFFER_GROUPS_MIXED_OFFER']
            );
            $table->create();
        }

        if (!$this->hasTable('offer_group_members')) {
            $table = $this->table('offer_group_members');
            $table->addColumn('offer_group_id', 'integer', [
                'default' => null,
                'null' => false,
            ]);
            $table->addColumn('offer_id', 'integer', [
                'default' => null,
                'null' => false,
            ]);
            $table->addColumn('display_order', 'integer', [
                'default' => 0,
                'null' => false,
            ]);
            $table->addColumn('split_ratio_percent', 'integer', [
                'default' => null,
                'null' => true,
            ]);
            $table->addTimestamps('created', 'modified');
            $table->addIndex(['offer_group_id', 'offer_id'], [
                'unique' => true,
                'name' => 'UQ_OFFER_GROUP_MEMBERS_GROUP_OFFER',
            ]);
            $table->addIndex(['offer_id'], [
                'unique' => true,
                'name' => 'UQ_OFFER_GROUP_MEMBERS_OFFER_ID',
            ]);
            $table->addIndex(['offer_group_id'], [
                'name' => 'IDX_OFFER_GROUP_MEMBERS_GROUP_ID',
            ]);
            $table->addForeignKey(
                'offer_group_id',
                'offer_groups',
                'id',
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'constraint' => 'FK_OFFER_GROUP_MEMBERS_GROUP']
            );
            $table->addForeignKey(
                'offer_id',
                'offers',
                'id',
                ['delete' => 'RESTRICT', 'update' => 'CASCADE', 'constraint' => 'FK_OFFER_GROUP_MEMBERS_OFFER']
            );
            $table->create();
        }
    }
}
