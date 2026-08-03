<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\WfmSettingsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\WfmSettingsTable Test Case
 */
class WfmSettingsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\WfmSettingsTable
     */
    protected $WfmSettings;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'app.WfmSettings',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('WfmSettings') ? [] : ['className' => WfmSettingsTable::class];
        $this->WfmSettings = $this->getTableLocator()->get('WfmSettings', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->WfmSettings);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @link \App\Model\Table\WfmSettingsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
