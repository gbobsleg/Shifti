<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\Authorization\LoginAsTrait;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class UsersRoleAssignmentTest extends TestCase
{
    use IntegrationTestTrait;
    use LoginAsTrait;

    protected array $fixtures = [
        'app.Regions',
        'app.Sites',
        'app.Roles',
        'app.Users',
    ];

    public function testManagerCannotCreateAdmin(): void
    {
        $this->loginAs(2);
        $before = TableRegistry::getTableLocator()->get('Users')->find()->count();
        $this->post('/users/add', [
            'role_id' => 1,
            'user_code' => 'NEW',
            'last_name' => 'Nouveau',
            'first_name' => 'Compte',
            'email' => 'nouveau@test.local',
            'password' => 'motdepasse',
            'site_id' => 1,
        ]);
        $this->assertResponseCode(403);
        $this->assertSame($before, TableRegistry::getTableLocator()->get('Users')->find()->count());
    }

    public function testManagerCannotPromoteAgentToPlanner(): void
    {
        $this->loginAs(2);
        $this->post('/users/edit/3', $this->agentPayload(4));
        $this->assertResponseCode(403);
        $user = TableRegistry::getTableLocator()->get('Users')->get(3);
        $this->assertSame(3, (int)$user->role_id);
    }

    public function testManagerCannotEditAdmin(): void
    {
        $this->loginAs(2);
        $this->get('/users/edit/1');
        $this->assertResponseCode(403);
    }

    public function testManagerCannotDeleteAdmin(): void
    {
        $this->loginAs(2);
        $this->post('/users/delete/1');
        $this->assertResponseCode(403);
        $this->assertTrue(TableRegistry::getTableLocator()->get('Users')->exists(['id' => 1]));
    }

    public function testPlannerCanPromoteAgentToPlanner(): void
    {
        $this->loginAs(4);
        $this->post('/users/edit/3', $this->agentPayload(4));
        $this->assertNotSame(403, $this->_response->getStatusCode());
        $user = TableRegistry::getTableLocator()->get('Users')->get(3);
        $this->assertSame(4, (int)$user->role_id);
    }

    /**
     * @return array<string, mixed>
     */
    private function agentPayload(int $roleId): array
    {
        return [
            'role_id' => $roleId,
            'user_code' => 'AG1',
            'last_name' => 'Agent',
            'first_name' => 'SiteA',
            'email' => 'agent.a@test.local',
            'site_id' => 1,
        ];
    }
}
