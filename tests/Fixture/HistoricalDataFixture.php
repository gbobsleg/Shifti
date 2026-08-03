<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * HistoricalDataFixture
 */
class HistoricalDataFixture extends TestFixture
{
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'offer_id' => 1,
                'datetime_interval' => '2025-10-22 16:04:38',
                'call_volume' => 1,
                'avg_handle_time_seconds' => 1,
            ],
        ];
        parent::init();
    }
}
