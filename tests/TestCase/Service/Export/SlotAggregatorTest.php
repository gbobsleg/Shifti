<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\Export;

use App\Service\Export\SlotAggregator;
use Cake\TestSuite\TestCase;

class SlotAggregatorTest extends TestCase
{
    public function testHourAndDayUseTheMeanNotThePeak(): void
    {
        $slots = [
            ['time' => '10:00:00', 'volume' => 10, 'need' => 4, 'dmt' => 100],
            ['time' => '10:15:00', 'volume' => 0, 'need' => 0, 'dmt' => null],
            ['time' => '10:30:00', 'volume' => 10, 'need' => 4, 'dmt' => 300],
            ['time' => '10:45:00', 'volume' => 0, 'need' => 0, 'dmt' => null],
        ];
        $aggregator = new SlotAggregator();

        $hour = $aggregator->aggregate($slots, 'hour');
        $this->assertCount(1, $hour);
        $this->assertSame('10:00', $hour[0]['time']);
        $this->assertSame(20, $hour[0]['volume']);
        $this->assertSame('2,00', $hour[0]['need']);
        $this->assertSame('200', $hour[0]['dmt']);

        $day = $aggregator->aggregate($slots, 'day');
        $this->assertSame('', $day[0]['time']);
        $this->assertSame(20, $day[0]['volume']);
        $this->assertSame('2,00', $day[0]['need']);
        $this->assertNotSame('4', $day[0]['need']);
    }

    public function testPartialHourDividesByTheSlotsPresent(): void
    {
        $slots = [
            ['time' => '17:00:00', 'volume' => 0, 'need' => 3, 'dmt' => null],
            ['time' => '17:15:00', 'volume' => 0, 'need' => 1, 'dmt' => null],
        ];

        $hour = (new SlotAggregator())->aggregate($slots, 'hour');

        $this->assertSame('2,00', $hour[0]['need']);
        $this->assertSame(0, $hour[0]['volume']);
        $this->assertSame('', $hour[0]['dmt']);
    }

    public function testQuarterKeepsAZeroSlotWithEmptyDmt(): void
    {
        $rows = (new SlotAggregator())->aggregate([
            ['time' => '10:00:00', 'volume' => 5, 'need' => 2, 'dmt' => 60],
            ['time' => '10:15:00', 'volume' => 0, 'need' => 0, 'dmt' => null],
        ], '15min');

        $this->assertSame('5', (string)$rows[0]['volume']);
        $this->assertSame('60', $rows[0]['dmt']);
        $this->assertSame(0, $rows[1]['volume']);
        $this->assertSame('', $rows[1]['dmt']);
        $this->assertSame('0', $rows[1]['need']);
    }
}
