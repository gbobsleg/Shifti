<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Mode pastel d'une famille visuelle. La teinte reste la même, seules
 * la saturation et la clarté changent. Le drapeau suit la palette.
 */
class AddPastelToColorFamilies extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->addPastel('offer_color_families', 'hue');
        $this->addPastel('offer_color_preset_items', 'hue');
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->dropPastel('offer_color_families');
        $this->dropPastel('offer_color_preset_items');
    }

    private function addPastel(string $tableName, string $after): void
    {
        if (!$this->hasTable($tableName)) {
            return;
        }

        $table = $this->table($tableName);
        if ($table->hasColumn('pastel')) {
            return;
        }

        $table->addColumn('pastel', 'boolean', [
            'default' => false,
            'null' => false,
            'after' => $after,
        ]);
        $table->update();
    }

    private function dropPastel(string $tableName): void
    {
        if (!$this->hasTable($tableName)) {
            return;
        }

        $table = $this->table($tableName);
        if (!$table->hasColumn('pastel')) {
            return;
        }

        $table->removeColumn('pastel');
        $table->update();
    }
}
