<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class RenameAndExtendAbsenceMappingsToPlanningEventMappings extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('planning_event_mappings');
        
        // Si la table n'existe pas, elle a peut-être encore l'ancien nom
        if (!$this->hasTable('planning_event_mappings') && $this->hasTable('absence_mappings')) {
            $this->execute("RENAME TABLE `absence_mappings` TO `planning_event_mappings`");
        }
        
        // Vérifier et renommer la colonne excel_pattern si elle existe
        try {
            $result = $this->query("SHOW COLUMNS FROM `planning_event_mappings` LIKE 'excel_pattern'");
            if ($result->rowCount() > 0) {
                $this->execute("ALTER TABLE `planning_event_mappings` CHANGE COLUMN `excel_pattern` `keywords` VARCHAR(255) NULL DEFAULT NULL");
            }
        } catch (\Exception $e) {
            // Colonne déjà renommée ou n'existe pas
        }
        
        // Ajouter color_code si nécessaire
        try {
            $result = $this->query("SHOW COLUMNS FROM `planning_event_mappings` LIKE 'color_code'");
            if ($result->rowCount() === 0) {
                $this->execute("ALTER TABLE `planning_event_mappings` ADD COLUMN `color_code` VARCHAR(6) NULL DEFAULT NULL AFTER `keywords`");
            }
        } catch (\Exception $e) {
            // Colonne existe déjà
        }
        
        // Ajouter type si nécessaire
        try {
            $result = $this->query("SHOW COLUMNS FROM `planning_event_mappings` LIKE 'type'");
            if ($result->rowCount() === 0) {
                $this->execute("ALTER TABLE `planning_event_mappings` ADD COLUMN `type` VARCHAR(20) NOT NULL DEFAULT 'absence' AFTER `color_code`");
            }
        } catch (\Exception $e) {
            // Colonne existe déjà
        }
        
        // Supprimer l'index unique s'il existe
        try {
            $this->execute("ALTER TABLE `planning_event_mappings` DROP INDEX `absence_mappings_excel_pattern_unique`");
        } catch (\Exception $e) {
            // Index n'existe pas, on continue
        }
        
        // Ajouter index composite si nécessaire
        try {
            $result = $this->query("SHOW INDEX FROM `planning_event_mappings` WHERE Key_name = 'idx_type_priority'");
            if ($result->rowCount() === 0) {
                $this->execute("ALTER TABLE `planning_event_mappings` ADD INDEX `idx_type_priority` (`type`, `priority`)");
            }
        } catch (\Exception $e) {
            // Index existe déjà
        }
        
        // Migrer les données existantes
        try {
            $this->execute("UPDATE `planning_event_mappings` SET `type` = 'absence' WHERE `type` IS NULL OR `type` = ''");
        } catch (\Exception $e) {
            // Ignorer si la colonne type n'existe pas encore
        }
    }
}
