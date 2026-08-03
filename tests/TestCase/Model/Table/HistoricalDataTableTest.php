<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\HistoricalDataTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\HistoricalDataTable Test Case
 */
class HistoricalDataTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\HistoricalDataTable
     */
    protected $HistoricalData;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'app.HistoricalData',
        'app.Offers',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('HistoricalData') ? [] : ['className' => HistoricalDataTable::class];
        $this->HistoricalData = $this->getTableLocator()->get('HistoricalData', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->HistoricalData);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @link \App\Model\Table\HistoricalDataTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @link \App\Model\Table\HistoricalDataTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
