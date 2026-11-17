<?php
declare(strict_types=1);

namespace App\Service\Export;

use DateTimeImmutable;

/**
 * Créneaux de 15 minutes entre le début inclus et la fin exclue.
 * Même convention que ForecastService : 17:00 → dernier créneau 16:45.
 */
final class TimeSlotGrid
{
    /**
     * @return list<string> Heures HH:MM:SS
     */
    public function slots(string $start, string $end): array
    {
        $current = $this->at($start);
        $limit = $this->at($end);
        if ($current === null || $limit === null) {
            return [];
        }

        $current = $this->floorToQuarterHour($current);
        $limit = $this->ceilToQuarterHour($limit);
        if ($current >= $limit) {
            return [];
        }

        $slots = [];
        for ($cursor = $current; $cursor < $limit; $cursor = $cursor->modify('+15 minutes')) {
            $slots[] = $cursor->format('H:i:s');
        }

        return $slots;
    }

    private function at(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if (preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
            $value .= ':00';
        }
        $date = DateTimeImmutable::createFromFormat('!H:i:s', $value);
        if (!$date instanceof DateTimeImmutable || $date->format('H:i:s') !== $value) {
            return null;
        }

        return $date;
    }

    private function floorToQuarterHour(DateTimeImmutable $date): DateTimeImmutable
    {
        $minute = (int)$date->format('i');
        $second = (int)$date->format('s');
        $quarter = intdiv($minute, 15) * 15;
        if ($minute === $quarter && $second === 0) {
            return $date;
        }

        return $date->setTime((int)$date->format('H'), $quarter, 0);
    }

    private function ceilToQuarterHour(DateTimeImmutable $date): DateTimeImmutable
    {
        $minute = (int)$date->format('i');
        $second = (int)$date->format('s');
        if ($minute % 15 === 0 && $second === 0) {
            return $date;
        }

        $extra = 15 - ($minute % 15);
        $shifted = $date->modify('+' . $extra . ' minutes');

        return $shifted->setTime((int)$shifted->format('H'), (int)$shifted->format('i'), 0);
    }
}
