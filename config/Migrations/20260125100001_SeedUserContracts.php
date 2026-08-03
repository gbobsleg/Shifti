<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class SeedUserContracts extends BaseMigration
{
    public function up(): void
    {
        // Insérer un contrat par défaut (CDI depuis 2000) pour tous les utilisateurs existants
        // qui n'ont pas encore de contrat.
        $this->execute("
            INSERT INTO user_contracts (user_id, start_date, end_date, created, modified)
            SELECT id, '2000-01-01', NULL, NOW(), NOW()
            FROM users
            WHERE id NOT IN (SELECT DISTINCT user_id FROM user_contracts)
        ");
    }

    public function down(): void
    {
        // On ne supprime pas les données au rollback pour éviter de perdre des infos saisies manuellement
        // Mais théoriquement, on pourrait vider la table si on voulait un rollback strict.
    }
}
