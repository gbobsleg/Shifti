<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\VolumeCompareService;
use Cake\TestSuite\TestCase;

class VolumeCompareServiceTest extends TestCase
{
    private VolumeCompareService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VolumeCompareService();
    }

    public function testHourAggregationCancelsIntraHourError(): void
    {
        $actuals = [
            ['at' => '2026-03-02 10:00:00', 'volume' => 10, 'dmt' => 100],
            ['at' => '2026-03-02 10:15:00', 'volume' => 10, 'dmt' => 300],
        ];
        $forecast = [
            '2026-03-02' => json_encode([
                '10:00:00' => ['volume' => 20, 'dmt' => 999],
                '10:15:00' => ['volume' => 0, 'dmt' => 1],
            ]),
        ];

        $hour = $this->service->build($actuals, $forecast, 'hour', true);
        $this->assertSame(20, $hour['points'][0]['volume']);
        $this->assertSame(20, $hour['points'][0]['forecast']);
        $this->assertSame(200, $hour['points'][0]['dmt']);
        $this->assertSame(0.0, $hour['compare']['wape']);
        $this->assertSame(0, $hour['compare']['gap']);

        $quarter = $this->service->build($actuals, $forecast, '15min', true);
        $this->assertSame(100.0, $quarter['compare']['wape']);
    }

    public function testWapeNullWhenRealVolumeIsZero(): void
    {
        $built = $this->service->build(
            [
                ['at' => '2026-03-02 10:00:00', 'volume' => 0, 'dmt' => 120],
            ],
            [
                '2026-03-02' => ['10:00:00' => ['volume' => 5, 'dmt' => 300]],
            ],
            '15min',
            true
        );

        $this->assertTrue($built['compare']['has_forecast']);
        $this->assertSame(0, $built['compare']['volume_real']);
        $this->assertSame(5, $built['compare']['volume_forecast']);
        $this->assertSame(5, $built['compare']['gap']);
        $this->assertNull($built['compare']['wape']);
        $this->assertNull($built['compare']['gap_percent']);
        $this->assertNull($built['compare']['dmt_weighted']);
    }

    public function testInvalidJsonExcludesDay(): void
    {
        $built = $this->service->build(
            [
                ['at' => '2026-03-02 10:00:00', 'volume' => 10, 'dmt' => 60],
                ['at' => '2026-03-03 10:00:00', 'volume' => 8, 'dmt' => 90],
            ],
            [
                '2026-03-02' => '{bad',
                '2026-03-03' => json_encode(['10:00' => ['volume' => 6]]),
            ],
            'day',
            true
        );

        $this->assertCount(2, $built['points']);
        $this->assertNull($built['points'][0]['forecast']);
        $this->assertSame(6, $built['points'][1]['forecast']);
        $this->assertSame(1, $built['compare']['missing_days']);
        $this->assertSame(8, $built['compare']['volume_real']);
        $this->assertSame(-25.0, $built['compare']['gap_percent']);
        $this->assertSame(25.0, $built['compare']['wape']);
    }

    public function testSlotWithoutVolumeDoesNotMakeTheDayComparable(): void
    {
        $built = $this->service->build(
            [
                ['at' => '2026-03-02 10:00:00', 'volume' => 4, 'dmt' => 60],
            ],
            [
                '2026-03-02' => json_encode([
                    '10:00:00' => ['dmt' => 300],
                    '10:15:00' => ['volume' => -2],
                ]),
            ],
            '15min',
            true
        );

        $this->assertFalse($built['compare']['has_forecast']);
        $this->assertNull($built['points'][0]['forecast']);
        $this->assertSame(1, $built['compare']['missing_days']);
    }

    public function testWeightedDmtIgnoresZeroVolumeAndForecastDmt(): void
    {
        $built = $this->service->build(
            [
                ['at' => '2026-03-02 10:00:00', 'volume' => 0, 'dmt' => 9999],
                ['at' => '2026-03-02 10:15:00', 'volume' => 10, 'dmt' => 120],
            ],
            [],
            'hour',
            false
        );

        $this->assertNull($built['compare']);
        $this->assertSame(120, $built['dmt_weighted']);
        $this->assertSame(120, $built['points'][0]['dmt']);
        $this->assertNull($built['points'][0]['forecast']);
        $this->assertSame('02/03/2026 10:00', $built['points'][0]['category']);
    }

    public function testDayAggregationSumsBothSeries(): void
    {
        $built = $this->service->build(
            [
                ['at' => '2026-03-02 09:00:00', 'volume' => 4, 'dmt' => 100],
                ['at' => '2026-03-02 11:30:00', 'volume' => 6, 'dmt' => 200],
            ],
            [
                '2026-03-02' => json_encode([
                    '09:00:00' => ['volume' => 5],
                    '11:30' => ['volume' => 5],
                ]),
            ],
            'day',
            true
        );

        $this->assertCount(1, $built['points']);
        $this->assertSame(10, $built['points'][0]['volume']);
        $this->assertSame(10, $built['points'][0]['forecast']);
        $this->assertSame(160, $built['points'][0]['dmt']);
        $this->assertSame('02/03/2026', $built['points'][0]['category']);
        $this->assertSame(0, $built['compare']['missing_days']);
    }

    public function testDayRangeIncludesDaysWithoutActuals(): void
    {
        $built = $this->service->build(
            [
                ['at' => '2026-03-02 10:00:00', 'volume' => 4, 'dmt' => 60],
            ],
            [],
            'day',
            false,
            '2026-03-01',
            '2026-03-03'
        );

        $this->assertSame(['2026-03-01', '2026-03-02', '2026-03-03'], array_column($built['points'], 'key'));
        $this->assertSame([0, 4, 0], array_column($built['points'], 'volume'));
        $this->assertSame([null, null, null], array_column($built['points'], 'forecast'));
        $this->assertSame(['01/03/2026', '02/03/2026', '03/03/2026'], array_column($built['points'], 'category'));
        $this->assertSame(4, $built['volume_total']);
        $this->assertNull($built['compare']);
    }

    public function testFilledDaysStayOutOfWapeWhenForecastIsMissing(): void
    {
        $built = $this->service->build(
            [
                ['at' => '2026-03-02 10:00:00', 'volume' => 10, 'dmt' => 60],
            ],
            [
                '2026-03-02' => json_encode(['10:00:00' => ['volume' => 8]]),
            ],
            'day',
            true,
            '2026-03-01',
            '2026-03-03'
        );

        $this->assertCount(3, $built['points']);
        $this->assertNull($built['points'][0]['forecast']);
        $this->assertNull($built['points'][2]['forecast']);
        $this->assertSame(10, $built['compare']['volume_real']);
        $this->assertSame(8, $built['compare']['volume_forecast']);
        $this->assertSame(20.0, $built['compare']['wape']);
        $this->assertSame(0, $built['compare']['missing_days']);
    }

    public function testHourGranularityDoesNotFillEmptyDays(): void
    {
        $built = $this->service->build(
            [
                ['at' => '2026-03-02 10:00:00', 'volume' => 4, 'dmt' => 60],
            ],
            [],
            'hour',
            false,
            '2026-03-01',
            '2026-03-03'
        );

        $this->assertCount(1, $built['points']);
    }
}
