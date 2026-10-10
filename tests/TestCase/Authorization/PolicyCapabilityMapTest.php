<?php
declare(strict_types=1);

namespace App\Test\TestCase\Authorization;

use App\Authorization\Capability;
use App\Authorization\RoleCapabilities;
use App\Policy\AbstractCapabilityPolicy;
use App\Service\Authorization\PermissionService;
use Cake\TestSuite\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

class PolicyCapabilityMapTest extends TestCase
{
    /**
     * Correspondance méthode de policy → capacité.
     *
     * @return array<class-string, array<string, string>>
     */
    private static function map(): array
    {
        $crud = static fn (string $capability): array => [
            'canIndex' => $capability,
            'canView' => $capability,
            'canAdd' => $capability,
            'canEdit' => $capability,
            'canDelete' => $capability,
        ];

        return [
            \App\Policy\AbsencesPolicy::class => $crud(Capability::ABSENCES_GERER),
            \App\Policy\AlertsPolicy::class => $crud(Capability::ALERTES_GERER),
            \App\Policy\BackgroundJobsPolicy::class => [
                'canIndex' => Capability::JOBS_SUPERVISER,
                'canStatus' => Capability::JOBS_SUPERVISER,
                'canCancelOptuna' => Capability::JOBS_SUPERVISER,
            ],
            \App\Policy\DisplaySettingsPolicy::class => $crud(Capability::REF_AFFICHAGE),
            \App\Policy\ExcelUploadsPolicy::class => [
                'canUpload' => Capability::IMPORT_PLANNING,
                'canPreview' => Capability::IMPORT_PLANNING,
                'canProcess' => Capability::IMPORT_PLANNING,
            ],
            \App\Policy\FixedActivityRulesPolicy::class => $crud(Capability::REF_ACTIVITES_FIXES),
            \App\Policy\ForecastScenariosPolicy::class => [
                'canIndex' => Capability::PREVISION_GERER,
                'canAdd' => Capability::PREVISION_GERER,
                'canRun' => Capability::PREVISION_GERER,
                'canStatus' => Capability::PREVISION_GERER,
                'canView' => Capability::PREVISION_GERER,
                'canDelete' => Capability::PREVISION_GERER,
                'canEdit' => Capability::PREVISION_GERER,
                'canPublish' => Capability::PREVISION_PUBLIER,
                'canUnpublish' => Capability::PREVISION_PUBLIER,
            ],
            \App\Policy\GridsPolicy::class => [
                'canIndex' => Capability::PLANNING_CONSULTER,
                'canGetUsersBySite' => Capability::PLANNING_CONSULTER,
                'canPlannedSeries' => Capability::PLANNING_INDICATEURS,
                'canNeedSeries' => Capability::PLANNING_INDICATEURS,
                'canAdd' => Capability::PLANNING_MODIFIER,
                'canDayHistory' => Capability::PLANNING_MODIFIER,
                'canRestoreDayHistory' => Capability::PLANNING_MODIFIER,
            ],
            \App\Policy\HistoricalDataPolicy::class => [
                'canImport' => Capability::HISTORIQUE_IMPORTER,
                'canVisualize' => Capability::HISTORIQUE_CONSULTER,
                'canGetData' => Capability::HISTORIQUE_CONSULTER,
            ],
            \App\Policy\OfferGroupsPolicy::class => $crud(Capability::OFFRES_GERER),
            \App\Policy\OffersPolicy::class => [
                'canIndex' => Capability::OFFRES_GERER,
                'canView' => Capability::OFFRES_GERER,
                'canAdd' => Capability::OFFRES_GERER,
                'canEdit' => Capability::OFFRES_GERER,
                'canDelete' => Capability::OFFRES_GERER,
                'canTuneStart' => Capability::OFFRES_OPTIMISER,
                'canTuneStatus' => Capability::OFFRES_OPTIMISER,
                'canTuneApply' => Capability::OFFRES_OPTIMISER,
                'canTuneReject' => Capability::OFFRES_OPTIMISER,
                'canTuneRollback' => Capability::OFFRES_OPTIMISER,
                'canTuneCancel' => Capability::OFFRES_OPTIMISER,
            ],
            \App\Policy\PagesPolicy::class => [
                'canAdmin' => Capability::ADMINISTRATION_ACCEDER,
            ],
            \App\Policy\PlanningEventMappingsPolicy::class => $crud(Capability::REF_CORRESPONDANCES),
            \App\Policy\PlanningGenerationJobsPolicy::class => [
                'canIndex' => Capability::PLANNING_GENERER,
                'canAdd' => Capability::PLANNING_GENERER,
                'canEdit' => Capability::PLANNING_GENERER,
                'canView' => Capability::PLANNING_GENERER,
                'canDelete' => Capability::PLANNING_GENERER,
                'canStatus' => Capability::PLANNING_GENERER,
                'canReport' => Capability::PLANNING_GENERER,
                'canEquityReport' => Capability::PLANNING_GENERER,
                'canDraft' => Capability::PLANNING_GENERER,
                'canSaveDraft' => Capability::PLANNING_GENERER,
                'canRetry' => Capability::PLANNING_GENERER,
                'canPublish' => Capability::PLANNING_PUBLIER,
                'canClearDraft' => Capability::PLANNING_PUBLIER,
            ],
            \App\Policy\RangesPolicy::class => $crud(Capability::PLAGES_GERER),
            \App\Policy\RegionsPolicy::class => $crud(Capability::REF_REGIONS),
            \App\Policy\RemoteWorkPolicy::class => [
                'canIndex' => Capability::TELETRAVAIL_GERER,
                'canConfigure' => Capability::TELETRAVAIL_GERER,
                'canAjaxGetUserSettings' => Capability::TELETRAVAIL_GERER,
                'canAddDay' => Capability::TELETRAVAIL_GERER,
                'canDelete' => Capability::TELETRAVAIL_GERER,
            ],
            \App\Policy\RolesPolicy::class => $crud(Capability::REF_ROLES),
            \App\Policy\RotationRulesPolicy::class => $crud(Capability::REF_ROTATIONS),
            \App\Policy\SchedulesPolicy::class => [
                'canGenerate' => Capability::PLANNING_GENERER,
            ],
            \App\Policy\SitesPolicy::class => $crud(Capability::REF_SITES),
            \App\Policy\SkillsPolicy::class => $crud(Capability::REF_COMPETENCES),
            \App\Policy\UserAvailabilitiesPolicy::class => $crud(Capability::REF_DISPONIBILITES),
            \App\Policy\UsersPolicy::class => $crud(Capability::UTILISATEURS_GERER),
            \App\Policy\WfmSettingsPolicy::class => $crud(Capability::REF_WFM),
        ];
    }

    /**
     * @return array<string, array{class-string, string, string, int, string}>
     */
    public static function policyDecisions(): array
    {
        $roles = [
            1 => 'admin',
            2 => 'manager',
            3 => 'agent',
            4 => 'planificateur',
        ];
        $cases = [];
        foreach (self::map() as $class => $methods) {
            foreach ($methods as $method => $capability) {
                foreach ($roles as $roleId => $code) {
                    $cases[$class . '::' . $method . ' ' . $code] = [
                        $class,
                        $method,
                        $capability,
                        $roleId,
                        $code,
                    ];
                }
            }
        }

        return $cases;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('policyDecisions')]
    public function testPolicyFollowsCapability(
        string $class,
        string $method,
        string $capability,
        int $roleId,
        string $code,
    ): void {
        $permissions = new PermissionService([
            1 => ['code' => 'admin', 'priority' => 10],
            2 => ['code' => 'manager', 'priority' => 30],
            3 => ['code' => 'agent', 'priority' => 40],
            4 => ['code' => 'planificateur', 'priority' => 20],
        ]);
        $policy = new $class($permissions);
        $resource = $this->resourceFor($class, $method);
        $allowed = in_array($capability, RoleCapabilities::forCode($code), true);

        $this->assertSame($allowed, $policy->{$method}(new TestIdentity(['role_id' => $roleId]), $resource));
    }

    public function testEveryPolicyMethodIsMapped(): void
    {
        $map = self::map();
        $dir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Policy';
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*Policy.php') ?: [] as $file) {
            $class = 'App\\Policy\\' . basename($file, '.php');
            if (!is_subclass_of($class, AbstractCapabilityPolicy::class)) {
                continue;
            }
            $reflection = new ReflectionClass($class);
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if (!str_starts_with($method->getName(), 'can') || $method->getDeclaringClass()->getName() !== $class) {
                    continue;
                }
                $this->assertArrayHasKey($method->getName(), $map[$class] ?? [], $class . '::' . $method->getName());
            }
        }
    }

    private function resourceFor(string $class, string $method): object
    {
        $parameter = (new ReflectionMethod($class, $method))->getParameters()[1] ?? null;
        $type = $parameter?->getType();
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $name = $type->getName();

            return new $name();
        }

        return new \stdClass();
    }
}
