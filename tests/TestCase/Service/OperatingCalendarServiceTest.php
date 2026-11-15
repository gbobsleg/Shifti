<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Model\Entity\WfmSetting;
use App\Service\OperatingCalendarService;
use Cake\I18n\FrozenDate;
use Cake\TestSuite\TestCase;

class OperatingCalendarServiceTest extends TestCase
{
    private function calendar(): OperatingCalendarService
    {
        return new OperatingCalendarService(
            [
                '2026-12-25' => false,
                '2027-01-01' => false,
                '2026-05-25' => true,
                '2026-04-06' => false,
            ],
            [
                '2026-12-25' => 'Noël',
                '2027-01-01' => 'Jour de l\'an',
                '2026-05-25' => 'Lundi de Pentecôte',
                '2026-04-06' => 'Lundi de Pâques',
            ]
        );
    }

    private function settings(): WfmSetting
    {
        return new WfmSetting(['worked_days_json' => [1, 2, 3, 4, 5]]);
    }

    public function testSaturdayIsNotFrameAndChristmasFridayIsClosedButVisible(): void
    {
        $cal = $this->calendar();
        $settings = $this->settings();
        $saturday = new FrozenDate('2026-12-26');
        $christmas = new FrozenDate('2026-12-25');

        $this->assertFalse($cal->isFrameDay($saturday, $settings));
        $this->assertFalse($cal->isOpen($saturday, $settings));
        $this->assertTrue($cal->isFrameDay($christmas, $settings));
        $this->assertFalse($cal->isOpen($christmas, $settings));
        $this->assertSame('Noël', $cal->label($christmas));
    }

    public function testPentecostForcedOpen(): void
    {
        $cal = $this->calendar();
        $day = new FrozenDate('2026-05-25');
        $this->assertTrue($cal->isOpen($day, $this->settings()));
    }

    public function testOpenIndicesChristmasWeekAndSpillover(): void
    {
        $cal = $this->calendar();
        $settings = $this->settings();

        $christmasWeek = $cal->openDayIndices(
            new FrozenDate('2026-12-21'),
            $settings,
            new FrozenDate('2026-12-01'),
            new FrozenDate('2026-12-31')
        );
        $this->assertSame([0, 1, 2, 3], $christmasWeek);

        $newYearWeek = $cal->openDayIndices(
            new FrozenDate('2026-12-28'),
            $settings,
            new FrozenDate('2026-12-01'),
            new FrozenDate('2026-12-31')
        );
        $this->assertSame([0, 1, 2, 3], $newYearWeek);
    }

    public function testEasterMondayStartsOnTuesdayIndex(): void
    {
        $cal = $this->calendar();
        $indices = $cal->openDayIndices(
            new FrozenDate('2026-04-06'),
            $this->settings(),
            new FrozenDate('2026-04-06'),
            new FrozenDate('2026-04-10')
        );
        $this->assertSame([1, 2, 3, 4], $indices);
    }
}
