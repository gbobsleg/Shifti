<?php
declare(strict_types=1);

namespace App\Test\TestCase\Authorization;

use App\Service\Authorization\PermissionService;
use Cake\TestSuite\TestCase;

class RoleAssignmentTest extends TestCase
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

    public function testManagerCannotManageAdmin(): void
    {
        $manager = new TestIdentity(['role_id' => 2]);
        $this->assertFalse($this->service->canManageUser($manager, 1));
    }

    public function testManagerCanManageAnotherManager(): void
    {
        $manager = new TestIdentity(['role_id' => 2]);
        $this->assertTrue($this->service->canManageUser($manager, 2));
    }

    public function testPlannerCanManageAnotherPlanner(): void
    {
        $planner = new TestIdentity(['role_id' => 4]);
        $this->assertTrue($this->service->canManageUser($planner, 4));
    }
}
