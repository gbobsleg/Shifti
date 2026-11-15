<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\FrenchPublicHolidays;
use Cake\TestSuite\TestCase;

class FrenchPublicHolidaysTest extends TestCase
{
    public function testEaster2026And2027(): void
    {
        $this->assertSame('2026-04-05', FrenchPublicHolidays::easterSunday(2026)->format('Y-m-d'));
        $this->assertSame('2027-03-28', FrenchPublicHolidays::easterSunday(2027)->format('Y-m-d'));
    }

    public function testChristmasAndNewYearAreListed(): void
    {
        $y2026 = FrenchPublicHolidays::forYear(2026);
        $this->assertArrayHasKey('2026-12-25', $y2026);
        $this->assertSame('Noël', $y2026['2026-12-25']);
        $this->assertSame('2026-04-06', array_search('Lundi de Pâques', $y2026, true));
        $this->assertSame('2026-05-14', array_search('Ascension', $y2026, true));
        $this->assertSame('2026-05-25', array_search('Lundi de Pentecôte', $y2026, true));

        $y2027 = FrenchPublicHolidays::forYear(2027);
        $this->assertArrayHasKey('2027-01-01', $y2027);
    }
}
