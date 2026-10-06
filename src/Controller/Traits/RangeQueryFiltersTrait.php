<?php
declare(strict_types=1);

namespace App\Controller\Traits;

/**
 * Parsing commun des filtres user_id et dates pour la table ranges.
 * Le tableau est passé en argument : la méthode ne lit pas la requête.
 */
trait RangeQueryFiltersTrait
{
    /**
     * @param array<string, mixed> $queryParams
     * @return array<string, mixed>
     */
    protected function buildRangeFilters(array $queryParams): array
    {
        $conditions = [];

        $userId = $this->rangeFilterPositiveInt($queryParams['user_id'] ?? null);
        if ($userId !== null) {
            $conditions['Ranges.user_id'] = $userId;
        }

        $filterStart = $this->rangeFilterBound($queryParams['date_start'] ?? null, '00:00:00');
        $filterEnd = $this->rangeFilterBound($queryParams['date_end'] ?? null, '23:59:59');

        if ($filterStart !== null && $filterEnd !== null) {
            $conditions['Ranges.date_start <='] = $filterEnd;
            $conditions['Ranges.date_end >='] = $filterStart;
        } elseif ($filterStart !== null) {
            $conditions['Ranges.date_end >='] = $filterStart;
        } elseif ($filterEnd !== null) {
            $conditions['Ranges.date_start <='] = $filterEnd;
        }

        return $conditions;
    }

    protected function rangeFilterPositiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (!is_string($value) || $value === '' || !ctype_digit($value)) {
            return null;
        }
        $int = (int)$value;

        return $int > 0 ? $int : null;
    }

    private function rangeFilterBound(mixed $value, string $time): ?string
    {
        if (is_array($value) && !empty($value['year']) && !empty($value['month']) && !empty($value['day'])) {
            $date = sprintf('%04d-%02d-%02d', $value['year'], $value['month'], $value['day']);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
                return $date . ' ' . $time;
            }

            return null;
        }
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value . ' ' . $time;
        }

        return null;
    }
}
