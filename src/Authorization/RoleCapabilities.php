<?php
declare(strict_types=1);

namespace App\Authorization;

/**
 * Matrice des capacités accordées à chaque code de rôle.
 */
final class RoleCapabilities
{
    /**
     * @return list<string>
     */
    public static function forCode(?string $code): array
    {
        $manager = [
            Capability::PLANNING_CONSULTER,
            Capability::PLANNING_CONSULTER_TOUS_SITES,
            Capability::PLANNING_INDICATEURS,
            Capability::PLANNING_MODIFIER,
            Capability::HISTORIQUE_CONSULTER,
            Capability::ABSENCES_GERER,
            Capability::TELETRAVAIL_GERER,
            Capability::ALERTES_GERER,
            Capability::ALERTES_CONSULTER_TOUTES,
            Capability::UTILISATEURS_GERER,
            Capability::ADMINISTRATION_ACCEDER,
        ];

        return match ($code) {
            'agent' => [Capability::PLANNING_CONSULTER],
            'manager' => $manager,
            'planificateur' => array_values(array_unique(array_merge($manager, [
                Capability::PLANNING_GENERER,
                Capability::PLANNING_PUBLIER,
                Capability::IMPORT_PLANNING,
                Capability::PREVISION_GERER,
                Capability::PREVISION_PUBLIER,
                Capability::JOBS_SUPERVISER,
                Capability::REF_ROTATIONS,
                Capability::REF_ACTIVITES_FIXES,
            ]))),
            'admin' => Capability::all(),
            default => [],
        };
    }
}
