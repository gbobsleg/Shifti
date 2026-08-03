<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * WfmSettingsFixture
 */
class WfmSettingsFixture extends TestFixture
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
                'name' => 'Lorem ipsum dolor sit amet',
                'service_level_percent' => 1.5,
                'service_level_seconds' => 1,
                'shrinkage_percent' => 1.5,
                'lunch_start_time' => '05:56:31',
                'lunch_end_time' => '05:56:31',
                'lunch_duration_minutes' => 1,
                'am_pause_duration_minutes' => 1,
                'am_pause_start_time' => '05:56:31',
                'am_pause_end_time' => '05:56:31',
                'pm_pause_duration_minutes' => 1,
                'pm_pause_start_time' => '05:56:31',
                'pm_pause_end_time' => '05:56:31',
                'min_block_minutes' => 1,
                'max_block_minutes' => 1,
            ],
        ];
        parent::init();
    }
}
