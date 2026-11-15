<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\Rotation;

use App\Service\Rotation\RotationTargetCalculatorService;
use Cake\TestSuite\TestCase;

class RotationWeeklyQuotaTest extends TestCase
{
    public function testSemaineDeNoelTroisCreneaux(): void
    {
        $calc = new RotationTargetCalculatorService();
        // base 3, trame 5, 4 jours ouverts, 0 absence → round(3*4/5) = 2
        $this->assertSame(2, $calc->weeklyQuota(3, 5, 4, 0));
    }

    public function testJobCoupeMardi(): void
    {
        $calc = new RotationTargetCalculatorService();
        // 2 jours ouverts → round(3*2/5) = 1
        $this->assertSame(1, $calc->weeklyQuota(3, 5, 2, 0));
    }

    public function testPlafondAuxJoursOuverts(): void
    {
        $calc = new RotationTargetCalculatorService();
        $this->assertSame(1, $calc->weeklyQuota(5, 5, 1, 0));
        $this->assertSame(0, $calc->weeklyQuota(3, 5, 1, 1));
    }
}
