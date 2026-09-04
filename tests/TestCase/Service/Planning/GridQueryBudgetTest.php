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

    public function testMoreThanOneMonthDenied(): void
    {
        $b = $this->budget();
        $result = $b->evaluate(
            new FrozenTime('2026-09-01'),
            new FrozenTime('2026-10-15'),
            1,
            0
        );
        $this->assertFalse($result['allowed']);
        $this->assertSame('span', $result['code']);
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
        $this->assertSame(GridQueryBudget::VIEW_GANTT, $result['view']);
    }
}
