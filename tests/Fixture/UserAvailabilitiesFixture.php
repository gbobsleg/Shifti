<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * UserAvailabilitiesFixture
 */
class UserAvailabilitiesFixture extends TestFixture
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
                'user_id' => 1,
                'day_of_week' => 1,
                'availability_start_time' => '09:00:00',
                'availability_end_time' => '17:00:00',
                'earliest_end_time' => null,
            ],
        ];
        parent::init();
    }
}
