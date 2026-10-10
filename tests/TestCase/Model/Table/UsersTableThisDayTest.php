<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use Cake\I18n\FrozenTime;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

class UsersTableThisDayTest extends TestCase
{
    protected array $fixtures = [
        'app.Regions',
        'app.Sites',
        'app.Roles',
        'app.Users',
        'app.UserContracts',
    ];

    public function testAllowedSiteIdsKeepsOnlyThatSite(): void
    {
        $users = TableRegistry::getTableLocator()->get('Users')->find('ThisDay', [
            'params' => ['allowed_site_ids' => [1]],
            'day_ranges' => [
                'begin' => new FrozenTime('2026-10-12 00:00:00'),
                'end' => new FrozenTime('2026-10-12 23:59:59'),
            ],
        ])->all();

        $this->assertNotEmpty($users);
        foreach ($users as $user) {
            $this->assertSame(1, (int)$user->site_id);
        }
    }
}
