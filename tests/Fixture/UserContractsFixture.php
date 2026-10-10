<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class UserContractsFixture extends TestFixture
{
    public function init(): void
    {
        $this->records = [];
        foreach ([1, 2, 3, 4, 5] as $userId) {
            $this->records[] = [
                'id' => $userId,
                'user_id' => $userId,
                'start_date' => '2020-01-01',
                'end_date' => null,
            ];
        }
        parent::init();
    }
}
