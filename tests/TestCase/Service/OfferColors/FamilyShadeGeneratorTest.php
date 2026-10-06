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
    public function testShadesAreDistinct(): void
    {
        $generator = new FamilyShadeGenerator();
        $hexes = $generator->shades(210, 4);

        $this->assertCount(4, $hexes);
        $this->assertCount(4, array_unique($hexes));
        foreach ($hexes as $hex) {
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $hex);
        }
    }

    public function testSingleRedOfferStaysOnCatalogHue(): void
    {
        $generator = new FamilyShadeGenerator();
        $hexes = $generator->shades(0, 1);

        $this->assertSame($generator->baseHex(0), $hexes[0]);
        $this->assertEqualsWithDelta(0.0, $generator->hueOfHex($hexes[0]), 2.0);
    }

    public function testSingleBlueOfferStaysOnCatalogHue(): void
    {
        $generator = new FamilyShadeGenerator();
        $hexes = $generator->shades(210, 1);

        $this->assertSame($generator->baseHex(210), $hexes[0]);
        $this->assertEqualsWithDelta(210.0, $generator->hueOfHex($hexes[0]), 2.0);
    }

    public function testOrangeSpreadsAroundTheBase(): void
    {
        $generator = new FamilyShadeGenerator();
        $hexes = $generator->shades(24, 7);

        $this->assertCount(7, array_unique($hexes));
        $this->assertSame($generator->baseHex(24), $hexes[3]);
        $this->assertEqualsWithDelta(359.0, $generator->hueOfHex($hexes[0]), 4.0);
        $this->assertEqualsWithDelta(79.0, $generator->hueOfHex($hexes[6]), 4.0);
    }

    public function testYellowBaseSitsInTheMiddle(): void
    {
        $generator = new FamilyShadeGenerator();
        $hexes = $generator->shades(48, 7);

        $this->assertSame('#ffcc00', $hexes[3]);
        $this->assertSame($generator->baseHex(48), $hexes[3]);
        $this->assertEqualsWithDelta(23.0, $generator->hueOfHex($hexes[0]), 4.0);
        $this->assertEqualsWithDelta(103.0, $generator->hueOfHex($hexes[6]), 4.0);
        $this->assertLessThan(48.0, $generator->hueOfHex($hexes[2]));
        $this->assertGreaterThan(48.0, $generator->hueOfHex($hexes[4]));
    }

    public function testBlueSpreadsAcrossTheAnalogousArc(): void
    {
        $generator = new FamilyShadeGenerator();
        $hexes = $generator->shades(210, 7);

        $this->assertCount(7, array_unique($hexes));
        $this->assertSame($generator->baseHex(210), $hexes[3]);
        $firstHue = $generator->hueOfHex($hexes[0]);
        $lastHue = $generator->hueOfHex($hexes[6]);
        $this->assertEqualsWithDelta(185.0, $firstHue, 4.0);
        $this->assertEqualsWithDelta(265.0, $lastHue, 4.0);
        $this->assertGreaterThan(60.0, $lastHue - $firstHue);
    }

    public function testBlueReachesIntoNeighborHues(): void
    {
        $generator = new FamilyShadeGenerator();
        $hexes = $generator->shades(210, 7);

        $this->assertLessThan(190.0, $generator->hueOfHex($hexes[0]));
        $this->assertGreaterThan(250.0, $generator->hueOfHex($hexes[6]));
    }

    public function testTwoOffersStayOnTheChosenHue(): void
    {
        $generator = new FamilyShadeGenerator();
        $hexes = $generator->shades(145, 2);

        $this->assertCount(2, $hexes);
        $this->assertSame($generator->baseHex(145), $hexes[1]);
        $this->assertEqualsWithDelta(145.0, $generator->hueOfHex($hexes[0]), 2.0);
        $this->assertEqualsWithDelta(145.0, $generator->hueOfHex($hexes[1]), 2.0);
        $this->assertNotSame($hexes[0], $hexes[1]);
        $this->assertGreaterThan(
            $generator->contrastOfHex($hexes[1]),
            $generator->contrastOfHex($hexes[0]),
        );
    }

    public function testPastelKeepsTheHueAndSoftensTheBase(): void
    {
        $generator = new FamilyShadeGenerator();
        $pair = $generator->shades(145, 2, true);
        $column = $generator->shades(48, 7, true);

        $this->assertSame('#70cd96', $pair[0]);
        $this->assertSame('#afdec3', $pair[1]);
        $this->assertEqualsWithDelta(145.0, $generator->hueOfHex($pair[0]), 2.0);
        $this->assertSame('#ded5af', $column[3]);
        $this->assertEqualsWithDelta(23.0, $generator->hueOfHex($column[0]), 4.0);
        $this->assertEqualsWithDelta(103.0, $generator->hueOfHex($column[6]), 4.0);
        $this->assertNotSame($generator->shades(48, 7)[3], $column[3]);
    }

    public function testRedWrapsAcrossZero(): void
    {
        $generator = new FamilyShadeGenerator();
        $hexes = $generator->shades(0, 7);

        $this->assertCount(7, array_unique($hexes));
        foreach ($hexes as $hex) {
            $recovered = $generator->hueOfHex($hex);
            $this->assertGreaterThanOrEqual(0.0, $recovered);
            $this->assertLessThan(360.0, $recovered);
        }
        $this->assertSame($generator->baseHex(0), $hexes[3]);
        $this->assertEqualsWithDelta(335.0, $generator->hueOfHex($hexes[0]), 4.0);
        $this->assertEqualsWithDelta(55.0, $generator->hueOfHex($hexes[6]), 4.0);
    }
}
