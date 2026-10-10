<?php
declare(strict_types=1);

namespace App\Test\TestCase\Authorization;

use App\Service\Authorization\PerimeterService;
use App\Service\Authorization\PermissionService;
use Cake\TestSuite\TestCase;

class PerimeterServiceTest extends TestCase
{
    private PerimeterService $perimeter;

    protected function setUp(): void
    {
        parent::setUp();
        $permissions = new PermissionService([
            1 => ['code' => 'admin', 'priority' => 10],
            2 => ['code' => 'manager', 'priority' => 30],
            3 => ['code' => 'agent', 'priority' => 40],
            4 => ['code' => 'planificateur', 'priority' => 20],
        ]);
        $this->perimeter = new PerimeterService($permissions);
    }

    public function testAgentSeesOnlyOwnSite(): void
    {
        $agent = new TestIdentity(['role_id' => 3, 'site_id' => 5]);
        $this->assertSame([5], $this->perimeter->visibleSiteIds($agent));
    }

    public function testAgentWithoutSiteSeesNobody(): void
    {
        $agent = new TestIdentity(['role_id' => 3, 'site_id' => null]);
        $this->assertSame([], $this->perimeter->visibleSiteIds($agent));
    }

    public function testManagerSeesAllSites(): void
    {
        $manager = new TestIdentity(['role_id' => 2, 'site_id' => 1]);
        $this->assertNull($this->perimeter->visibleSiteIds($manager));
    }

    public function testAgentSeesOnlyPriorityThreeAlerts(): void
    {
        $agent = new TestIdentity(['role_id' => 3]);
        $this->assertSame([3], $this->perimeter->visibleAlertPriorities($agent));
    }

    public function testManagerSeesAllAlerts(): void
    {
        $manager = new TestIdentity(['role_id' => 2]);
        $this->assertNull($this->perimeter->visibleAlertPriorities($manager));
    }
}
