<?php
declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use JsonException;

/**
 * Aligne le volume réel et la prévision publiée sur une grille de 15 minutes,
 * puis agrège les deux séries de la même façon.
 *
 * Les durées sont en secondes. Un JSON de prévision illisible retire le jour
 * de la comparaison, sans lever d'exception.
 */
class VolumeCompareService
{
    /**
     * @param list<array{at: string, volume: int, dmt: int}> $actuals
     * @param array<string, mixed> $forecastByDate date Y-m-d => data_json (chaîne ou tableau)
     * @param string|null $startDate Borne inclusive Y-m-d. En granularité jour, chaque jour de l'intervalle a un point, à 0 s'il n'y a aucune donnée.
     * @param string|null $endDate Borne inclusive Y-m-d.
     * @return array{
     *   points: list<array{key: string, category: string, volume: int, dmt: int|null, forecast: int|null}>,
     *   volume_total: int,
     *   dmt_weighted: int|null,
     *   compare: array{
     *     has_forecast: bool,
     *     volume_real: int,
     *     volume_forecast: int,
     *     gap: int,
     *     gap_percent: float|null,
     *     wape: float|null,
     *     missing_days: int,
     *     dmt_weighted: int|null
     *   }|null
     * }
     */
    public function build(array $actuals, array $forecastByDate, string $granularity, bool $compare, ?string $startDate = null, ?string $endDate = null): array
    {
        if (!in_array($granularity, ['15min', 'hour', 'day'], true)) {
            $granularity = '15min';
        }

        $actualSlots = [];
        $actualDates = [];
        foreach ($actuals as $row) {
            $key = $this->slotKeyFromDateTime((string)($row['at'] ?? ''));
            if ($key === null) {
                continue;
            }
            $volume = (int)($row['volume'] ?? 0);
            $dmt = (int)($row['dmt'] ?? 0);
            if (!isset($actualSlots[$key])) {
                $actualSlots[$key] = ['volume' => 0, 'dmt_weight' => 0];
            }
            $actualSlots[$key]['volume'] += $volume;
            if ($volume > 0) {
                $actualSlots[$key]['dmt_weight'] += $volume * $dmt;
            }
            $actualDates[substr($key, 0, 10)] = true;
        }

        $forecastSlots = [];
        $comparableDates = [];
        if ($compare) {
            foreach ($forecastByDate as $date => $payload) {
                $dateKey = substr((string)$date, 0, 10);
                $slots = $this->decodeForecast($payload);
                if ($slots === null) {
                    continue;
                }
                $comparableDates[$dateKey] = true;
                foreach ($slots as $time => $volume) {
                    $forecastSlots[$dateKey . ' ' . $time] = $volume;
                }
            }
        }

        $slotKeys = array_fill_keys(array_keys($actualSlots), true);
        foreach (array_keys($forecastSlots) as $key) {
            $slotKeys[$key] = true;
        }

        /** @var array<string, array{volume: int, dmt_weight: int, forecast: int|null, comparable: bool}> $buckets */
        $buckets = [];
        foreach (array_keys($slotKeys) as $slotKey) {
            $date = substr($slotKey, 0, 10);
            $comparable = $compare && isset($comparableDates[$date]);
            $bucketKey = $this->bucketKey($slotKey, $granularity);
            if (!isset($buckets[$bucketKey])) {
                $buckets[$bucketKey] = [
                    'volume' => 0,
                    'dmt_weight' => 0,
                    'forecast' => $comparable ? 0 : null,
                    'comparable' => $comparable,
                ];
            }
            $actual = $actualSlots[$slotKey] ?? ['volume' => 0, 'dmt_weight' => 0];
            $buckets[$bucketKey]['volume'] += $actual['volume'];
            $buckets[$bucketKey]['dmt_weight'] += $actual['dmt_weight'];
            if ($comparable) {
                $buckets[$bucketKey]['forecast'] = (int)$buckets[$bucketKey]['forecast'] + (int)($forecastSlots[$slotKey] ?? 0);
            }
        }

        if ($granularity === 'day') {
            $this->fillMissingDays($buckets, $startDate, $endDate, $compare, $comparableDates);
        }

        ksort($buckets);

        $points = [];
        $volumeTotal = 0;
        $dmtWeightTotal = 0;
        $comparedReal = 0;
        $comparedForecast = 0;
        $absError = 0;
        $comparedDmtWeight = 0;
        $comparedDates = [];

        foreach ($buckets as $key => $bucket) {
            $volume = (int)$bucket['volume'];
            $dmt = $volume > 0 ? (int)round($bucket['dmt_weight'] / $volume) : null;
            $forecast = $bucket['comparable'] ? (int)$bucket['forecast'] : null;
            $points[] = [
                'key' => $key,
                'category' => $this->category($key, $granularity),
                'volume' => $volume,
                'dmt' => $dmt,
                'forecast' => $forecast,
            ];
            $volumeTotal += $volume;
            $dmtWeightTotal += (int)$bucket['dmt_weight'];
            if ($forecast !== null) {
                $comparedReal += $volume;
                $comparedForecast += $forecast;
                $absError += abs($forecast - $volume);
                $comparedDmtWeight += (int)$bucket['dmt_weight'];
                $comparedDates[substr($key, 0, 10)] = true;
            }
        }

        $missingDays = 0;
        if ($compare) {
            foreach (array_keys($actualDates) as $date) {
                if (!isset($comparableDates[$date])) {
                    $missingDays++;
                }
            }
        }

        return [
            'points' => $points,
            'volume_total' => $volumeTotal,
            'dmt_weighted' => $volumeTotal > 0 ? (int)round($dmtWeightTotal / $volumeTotal) : null,
            'compare' => $compare ? [
                'has_forecast' => $comparedDates !== [],
                'volume_real' => $comparedReal,
                'volume_forecast' => $comparedForecast,
                'gap' => $comparedForecast - $comparedReal,
                'gap_percent' => $comparedReal > 0 ? round((($comparedForecast - $comparedReal) / $comparedReal) * 100, 2) : null,
                'wape' => $comparedReal > 0 ? round(($absError / $comparedReal) * 100, 2) : null,
                'missing_days' => $missingDays,
                'dmt_weighted' => $comparedReal > 0 ? (int)round($comparedDmtWeight / $comparedReal) : null,
            ] : null,
        ];
    }

    /**
     * @return array<string, int>|null heure HH:MM:SS => volume, ou null si le jour est inutilisable
     */
    private function decodeForecast(mixed $payload): ?array
    {
        if (is_string($payload)) {
            $payload = trim($payload);
            if ($payload === '') {
                return null;
            }
            try {
                $payload = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return null;
            }
        }

        if (!is_array($payload) || array_is_list($payload)) {
            return null;
        }

        $slots = [];
        foreach ($payload as $key => $row) {
            $time = $this->normalizeTime((string)$key);
            if ($time === null || !is_array($row) || !isset($row['volume']) || !is_numeric($row['volume'])) {
                continue;
            }
            $volume = (int)round((float)$row['volume']);
            if ($volume < 0) {
                continue;
            }
            $slots[$time] = $volume;
        }

        return $slots === [] ? null : $slots;
    }

    private function normalizeTime(string $key): ?string
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim($key), $matches)) {
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

    private function slotKeyFromDateTime(string $dateTime): ?string
    {
        if (!preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{1,2}):(\d{2})(?::(\d{2}))?/', trim($dateTime), $matches)) {
            return null;
        }
        $time = $this->normalizeTime($matches[2] . ':' . $matches[3] . ':00');
        if ($time === null) {
            return null;
        }

        return $matches[1] . ' ' . $time;
    }

    /**
     * @param array<string, array{volume: int, dmt_weight: int, forecast: int|null, comparable: bool}> $buckets
     * @param array<string, true> $comparableDates
     */
    private function fillMissingDays(array &$buckets, ?string $startDate, ?string $endDate, bool $compare, array $comparableDates): void
    {
        $start = $this->parseDay($startDate);
        $end = $this->parseDay($endDate);
        if ($start === null || $end === null || $start > $end) {
            return;
        }

        $cursor = $start;
        while ($cursor <= $end) {
            $day = $cursor->format('Y-m-d');
            if (!isset($buckets[$day])) {
                $comparable = $compare && isset($comparableDates[$day]);
                $buckets[$day] = [
                    'volume' => 0,
                    'dmt_weight' => 0,
                    'forecast' => $comparable ? 0 : null,
                    'comparable' => $comparable,
                ];
            }
            $cursor = $cursor->modify('+1 day');
        }
    }

    private function parseDay(?string $value): ?DateTimeImmutable
    {
        if ($value === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $date;
    }

    private function bucketKey(string $slotKey, string $granularity): string
    {
        if ($granularity === 'day') {
            return substr($slotKey, 0, 10);
        }
        if ($granularity === 'hour') {
            return substr($slotKey, 0, 13) . ':00:00';
        }

        return $slotKey;
    }

    private function category(string $bucketKey, string $granularity): string
    {
        $stamp = strlen($bucketKey) === 10 ? $bucketKey . ' 00:00:00' : $bucketKey;
        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $stamp);
        if (!$date instanceof DateTimeImmutable) {
            return $bucketKey;
        }
        if ($granularity === 'day') {
            return $date->format('d/m/Y');
        }
        if ($granularity === 'hour') {
            return $date->format('d/m/Y H:00');
        }

        return $date->format('d/m/Y H:i');
    }
}
