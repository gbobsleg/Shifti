<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Une palette retient aussi le rangement. Le jeton de révision du brouillon
 * vit dans une table à une ligne, pas dans les familles qui sont recréées.
 */
class AddPaletteFamiliesAndMetadata extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        if ($this->hasTable('offer_color_preset_items')) {
            $items = $this->table('offer_color_preset_items');
            if (!$items->hasColumn('family_name')) {
                $items->addColumn('family_name', 'string', [
                    'default' => null,
                    'limit' => 255,
                    'null' => true,
                    'after' => 'display_order',
                    'collation' => 'utf8mb4_unicode_ci',
                ]);
            }
            if (!$items->hasColumn('family_position')) {
                $items->addColumn('family_position', 'integer', [
                    'default' => null,
                    'null' => true,
                    'after' => 'family_name',
                ]);
            }
            if (!$items->hasColumn('hue')) {
                $items->addColumn('hue', 'smallinteger', [
                    'default' => null,
                    'null' => true,
                    'signed' => false,
                    'after' => 'family_position',
                ]);
            }
            if (!$items->hasColumn('position')) {
                $items->addColumn('position', 'integer', [
                    'default' => null,
                    'null' => true,
                    'after' => 'hue',
                ]);
            }
            $items->update();
        }

        if ($this->hasTable('offer_color_metadata')) {
            return;
        }

        $presetIdSigned = $this->columnIsSigned('offer_color_presets', 'id');
        $table = $this->table('offer_color_metadata', [
            'encoding' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);
        $table->addColumn('revision', 'integer', [
            'default' => 0,
            'null' => false,
            'signed' => false,
        ]);
        $table->addColumn('preset_id', 'integer', [
            'default' => null,
            'null' => true,
            'signed' => $presetIdSigned,
        ]);
        $table->addForeignKey(
            'preset_id',
            'offer_color_presets',
            'id',
            ['delete' => 'SET_NULL', 'update' => 'CASCADE', 'constraint' => 'FK_OFFER_COLOR_METADATA_PRESET']
        );
        $table->create();

        $this->table('offer_color_metadata')->insert([
            ['revision' => 0, 'preset_id' => null],
        ])->saveData();
    }

    /**
     * @return void
     */
    public function down(): void
    {
        if ($this->hasTable('offer_color_metadata')) {
            $this->table('offer_color_metadata')->drop()->save();
        }

        if (!$this->hasTable('offer_color_preset_items')) {
            return;
        }

        $items = $this->table('offer_color_preset_items');
        foreach (['position', 'hue', 'family_position', 'family_name'] as $column) {
            if ($items->hasColumn($column)) {
                $items->removeColumn($column);
            }
        }
        $items->update();
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
