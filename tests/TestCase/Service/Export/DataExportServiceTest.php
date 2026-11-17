<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\Export;

use App\Service\Export\DataExportService;
use App\Service\Export\ExportRequest;
use Cake\TestSuite\TestCase;

class DataExportServiceTest extends TestCase
{
    protected array $fixtures = [
        'app.Offers',
        'app.HistoricalData',
        'app.WfmSettings',
        'app.ForecastScenarios',
        'app.ForecastScenarioPublications',
        'app.ScenarioSeries',
    ];

    private DataExportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DataExportService();
    }

    public function testActualFillsMissingQuartersAndSkipsEmptyDays(): void
    {
        $this->setWindow('10:00:00', '11:00:00');
        $this->insertActual(1, '2026-03-02 10:00:00', 10, 100);
        $this->insertActual(1, '2026-03-02 10:30:00', 10, 300);

        $rows = $this->read($this->service->exportToTempStream(new ExportRequest(
            'actual',
            [1],
            '2026-03-02',
            '2026-03-03',
            '15min',
        )));

        $this->assertSame(['Date', 'Heure', 'Offre', 'Volume réel', 'DMT (s)'], $rows[0]);
        $this->assertSame(
            [
                ['2026-03-02', '10:00', 'Zèbre', '10', '100'],
                ['2026-03-02', '10:15', 'Zèbre', '0', ''],
                ['2026-03-02', '10:30', 'Zèbre', '10', '300'],
                ['2026-03-02', '10:45', 'Zèbre', '0', ''],
            ],
            array_slice($rows, 1)
        );
    }

    public function testActualSortsOffersByName(): void
    {
        $this->setWindow('10:00:00', '10:15:00');
        $this->insertActual(1, '2026-03-02 10:00:00', 1, 10);
        $this->insertActual(2, '2026-03-02 10:00:00', 2, 20);

        $rows = $this->read($this->service->exportToTempStream(new ExportRequest(
            'actual',
            [1, 2],
            '2026-03-02',
            '2026-03-02',
            '15min',
        )));

        $this->assertSame('Accueil', $rows[1][2]);
        $this->assertSame('Zèbre', $rows[2][2]);
    }

    public function testPointOutsideTheWindowStillOpensTheDayAtZero(): void
    {
        $this->setWindow('10:00:00', '10:30:00');
        $this->insertActual(1, '2026-03-02 08:00:00', 7, 90);

        $rows = $this->read($this->service->exportToTempStream(new ExportRequest(
            'actual',
            [1],
            '2026-03-02',
            '2026-03-02',
            '15min',
        )));

        $this->assertSame(
            [
                ['2026-03-02', '10:00', 'Zèbre', '0', ''],
                ['2026-03-02', '10:15', 'Zèbre', '0', ''],
            ],
            array_slice($rows, 1)
        );
    }

    public function testOfferNameWithSemicolonIsQuoted(): void
    {
        $offers = $this->getTableLocator()->get('Offers');
        $offer = $offers->get(2);
        $offer->name = 'Nord; Sud';
        $offers->saveOrFail($offer);
        $this->setWindow('10:00:00', '10:15:00');
        $this->insertActual(2, '2026-03-02 10:00:00', 3, 40);

        $handle = $this->service->exportToTempStream(new ExportRequest(
            'actual',
            [2],
            '2026-03-02',
            '2026-03-02',
            '15min',
        ));
        rewind($handle);
        $raw = stream_get_contents($handle);

        $this->assertIsString($raw);
        $this->assertStringContainsString('"Nord; Sud"', $raw);
    }

    public function testScenarioHourAndDayMeans(): void
    {
        $this->insertSeries(1, 1, '2026-03-02', 'forecast', '10:00:00', '11:00:00', [
            '10:00:00' => ['volume' => 10, 'dmt' => 100],
            '10:15:00' => ['volume' => 0, 'dmt' => 1],
            '10:30:00' => ['volume' => 10, 'dmt' => 300],
            '10:45:00' => ['volume' => 0, 'dmt' => 1],
        ]);
        $this->insertSeries(1, 1, '2026-03-02', 'need', '10:00:00', '11:00:00', [
            '10:00:00' => 4,
            '10:15:00' => 0,
            '10:30:00' => ['volume' => 4],
            '10:45:00' => 0,
        ]);
        $request = new ExportRequest('scenario', [1], '2026-03-02', '2026-03-02', 'hour', 1);

        $hour = $this->read($this->service->exportToTempStream($request));
        $this->assertSame(['2026-03-02', '10:00', 'Zèbre', '20', '2,00', '200'], $hour[1]);

        $day = $this->read($this->service->exportToTempStream(new ExportRequest(
            'scenario',
            [1],
            '2026-03-02',
            '2026-03-02',
            'day',
            1,
        )));
        $this->assertSame(['2026-03-02', '', 'Zèbre', '20', '2,00', '200'], $day[1]);
    }

    public function testScenarioPartialHourDividesByTwo(): void
    {
        $this->insertSeries(1, 1, '2026-03-02', 'forecast', '17:00:00', '17:30:00', [
            '17:00:00' => ['volume' => 0, 'dmt' => 10],
            '17:15:00' => ['volume' => 0, 'dmt' => 10],
        ]);
        $this->insertSeries(1, 1, '2026-03-02', 'need', '17:00:00', '17:30:00', [
            '17:00:00' => 3,
            '17:15:00' => 1,
        ]);

        $rows = $this->read($this->service->exportToTempStream(new ExportRequest(
            'scenario',
            [1],
            '2026-03-02',
            '2026-03-02',
            'hour',
            1,
        )));

        $this->assertSame(['2026-03-02', '17:00', 'Zèbre', '0', '2,00', ''], $rows[1]);
        $this->assertCount(2, $rows);
    }

    public function testUnreadableJsonReturnsNull(): void
    {
        $this->insertSeries(1, 1, '2026-03-02', 'forecast', '10:00:00', '11:00:00', '{bad', true);

        $handle = $this->service->exportToTempStream(new ExportRequest(
            'scenario',
            [1],
            '2026-03-02',
            '2026-03-02',
            '15min',
            1,
        ));

        $this->assertNull($handle);
    }

    public function testJsonListIsNotAnEmptyDay(): void
    {
        $this->insertSeries(1, 1, '2026-03-02', 'forecast', '10:00:00', '11:00:00', '[]', true);

        $handle = $this->service->exportToTempStream(new ExportRequest(
            'scenario',
            [1],
            '2026-03-02',
            '2026-03-02',
            '15min',
            1,
        ));

        $this->assertNull($handle);
    }

    public function testPublishedUsesTheScenarioOfEachDay(): void
    {
        $scenarios = $this->getTableLocator()->get('ForecastScenarios');
        $first = $scenarios->get(1);
        $first->name = 'NomA';
        $scenarios->saveOrFail($first);
        $second = $scenarios->get(2);
        $second->name = 'NomB';
        $second->status = 'completed';
        $scenarios->saveOrFail($second);

        $this->insertPublication(1, '2026-03-02');
        $this->insertPublication(2, '2026-03-03');
        $this->insertSeries(1, 2, '2026-03-02', 'forecast', '09:00:00', '09:15:00', []);
        $this->insertSeries(2, 2, '2026-03-03', 'forecast', '09:00:00', '09:15:00', []);

        $rows = $this->read($this->service->exportToTempStream(new ExportRequest(
            'published',
            [2],
            '2026-03-02',
            '2026-03-03',
            'day',
        )));

        $this->assertSame('1 - NomA', $rows[1][3]);
        $this->assertSame('2 - NomB', $rows[2][3]);
        $this->assertSame('2026-03-02', $rows[1][0]);
        $this->assertSame('2026-03-03', $rows[2][0]);
    }

    private function setWindow(string $start, string $end): void
    {
        $this->getTableLocator()->get('WfmSettings')->updateAll(
            ['day_start_time' => $start, 'day_end_time' => $end],
            ['id' => 1]
        );
    }

    private function insertActual(int $offerId, string $at, int $volume, int $dmt): void
    {
        $this->getTableLocator()->get('HistoricalData')->insertQuery()
            ->insert(['offer_id', 'datetime_interval', 'call_volume', 'avg_handle_time_seconds'])
            ->values([
                'offer_id' => $offerId,
                'datetime_interval' => $at,
                'call_volume' => $volume,
                'avg_handle_time_seconds' => $dmt,
            ])
            ->execute();
    }

    /**
     * @param array<string, mixed>|string $payload
     */
    private function insertSeries(
        int $scenarioId,
        int $offerId,
        string $date,
        string $type,
        string $start,
        string $end,
        array|string $payload,
        bool $raw = false,
    ): void {
        if ($raw) {
            $json = (string)$payload;
        } else {
            $json = json_encode($payload === [] ? new \stdClass() : $payload, JSON_THROW_ON_ERROR);
        }
        $this->getTableLocator()->get('ScenarioSeries')->insertQuery()
            ->insert(['scenario_id', 'offer_id', 'date', 'type', 'step_seconds', 'start_time', 'end_time', 'data_json'])
            ->values([
                'scenario_id' => $scenarioId,
                'offer_id' => $offerId,
                'date' => $date,
                'type' => $type,
                'step_seconds' => 900,
                'start_time' => $start,
                'end_time' => $end,
                'data_json' => $json,
            ])
            ->execute();
    }

    private function insertPublication(int $scenarioId, string $date): void
    {
        $this->getTableLocator()->get('ForecastScenarioPublications')->insertQuery()
            ->insert(['scenario_id', 'date'])
            ->values([
                'scenario_id' => $scenarioId,
                'date' => $date,
            ])
            ->execute();
    }

    /**
     * @return list<list<string>>
     */
    private function read(mixed $handle): array
    {
        $this->assertIsResource($handle);
        rewind($handle);
        $this->assertSame("\xEF\xBB\xBF", fread($handle, 3));
        $rows = [];
        while (($row = fgetcsv($handle, 0, ';', '"', '')) !== false) {
            $rows[] = $row;
        }

        return $rows;
    }
}
