<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\Authorization\LoginAsTrait;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class GridsPerimeterTest extends TestCase
{
    use IntegrationTestTrait;
    use LoginAsTrait;

    protected array $fixtures = [
        'app.Regions',
        'app.Sites',
        'app.Roles',
        'app.Users',
    ];

    public function testAgentCannotListAnotherSite(): void
    {
        $this->loginAs(3);
        $this->get('/grids/get-users-by-site.json?site_id=2');
        $this->assertResponseOk();
        $ids = $this->userIds();
        $this->assertNotContains(5, $ids);
        $this->assertContains(3, $ids);
    }

    public function testAgentWithoutSiteParamStaysOnOwnSite(): void
    {
        $this->loginAs(3);
        $this->get('/grids/get-users-by-site.json');
        $this->assertResponseOk();
        $ids = $this->userIds();
        $this->assertContains(3, $ids);
        $this->assertNotContains(5, $ids);
    }

    public function testManagerCanListAnotherSite(): void
    {
        $this->loginAs(2);
        $this->get('/grids/get-users-by-site.json?site_id=2');
        $this->assertResponseOk();
        $this->assertContains(5, $this->userIds());
    }

    /**
     * @return list<int>
     */
    private function userIds(): array
    {
        $payload = json_decode((string)$this->_response->getBody(), true);

        return array_map('intval', array_column($payload['users'] ?? [], 'id'));
    }
}
