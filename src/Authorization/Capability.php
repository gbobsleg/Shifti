<?php
declare(strict_types=1);

namespace App\Authorization;

use ReflectionClass;

/**
 * Capacités métier vérifiées par les policies.
 */
final class Capability
{
    public const PLANNING_CONSULTER = 'planning.consulter';
    public const PLANNING_CONSULTER_TOUS_SITES = 'planning.consulter_tous_sites';
    public const PLANNING_INDICATEURS = 'planning.indicateurs';
    public const PLANNING_MODIFIER = 'planning.modifier';
    public const PLANNING_GENERER = 'planning.generer';
    public const PLANNING_PUBLIER = 'planning.publier';
    public const PLAGES_GERER = 'plages.gerer';

    public const IMPORT_PLANNING = 'import.planning';
    public const HISTORIQUE_IMPORTER = 'historique.importer';
    public const HISTORIQUE_CONSULTER = 'historique.consulter';

    public const PREVISION_GERER = 'prevision.gerer';
    public const PREVISION_PUBLIER = 'prevision.publier';

    public const OFFRES_GERER = 'offres.gerer';
    public const OFFRES_OPTIMISER = 'offres.optimiser';

    public const ABSENCES_GERER = 'absences.gerer';
    public const TELETRAVAIL_GERER = 'teletravail.gerer';
    public const ALERTES_GERER = 'alertes.gerer';
    public const ALERTES_CONSULTER_TOUTES = 'alertes.consulter_toutes';

    public const UTILISATEURS_GERER = 'utilisateurs.gerer';

    public const REF_ROLES = 'referentiels.roles';
    public const REF_SITES = 'referentiels.sites';
    public const REF_REGIONS = 'referentiels.regions';
    public const REF_COMPETENCES = 'referentiels.competences';
    public const REF_DISPONIBILITES = 'referentiels.disponibilites';
    public const REF_AFFICHAGE = 'referentiels.affichage';
    public const REF_CORRESPONDANCES = 'referentiels.correspondances';
    public const REF_WFM = 'referentiels.wfm';
    public const REF_ROTATIONS = 'referentiels.rotations';
    public const REF_ACTIVITES_FIXES = 'referentiels.activites_fixes';

    public const JOBS_SUPERVISER = 'jobs.superviser';
    public const ADMINISTRATION_ACCEDER = 'administration.acceder';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_values((new ReflectionClass(self::class))->getConstants());
    }
}
