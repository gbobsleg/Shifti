<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\UserAvailabilitiesTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\UserAvailabilitiesTable Test Case
 */
class UserAvailabilitiesTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\UserAvailabilitiesTable
     */
    protected $UserAvailabilities;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'app.UserAvailabilities',
        'app.Users',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('UserAvailabilities') ? [] : ['className' => UserAvailabilitiesTable::class];
        $this->UserAvailabilities = $this->getTableLocator()->get('UserAvailabilities', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->UserAvailabilities);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @link \App\Model\Table\UserAvailabilitiesTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @link \App\Model\Table\UserAvailabilitiesTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
