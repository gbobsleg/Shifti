<?php
declare(strict_types=1);

namespace App\Authorization;

/**
 * Libellés lisibles des capacités, regroupés pour l'affichage.
 */
final class CapabilityCatalog
{
    /**
     * @return array<string, array<string, string>> Nom du groupe => [capacité => libellé]
     */
    public static function groups(): array
    {
        return [
            'Planning' => [
                Capability::PLANNING_CONSULTER => 'Consulter la grille de planning',
                Capability::PLANNING_CONSULTER_TOUS_SITES => 'Voir tous les sites (sinon : son site uniquement)',
                Capability::PLANNING_INDICATEURS => 'Voir les lignes besoin et réel de la grille',
                Capability::PLANNING_MODIFIER => 'Modifier la grille et restaurer l\'historique du jour',
                Capability::PLANNING_GENERER => 'Lancer et retravailler les générations de planning',
                Capability::PLANNING_PUBLIER => 'Publier une génération de planning',
                Capability::PLAGES_GERER => 'Gérer les plages depuis le menu Plages',
                Capability::IMPORT_PLANNING => 'Importer les plannings Excel',
            ],
            'Prévisions et historique' => [
                Capability::PREVISION_GERER => 'Créer et modifier les scénarios de prévision',
                Capability::PREVISION_PUBLIER => 'Publier une prévision',
                Capability::HISTORIQUE_CONSULTER => 'Consulter le réel et la prévision',
                Capability::HISTORIQUE_IMPORTER => 'Importer les données historiques (CSV)',
            ],
            'Offres' => [
                Capability::OFFRES_GERER => 'Gérer les offres et les groupes d\'offres',
                Capability::OFFRES_OPTIMISER => 'Optimiser les paramètres de prévision d\'une offre',
            ],
            'Équipe' => [
                Capability::ABSENCES_GERER => 'Gérer les absences',
                Capability::TELETRAVAIL_GERER => 'Gérer le télétravail',
                Capability::UTILISATEURS_GERER => 'Gérer les utilisateurs (rôle égal ou inférieur au sien)',
                Capability::ALERTES_GERER => 'Créer et supprimer des alertes',
                Capability::ALERTES_CONSULTER_TOUTES => 'Voir toutes les alertes (sinon : priorité 3 uniquement)',
            ],
            'Référentiels' => [
                Capability::REF_ROLES => 'Rôles',
                Capability::REF_SITES => 'Sites',
                Capability::REF_REGIONS => 'Régions',
                Capability::REF_COMPETENCES => 'Compétences',
                Capability::REF_DISPONIBILITES => 'Disponibilités',
                Capability::REF_AFFICHAGE => 'Paramètres d\'affichage',
                Capability::REF_CORRESPONDANCES => 'Correspondances d\'absences (GroomRH)',
                Capability::REF_WFM => 'Paramètres WFM',
                Capability::REF_ROTATIONS => 'Règles de rotation',
                Capability::REF_ACTIVITES_FIXES => 'Activités fixes',
            ],
            'Administration' => [
                Capability::ADMINISTRATION_ACCEDER => 'Accéder à la page Administration',
                Capability::JOBS_SUPERVISER => 'Superviser et annuler les jobs',
            ],
        ];
    }
}
