<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class RefactorOffersPriorityToStructure extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('offers');

        // Étape 1: Ajouter les nouveaux champs
        if (!$table->hasColumn('offer_type')) {
            $table->addColumn('offer_type', 'string', [
                'default' => 'normal',
                'limit' => 20,
                'null' => false,
                'comment' => 'Type d\'offre: normal, absence, remote_work, pause, lunch',
                'after' => 'color',
            ]);
        }

        if (!$table->hasColumn('display_order')) {
            $table->addColumn('display_order', 'integer', [
                'default' => 10,
                'null' => false,
                'comment' => 'Ordre d\'affichage (ex-priority)',
                'after' => 'offer_type',
            ]);
        }

        if (!$table->hasColumn('is_displayed_in_grid')) {
            $table->addColumn('is_displayed_in_grid', 'boolean', [
                'default' => true,
                'null' => false,
                'comment' => 'Afficher dans la colonne gauche du planning',
                'after' => 'display_order',
            ]);
        }

        if (!$table->hasColumn('is_forecastable')) {
            $table->addColumn('is_forecastable', 'boolean', [
                'default' => true,
                'null' => false,
                'comment' => 'Utilisable dans les prévisions',
                'after' => 'is_displayed_in_grid',
            ]);
        }

        $table->update();

        // Étape 2: Migrer les données existantes
        $this->migrateOfferData();

        // Étape 3: Supprimer l'ancien champ priority
        $table = $this->table('offers');
        if ($table->hasColumn('priority')) {
            $table->removeColumn('priority');
            $table->update();
        }
    }

    public function down(): void
    {
        $table = $this->table('offers');

        // Restaurer le champ priority
        if (!$table->hasColumn('priority')) {
            $table->addColumn('priority', 'integer', [
                'default' => 1,
                'null' => false,
                'after' => 'color',
            ]);
        }

        // Copier display_order vers priority
        $this->execute('UPDATE offers SET priority = display_order WHERE display_order IS NOT NULL');

        // Supprimer les nouveaux champs
        if ($table->hasColumn('is_forecastable')) {
            $table->removeColumn('is_forecastable');
        }
        if ($table->hasColumn('is_displayed_in_grid')) {
            $table->removeColumn('is_displayed_in_grid');
        }
        if ($table->hasColumn('display_order')) {
            $table->removeColumn('display_order');
        }
        if ($table->hasColumn('offer_type')) {
            $table->removeColumn('offer_type');
        }

        $table->update();
    }

    /**
     * Migrer les données en détectant le type d'offre
     */
    private function migrateOfferData(): void
    {
        $offers = $this->fetchAll('SELECT id, name, priority FROM offers');

        foreach ($offers as $offer) {
            $name = strtolower($offer['name']);
            $priority = (int)$offer['priority'];

            // Détection du type par priority + nom
            $type = 'normal';
            $displayed = 1;
            $forecastable = 1;

            if ($priority == 0 || stripos($name, 'absence') !== false) {
                $type = 'absence';
                $displayed = 0;  // Absences non affichées dans grille
                $forecastable = 0;
            } elseif (stripos($name, 'télétravail') !== false || stripos($name, 'teletravail') !== false || stripos($name, 'remote') !== false) {
                $type = 'remote_work';
                $displayed = 0;  // Non sélectionnable
                $forecastable = 0;
            } elseif (stripos($name, 'pause') !== false) {
                $type = 'pause';
                $displayed = 0;  // Géré automatiquement par solver
                $forecastable = 0;
            } elseif (stripos($name, 'repas') !== false || stripos($name, 'déjeuner') !== false || stripos($name, 'lunch') !== false) {
                $type = 'lunch';
                $displayed = 0;
                $forecastable = 0;
            } elseif (stripos($name, 'temps partiel') !== false || stripos($name, 'part time') !== false) {
                // Temps partiel devient une offre normale non forecastable
                // Elle sera progressivement supprimée au profit de user_availabilities
                $type = 'normal';
                $displayed = 0;
                $forecastable = 0;
            }

            $displayOrder = $priority > 0 ? $priority : 999;

            $this->execute(sprintf(
                "UPDATE offers SET offer_type = '%s', display_order = %d, is_displayed_in_grid = %d, is_forecastable = %d WHERE id = %d",
                $type,
                $displayOrder,
                $displayed,
                $forecastable,
                $offer['id']
            ));
        }

        // Log des migrations pour traçabilité
        $this->output->writeln('<info>Migration des offres terminée:</info>');
        $summary = $this->fetchAll("SELECT offer_type, COUNT(*) as count FROM offers GROUP BY offer_type");
        foreach ($summary as $row) {
            $this->output->writeln(sprintf('  - %s: %d offre(s)', $row['offer_type'], $row['count']));
        }
    }
}
