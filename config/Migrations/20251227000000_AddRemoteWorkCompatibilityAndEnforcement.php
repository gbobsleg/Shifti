<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddRemoteWorkCompatibilityAndEnforcement extends AbstractMigration
{
    public function up(): void
    {
        // offers: compatibilité télétravail par offre
        if ($this->hasTable('offers')) {
            $offers = $this->table('offers');
            if (!$offers->hasColumn('is_remote_work_compatible')) {
                $offers->addColumn('is_remote_work_compatible', 'boolean', [
                    'default' => true,
                    'null' => false,
                    'comment' => 'Si false, l’offre est incompatible avec le télétravail (interdite sur les créneaux remote_work si l’option WFM est activée).',
                    'after' => 'is_forecastable',
                ]);
                $offers->update();
            }
        }

        // wfm_settings: activation globale de la contrainte
        if ($this->hasTable('wfm_settings')) {
            $settings = $this->table('wfm_settings');
            if (!$settings->hasColumn('enforce_remote_work_incompatibilities')) {
                $settings->addColumn('enforce_remote_work_incompatibilities', 'boolean', [
                    'default' => false,
                    'null' => false,
                    'comment' => 'Si true, interdit d’assigner des activités incompatibles télétravail sur les créneaux remote_work.',
                ]);
                $settings->update();
            }
        }
    }

    public function down(): void
    {
        if ($this->hasTable('offers')) {
            $offers = $this->table('offers');
            if ($offers->hasColumn('is_remote_work_compatible')) {
                $offers->removeColumn('is_remote_work_compatible')->update();
            }
        }

        if ($this->hasTable('wfm_settings')) {
            $settings = $this->table('wfm_settings');
            if ($settings->hasColumn('enforce_remote_work_incompatibilities')) {
                $settings->removeColumn('enforce_remote_work_incompatibilities')->update();
            }
        }
    }
}








