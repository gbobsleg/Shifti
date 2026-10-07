<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\PlanningDayHistory;
use App\Model\Table\PlanningDayHistoriesTable;
use App\Model\Table\RangesTable;
use Cake\I18n\FrozenTime;
use Cake\ORM\Locator\LocatorAwareTrait;
use DateTimeInterface;
use RuntimeException;

/**
 * Snapshots d'historique du planning publié (agent × jour).
 */
class PlanningDayHistoryService
{
    use LocatorAwareTrait;

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_PUBLISH = 'publish';
    public const SOURCE_GENERATION = 'generation';
    public const SOURCE_RESTORE = 'restore';
    public const SOURCE_FORM = 'form';
    public const SOURCE_IMPORT = 'import';
    public const SOURCE_SYNC = 'sync';
    public const SOURCE_BASELINE = 'baseline';

    private const MAX_VERSIONS_PER_DAY = 30;
    private const BATCH_SIZE = 500;

    private RangesTable $Ranges;
    private PlanningDayHistoriesTable $PlanningDayHistories;

    public function __construct(
        ?RangesTable $ranges = null,
        ?PlanningDayHistoriesTable $histories = null,
    ) {
        $locator = $this->getTableLocator();
        $this->Ranges = $ranges ?? $locator->get('Ranges');
        $this->PlanningDayHistories = $histories ?? $locator->get('PlanningDayHistories');
    }

    /**
     * Construit les segments du jour pour un agent (heures murales, DATE(date_start)).
     *
     * @return list<array{offer_id:int,color:?string,date_start:string,date_end:string,comment:?string,source:string}>
     */
    public function buildSnapshotForDay(int $userId, string $dayYmd): array
    {
        $dayYmd = $this->normalizeDay($dayYmd);

        $ranges = $this->Ranges->find()
            ->contain(['Offers'])
            ->where([
                'Ranges.user_id' => $userId,
                'DATE(Ranges.date_start)' => $dayYmd,
            ])
            ->orderBy(['Ranges.date_start' => 'ASC', 'Ranges.offer_id' => 'ASC'])
            ->all();

        $segments = [];
        foreach ($ranges as $range) {
            $segments[] = [
                'offer_id' => (int)$range->offer_id,
                'color' => $range->offer->color ?? null,
                'date_start' => $this->formatDateTime($range->date_start),
                'date_end' => $this->formatDateTime($range->date_end),
                'comment' => $range->comment !== null && $range->comment !== ''
                    ? (string)$range->comment
                    : null,
                'source' => RangeSource::isValid((string)$range->source)
                    ? (string)$range->source
                    : RangeSource::fromLegacyComment(
                        $range->comment !== null ? (string)$range->comment : null
                    ),
                'created_by_user_id' => $range->created_by_user_id !== null
                    ? (int)$range->created_by_user_id
                    : null,
            ];
        }

        return $segments;
    }

    /**
     * Enregistre un snapshot si le contenu a changé ; purge au-delà de 30 versions.
     *
     * @return \App\Model\Entity\PlanningDayHistory|null Entité créée, ou null si inchangé
     */
    public function maybeRecord(
        int $userId,
        string $day,
        string $source,
        ?int $actorUserId,
    ): ?PlanningDayHistory {
        $dayYmd = $this->normalizeDay($day);
        $snapshot = $this->buildSnapshotForDay($userId, $dayYmd);
        $contentHash = $this->hashSnapshot($snapshot);

        $latest = $this->PlanningDayHistories->find()
            ->select(['id', 'content_hash'])
            ->where([
                'user_id' => $userId,
                'day' => $dayYmd,
            ])
            ->orderBy(['created' => 'DESC', 'id' => 'DESC'])
            ->first();

        if ($latest !== null && (string)$latest->content_hash === $contentHash) {
            return null;
        }

        $entity = $this->PlanningDayHistories->newEntity([
            'user_id' => $userId,
            'day' => $dayYmd,
            'snapshot' => $snapshot,
            'content_hash' => $contentHash,
            'source' => $source,
            'actor_user_id' => $actorUserId,
        ]);

        if (!$this->PlanningDayHistories->save($entity)) {
            throw new RuntimeException(sprintf(
                'Impossible d\'enregistrer l\'historique planning (user_id=%d, day=%s, source=%s).',
                $userId,
                $dayYmd,
                $source,
            ));
        }

        $this->trimOldVersions($userId, $dayYmd);

        return $entity;
    }

    /**
     * Couples distincts user_id × jour de début correspondant aux conditions d'une suppression.
     *
     * @param array<string, mixed> $conditions
     * @return list<array{user_id:int, day:string}>
     */
    public function pairsForConditions(array $conditions): array
    {
        if ($conditions === []) {
            return [];
        }

        $rows = $this->Ranges->find()
            ->select([
                'user_id' => 'Ranges.user_id',
                'day' => 'DATE(Ranges.date_start)',
            ])
            ->where($conditions)
            ->groupBy(['Ranges.user_id', 'DATE(Ranges.date_start)'])
            ->disableHydration()
            ->all();

        $pairs = [];
        foreach ($rows as $row) {
            $day = $row['day'] ?? '';
            if ($day instanceof DateTimeInterface) {
                $day = $day->format('Y-m-d');
            }
            $pairs[] = [
                'user_id' => (int)($row['user_id'] ?? 0),
                'day' => (string)$day,
            ];
        }

        return $this->uniquePairs($pairs);
    }

    /**
     * Photographie l'état actuel des couples qui n'ont encore aucune version.
     *
     * @param list<array{user_id:int, day:string}> $pairs
     */
    public function captureBaseline(array $pairs): void
    {
        foreach (array_chunk($this->uniquePairs($pairs), self::BATCH_SIZE) as $chunk) {
            $this->recordChunk($chunk, self::SOURCE_BASELINE, null, true);
        }
    }

    /**
     * Enregistre une version après modification pour les couples réellement touchés.
     *
     * @param list<array{user_id:int, day:string}> $pairs
     */
    public function recordPairs(array $pairs, string $source, ?int $actorUserId): void
    {
        foreach (array_chunk($this->uniquePairs($pairs), self::BATCH_SIZE) as $chunk) {
            $this->recordChunk($chunk, $source, $actorUserId, false);
        }
    }

    /**
     * @return array{user_id:int, day:string}|null
     */
    public function pairFromDate(int $userId, mixed $dateStart): ?array
    {
        if ($userId <= 0) {
            return null;
        }
        $formatted = $this->formatDateTime($dateStart);
        if ($formatted === '') {
            return null;
        }

        return [
            'user_id' => $userId,
            'day' => substr($formatted, 0, 10),
        ];
    }

    /**
     * @param list<array{user_id:int, day:string}> $pairs
     * @return list<array{user_id:int, day:string}>
     */
    public function uniquePairs(array $pairs): array
    {
        $unique = [];
        foreach ($pairs as $pair) {
            $userId = (int)($pair['user_id'] ?? 0);
            $dayRaw = (string)($pair['day'] ?? '');
            if ($userId <= 0 || $dayRaw === '') {
                continue;
            }
            $day = $this->normalizeDay($dayRaw);
            $unique[$userId . '|' . $day] = [
                'user_id' => $userId,
                'day' => $day,
            ];
        }

        return array_values($unique);
    }

    /**
     * Restaure une version : delete jour + insert snapshot + record, en une seule transaction.
     */
    public function restore(int $historyId, int $actorUserId): void
    {
        $history = $this->PlanningDayHistories->get($historyId);
        $userId = (int)$history->user_id;
        $dayRaw = $history->day;
        if (is_object($dayRaw) && method_exists($dayRaw, 'format')) {
            $dayYmd = $dayRaw->format('Y-m-d');
        } else {
            // Fallback : force le format Y-m-d même si la date sort en FR (10/08/2026)
            $dayStr = str_replace('/', '-', (string)$dayRaw);
            $dayYmd = date('Y-m-d', strtotime($dayStr));
        }

        /** @var list<array<string, mixed>> $snapshot */
        $snapshot = is_array($history->snapshot) ? $history->snapshot : [];

        $connection = $this->Ranges->getConnection();

        $connection->transactional(function () use ($userId, $dayYmd, $snapshot, $actorUserId) {
            $this->Ranges->deleteAll([
                'user_id' => $userId,
                'DATE(date_start)' => $dayYmd,
            ]);

            foreach ($snapshot as $segment) {
                if (!isset($segment['offer_id'], $segment['date_start'], $segment['date_end'])) {
                    throw new RuntimeException(
                        'Segment de snapshot invalide (offer_id/date_start/date_end requis).'
                    );
                }

                $comment = isset($segment['comment']) && $segment['comment'] !== ''
                    ? (string)$segment['comment']
                    : null;
                $source = isset($segment['source']) && RangeSource::isValid((string)$segment['source'])
                    ? (string)$segment['source']
                    : RangeSource::fromLegacyComment($comment);
                $entity = $this->Ranges->newEntity([
                    'user_id' => $userId,
                    'offer_id' => (int)$segment['offer_id'],
                    'date_start' => $this->formatDateTime($segment['date_start']),
                    'date_end' => $this->formatDateTime($segment['date_end']),
                    'comment' => $comment,
                ]);
                $entity->set('source', $source);
                $createdBy = $segment['created_by_user_id'] ?? null;
                $entity->set('created_by_user_id', $createdBy !== null && $createdBy !== '' ? (int)$createdBy : null);
                $this->Ranges->saveOrFail($entity);
            }

            $this->maybeRecord($userId, $dayYmd, self::SOURCE_RESTORE, $actorUserId);
        });
    }

    /**
     * Hash déterministe : offer_id + date_start + date_end, tri date_start ASC puis offer_id ASC.
     *
     * @param list<array<string, mixed>> $snapshot
     */
    private function hashSnapshot(array $snapshot): string
    {
        $normalized = [];
        foreach ($snapshot as $segment) {
            $normalized[] = [
                'offer_id' => (int)($segment['offer_id'] ?? 0),
                'date_start' => $this->formatDateTime($segment['date_start'] ?? null),
                'date_end' => $this->formatDateTime($segment['date_end'] ?? null),
            ];
        }

        usort($normalized, static function (array $a, array $b): int {
            $byStart = strcmp($a['date_start'], $b['date_start']);
            if ($byStart !== 0) {
                return $byStart;
            }

            return $a['offer_id'] <=> $b['offer_id'];
        });

        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param list<array{user_id:int, day:string}> $pairs
     */
    private function recordChunk(array $pairs, string $source, ?int $actorUserId, bool $baseline): void
    {
        if ($pairs === []) {
            return;
        }

        $snapshots = $this->snapshotsForPairs($pairs);
        $latest = $this->latestHashes($pairs);
        $toInsert = [];
        $touched = [];

        foreach ($pairs as $pair) {
            $key = $pair['user_id'] . '|' . $pair['day'];
            $snapshot = $snapshots[$key] ?? [];
            $hasHistory = array_key_exists($key, $latest);
            if ($baseline) {
                if ($hasHistory) {
                    continue;
                }
            } elseif (!$hasHistory && $snapshot === []) {
                continue;
            } elseif ($hasHistory && $latest[$key] === $this->hashSnapshot($snapshot)) {
                continue;
            }

            $toInsert[] = [
                'user_id' => $pair['user_id'],
                'day' => $pair['day'],
                'snapshot' => $snapshot,
                'content_hash' => $this->hashSnapshot($snapshot),
                'source' => $source,
                'actor_user_id' => $actorUserId,
            ];
            $touched[] = $pair;
        }

        if ($toInsert === []) {
            return;
        }

        $entities = $this->PlanningDayHistories->newEntities($toInsert);
        if (!$this->PlanningDayHistories->saveMany($entities)) {
            throw new RuntimeException('Impossible d\'enregistrer un lot d\'historique planning.');
        }

        $this->trimChunk($touched);
    }

    /**
     * @param list<array{user_id:int, day:string}> $pairs
     * @return array<string, list<array<string, mixed>>>
     */
    private function snapshotsForPairs(array $pairs): array
    {
        $result = [];
        $wanted = [];
        $userIds = [];
        $min = null;
        $max = null;
        foreach ($pairs as $pair) {
            $key = $pair['user_id'] . '|' . $pair['day'];
            $result[$key] = [];
            $wanted[$key] = true;
            $userIds[$pair['user_id']] = $pair['user_id'];
            if ($min === null || $pair['day'] < $min) {
                $min = $pair['day'];
            }
            if ($max === null || $pair['day'] > $max) {
                $max = $pair['day'];
            }
        }
        if ($userIds === []) {
            return $result;
        }

        $rows = $this->Ranges->find()
            ->select([
                'Ranges.user_id',
                'Ranges.offer_id',
                'Ranges.date_start',
                'Ranges.date_end',
                'Ranges.comment',
                'Ranges.source',
                'Ranges.created_by_user_id',
                'offer_color' => 'Offers.color',
            ])
            ->leftJoinWith('Offers')
            ->where([
                'Ranges.user_id IN' => array_values($userIds),
                'Ranges.date_start >=' => $min . ' 00:00:00',
                'Ranges.date_start <=' => $max . ' 23:59:59',
            ])
            ->orderBy(['Ranges.date_start' => 'ASC', 'Ranges.offer_id' => 'ASC'])
            ->disableHydration()
            ->all();

        foreach ($rows as $row) {
            $start = $this->formatDateTime($row['date_start'] ?? null);
            $day = substr($start, 0, 10);
            $key = (int)($row['user_id'] ?? 0) . '|' . $day;
            if (!isset($wanted[$key])) {
                continue;
            }
            $comment = $row['comment'] ?? null;
            $comment = $comment !== null && $comment !== '' ? (string)$comment : null;
            $source = RangeSource::isValid((string)($row['source'] ?? ''))
                ? (string)$row['source']
                : RangeSource::fromLegacyComment($comment);
            $result[$key][] = [
                'offer_id' => (int)($row['offer_id'] ?? 0),
                'color' => $row['offer_color'] ?? null,
                'date_start' => $start,
                'date_end' => $this->formatDateTime($row['date_end'] ?? null),
                'comment' => $comment,
                'source' => $source,
                'created_by_user_id' => isset($row['created_by_user_id']) && $row['created_by_user_id'] !== null
                    ? (int)$row['created_by_user_id']
                    : null,
            ];
        }

        return $result;
    }

    /**
     * @param list<array{user_id:int, day:string}> $pairs
     * @return array<string, string>
     */
    private function latestHashes(array $pairs): array
    {
        $userIds = [];
        $days = [];
        $wanted = [];
        foreach ($pairs as $pair) {
            $userIds[$pair['user_id']] = $pair['user_id'];
            $days[$pair['day']] = $pair['day'];
            $wanted[$pair['user_id'] . '|' . $pair['day']] = true;
        }

        $rows = $this->PlanningDayHistories->find()
            ->select([
                'id',
                'user_id',
                'day_key' => 'DATE_FORMAT(PlanningDayHistories.day, "%Y-%m-%d")',
                'content_hash',
            ])
            ->where([
                'user_id IN' => array_values($userIds),
                'day IN' => array_values($days),
            ])
            ->orderBy(['id' => 'DESC'])
            ->disableHydration()
            ->all();

        $latest = [];
        foreach ($rows as $row) {
            $day = (string)($row['day_key'] ?? '');
            $key = (int)($row['user_id'] ?? 0) . '|' . $day;
            if (!isset($wanted[$key]) || isset($latest[$key])) {
                continue;
            }
            $latest[$key] = (string)($row['content_hash'] ?? '');
        }

        return $latest;
    }

    /**
     * @param list<array{user_id:int, day:string}> $pairs
     */
    private function trimChunk(array $pairs): void
    {
        if ($pairs === []) {
            return;
        }

        $connection = $this->PlanningDayHistories->getConnection();
        $tuples = [];
        $params = [];
        foreach (array_values($pairs) as $index => $pair) {
            $tuples[] = '(:u' . $index . ', :d' . $index . ')';
            $params['u' . $index] = $pair['user_id'];
            $params['d' . $index] = $pair['day'];
        }

        $sql = 'DELETE FROM planning_day_histories WHERE id IN ('
            . 'SELECT id FROM ('
            . 'SELECT id, ROW_NUMBER() OVER (PARTITION BY user_id, day ORDER BY created DESC, id DESC) AS rn '
            . 'FROM planning_day_histories WHERE (user_id, day) IN (' . implode(', ', $tuples) . ')'
            . ') ranked WHERE rn > ' . self::MAX_VERSIONS_PER_DAY
            . ')';

        $connection->execute($sql, $params);
    }

    private function trimOldVersions(int $userId, string $dayYmd): void
    {
        $idsToKeep = $this->PlanningDayHistories->find()
            ->select(['id'])
            ->where([
                'user_id' => $userId,
                'day' => $dayYmd,
            ])
            ->orderBy(['created' => 'DESC', 'id' => 'DESC'])
            ->limit(self::MAX_VERSIONS_PER_DAY)
            ->all()
            ->extract('id')
            ->toList();

        if ($idsToKeep === []) {
            return;
        }

        $this->PlanningDayHistories->deleteAll([
            'user_id' => $userId,
            'day' => $dayYmd,
            'id NOT IN' => $idsToKeep,
        ]);
    }

    private function normalizeDay(string $day): string
    {
        $day = trim($day);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1) {
            return $day;
        }

        try {
            return (new FrozenTime($day))->format('Y-m-d');
        } catch (\Exception $e) {
            throw new RuntimeException(sprintf('Jour invalide: %s', $day), 0, $e);
        }
    }

    private function formatDateTime(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value === null || $value === '') {
            return '';
        }

        return (new FrozenTime((string)$value))->format('Y-m-d H:i:s');
    }
}
