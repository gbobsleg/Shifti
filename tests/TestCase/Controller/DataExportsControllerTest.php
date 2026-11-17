<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Service\Authorization\PermissionService;
use App\Test\TestCase\Authorization\LoginAsTrait;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use ReflectionProperty;

class DataExportsControllerTest extends TestCase
{
    use IntegrationTestTrait;
    use LoginAsTrait;

    protected array $fixtures = [
        'app.Regions',
        'app.Sites',
        'app.Roles',
        'app.Users',
        'app.Offers',
        'app.HistoricalData',
        'app.WfmSettings',
        'app.ForecastScenarios',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $property = new ReflectionProperty(PermissionService::class, 'cachedRoles');
        $property->setValue(null, null);
    }

    public function testAgentCannotOpenThePage(): void
    {
        $this->loginAs(3);

        $this->get('/data-exports');

        $this->assertResponseCode(403);
    }

    public function testManagerDoesNotSeeScenarioExport(): void
    {
        $this->loginAs(2);

        $this->get('/data-exports');

        $this->assertResponseOk();
        $this->assertResponseNotContains('value="scenario"');
    }

    public function testManagerCannotDownloadAScenario(): void
    {
        $this->loginAs(2);

        $this->post('/data-exports/download', [
            'type' => 'scenario',
            'scenario_id' => '1',
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-02',
            'granularity' => 'day',
            'offers' => ['1'],
        ]);

        $this->assertResponseCode(403);
    }

    public function testEmptySelectionRedirectsWithoutAFile(): void
    {
        $this->loginAs(2);

        $this->post('/data-exports/download', [
            'type' => 'actual',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-02',
            'granularity' => 'day',
            'offers' => ['1'],
        ]);

        $this->assertResponseCode(302);
        $this->assertFlashMessage('Aucune donnée pour cette sélection.');
    }
}
