<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Familles visuelles d'offres (rangement, pas les couleurs).
 */
class CreateOfferColorFamilies extends BaseMigration
{
    public function change(): void
    {
        if (!$this->hasTable('offer_color_families')) {
            $table = $this->table('offer_color_families', [
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]);
            $table->addColumn('name', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
                'collation' => 'utf8mb4_unicode_ci',
            ]);
            $table->addColumn('position', 'integer', [
                'default' => 0,
                'null' => false,
            ]);
            $table->addTimestamps('created', 'modified');
            $table->addIndex(['name'], [
                'unique' => true,
                'name' => 'UQ_OFFER_COLOR_FAMILIES_NAME',
            ]);
            $table->addIndex(['position'], [
                'name' => 'IDX_OFFER_COLOR_FAMILIES_POSITION',
            ]);
            $table->create();
        }

        if (!$this->hasTable('offer_color_family_offers')) {
            $offerIdSigned = $this->columnIsSigned('offers', 'id');
            $table = $this->table('offer_color_family_offers', [
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]);
            $table->addColumn('family_id', 'integer', [
                'default' => null,
                'null' => false,
            ]);
            $table->addColumn('offer_id', 'integer', [
                'default' => null,
                'null' => false,
                'signed' => $offerIdSigned,
            ]);
            $table->addColumn('position', 'integer', [
                'default' => 0,
                'null' => false,
            ]);
            $table->addIndex(['offer_id'], [
                'unique' => true,
                'name' => 'UQ_OFFER_COLOR_FAMILY_OFFERS_OFFER',
            ]);
            $table->addIndex(['family_id'], [
                'name' => 'IDX_OFFER_COLOR_FAMILY_OFFERS_FAMILY',
            ]);
            $table->addForeignKey(
                'family_id',
                'offer_color_families',
                'id',
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'constraint' => 'FK_OFFER_COLOR_FAMILY_OFFERS_FAMILY']
            );
            $table->addForeignKey(
                'offer_id',
                'offers',
                'id',
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'constraint' => 'FK_OFFER_COLOR_FAMILY_OFFERS_OFFER']
            );
            $table->create();
        }
    }

    private function columnIsSigned(string $table, string $column): bool
    {
        $row = $this->fetchRow(sprintf(
            "SHOW COLUMNS FROM `%s` LIKE '%s'",
            $table,
            $column
        ));
        $type = strtolower((string)($row['Type'] ?? $row['type'] ?? ''));

        return !str_contains($type, 'unsigned');
    }
}
