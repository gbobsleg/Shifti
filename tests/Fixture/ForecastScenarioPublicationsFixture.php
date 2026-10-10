<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class ForecastScenarioPublicationsFixture extends TestFixture
{
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'scenario_id' => 1,
                'date' => '2026-10-12',
                'published_at' => '2026-10-01 08:00:00',
            ],
        ];
        parent::init();
    }
}
