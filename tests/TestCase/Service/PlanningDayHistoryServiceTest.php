<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\PlanningDayHistoryService;
use App\Service\RangeSource;
use Cake\I18n\FrozenTime;
use Cake\TestSuite\TestCase;

class PlanningDayHistoryServiceTest extends TestCase
{
    protected array $fixtures = [];

    private int $userId = 0;

    private int $offerId = 0;

    /** @var list<int> */
    private array $rangeIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $locator = $this->getTableLocator();
        $region = $locator->get('Regions')->saveOrFail($locator->get('Regions')->newEntity([
            'name' => 'Région hist',
            'number' => 'H' . random_int(1000, 9999),
        ]));
        $site = $locator->get('Sites')->saveOrFail($locator->get('Sites')->newEntity([
            'name' => 'Site hist',
            'number' => random_int(1000, 9999),
            'region_id' => $region->id,
        ]));
        $role = $locator->get('Roles')->saveOrFail($locator->get('Roles')->newEntity([
            'name' => 'Rôle hist',
            'priority' => 1,
        ]));
        $user = $locator->get('Users')->saveOrFail($locator->get('Users')->newEntity([
            'user_code' => (string)random_int(100000, 999999),
            'last_name' => 'Hist',
            'first_name' => 'Test',
            'email' => 'hist-' . random_int(1000, 9999) . '@example.com',
            'password' => 'secret',
            'role_id' => $role->id,
            'site_id' => $site->id,
        ]));
        $offer = $locator->get('Offers')->saveOrFail($locator->get('Offers')->newEntity([
            'name' => 'Offre hist ' . random_int(1000, 9999),
            'color' => '#112233',
            'offer_type' => 'normal',
            'display_order' => 1,
            'is_displayed_in_grid' => true,
            'is_forecastable' => false,
            'equity_enabled' => false,
            'is_remote_work_compatible' => false,
        ]));
        $this->userId = (int)$user->id;
        $this->offerId = (int)$offer->id;
    }

    protected function tearDown(): void
    {
        $locator = $this->getTableLocator();
        $locator->get('PlanningDayHistories')->deleteAll(['user_id' => $this->userId]);
        if ($this->rangeIds !== []) {
            $locator->get('Ranges')->deleteAll(['id IN' => $this->rangeIds]);
        }
        $locator->get('Ranges')->deleteAll(['user_id' => $this->userId]);
        parent::tearDown();
    }

    public function testRecordPairsSkipsEmptyDayWithoutHistory(): void
    {
        $service = new PlanningDayHistoryService();
        $service->recordPairs([
            ['user_id' => $this->userId, 'day' => '2026-04-01'],
        ], PlanningDayHistoryService::SOURCE_FORM, null);

        $this->assertSame(0, $this->historyCount());
    }

    public function testBaselineIsCreatedOnceEvenWhenEmpty(): void
    {
        $service = new PlanningDayHistoryService();
        $pairs = [['user_id' => $this->userId, 'day' => '2026-04-02']];
        $service->captureBaseline($pairs);
        $service->captureBaseline($pairs);

        $rows = $this->histories();
        $this->assertCount(1, $rows);
        $this->assertSame(PlanningDayHistoryService::SOURCE_BASELINE, $rows[0]->source);
        $this->assertNull($rows[0]->actor_user_id);
        $this->assertSame([], $rows[0]->snapshot);
    }

    public function testRecordAfterChangeKeepsBaselineThenNewVersion(): void
    {
        $service = new PlanningDayHistoryService();
        $pairs = [['user_id' => $this->userId, 'day' => '2026-04-03']];
        $service->captureBaseline($pairs);
        $this->insertRange('2026-04-03 09:00:00', '2026-04-03 12:00:00');
        $service->recordPairs($pairs, PlanningDayHistoryService::SOURCE_FORM, $this->userId);
        $service->recordPairs($pairs, PlanningDayHistoryService::SOURCE_FORM, $this->userId);

        $rows = $this->histories();
        $this->assertCount(2, $rows);
        $this->assertSame(PlanningDayHistoryService::SOURCE_BASELINE, $rows[0]->source);
        $this->assertSame(PlanningDayHistoryService::SOURCE_FORM, $rows[1]->source);
        $this->assertSame($this->userId, (int)$rows[1]->actor_user_id);
    }

    public function testPairsAreReadBeforeDelete(): void
    {
        $first = $this->insertRange('2026-04-04 09:00:00', '2026-04-04 12:00:00');
        $second = $this->insertRange('2026-04-05 09:00:00', '2026-04-05 12:00:00');
        $service = new PlanningDayHistoryService();
        $conditions = ['Ranges.id IN' => [$first, $second]];
        $pairs = $service->pairsForConditions($conditions);
        $this->getTableLocator()->get('Ranges')->deleteAll($conditions);

        $days = array_column($pairs, 'day');
        sort($days);
        $this->assertSame(['2026-04-04', '2026-04-05'], $days);
    }

    public function testTrimKeepsThirtyVersions(): void
    {
        $service = new PlanningDayHistoryService();
        $day = '2026-04-06';
        $pairs = [['user_id' => $this->userId, 'day' => $day]];
        $service->captureBaseline($pairs);
        for ($minute = 0; $minute < 35; $minute++) {
            $this->getTableLocator()->get('Ranges')->deleteAll([
                'user_id' => $this->userId,
                'DATE(date_start)' => $day,
            ]);
            $start = sprintf('2026-04-06 08:%02d:00', $minute);
            $end = sprintf('2026-04-06 09:%02d:00', $minute);
            $this->insertRange($start, $end);
            $service->recordPairs($pairs, PlanningDayHistoryService::SOURCE_FORM, null);
        }

        $this->assertSame(30, $this->historyCount());
    }

    private function insertRange(string $start, string $end): int
    {
        $Ranges = $this->getTableLocator()->get('Ranges');
        $entity = $Ranges->newEntity([
            'user_id' => $this->userId,
            'offer_id' => $this->offerId,
            'date_start' => new FrozenTime($start),
            'date_end' => new FrozenTime($end),
            'comment' => 'test',
        ]);
        $entity->set('source', RangeSource::MANUAL);
        $saved = $Ranges->saveOrFail($entity);
        $this->rangeIds[] = (int)$saved->id;

        return (int)$saved->id;
    }

    private function historyCount(): int
    {
        return $this->getTableLocator()->get('PlanningDayHistories')->find()
            ->where(['user_id' => $this->userId])
            ->count();
    }

    /**
     * @return list<\App\Model\Entity\PlanningDayHistory>
     */
    private function histories(): array
    {
        return $this->getTableLocator()->get('PlanningDayHistories')->find()
            ->where(['user_id' => $this->userId])
            ->orderBy(['id' => 'ASC'])
            ->all()
            ->toList();
    }
}
