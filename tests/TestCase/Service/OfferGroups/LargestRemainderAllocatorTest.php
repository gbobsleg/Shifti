<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\OfferGroups;

use App\Service\OfferGroups\LargestRemainderAllocator;
use Cake\TestSuite\TestCase;

/**
 * Tests Largest Remainder Method (répartition need_curve mode group).
 */
class LargestRemainderAllocatorTest extends TestCase
{
    private LargestRemainderAllocator $allocator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->allocator = new LargestRemainderAllocator();
    }

    public function testAllocate10WithRatios40_60(): void
    {
        $result = $this->allocator->allocate(10, [
            ['offer_id' => 1, 'display_order' => 0, 'ratio_percent' => 40, 'key' => 'TI'],
            ['offer_id' => 2, 'display_order' => 1, 'ratio_percent' => 60, 'key' => 'AE'],
        ]);

        $this->assertSame(['TI' => 4, 'AE' => 6], $result);
        $this->assertSame(10, array_sum($result));
    }

    public function testAllocate10WithRatios33_33_34(): void
    {
        $result = $this->allocator->allocate(10, [
            ['offer_id' => 10, 'display_order' => 0, 'ratio_percent' => 33, 'key' => 'A'],
            ['offer_id' => 20, 'display_order' => 1, 'ratio_percent' => 33, 'key' => 'B'],
            ['offer_id' => 30, 'display_order' => 2, 'ratio_percent' => 34, 'key' => 'C'],
        ]);

        // floor: 3,3,3 = 9 ; R=1 ; reste max = C (0.4) → C +1
        $this->assertSame(3, $result['A']);
        $this->assertSame(3, $result['B']);
        $this->assertSame(4, $result['C']);
        $this->assertSame(10, array_sum($result));
    }

    public function testAllocate1WithRatios50_50UsesTieBreakers(): void
    {
        // floor(0.5)=0,0 ; R=1 ; restes égaux → display_order puis offer_id
        $result = $this->allocator->allocate(1, [
            ['offer_id' => 5, 'display_order' => 1, 'ratio_percent' => 50, 'key' => 'Second'],
            ['offer_id' => 2, 'display_order' => 0, 'ratio_percent' => 50, 'key' => 'First'],
        ]);

        $this->assertSame(1, array_sum($result));
        $this->assertSame(1, $result['First']);
        $this->assertSame(0, $result['Second']);
    }

    public function testAllocate0ReturnsZeros(): void
    {
        $result = $this->allocator->allocate(0, [
            ['offer_id' => 1, 'display_order' => 0, 'ratio_percent' => 40, 'key' => 'TI'],
            ['offer_id' => 2, 'display_order' => 1, 'ratio_percent' => 60, 'key' => 'AE'],
        ]);

        $this->assertSame(['TI' => 0, 'AE' => 0], $result);
    }

    public function testRejectsInvalidRatioSum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->allocator->allocate(10, [
            ['offer_id' => 1, 'display_order' => 0, 'ratio_percent' => 40, 'key' => 'TI'],
            ['offer_id' => 2, 'display_order' => 1, 'ratio_percent' => 50, 'key' => 'AE'],
        ]);
    }
}
