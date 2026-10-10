<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class SitesFixture extends TestFixture
{
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'name' => 'Site A',
                'number' => 1,
                'region_id' => 1,
            ],
            [
                'id' => 2,
                'name' => 'Site B',
                'number' => 2,
                'region_id' => 1,
            ],
        ];
        parent::init();
    }
}
