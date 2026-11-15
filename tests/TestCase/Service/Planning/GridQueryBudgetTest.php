<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\Planning;

use App\Service\Planning\GridQueryBudget;
use Cake\I18n\FrozenTime;
use Cake\TestSuite\TestCase;

class GridQueryBudgetTest extends TestCase
{
    /**
     * @var array<string,int>
     */
    private array $config = [
        'free_days' => 5,
        'need_site_or_user_after' => 5,
        'month_view_after' => 10,
        'max_working_days' => 23,
        'max_calendar_months' => 1,
        'max_weeks' => 5,
    ];

    private function budget(): GridQueryBudget
    {
        return new GridQueryBudget($this->config);
    }

    public function testOneDayAllSitesAllowedGantt(): void
    {
        $b = $this->budget();
        $result = $b->evaluate(
            new FrozenTime('2026-09-07 00:00:00'),
            new FrozenTime('2026-09-07 23:59:59'),
            0,
            0
        );
        $this->assertTrue($result['allowed']);
        $this->assertSame(GridQueryBudget::VIEW_GANTT, $result['view']);
        $this->assertSame(1, $result['working_days']);
    }

    public function testWeekWithoutDimensionDenied(): void
    {
        $b = $this->budget();
        // lundi 7 → vendredi 18 sept 2026 = 10 j ouvrés
        $result = $b->evaluate(
            new FrozenTime('2026-09-07'),
            new FrozenTime('2026-09-18'),
            0,
            0
        );
        $this->assertFalse($result['allowed']);
        $this->assertSame('need_dimension', $result['code']);
        $this->assertSame(10, $result['working_days']);
    }

    public function testMonthWithSiteAllowedMonthView(): void
    {
        $b = $this->budget();
        $result = $b->evaluate(
            new FrozenTime('2026-09-01'),
            new FrozenTime('2026-09-30'),
            3,
            0
        );
        $this->assertTrue($result['allowed']);
        $this->assertSame(GridQueryBudget::VIEW_MONTH, $result['view']);
        $this->assertGreaterThan(10, $result['working_days']);
    }

    public function testMonthWithUserKeepsGantt(): void
    {
        $b = $this->budget();
        $result = $b->evaluate(
            new FrozenTime('2026-09-01'),
            new FrozenTime('2026-09-30'),
            0,
            12
        );
        $this->assertTrue($result['allowed']);
        $this->assertTrue($result['gantt_allowed']);
        $this->assertSame(GridQueryBudget::VIEW_GANTT, $result['view']);
        $this->assertGreaterThan(10, $result['working_days']);
    }

    public function testMonthWithOffersAllowedMonthView(): void
    {
        $b = $this->budget();
        $result = $b->evaluate(
            new FrozenTime('2026-09-01'),
            new FrozenTime('2026-09-30'),
            0,
            0,
            [4, 9]
        );
        $this->assertTrue($result['allowed']);
        $this->assertSame(GridQueryBudget::VIEW_MONTH, $result['view']);
    }

    public function testMonthWithoutSiteDenied(): void
    {
        $b = $this->budget();
        $result = $b->evaluate(
            new FrozenTime('2026-09-01'),
            new FrozenTime('2026-09-30'),
            0,
            0
        );
        $this->assertFalse($result['allowed']);
        $this->assertSame('need_dimension', $result['code']);
    }

    public function testMoreThanFiveWeeksDenied(): void
    {
        $b = $this->budget();
        // 1er sept. → 15 oct. 2026 dépasse 5 semaines (écart 44 jours)
        $result = $b->evaluate(
            new FrozenTime('2026-09-01'),
            new FrozenTime('2026-10-15'),
            1,
            0
        );
        $this->assertFalse($result['allowed']);
        $this->assertSame('span', $result['code']);
        $this->assertStringContainsString('5 semaines', $result['message']);
    }

    public function testWeekendsExcludedFromWorkingDays(): void
    {
        $b = $this->budget();
        // vendredi 4 → lundi 7 sept 2026 = 2 j ouvrés
        $this->assertSame(2, $b->countWorkingDays(
            new FrozenTime('2026-09-04'),
            new FrozenTime('2026-09-07')
        ));
    }

    public function testFiveWorkingDaysWithoutFilterIsGantt(): void
    {
        $b = $this->budget();
        $result = $b->evaluate(
            new FrozenTime('2026-09-07'),
            new FrozenTime('2026-09-11'),
            0,
            0
        );
        $this->assertTrue($result['allowed']);
        $this->assertSame(5, $result['working_days']);
        $this->assertTrue($result['gantt_allowed']);
        $this->assertSame(GridQueryBudget::VIEW_GANTT, $result['view']);
        $this->assertSame(GridQueryBudget::VIEW_GANTT, $result['auto_view']);
    }

    public function testThresholdsExposeFiveWeekSpan(): void
    {
        $thresholds = $this->budget()->thresholds();
        $this->assertSame(5, $thresholds['max_weeks']);
        $this->assertSame(34, $thresholds['max_span_days']);
    }

    public function testFiveWeeksWithSiteIsPastilles(): void
    {
        $b = $this->budget();
        // lundi 7 sept. → dimanche 11 oct. 2026 = 5 semaines, écart 34 jours
        $result = $b->evaluate(
            new FrozenTime('2026-09-07'),
            new FrozenTime('2026-10-11'),
            3,
            0
        );
        $this->assertTrue($result['allowed']);
        $this->assertFalse($result['gantt_allowed']);
        $this->assertSame(25, $result['working_days']);
        $this->assertSame(GridQueryBudget::VIEW_MONTH, $result['view']);
    }

    public function testFiveWeeksWithUserIsPastilles(): void
    {
        $b = $this->budget();
        $result = $b->evaluate(
            new FrozenTime('2026-09-07'),
            new FrozenTime('2026-10-11'),
            0,
            12
        );
        $this->assertTrue($result['allowed']);
        $this->assertFalse($result['gantt_allowed']);
        $this->assertSame(GridQueryBudget::VIEW_GANTT, $result['auto_view']);
        $this->assertSame(GridQueryBudget::VIEW_MONTH, $result['view']);
    }

    public function testSpanWithinFiveWeeksAboveGanttCapIsPastilles(): void
    {
        $b = $this->budget();
        // lundi 7 sept. → vendredi 9 oct. 2026 = 25 jours de trame, écart 32 jours
        $result = $b->evaluate(
            new FrozenTime('2026-09-07'),
            new FrozenTime('2026-10-09'),
            3,
            0
        );
        $this->assertTrue($result['allowed']);
        $this->assertSame('ok', $result['code']);
        $this->assertFalse($result['gantt_allowed']);
        $this->assertSame(25, $result['working_days']);
        $this->assertSame(GridQueryBudget::VIEW_MONTH, $result['view']);
    }

    public function testSaturdayMonthWithinFiveWeeksIsPastilles(): void
    {
        $b = $this->budget();
        $result = $b->evaluate(
            new FrozenTime('2026-10-01'),
            new FrozenTime('2026-10-31'),
            3,
            0,
            [],
            [1, 2, 3, 4, 5, 6]
        );
        $this->assertTrue($result['allowed']);
        $this->assertFalse($result['gantt_allowed']);
        $this->assertGreaterThan(23, $result['working_days']);
        $this->assertSame(GridQueryBudget::VIEW_MONTH, $result['view']);
    }

    public function testWeekCanForcePastilles(): void
    {
        $b = $this->budget();
        $forced = $b->evaluate(
            new FrozenTime('2026-09-07'),
            new FrozenTime('2026-09-11'),
            0,
            0,
            [],
            null,
            GridQueryBudget::VIEW_MONTH
        );
        $this->assertTrue($forced['allowed']);
        $this->assertTrue($forced['gantt_allowed']);
        $this->assertSame(GridQueryBudget::VIEW_GANTT, $forced['auto_view']);
        $this->assertSame(GridQueryBudget::VIEW_MONTH, $forced['view']);
    }

    public function testRequestedGanttDoesNotReopenAutoPastilles(): void
    {
        $b = $this->budget();
        // lundi 7 → vendredi 25 sept. 2026 = 15 jours, sous le plafond Gantt
        $result = $b->evaluate(
            new FrozenTime('2026-09-07'),
            new FrozenTime('2026-09-25'),
            3,
            0,
            [],
            null,
            GridQueryBudget::VIEW_GANTT
        );
        $this->assertTrue($result['gantt_allowed']);
        $this->assertSame(GridQueryBudget::VIEW_MONTH, $result['auto_view']);
        $this->assertSame(GridQueryBudget::VIEW_MONTH, $result['view']);
    }

    public function testMidOctoberToNovemberWithUserKeepsGantt(): void
    {
        $b = $this->budget();
        $result = $b->evaluate(
            new FrozenTime('2026-10-15'),
            new FrozenTime('2026-11-10'),
            0,
            12
        );
        $this->assertTrue($result['allowed']);
        $this->assertTrue($result['gantt_allowed']);
        $this->assertSame(19, $result['working_days']);
        $this->assertSame(GridQueryBudget::VIEW_GANTT, $result['view']);
    }

    public function testSameRangeWithSaturdayStaysAtGanttCap(): void
    {
        $b = $this->budget();
        $result = $b->evaluate(
            new FrozenTime('2026-10-15'),
            new FrozenTime('2026-11-10'),
            0,
            12,
            [],
            [1, 2, 3, 4, 5, 6]
        );
        $this->assertTrue($result['allowed']);
        $this->assertTrue($result['gantt_allowed']);
        $this->assertSame(23, $result['working_days']);
        $this->assertSame(GridQueryBudget::VIEW_GANTT, $result['view']);
    }
}
