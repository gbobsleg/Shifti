<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class RemoveTypeFromPlanningEventMappings extends BaseMigration
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
        $table = $this->table('planning_event_mappings');
        $table->removeColumn('type');
        // Supprimer aussi l'index composite qui incluait type
        try {
            $this->execute("ALTER TABLE `planning_event_mappings` DROP INDEX `idx_type_priority`");
        } catch (\Exception $e) {
            // Index n'existe peut-être pas, on continue
        }
        $table->update();
    }
}
