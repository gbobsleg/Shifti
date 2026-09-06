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
    public const STATUS_SKIP_IDENTICAL = 'skip_identical';
    public const STATUS_SKIP_GROOMRH_PARTIAL = 'skip_groomrh_partial';
    public const STATUS_SKIP_INTRA_EXCEL = 'skip_intra_excel';
    public const STATUS_REPLACE_GROOMRH = 'replace_groomrh';

    public const PROVENANCE_AUTO_TAD = 'auto_tad';
    public const PROVENANCE_GROOMRH = 'groomrh';
    public const PROVENANCE_MANUAL = 'manual';

    public const AUTO_TAD_PREFIX = '[AUTO-TAD]';
    public const GROOMRH_SUFFIX = ' - GroomRH';

    /**
     * @param array<int, array<string, mixed>> $groupedRanges
     * @return array<int, array{status: string, conflicts: array, replace_ids: int[]}>
     */
    public function classify(array $groupedRanges, RangesTable $rangesTable): array
    {
        if ($groupedRanges === []) {
            return [];
        }

        $existing = $this->loadExisting($groupedRanges, $rangesTable);
        $planned = [];
        $decisions = [];

        foreach ($groupedRanges as $index => $range) {
            $decision = $this->classifyOne($range, $existing, $planned);
            $decisions[$index] = $decision;

            if (in_array($decision['status'], [self::STATUS_NEW, self::STATUS_REPLACE_GROOMRH], true)) {
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
            self::STATUS_REPLACE_GROOMRH => 'Remplace',
            self::STATUS_SKIP_AUTO_TAD => 'Ignoré (TAD fixe)',
            self::STATUS_SKIP_MANUAL => 'Ignoré (saisie existante)',
            self::STATUS_SKIP_IDENTICAL => 'Ignoré (déjà présent)',
            self::STATUS_SKIP_GROOMRH_PARTIAL => 'Ignoré (import précédent plus large)',
            self::STATUS_SKIP_INTRA_EXCEL => 'Ignoré (doublon fichier)',
            default => 'Ignoré',
        };
    }

    public static function statusGroup(string $status): string
    {
        if ($status === self::STATUS_NEW) {
            return 'new';
        }
        if ($status === self::STATUS_REPLACE_GROOMRH) {
            return 'replace';
        }

        return 'skip';
    }

    public static function provenanceLabel(string $provenance): string
    {
        return match ($provenance) {
            self::PROVENANCE_AUTO_TAD => 'TAD fixe',
            self::PROVENANCE_GROOMRH => 'import GroomRH',
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
            $overlaps[] = [
                'id' => (int)$row['id'],
                'user_id' => (int)$row['user_id'],
                'offer_id' => (int)$row['offer_id'],
                'date_start' => $existingStart,
                'date_end' => $existingEnd,
                'comment' => (string)($row['comment'] ?? ''),
                'provenance' => $this->provenance((string)($row['comment'] ?? '')),
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
        $groomrh = [];
        foreach ($overlaps as $overlap) {
            if ($overlap['provenance'] === self::PROVENANCE_GROOMRH) {
                $groomrh[] = $overlap;
            } else {
                $protected[] = $overlap;
            }
        }

        if ($protected !== []) {
            $hasAuto = false;
            foreach ($protected as $row) {
                if ($row['provenance'] === self::PROVENANCE_AUTO_TAD) {
                    $hasAuto = true;
                    break;
                }
            }

            return [
                'status' => $hasAuto ? self::STATUS_SKIP_AUTO_TAD : self::STATUS_SKIP_MANUAL,
                'conflicts' => $protected,
                'replace_ids' => [],
            ];
        }

        if ($groomrh !== []) {
            $replaceIds = [];
            foreach ($groomrh as $row) {
                if (!$this->fullyCovered($row['date_start'], $row['date_end'], $excelStart, $excelEnd)) {
                    return [
                        'status' => self::STATUS_SKIP_GROOMRH_PARTIAL,
                        'conflicts' => $groomrh,
                        'replace_ids' => [],
                    ];
                }
                $replaceIds[] = $row['id'];
            }

            return [
                'status' => self::STATUS_REPLACE_GROOMRH,
                'conflicts' => $groomrh,
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
                        'provenance' => self::PROVENANCE_GROOMRH,
                    ]],
                    'replace_ids' => [],
                ];
            }
        }

        return $empty;
    }

    /**
     * @param array<int, array<string, mixed>> $groupedRanges
     * @return array<int, array<string, mixed>>
     */
    private function loadExisting(array $groupedRanges, RangesTable $rangesTable): array
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

        return $rangesTable->find()
            ->select(['id', 'user_id', 'offer_id', 'date_start', 'date_end', 'comment'])
            ->where([
                'user_id IN' => array_keys($userIds),
                'date_start <' => $maxEnd->format('Y-m-d H:i:s'),
                'date_end >' => $minStart->format('Y-m-d H:i:s'),
            ])
            ->disableHydration()
            ->all()
            ->toList();
    }

    public function provenance(string $comment): string
    {
        if (str_starts_with($comment, self::AUTO_TAD_PREFIX)) {
            return self::PROVENANCE_AUTO_TAD;
        }
        if (str_ends_with($comment, self::GROOMRH_SUFFIX)) {
            return self::PROVENANCE_GROOMRH;
        }

        return self::PROVENANCE_MANUAL;
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
