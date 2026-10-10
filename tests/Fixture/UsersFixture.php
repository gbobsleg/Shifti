<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class UsersFixture extends TestFixture
{
    public function init(): void
    {
        $this->records = [
            $this->user(1, 'ADM', 'Admin', 'Test', 'admin@test.local', 1, 1),
            $this->user(2, 'MAN', 'Manager', 'Test', 'manager@test.local', 2, 1),
            $this->user(3, 'AG1', 'Agent', 'SiteA', 'agent.a@test.local', 3, 1),
            $this->user(4, 'PLN', 'Planificateur', 'Test', 'planificateur@test.local', 4, 1),
            $this->user(5, 'AG2', 'Agent', 'SiteB', 'agent.b@test.local', 3, 2),
        ];
        parent::init();
    }

    /**
     * @return array<string, mixed>
     */
    private function user(
        int $id,
        string $code,
        string $lastName,
        string $firstName,
        string $email,
        int $roleId,
        int $siteId,
    ): array {
        return [
            'id' => $id,
            'user_code' => $code,
            'last_name' => $lastName,
            'first_name' => $firstName,
            'email' => $email,
            'password' => '$2y$10$abcdefghijklmnopqrstuuO6Y7C8D9E0F1G2H3I4J5K',
            'role_id' => $roleId,
            'site_id' => $siteId,
        ];
    }
}
