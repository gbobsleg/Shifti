<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class OffersFixture extends TestFixture
{
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'name' => 'Zèbre',
                'color' => '#111111',
            ],
            [
                'id' => 2,
                'name' => 'Accueil',
                'color' => '#222222',
            ],
        ];
        parent::init();
    }
}
