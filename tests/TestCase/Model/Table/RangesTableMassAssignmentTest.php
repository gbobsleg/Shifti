<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use Cake\TestSuite\TestCase;

/**
 * Une plage ne doit pas pouvoir créer un compte via l'association user.
 */
class RangesTableMassAssignmentTest extends TestCase
{
    protected array $fixtures = [];

    public function testNestedUserIsNotMassAssignable(): void
    {
        $Ranges = $this->getTableLocator()->get('Ranges');
        $entity = $Ranges->newEntity([
            'user_id' => 1,
            'offer_id' => 1,
            'date_start' => '2026-01-01 09:00:00',
            'date_end' => '2026-01-01 17:00:00',
            'created' => '2000-01-01 00:00:00',
            'modified' => '2000-01-01 00:00:00',
            'user' => [
                'email' => 'injected@example.com',
                'role_id' => 1,
                'password' => 'secret',
                'user_code' => 'INJECT',
                'last_name' => 'Injected',
                'first_name' => 'User',
            ],
            'offer' => [
                'name' => 'Offre injectée',
            ],
        ]);

        $this->assertEmpty($entity->get('user'));
        $this->assertEmpty($entity->get('offer'));
        $this->assertEmpty($entity->get('created'));
        $this->assertEmpty($entity->get('modified'));
    }
}
