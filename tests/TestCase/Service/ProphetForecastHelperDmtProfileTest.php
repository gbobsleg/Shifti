<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\ProphetForecastHelper;
use Cake\TestSuite\TestCase;

class ProphetForecastHelperDmtProfileTest extends TestCase
{
    public function testNormalizeDmtProfileStringifiesIsoDayKeys(): void
    {
        $profile = ProphetForecastHelper::normalizeDmtProfile([
            'level_seconds' => 360,
            'coefficients' => [
                1 => ['08:15' => 1.25, '08:30' => 1.10],
                2 => ['08:15' => 1.0],
            ],
        ]);

        $this->assertNotNull($profile);
        $this->assertArrayHasKey('1', $profile['coefficients']);
        $encoded = json_encode($profile['coefficients']);
        $this->assertIsString($encoded);
        $this->assertStringStartsWith('{', $encoded);
        $this->assertStringContainsString('"1"', $encoded);
        $this->assertStringNotContainsString('[', substr($encoded, 0, 1));
        $decoded = json_decode($encoded, true);
        $this->assertSame(1.25, $decoded['1']['08:15']);
    }
}
