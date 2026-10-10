<?php
declare(strict_types=1);

namespace App\Test\TestCase\Authorization;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class AccessDeniedTest extends TestCase
{
    use IntegrationTestTrait;
    use LoginAsTrait;

    protected array $fixtures = [
        'app.Regions',
        'app.Sites',
        'app.Roles',
        'app.Users',
    ];

    /**
     * @return array<string, array{int, string}>
     */
    public static function deniedRequests(): array
    {
        return [
            'agent paramètres wfm' => [3, '/wfm-settings'],
            'agent édition prévision' => [3, '/forecast-scenarios/edit/1'],
            'agent administration' => [3, '/pages/admin'],
            'agent alertes' => [3, '/alerts'],
            'agent utilisateurs' => [3, '/users'],
            'manager paramètres wfm' => [2, '/wfm-settings'],
            'manager prévisions' => [2, '/forecast-scenarios'],
            'manager générations' => [2, '/planning-generation-jobs'],
            'manager import excel' => [2, '/excel-uploads/upload'],
            'manager jobs' => [2, '/background-jobs'],
            'manager rotations' => [2, '/rotation-rules'],
            'manager activités fixes' => [2, '/fixed-activity-rules'],
            'manager correspondances' => [2, '/planning-event-mappings'],
            'manager plages' => [2, '/ranges'],
            'planificateur paramètres wfm' => [4, '/wfm-settings'],
            'planificateur plages' => [4, '/ranges'],
            'planificateur offres' => [4, '/offers'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('deniedRequests')]
    public function testDenied(int $userId, string $url): void
    {
        $this->loginAs($userId);
        $this->get($url);
        $this->assertResponseCode(403);
    }
}
