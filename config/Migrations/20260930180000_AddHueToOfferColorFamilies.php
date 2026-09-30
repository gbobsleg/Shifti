<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Teinte choisie pour une famille visuelle. Null tant que l'application
 * au planning n'a pas remplacé « Automatique ».
 */
class AddHueToOfferColorFamilies extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        if (!$this->hasTable('offer_color_families')) {
            return;
        }

        $table = $this->table('offer_color_families');
        if ($table->hasColumn('hue')) {
            return;
        }

        $table->addColumn('hue', 'smallinteger', [
            'default' => null,
            'null' => true,
            'signed' => false,
            'after' => 'position',
        ]);
        $table->update();
    }

    /**
     * @return void
     */
    public function down(): void
    {
        if (!$this->hasTable('offer_color_families')) {
            return;
        }

        $table = $this->table('offer_color_families');
        if (!$table->hasColumn('hue')) {
            return;
        }

        $table->removeColumn('hue');
        $table->update();
    }
}
