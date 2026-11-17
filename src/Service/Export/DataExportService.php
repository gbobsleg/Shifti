<?php
declare(strict_types=1);

namespace App\Service\Export;

use Cake\ORM\Locator\LocatorAwareTrait;
use DateTimeImmutable;
use JsonException;
use RuntimeException;

/**
 * Construit le CSV dans php://temp, mois par mois, sans hydrater d'entités.
 * Retourne null si aucune ligne métier n'a été émise (JSON illisible compris).
 */
final class DataExportService
{
    use LocatorAwareTrait;

    private TimeSlotGrid $grid;

    private SlotAggregator $aggregator;

    public function __construct(?TimeSlotGrid $grid = null, ?SlotAggregator $aggregator = null)
    {
        $this->grid = $grid ?? new TimeSlotGrid();
        $this->aggregator = $aggregator ?? new SlotAggregator();
    }

    /**
     * @return resource|null
     */
    public function exportToTempStream(ExportRequest $request): mixed
    {
        if ($request->offerIds === []) {
            return null;
        }

        $writer = CsvStreamWriter::open();
        $writer->write($this->headers($request->type));
        $offerNames = $this->offerNames($request->offerIds);
        foreach ($request->offerIds as $offerId) {
            $offerNames[$offerId] ??= 'Offre #' . $offerId;
        }
        $count = match ($request->type) {
            'actual' => $this->writeActual($writer, $request, $offerNames),
            'scenario' => $this->writeSeries($writer, $request, $offerNames, false),
            'published' => $this->writeSeries($writer, $request, $offerNames, true),
            default => 0,
        };
        if ($count === 0) {
            $writer->close();

            return null;
        }

        return $writer->detach();
    }

    public function filename(ExportRequest $request): string
    {
        $start = str_replace('-', '', $request->startDate);
        $end = str_replace('-', '', $request->endDate);
        $grain = $request->granularity;

        return match ($request->type) {
            'scenario' => 'scenario_' . (int)$request->scenarioId . "_{$start}-{$end}_{$grain}.csv",
            'published' => "publie_{$start}-{$end}_{$grain}.csv",
            default => "reel_{$start}-{$end}_{$grain}.csv",
        };
    }

    /**
     * @param array<int, string> $offerNames
     */
    private function writeActual(CsvStreamWriter $writer, ExportRequest $request, array $offerNames): int
    {
        $count = 0;
        foreach ($this->months($request->startDate, $request->endDate) as [$from, $to]) {
            $count += $this->emitActualMonth($writer, $request, $offerNames, $from, $to);
        }

        return $count;
    }

    /**
     * @param array<int, string> $offerNames
     */
    private function emitActualMonth(
        CsvStreamWriter $writer,
        ExportRequest $request,
        array $offerNames,
        string $from,
        string $to,
    ): int {
        $rows = $this->fetchTable('HistoricalData')->find()
            ->select(['offer_id', 'datetime_interval', 'call_volume', 'avg_handle_time_seconds'])
            ->where([
                'offer_id IN' => $request->offerIds,
                'datetime_interval >=' => $from . ' 00:00:00',
                'datetime_interval <=' => $to . ' 23:59:59',
            ])
            ->orderBy(['datetime_interval' => 'ASC', 'offer_id' => 'ASC'])
            ->disableHydration()
            ->all()
            ->toList();
        if ($rows === []) {
            return 0;
        }
        $window = $this->wfmWindow();

        /** @var array<int, array<string, array<string, array{volume: int, weight: int}>>> $byOffer */
        $byOffer = [];
        /** @var array<int, array<string, true>> $present */
        $present = [];
        foreach ($rows as $row) {
            $offerId = (int)($row['offer_id'] ?? 0);
            $stamp = $this->slotFromDateTime($this->asDateTime($row['datetime_interval'] ?? ''));
            if ($offerId <= 0 || $stamp === null) {
                continue;
            }
            $present[$offerId][$stamp['date']] = true;
            $time = $stamp['time'];
            $volume = (int)($row['call_volume'] ?? 0);
            if (!isset($byOffer[$offerId][$stamp['date']][$time])) {
                $byOffer[$offerId][$stamp['date']][$time] = ['volume' => 0, 'weight' => 0];
            }
            $byOffer[$offerId][$stamp['date']][$time]['volume'] += $volume;
            if ($volume > 0) {
                $byOffer[$offerId][$stamp['date']][$time]['weight'] += $volume * (int)($row['avg_handle_time_seconds'] ?? 0);
            }
        }

        $lines = [];
        foreach ($this->sortedOfferIds($offerNames) as $offerId) {
            $dates = array_keys($present[$offerId] ?? []);
            sort($dates);
            foreach ($dates as $date) {
                $slots = [];
                foreach ($this->grid->slots($window[0], $window[1]) as $time) {
                    $point = $byOffer[$offerId][$date][$time] ?? null;
                    $volume = $point['volume'] ?? 0;
                    $dmt = null;
                    if ($point !== null && $volume > 0) {
                        $dmt = (int)round($point['weight'] / $volume);
                    }
                    $slots[] = [
                        'time' => $time,
                        'volume' => $volume,
                        'need' => 0,
                        'dmt' => $dmt,
                    ];
                }
                $this->appendLines($lines, $date, $offerNames[$offerId] ?? ('Offre #' . $offerId), '', $slots, $request->granularity);
            }
        }

        return $this->writeSorted($writer, 'actual', $lines);
    }

    /**
     * @param array<int, string> $offerNames
     */
    private function writeSeries(
        CsvStreamWriter $writer,
        ExportRequest $request,
        array $offerNames,
        bool $published,
    ): int {
        $scenarioNames = $published ? $this->scenarioNames() : [];
        $count = 0;
        foreach ($this->months($request->startDate, $request->endDate) as [$from, $to]) {
            $count += $this->emitSeriesMonth($writer, $request, $offerNames, $scenarioNames, $published, $from, $to);
        }

        return $count;
    }

    /**
     * @param array<int, string> $offerNames
     * @param array<int, string> $scenarioNames
     */
    private function emitSeriesMonth(
        CsvStreamWriter $writer,
        ExportRequest $request,
        array $offerNames,
        array $scenarioNames,
        bool $published,
        string $from,
        string $to,
    ): int {
        $query = $this->fetchTable('ScenarioSeries')->find()
            ->select([
                'date' => 'ScenarioSeries.date',
                'offer_id' => 'ScenarioSeries.offer_id',
                'type' => 'ScenarioSeries.type',
                'start_time' => 'ScenarioSeries.start_time',
                'end_time' => 'ScenarioSeries.end_time',
                'data_json' => 'ScenarioSeries.data_json',
                'scenario_id' => 'ScenarioSeries.scenario_id',
            ])
            ->where([
                'ScenarioSeries.offer_id IN' => $request->offerIds,
                'ScenarioSeries.type IN' => ['forecast', 'need'],
                'ScenarioSeries.date >=' => $from,
                'ScenarioSeries.date <=' => $to,
            ])
            ->disableHydration();

        if ($published) {
            $query
                ->innerJoin(
                    ['Publications' => 'forecast_scenario_publications'],
                    [
                        'Publications.scenario_id = ScenarioSeries.scenario_id',
                        'Publications.date = ScenarioSeries.date',
                    ]
                );
        } else {
            $query->where(['ScenarioSeries.scenario_id' => (int)$request->scenarioId]);
        }

        /** @var array<string, array<string, array<string, mixed>>> $bucket */
        $bucket = [];
        foreach ($query->all() as $row) {
            $offerId = (int)($row['offer_id'] ?? 0);
            $date = $this->asDate($row['date'] ?? null);
            $type = (string)($row['type'] ?? '');
            if ($offerId <= 0 || $date === null || $type === '') {
                continue;
            }
            $bucket[$offerId . '|' . $date][$type] = $row;
        }

        $lines = [];
        foreach ($this->sortedOfferIds($offerNames) as $offerId) {
            $keys = [];
            foreach (array_keys($bucket) as $key) {
                if (str_starts_with($key, $offerId . '|')) {
                    $keys[] = substr($key, strlen((string)$offerId) + 1);
                }
            }
            sort($keys);
            foreach ($keys as $date) {
                $pair = $bucket[$offerId . '|' . $date];
                $forecastRow = $pair['forecast'] ?? null;
                if (!is_array($forecastRow)) {
                    continue;
                }
                $forecast = $this->decodeMap($forecastRow['data_json'] ?? null, 'forecast');
                if ($forecast === null) {
                    continue;
                }
                $need = [];
                if (isset($pair['need']) && is_array($pair['need'])) {
                    $need = $this->decodeMap($pair['need']['data_json'] ?? null, 'need') ?? [];
                }
                $start = $this->asTime($forecastRow['start_time'] ?? null);
                $end = $this->asTime($forecastRow['end_time'] ?? null);
                if ($start === null || $end === null) {
                    continue;
                }
                $slots = [];
                foreach ($this->grid->slots($start, $end) as $time) {
                    $point = $forecast[$time] ?? null;
                    $volume = is_array($point) ? (int)$point['volume'] : 0;
                    $dmt = null;
                    if ($volume > 0 && is_array($point)) {
                        $dmt = $point['dmt'];
                    }
                    $slots[] = [
                        'time' => $time,
                        'volume' => $volume,
                        'need' => (int)($need[$time] ?? 0),
                        'dmt' => $dmt,
                    ];
                }
                $scenarioId = (int)($forecastRow['scenario_id'] ?? 0);
                $label = $scenarioId . ' - ' . ($scenarioNames[$scenarioId] ?? '');
                $this->appendLines(
                    $lines,
                    $date,
                    $offerNames[$offerId] ?? ('Offre #' . $offerId),
                    $label,
                    $slots,
                    $request->granularity
                );
            }
        }

        return $this->writeSorted($writer, $published ? 'published' : 'scenario', $lines);
    }

    /**
     * @param list<array{date: string, time: string, offer: string, scenario: string, volume: int, need: string, dmt: string}> $lines
     * @param list<array{time: string, volume: int, need: int, dmt: int|null}> $slots
     */
    private function appendLines(
        array &$lines,
        string $date,
        string $offer,
        string $scenario,
        array $slots,
        string $granularity,
    ): void {
        foreach ($this->aggregator->aggregate($slots, $granularity) as $row) {
            $lines[] = [
                'date' => $date,
                'time' => $row['time'],
                'offer' => $offer,
                'scenario' => $scenario,
                'volume' => $row['volume'],
                'need' => $row['need'],
                'dmt' => $row['dmt'],
            ];
        }
    }

    /**
     * @param list<array{date: string, time: string, offer: string, scenario: string, volume: int, need: string, dmt: string}> $lines
     */
    private function writeSorted(CsvStreamWriter $writer, string $type, array $lines): int
    {
        usort($lines, static function (array $left, array $right): int {
            $date = strcmp($left['date'], $right['date']);
            if ($date !== 0) {
                return $date;
            }
            $offer = strnatcasecmp($left['offer'], $right['offer']);
            if ($offer !== 0) {
                return $offer;
            }

            return strcmp($left['time'], $right['time']);
        });

        foreach ($lines as $line) {
            if ($type === 'actual') {
                $writer->write([$line['date'], $line['time'], $line['offer'], $line['volume'], $line['dmt']]);
                continue;
            }
            if ($type === 'published') {
                $writer->write([
                    $line['date'],
                    $line['time'],
                    $line['offer'],
                    $line['scenario'],
                    $line['volume'],
                    $line['need'],
                    $line['dmt'],
                ]);
                continue;
            }
            $writer->write([
                $line['date'],
                $line['time'],
                $line['offer'],
                $line['volume'],
                $line['need'],
                $line['dmt'],
            ]);
        }

        return count($lines);
    }

    /**
     * @return list<string>
     */
    private function headers(string $type): array
    {
        return match ($type) {
            'published' => ['Date', 'Heure', 'Offre', 'Scénario', 'Volume prévu', 'Besoin', 'DMT (s)'],
            'scenario' => ['Date', 'Heure', 'Offre', 'Volume prévu', 'Besoin', 'DMT (s)'],
            default => ['Date', 'Heure', 'Offre', 'Volume réel', 'DMT (s)'],
        };
    }

    /**
     * @param list<int> $offerIds
     * @return array<int, string>
     */
    private function offerNames(array $offerIds): array
    {
        $rows = $this->fetchTable('Offers')->find()
            ->select(['id', 'name'])
            ->where(['id IN' => $offerIds])
            ->disableHydration()
            ->all();
        $names = [];
        foreach ($rows as $row) {
            $names[(int)$row['id']] = (string)$row['name'];
        }

        return $names;
    }

    /**
     * @return array<int, string>
     */
    private function scenarioNames(): array
    {
        $rows = $this->fetchTable('ForecastScenarios')->find()
            ->select(['id', 'name'])
            ->disableHydration()
            ->all();
        $names = [];
        foreach ($rows as $row) {
            $names[(int)$row['id']] = (string)$row['name'];
        }

        return $names;
    }

    /**
     * @param array<int, string> $offerNames
     * @return list<int>
     */
    private function sortedOfferIds(array $offerNames): array
    {
        $ids = array_keys($offerNames);
        usort($ids, static function (int $left, int $right) use ($offerNames): int {
            return strnatcasecmp($offerNames[$left], $offerNames[$right]);
        });

        return $ids;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function wfmWindow(): array
    {
        $settings = $this->fetchTable('WfmSettings')->find()
            ->select(['day_start_time', 'day_end_time'])
            ->disableHydration()
            ->first();
        if (!is_array($settings)) {
            throw new RuntimeException('Profil WFM manquant.');
        }
        $start = $this->asTime($settings['day_start_time'] ?? null);
        $end = $this->asTime($settings['day_end_time'] ?? null);
        if ($start === null || $end === null) {
            throw new RuntimeException('Horaires de journée WFM manquants.');
        }

        return [$start, $end];
    }

    /**
     * Un point hors fenêtre marque quand même le jour comme présent.
     * On ne garde ensuite que les créneaux dont l'heure est dans [début, fin).
     * La comparaison brute HH:MM:SS suffit parce que la grille est calée sur ces bornes.
     *
     * @return array{date: string, time: string}|null
     */
    private function slotFromDateTime(string $value): ?array
    {
        if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}(?::\d{2})?)/', trim($value), $matches) !== 1) {
            return null;
        }
        $time = $this->normalizeQuarter($matches[2]);
        if ($time === null) {
            return null;
        }

        return ['date' => $matches[1], 'time' => $time];
    }

    /**
     * @return array<string, array{volume: int, dmt: int|null}|int>|null
     */
    private function decodeMap(mixed $payload, string $kind): ?array
    {
        if (is_string($payload)) {
            $payload = trim($payload);
            if ($payload === '') {
                return null;
            }
            try {
                $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return null;
            }
            if (!is_array($decoded)) {
                return null;
            }
            // json_decode('{}', true) et json_decode('[]', true) donnent tous les deux [].
            // Seul l'objet vide est une journée présente, remplie de zéros.
            if ($decoded === []) {
                return str_starts_with($payload, '{') ? [] : null;
            }
            $payload = $decoded;
        }
        if (!is_array($payload) || array_is_list($payload)) {
            return null;
        }

        $slots = [];
        foreach ($payload as $key => $row) {
            $time = $this->normalizeQuarter((string)$key);
            if ($time === null) {
                continue;
            }
            if ($kind === 'need') {
                $slots[$time] = $this->needValue($row);
                continue;
            }
            $parsed = $this->forecastValue($row);
            if ($parsed !== null) {
                $slots[$time] = $parsed;
            }
        }

        return $slots;
    }

    /**
     * @return array{volume: int, dmt: int|null}|null
     */
    private function forecastValue(mixed $row): ?array
    {
        if (is_array($row)) {
            if (!isset($row['volume']) || !is_numeric($row['volume'])) {
                return null;
            }
            $volume = (int)round((float)$row['volume']);
            if ($volume < 0) {
                return null;
            }
            $dmt = null;
            if (isset($row['dmt']) && is_numeric($row['dmt'])) {
                $dmtValue = (int)round((float)$row['dmt']);
                if ($dmtValue >= 0) {
                    $dmt = $dmtValue;
                }
            }

            return ['volume' => $volume, 'dmt' => $dmt];
        }
        if (!is_numeric($row)) {
            return null;
        }
        $volume = (int)round((float)$row);
        if ($volume < 0) {
            return null;
        }

        return ['volume' => $volume, 'dmt' => null];
    }

    private function needValue(mixed $row): int
    {
        $raw = is_array($row) ? ($row['volume'] ?? null) : $row;
        if (!is_numeric($raw)) {
            return 0;
        }

        return max(0, (int)round((float)$raw));
    }

    private function normalizeQuarter(string $value): ?string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim($value), $matches) !== 1) {
            return null;
        }
        $hour = (int)$matches[1];
        $minute = (int)$matches[2];
        if ($hour > 23 || $minute > 59) {
            return null;
        }
        $minute = intdiv($minute, 15) * 15;

        return sprintf('%02d:%02d:00', $hour, $minute);
    }

    private function asDateTime(mixed $value): string
    {
        $formatted = $this->formatValue($value, 'Y-m-d H:i:s');
        if ($formatted !== null) {
            return $formatted;
        }

        return trim((string)$value);
    }

    private function asDate(mixed $value): ?string
    {
        $formatted = $this->formatValue($value, 'Y-m-d');
        if ($formatted !== null) {
            return $formatted;
        }
        $text = substr(trim((string)$value), 0, 10);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1 ? $text : null;
    }

    private function asTime(mixed $value): ?string
    {
        $formatted = $this->formatValue($value, 'H:i:s');
        if ($formatted !== null) {
            return $formatted;
        }
        $text = trim((string)$value);
        if (preg_match('/(\d{2}:\d{2}:\d{2})/', $text, $matches) !== 1) {
            if (preg_match('/(\d{2}:\d{2})/', $text, $matches) !== 1) {
                return null;
            }

            return $matches[1] . ':00';
        }

        return $matches[1];
    }

    /**
     * Cake\I18n\Date et Cake\I18n\Time ne sont pas des DateTimeInterface.
     * Leur cast (string) suit la locale (fr_FR), pas Y-m-d.
     */
    private function formatValue(mixed $value, string $format): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format($format);
        }
        if (is_object($value) && method_exists($value, 'format')) {
            $formatted = $value->format($format);

            return is_string($formatted) ? $formatted : null;
        }

        return null;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function months(string $start, string $end): array
    {
        $cursor = new DateTimeImmutable($start);
        $limit = new DateTimeImmutable($end);
        $month = $cursor->modify('first day of this month');
        $ranges = [];
        while ($month <= $limit) {
            $from = $month < $cursor ? $cursor : $month;
            $monthEnd = $month->modify('last day of this month');
            $to = $monthEnd > $limit ? $limit : $monthEnd;
            $ranges[] = [$from->format('Y-m-d'), $to->format('Y-m-d')];
            $month = $month->modify('first day of next month');
        }

        return $ranges;
    }
}
