<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\Export;

use App\Service\Export\TimeSlotGrid;
use Cake\TestSuite\TestCase;

class TimeSlotGridTest extends TestCase
{
    public function testHourWindowStopsBeforeTheEnd(): void
    {
        $slots = (new TimeSlotGrid())->slots('09:00:00', '10:00:00');

        $this->assertSame(['09:00:00', '09:15:00', '09:30:00', '09:45:00'], $slots);
    }

    public function testPartialHourKeepsOnlyOpenQuarters(): void
    {
        $slots = (new TimeSlotGrid())->slots('17:00:00', '17:30:00');

        $this->assertSame(['17:00:00', '17:15:00'], $slots);
    }
}
