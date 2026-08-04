<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\OfferGroups;

use App\Service\OfferGroups\EquityBucketsMigrator;
use Cake\TestSuite\TestCase;

/**
 * Migration idempotente equity_state → buckets de groupe.
 */
class EquityBucketsMigratorTest extends TestCase
{
    private EquityBucketsMigrator $migrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrator = new EquityBucketsMigrator();
    }

    public function testMigratesLegacyOfferKeysIntoGroupBucket(): void
    {
        $state = [
            'activities' => [],
            'forecastables' => [
                'TI' => [1 => 60, 2 => 30],
                'AE' => [1 => 15, 3 => 45],
                'TI-AE' => [2 => 20],
                'Employeurs' => [1 => 100],
            ],
            'cumulative_targets' => ['x' => 1],
        ];

        $groups = [[
            'name' => 'G_TI_AE',
            'mixed' => 'TI-AE',
            'members' => ['TI', 'AE'],
        ]];

        $first = $this->migrator->migrateState($state, $groups);
        $this->assertTrue($first['migrated']);
        $this->assertSame(2, $first['state'][EquityBucketsMigrator::VERSION_KEY]);

        $forecastables = $first['state']['forecastables'];
        $this->assertArrayNotHasKey('TI', $forecastables);
        $this->assertArrayNotHasKey('AE', $forecastables);
        $this->assertArrayNotHasKey('TI-AE', $forecastables);
        $this->assertSame(100, $forecastables['Employeurs'][1]);

        // agent 1: 60+15=75 ; agent 2: 30+20=50 ; agent 3: 45
        $this->assertSame(75, $forecastables['G_TI_AE'][1]);
        $this->assertSame(50, $forecastables['G_TI_AE'][2]);
        $this->assertSame(45, $forecastables['G_TI_AE'][3]);
    }

    public function testSecondLoadIsIdempotentNoDoubleCounting(): void
    {
        $legacy = [
            'forecastables' => [
                'CESU' => [10 => 40],
                'PAJEMPLOI' => [10 => 20],
                'C/P' => [10 => 10],
            ],
        ];
        $groups = [[
            'name' => 'G_CP',
            'mixed' => 'C/P',
            'members' => ['CESU', 'PAJEMPLOI'],
        ]];

        $once = $this->migrator->migrateState($legacy, $groups);
        $this->assertTrue($once['migrated']);
        $minutesAfterFirst = $once['state']['forecastables']['G_CP'][10];
        $this->assertSame(70, $minutesAfterFirst); // 40+20+10

        $twice = $this->migrator->migrateState($once['state'], $groups);
        $this->assertFalse($twice['migrated']);
        $this->assertSame(70, $twice['state']['forecastables']['G_CP'][10]);
        $this->assertSame(2, $twice['state'][EquityBucketsMigrator::VERSION_KEY]);

        // Même JSON legacy rejoué deux fois depuis zéro ne doit pas doubler non plus
        // si on persiste la version (simulé ici par le 2e appel sur state migré).
        $againFromMigrated = $this->migrator->migrateState($twice['state'], $groups);
        $this->assertFalse($againFromMigrated['migrated']);
        $this->assertSame(70, $againFromMigrated['state']['forecastables']['G_CP'][10]);
    }
}
