<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Table\RangesTable;
use Cake\Core\Configure;
use Cake\I18n\FrozenTime;
use DateTimeInterface;

/**
 * Retire ou découpe les absences et télétravails saisis sur un mois avant un import.
 */
class ExcelRangeMonthPurge
{
    public const SCOPE_FILE = 'file';
    public const SCOPE_ALL = 'all';

    /**
     * @param array<int, int> $fileUserIds
     * @return array{delete_ids: array<int, int>, remnants: array<int, array<string, mixed>>, range_count: int, agent_count: int}
     */
    public function plan(
        RangesTable $rangesTable,
        int $year,
        int $month,
        bool $purgeAbsence,
        bool $purgeRemote,
        string $scope,
        array $fileUserIds,
    ): array {
        $empty = ['delete_ids' => [], 'remnants' => [], 'range_count' => 0, 'agent_count' => 0];
        $types = [];
        if ($purgeAbsence) {
            $types[] = 'absence';
        }
        if ($purgeRemote) {
            $types[] = 'remote_work';
        }
        if ($types === []) {
            return $empty;
        }

        $fileUserIds = array_values(array_unique(array_map('intval', $fileUserIds)));
        if ($scope !== self::SCOPE_ALL && $fileUserIds === []) {
            return $empty;
        }

        $offerIds = $rangesTable->Offers->find()
            ->select(['id'])
            ->where(['offer_type IN' => $types])
            ->all()
            ->extract('id')
            ->map(fn($id) => (int)$id)
            ->toList();
        if ($offerIds === []) {
            return $empty;
        }

        $bounds = $this->monthBounds($year, $month);
        $conditions = [
            'Ranges.offer_id IN' => $offerIds,
            'Ranges.source IN' => [RangeSource::MANUAL, RangeSource::IMPORT],
            'Ranges.date_start <' => $bounds['end'],
            'Ranges.date_end >' => $bounds['start'],
        ];
        if ($scope !== self::SCOPE_ALL) {
            $conditions['Ranges.user_id IN'] = $fileUserIds;
        }

        $rows = $rangesTable->find()
            ->select(['id', 'user_id', 'offer_id', 'date_start', 'date_end', 'comment', 'source'])
            ->where($conditions)
            ->all();

        $deleteIds = [];
        $remnants = [];
        $agents = [];
        foreach ($rows as $range) {
            $start = $this->asTime($range->date_start);
            $end = $this->asTime($range->date_end);
            $pieces = $this->remnantBounds($start, $end, $bounds['start'], $bounds['end']);
            $deleteIds[] = (int)$range->id;
            $agents[(int)$range->user_id] = true;
            foreach ($pieces as $piece) {
                $remnants[] = [
                    'user_id' => (int)$range->user_id,
                    'offer_id' => (int)$range->offer_id,
                    'date_start' => $piece['date_start'],
                    'date_end' => $piece['date_end'],
                    'comment' => $range->comment,
                    'source' => (string)$range->source,
                ];
            }
        }

        return [
            'delete_ids' => $deleteIds,
            'remnants' => $remnants,
            'range_count' => count($deleteIds),
            'agent_count' => count($agents),
        ];
    }

    /**
     * Supprime les ids puis insère les reliquats. À appeler dans la transaction de l'import.
     *
     * @param array{delete_ids: array<int, int>, remnants: array<int, array<string, mixed>>, range_count: int, agent_count: int} $plan
     */
    public function apply(RangesTable $rangesTable, array $plan): void
    {
        if ($plan['delete_ids'] !== []) {
            $rangesTable->deleteAll(['id IN' => $plan['delete_ids']]);
        }

        $entities = [];
        foreach ($plan['remnants'] as $data) {
            $source = (string)($data['source'] ?? RangeSource::MANUAL);
            unset($data['source']);
            $entity = $rangesTable->newEntity($data);
            $entity->set('source', $source);
            $entities[] = $entity;
        }
        if ($entities !== []) {
            $rangesTable->saveManyOrFail($entities);
        }
    }

    /**
     * Premier instant du mois et du mois suivant, dans le fuseau applicatif.
     *
     * @return array{start: \Cake\I18n\FrozenTime, end: \Cake\I18n\FrozenTime}
     */
    public function monthBounds(int $year, int $month): array
    {
        $timezone = Configure::read('App.defaultTimezone') ?: 'UTC';
        $start = FrozenTime::create($year, $month, 1, 0, 0, 0, 0, $timezone);

        return [
            'start' => $start,
            'end' => $start->addMonths(1),
        ];
    }

    /**
     * Bornes hors du mois. Tableau vide si la plage est entièrement dedans.
     * Le mois est [start, end) : une fin égale à start est hors mois.
     *
     * @return list<array{date_start: \Cake\I18n\FrozenTime, date_end: \Cake\I18n\FrozenTime}>
     */
    public function remnantBounds(
        FrozenTime $start,
        FrozenTime $end,
        FrozenTime $monthStart,
        FrozenTime $monthEnd,
    ): array {
        $pieces = [];
        if ($start->getTimestamp() < $monthStart->getTimestamp()) {
            $pieces[] = [
                'date_start' => $start,
                'date_end' => $monthStart,
            ];
        }
        if ($end->getTimestamp() > $monthEnd->getTimestamp()) {
            $pieces[] = [
                'date_start' => $monthEnd,
                'date_end' => $end,
            ];
        }

        return $pieces;
    }

    /**
     * @param mixed $value Date issue de l'ORM ou chaîne SQL.
     */
    private function asTime(mixed $value): FrozenTime
    {
        if ($value instanceof FrozenTime) {
            return $value;
        }
        if ($value instanceof DateTimeInterface) {
            return FrozenTime::parse($value->format('Y-m-d H:i:s'));
        }

        return FrozenTime::parse((string)$value);
    }
}
