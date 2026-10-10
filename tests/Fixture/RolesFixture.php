<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class RolesFixture extends TestFixture
{
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'name' => 'Administrateur',
                'code' => 'admin',
                'priority' => 10,
            ],
            [
                'id' => 2,
                'name' => 'Manager',
                'code' => 'manager',
                'priority' => 30,
            ],
            [
                'id' => 3,
                'name' => 'Utilisateur',
                'code' => 'agent',
                'priority' => 40,
            ],
            [
                'id' => 4,
                'name' => 'Planificateur',
                'code' => 'planificateur',
                'priority' => 20,
            ],
        ];
        parent::init();
    }
}
