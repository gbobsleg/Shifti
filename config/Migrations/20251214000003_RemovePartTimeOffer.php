<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class RemovePartTimeOffer extends AbstractMigration
{
    public function up(): void
    {
        // Étape 1: Supprimer les ranges associés à l'offre "Temps partiel"
        // Utilisation d'une table temporaire pour contourner la limitation MySQL
        $this->execute("
            DELETE r FROM ranges r
            INNER JOIN offers o ON r.offer_id = o.id
            WHERE o.name LIKE '%temps partiel%' 
               OR o.name LIKE '%part time%'
        ");
        
        // Étape 2: Supprimer l'offre elle-même
        $this->execute("
            DELETE FROM offers 
            WHERE name LIKE '%temps partiel%' 
               OR name LIKE '%part time%'
        ");
    }

    public function down(): void
    {
        // Ne peut pas être annulé car les données sont supprimées
        // Cette migration est irréversible
    }
}
