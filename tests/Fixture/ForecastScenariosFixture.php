<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class ForecastScenariosFixture extends TestFixture
{
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'name' => 'Publié',
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
                'status' => 'completed',
            ],
            [
                'id' => 2,
                'name' => 'Brouillon',
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
                'status' => 'draft',
            ],
        ];
        parent::init();
    }
}
