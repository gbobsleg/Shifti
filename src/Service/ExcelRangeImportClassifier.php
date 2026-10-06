<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Table\RangesTable;
use Cake\I18n\FrozenTime;
use DateTimeInterface;

/**
 * Classe les plages Excel contre la BDD (et entre elles) avant insert.
 */
class ExcelRangeImportClassifier
{
    public const STATUS_NEW = 'new';
    public const STATUS_SKIP_AUTO_TAD = 'skip_auto_tad';
    public const STATUS_SKIP_MANUAL = 'skip_manual';
    public const STATUS_SKIP_PLANNING = 'skip_planning';
    public const STATUS_SKIP_IDENTICAL = 'skip_identical';
    public const STATUS_SKIP_IMPORT_PARTIAL = 'skip_import_partial';
    public const STATUS_SKIP_INTRA_EXCEL = 'skip_intra_excel';
    public const STATUS_REPLACE_IMPORT = 'replace_import';

    public const PROVENANCE_AUTO_TAD = RangeSource::AUTO_TAD;
    public const PROVENANCE_IMPORT = RangeSource::IMPORT;
    public const PROVENANCE_MANUAL = RangeSource::MANUAL;
    public const PROVENANCE_PLANNING = RangeSource::PLANNING;

    /**
     * @param array<int, array<string, mixed>> $groupedRanges
     * @param array<int, int> $excludeIds Plages qui seront supprimées par la purge, ignorées ici.
     * @return array<int, array{status: string, conflicts: array, replace_ids: int[]}>
     */
    public function classify(array $groupedRanges, RangesTable $rangesTable, array $excludeIds = []): array
    {
        if ($groupedRanges === []) {
            return [];
        }

        $existing = $this->loadExisting($groupedRanges, $rangesTable, $excludeIds);
        $planned = [];
        $decisions = [];

        foreach ($groupedRanges as $index => $range) {
            $decision = $this->classifyOne($range, $existing, $planned);
            $decisions[$index] = $decision;

            if (in_array($decision['status'], [self::STATUS_NEW, self::STATUS_REPLACE_IMPORT], true)) {
                $planned[] = [
                    'user_id' => (int)$range['user_id'],
                    'offer_id' => (int)$range['offer_id'],
                    'date_start' => $this->normalizeTime($range['date_start']),
                    'date_end' => $this->normalizeTime($range['date_end']),
                ];
            }
        }

        return $decisions;
    }

    public static function isSkipStatus(string $status): bool
    {
        return str_starts_with($status, 'skip_');
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_NEW => 'Nouveau',
            self::STATUS_REPLACE_IMPORT => 'Remplace',
            self::STATUS_SKIP_AUTO_TAD => 'Ignoré (TAD fixe)',
            self::STATUS_SKIP_MANUAL => 'Ignoré (saisie existante)',
            self::STATUS_SKIP_PLANNING => 'Ignoré (planning)',
            self::STATUS_SKIP_IDENTICAL => 'Ignoré (déjà présent)',
            self::STATUS_SKIP_IMPORT_PARTIAL => 'Ignoré (import précédent plus large)',
            self::STATUS_SKIP_INTRA_EXCEL => 'Ignoré (doublon fichier)',
            default => 'Ignoré',
        };
    }

    public static function statusGroup(string $status): string
    {
        if ($status === self::STATUS_NEW) {
            return 'new';
        }
        if ($status === self::STATUS_REPLACE_IMPORT) {
            return 'replace';
        }

        return 'skip';
    }

    public static function provenanceLabel(string $provenance): string
    {
        return match ($provenance) {
            self::PROVENANCE_AUTO_TAD => 'TAD fixe',
            self::PROVENANCE_IMPORT => 'import',
            self::PROVENANCE_PLANNING => 'planning',
            default => 'saisie manuelle',
        };
    }

    /**
     * @param array<string, mixed> $range
     * @param array<int, array<string, mixed>> $existing
     * @param array<int, array<string, mixed>> $planned
     * @return array{status: string, conflicts: array, replace_ids: int[]}
     */
    private function classifyOne(array $range, array $existing, array $planned): array
    {
        $empty = ['status' => self::STATUS_NEW, 'conflicts' => [], 'replace_ids' => []];
        if (empty($range['user_id']) || empty($range['offer_id']) || empty($range['date_start']) || empty($range['date_end'])) {
            return $empty;
        }

        $userId = (int)$range['user_id'];
        $offerId = (int)$range['offer_id'];
        $excelStart = $this->normalizeTime($range['date_start']);
        $excelEnd = $this->normalizeTime($range['date_end']);

        $overlaps = [];
        foreach ($existing as $row) {
            if ((int)$row['user_id'] !== $userId || (int)$row['offer_id'] !== $offerId) {
                continue;
            }
            $existingStart = $this->normalizeTime($row['date_start']);
            $existingEnd = $this->normalizeTime($row['date_end']);
            if (!$this->overlaps($existingStart, $existingEnd, $excelStart, $excelEnd)) {
                continue;
            }
            $source = (string)($row['source'] ?? RangeSource::MANUAL);
            if (!RangeSource::isValid($source)) {
                $source = RangeSource::MANUAL;
            }
            $overlaps[] = [
                'id' => (int)$row['id'],
                'user_id' => (int)$row['user_id'],
                'offer_id' => (int)$row['offer_id'],
                'date_start' => $existingStart,
                'date_end' => $existingEnd,
                'comment' => (string)($row['comment'] ?? ''),
                'provenance' => $source,
            ];
        }

        foreach ($overlaps as $overlap) {
            if ($this->sameBounds($overlap['date_start'], $overlap['date_end'], $excelStart, $excelEnd)) {
                return [
                    'status' => self::STATUS_SKIP_IDENTICAL,
                    'conflicts' => [$overlap],
                    'replace_ids' => [],
                ];
            }
        }

        $protected = [];
        $imported = [];
        foreach ($overlaps as $overlap) {
            if ($overlap['provenance'] === self::PROVENANCE_IMPORT) {
                $imported[] = $overlap;
            } else {
                $protected[] = $overlap;
            }
        }

        if ($protected !== []) {
            return [
                'status' => $this->protectedStatus($protected),
                'conflicts' => $protected,
                'replace_ids' => [],
            ];
        }

        if ($imported !== []) {
            $replaceIds = [];
            foreach ($imported as $row) {
                if (!$this->fullyCovered($row['date_start'], $row['date_end'], $excelStart, $excelEnd)) {
                    return [
                        'status' => self::STATUS_SKIP_IMPORT_PARTIAL,
                        'conflicts' => $imported,
                        'replace_ids' => [],
                    ];
                }
                $replaceIds[] = $row['id'];
            }

            return [
                'status' => self::STATUS_REPLACE_IMPORT,
                'conflicts' => $imported,
                'replace_ids' => $replaceIds,
            ];
        }

        foreach ($planned as $plannedRange) {
            if ((int)$plannedRange['user_id'] !== $userId || (int)$plannedRange['offer_id'] !== $offerId) {
                continue;
            }
            if ($this->overlaps($plannedRange['date_start'], $plannedRange['date_end'], $excelStart, $excelEnd)) {
                return [
                    'status' => self::STATUS_SKIP_INTRA_EXCEL,
                    'conflicts' => [[
                        'id' => null,
                        'user_id' => $userId,
                        'offer_id' => $offerId,
                        'date_start' => $plannedRange['date_start'],
                        'date_end' => $plannedRange['date_end'],
                        'comment' => '',
                        'provenance' => self::PROVENANCE_IMPORT,
                    ]],
                    'replace_ids' => [],
                ];
            }
        }

        return $empty;
    }

    /**
     * @param array<int, array<string, mixed>> $protected
     */
    private function protectedStatus(array $protected): string
    {
        $hasAuto = false;
        $hasManual = false;
        foreach ($protected as $row) {
            if ($row['provenance'] === self::PROVENANCE_AUTO_TAD) {
                $hasAuto = true;
            } elseif ($row['provenance'] === self::PROVENANCE_MANUAL) {
                $hasManual = true;
            }
        }
        if ($hasAuto) {
            return self::STATUS_SKIP_AUTO_TAD;
        }
        if ($hasManual) {
            return self::STATUS_SKIP_MANUAL;
        }

        return self::STATUS_SKIP_PLANNING;
    }

    /**
     * @param array<int, array<string, mixed>> $groupedRanges
     * @param array<int, int> $excludeIds
     * @return array<int, array<string, mixed>>
     */
    private function loadExisting(array $groupedRanges, RangesTable $rangesTable, array $excludeIds): array
    {
        $userIds = [];
        $minStart = null;
        $maxEnd = null;

        foreach ($groupedRanges as $range) {
            if (!empty($range['user_id'])) {
                $userIds[(int)$range['user_id']] = true;
            }
            if (empty($range['date_start']) || empty($range['date_end'])) {
                continue;
            }
            $start = $this->normalizeTime($range['date_start']);
            $end = $this->normalizeTime($range['date_end']);
            if ($minStart === null || $start->getTimestamp() < $minStart->getTimestamp()) {
                $minStart = $start;
            }
            if ($maxEnd === null || $end->getTimestamp() > $maxEnd->getTimestamp()) {
                $maxEnd = $end;
            }
        }

        if ($userIds === [] || $minStart === null || $maxEnd === null) {
            return [];
        }

        $conditions = [
            'user_id IN' => array_keys($userIds),
            'date_start <' => $maxEnd,
            'date_end >' => $minStart,
        ];
        $excludeIds = array_values(array_unique(array_map('intval', $excludeIds)));
        if ($excludeIds !== []) {
            $conditions['id NOT IN'] = $excludeIds;
        }

        $rows = $rangesTable->find()
            ->select(['id', 'user_id', 'offer_id', 'date_start', 'date_end', 'comment', 'source'])
            ->where($conditions)
            ->disableHydration()
            ->all()
            ->toList();

        if ($excludeIds === []) {
            return $rows;
        }

        $excluded = array_fill_keys($excludeIds, true);

        return array_values(array_filter(
            $rows,
            static fn(array $row): bool => !isset($excluded[(int)$row['id']]),
        ));
    }

    public function normalizeTime(mixed $value): FrozenTime
    {
        if ($value instanceof FrozenTime) {
            return FrozenTime::parse($value->format('Y-m-d H:i:s'));
        }
        if ($value instanceof DateTimeInterface) {
            return FrozenTime::parse($value->format('Y-m-d H:i:s'));
        }

        return FrozenTime::parse(trim((string)$value));
    }

    private function overlaps(FrozenTime $startA, FrozenTime $endA, FrozenTime $startB, FrozenTime $endB): bool
    {
        return $startA->getTimestamp() < $endB->getTimestamp()
            && $endA->getTimestamp() > $startB->getTimestamp();
    }

    private function sameBounds(FrozenTime $startA, FrozenTime $endA, FrozenTime $startB, FrozenTime $endB): bool
    {
        return $startA->format('Y-m-d H:i:s') === $startB->format('Y-m-d H:i:s')
            && $endA->format('Y-m-d H:i:s') === $endB->format('Y-m-d H:i:s');
    }

    private function fullyCovered(FrozenTime $existingStart, FrozenTime $existingEnd, FrozenTime $excelStart, FrozenTime $excelEnd): bool
    {
        return $existingStart->getTimestamp() >= $excelStart->getTimestamp()
            && $existingEnd->getTimestamp() <= $excelEnd->getTimestamp();
    }
}
