<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\OfferColors;

use App\Service\OfferColors\FamilyShadeGenerator;
use Cake\TestSuite\TestCase;

/**
 * App\Service\OfferColors\FamilyShadeGenerator Test Case
 */
class FamilyShadeGeneratorTest extends TestCase
{
    public function testShadesAreDistinctAndDarkestFirst(): void
    {
        $hexes = (new FamilyShadeGenerator())->shades(210, 4);

        $this->assertCount(4, $hexes);
        $this->assertCount(4, array_unique($hexes));
        foreach ($hexes as $hex) {
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $hex);
        }
        $this->assertLessThan($this->channelSum($hexes[3]), $this->channelSum($hexes[0]));
    }

    public function testSingleOfferUsesTheMiddleShade(): void
    {
        $hexes = (new FamilyShadeGenerator())->shades(0, 1);

        $this->assertCount(1, $hexes);
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $hexes[0]);
    }

    private function channelSum(string $hex): int
    {
        return hexdec(substr($hex, 1, 2)) + hexdec(substr($hex, 3, 2)) + hexdec(substr($hex, 5, 2));
    }
}
