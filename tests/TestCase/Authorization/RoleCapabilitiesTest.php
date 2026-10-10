<?php
declare(strict_types=1);

namespace App\Test\TestCase\Authorization;

use App\Authorization\Capability;
use App\Authorization\RoleCapabilities;
use Cake\TestSuite\TestCase;

class RoleCapabilitiesTest extends TestCase
{
    public function testEveryGrantedCapabilityExists(): void
    {
        $known = Capability::all();
        foreach (['agent', 'manager', 'planificateur', 'admin', null, 'inconnu'] as $code) {
            foreach (RoleCapabilities::forCode($code) as $capability) {
                $this->assertContains($capability, $known, $code . ' : ' . $capability);
            }
        }
    }

    public function testPlannerIncludesEveryManagerCapability(): void
    {
        $manager = RoleCapabilities::forCode('manager');
        $planner = RoleCapabilities::forCode('planificateur');
        foreach ($manager as $capability) {
            $this->assertContains($capability, $planner);
        }
    }
}
