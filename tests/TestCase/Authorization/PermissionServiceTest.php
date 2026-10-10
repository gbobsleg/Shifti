<?php
declare(strict_types=1);

namespace App\Test\TestCase\Authorization;

use App\Authorization\Capability;
use App\Authorization\RoleCapabilities;
use App\Service\Authorization\PermissionService;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class PermissionServiceTest extends TestCase
{
    private PermissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PermissionService([
            1 => ['code' => 'admin', 'priority' => 10],
            2 => ['code' => 'manager', 'priority' => 30],
            3 => ['code' => 'agent', 'priority' => 40],
            4 => ['code' => 'planificateur', 'priority' => 20],
        ]);
    }

    /**
     * @return array<string, array{int, string, bool}>
     */
    public static function capabilityMatrix(): array
    {
        $roles = [
            1 => 'admin',
            2 => 'manager',
            3 => 'agent',
            4 => 'planificateur',
        ];
        $cases = [];
        foreach ($roles as $roleId => $code) {
            $granted = RoleCapabilities::forCode($code);
            foreach (Capability::all() as $capability) {
                $cases[$code . ' ' . $capability] = [
                    $roleId,
                    $capability,
                    in_array($capability, $granted, true),
                ];
            }
        }

        return $cases;
    }

    #[DataProvider('capabilityMatrix')]
    public function testHasMatchesRoleMatrix(int $roleId, string $capability, bool $expected): void
    {
        $identity = new TestIdentity(['role_id' => $roleId]);
        $this->assertSame($expected, $this->service->has($identity, $capability));
    }

    public function testUnknownRoleHasNoCapability(): void
    {
        $identity = new TestIdentity(['role_id' => 99]);
        foreach (Capability::all() as $capability) {
            $this->assertFalse($this->service->has($identity, $capability));
        }
    }

    public function testManagerAssignableRolesExcludeHigherRoles(): void
    {
        $ids = $this->service->assignableRoleIds(new TestIdentity(['role_id' => 2]));
        sort($ids);
        $this->assertSame([2, 3], $ids);
    }

    public function testCanManageUserRespectsPriority(): void
    {
        $manager = new TestIdentity(['role_id' => 2]);
        $planner = new TestIdentity(['role_id' => 4]);

        $this->assertFalse($this->service->canManageUser($manager, 1));
        $this->assertTrue($this->service->canManageUser($manager, 2));
        $this->assertTrue($this->service->canManageUser($planner, 4));
        $this->assertFalse($this->service->canManageUser($manager, 4));
    }
}
