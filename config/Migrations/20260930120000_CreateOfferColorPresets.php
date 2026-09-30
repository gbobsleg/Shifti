<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Instantanés des couleurs et de l'ordre d'affichage des offres.
 */
class CreateOfferColorPresets extends BaseMigration
{
    public function change(): void
    {
        if (!$this->hasTable('offer_color_presets')) {
            $table = $this->table('offer_color_presets', [
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]);
            $table->addColumn('name', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
                'collation' => 'utf8mb4_unicode_ci',
            ]);
            $table->addTimestamps('created', 'modified');
            $table->addIndex(['name'], [
                'unique' => true,
                'name' => 'UQ_OFFER_COLOR_PRESETS_NAME',
            ]);
            $table->create();
        }

        if (!$this->hasTable('offer_color_preset_items')) {
            $offerIdSigned = $this->columnIsSigned('offers', 'id');
            $table = $this->table('offer_color_preset_items', [
                'encoding' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]);
            $table->addColumn('preset_id', 'integer', [
                'default' => null,
                'null' => false,
            ]);
            $table->addColumn('offer_id', 'integer', [
                'default' => null,
                'null' => false,
                'signed' => $offerIdSigned,
            ]);
            $table->addColumn('color', 'string', [
                'default' => null,
                'limit' => 7,
                'null' => false,
            ]);
            $table->addColumn('display_order', 'integer', [
                'default' => null,
                'null' => false,
            ]);
            $table->addIndex(['preset_id', 'offer_id'], [
                'unique' => true,
                'name' => 'UQ_OFFER_COLOR_PRESET_ITEMS_PRESET_OFFER',
            ]);
            $table->addIndex(['offer_id'], [
                'name' => 'IDX_OFFER_COLOR_PRESET_ITEMS_OFFER',
            ]);
            $table->addForeignKey(
                'preset_id',
                'offer_color_presets',
                'id',
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'constraint' => 'FK_OFFER_COLOR_PRESET_ITEMS_PRESET']
            );
            $table->addForeignKey(
                'offer_id',
                'offers',
                'id',
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'constraint' => 'FK_OFFER_COLOR_PRESET_ITEMS_OFFER']
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
