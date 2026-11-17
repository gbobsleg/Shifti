<?php
declare(strict_types=1);

namespace App\Service\Export;

use InvalidArgumentException;

/**
 * Agrège une grille 15 min déjà complète (les trous sont des volumes à 0).
 * Le besoin est une moyenne sur les créneaux réellement présents, pas sur 4.
 */
final class SlotAggregator
{
    /**
     * @param list<array{time: string, volume: int, need: int, dmt: int|null}> $slots
     * @return list<array{time: string, volume: int, need: string, dmt: string}>
     */
    public function aggregate(array $slots, string $granularity): array
    {
        if (!in_array($granularity, ['15min', 'hour', 'day'], true)) {
            throw new InvalidArgumentException('Granularité inconnue.');
        }
        if ($slots === []) {
            return [];
        }
        if ($granularity === '15min') {
            return $this->asIs($slots);
        }

        /** @var array<string, list<array{time: string, volume: int, need: int, dmt: int|null}>> $groups */
        $groups = [];
        foreach ($slots as $slot) {
            $key = $granularity === 'day' ? 'day' : substr($slot['time'], 0, 2);
            $groups[$key][] = $slot;
        }

        $rows = [];
        foreach ($groups as $key => $group) {
            $rows[] = $this->collapse($group, $granularity === 'day' ? '' : $key . ':00');
        }

        return $rows;
    }

    /**
     * @param list<array{time: string, volume: int, need: int, dmt: int|null}> $slots
     * @return list<array{time: string, volume: int, need: string, dmt: string}>
     */
    private function asIs(array $slots): array
    {
        $rows = [];
        foreach ($slots as $slot) {
            $volume = (int)$slot['volume'];
            $rows[] = [
                'time' => substr($slot['time'], 0, 5),
                'volume' => $volume,
                'need' => (string)(int)$slot['need'],
                'dmt' => $this->dmtLabel($volume, $slot['dmt']),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array{time: string, volume: int, need: int, dmt: int|null}> $group
     * @return array{time: string, volume: int, need: string, dmt: string}
     */
    private function collapse(array $group, string $time): array
    {
        $volume = 0;
        $needSum = 0;
        $weight = 0;
        $weightedVolume = 0;
        foreach ($group as $slot) {
            $slotVolume = (int)$slot['volume'];
            $volume += $slotVolume;
            $needSum += (int)$slot['need'];
            if ($slotVolume > 0 && $slot['dmt'] !== null) {
                $weight += $slotVolume * (int)$slot['dmt'];
                $weightedVolume += $slotVolume;
            }
        }

        $count = count($group);
        $average = $count > 0 ? $needSum / $count : 0.0;

        return [
            'time' => $time,
            'volume' => $volume,
            'need' => number_format(round($average, 2), 2, ',', ''),
            'dmt' => $weightedVolume > 0 ? (string)(int)round($weight / $weightedVolume) : '',
        ];
    }

    private function dmtLabel(int $volume, ?int $dmt): string
    {
        if ($volume <= 0 || $dmt === null) {
            return '';
        }

        return (string)$dmt;
    }
}
