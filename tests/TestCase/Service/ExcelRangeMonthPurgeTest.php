<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\ExcelRangeMonthPurge;
use Cake\Core\Configure;
use Cake\I18n\FrozenTime;
use Cake\TestSuite\TestCase;

class ExcelRangeMonthPurgeTest extends TestCase
{
    public function testMonthBoundsUseAppTimezone(): void
    {
        $previous = Configure::read('App.defaultTimezone');
        Configure::write('App.defaultTimezone', 'Europe/Paris');
        try {
            $bounds = (new ExcelRangeMonthPurge())->monthBounds(2026, 2);

            $this->assertSame('2026-02-01 00:00:00', $bounds['start']->format('Y-m-d H:i:s'));
            $this->assertSame('2026-03-01 00:00:00', $bounds['end']->format('Y-m-d H:i:s'));
            $this->assertSame('Europe/Paris', $bounds['start']->getTimezone()->getName());
        } finally {
            Configure::write('App.defaultTimezone', $previous);
        }
    }

    public function testRangeEndingAtMonthStartKeepsOnlyThePreviousMonth(): void
    {
        $purge = new ExcelRangeMonthPurge();
        $monthStart = FrozenTime::parse('2026-02-01 00:00:00');
        $monthEnd = FrozenTime::parse('2026-03-01 00:00:00');
        $pieces = $purge->remnantBounds(
            FrozenTime::parse('2026-01-28 08:00:00'),
            $monthStart,
            $monthStart,
            $monthEnd,
        );

        $this->assertCount(1, $pieces);
        $this->assertSame('2026-01-28 08:00:00', $pieces[0]['date_start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-02-01 00:00:00', $pieces[0]['date_end']->format('Y-m-d H:i:s'));
    }

    public function testSpanningRangeSplitsOnBothSides(): void
    {
        $purge = new ExcelRangeMonthPurge();
        $monthStart = FrozenTime::parse('2026-02-01 00:00:00');
        $monthEnd = FrozenTime::parse('2026-03-01 00:00:00');
        $pieces = $purge->remnantBounds(
            FrozenTime::parse('2026-01-28 08:00:00'),
            FrozenTime::parse('2026-03-05 18:00:00'),
            $monthStart,
            $monthEnd,
        );

        $this->assertCount(2, $pieces);
        $this->assertSame('2026-01-28 08:00:00', $pieces[0]['date_start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-02-01 00:00:00', $pieces[0]['date_end']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-01 00:00:00', $pieces[1]['date_start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-05 18:00:00', $pieces[1]['date_end']->format('Y-m-d H:i:s'));
    }

    public function testRangeFullyInsideMonthHasNoRemnant(): void
    {
        $purge = new ExcelRangeMonthPurge();
        $pieces = $purge->remnantBounds(
            FrozenTime::parse('2026-02-02 08:00:00'),
            FrozenTime::parse('2026-02-05 18:00:00'),
            FrozenTime::parse('2026-02-01 00:00:00'),
            FrozenTime::parse('2026-03-01 00:00:00'),
        );

        $this->assertSame([], $pieces);
    }
}
