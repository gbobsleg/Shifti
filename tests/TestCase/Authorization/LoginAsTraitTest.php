<?php
declare(strict_types=1);

namespace App\Test\TestCase\Authorization;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class LoginAsTraitTest extends TestCase
{
    use IntegrationTestTrait;
    use LoginAsTrait;

    protected array $fixtures = [
        'app.Regions',
        'app.Sites',
        'app.Roles',
        'app.Users',
    ];

    public function testAdminCanOpenAccount(): void
    {
        $this->loginAs(1);
        $this->get('/users/account');
        $this->assertResponseOk();
    }
}
