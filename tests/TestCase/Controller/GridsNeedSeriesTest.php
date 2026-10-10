<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\Authorization\LoginAsTrait;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class GridsNeedSeriesTest extends TestCase
{
    use IntegrationTestTrait;
    use LoginAsTrait;

    protected array $fixtures = [
        'app.Regions',
        'app.Sites',
        'app.Roles',
        'app.Users',
        'app.ForecastScenarios',
        'app.ForecastScenarioPublications',
    ];

    public function testAgentIsForbidden(): void
    {
        $this->loginAs(3);
        $this->get('/grids/need-series/1.json?offer_id=1&date=2026-10-12&type=need');
        $this->assertResponseCode(403);
    }

    public function testUnpublishedScenarioIsNotFound(): void
    {
        $this->loginAs(2);
        $this->get('/grids/need-series/2.json?offer_id=1&date=2026-10-12&type=need');
        $this->assertResponseCode(404);
    }

    public function testPublishedScenarioIsReadable(): void
    {
        $this->loginAs(2);
        $this->get('/grids/need-series/1.json?offer_id=1&date=2026-10-12&type=need');
        $status = $this->_response->getStatusCode();
        $this->assertNotSame(403, $status);
        $this->assertNotSame(404, $status);
    }
}
