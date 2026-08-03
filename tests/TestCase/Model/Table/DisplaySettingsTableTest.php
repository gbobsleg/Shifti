<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\DisplaySettingsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\DisplaySettingsTable Test Case
 */
class DisplaySettingsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\DisplaySettingsTable
     */
    protected $DisplaySettings;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'app.DisplaySettings',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('DisplaySettings') ? [] : ['className' => DisplaySettingsTable::class];
        $this->DisplaySettings = $this->getTableLocator()->get('DisplaySettings', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->DisplaySettings);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @link \App\Model\Table\DisplaySettingsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
