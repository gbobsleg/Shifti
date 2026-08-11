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

    private const MAX_VERSIONS_PER_DAY = 30;

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
     * @return list<array{offer_id:int,color:?string,date_start:string,date_end:string,comment:?string}>
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
     * Enregistre l'historique pour chaque couple (userId × day) touché.
     *
     * @param list<int> $userIds
     * @param list<string> $days
     */
    public function recordAffectedUsers(
        array $userIds,
        array $days,
        string $source,
        ?int $actorUserId,
    ): void {
        $uniqueUserIds = [];
        foreach ($userIds as $userId) {
            $id = (int)$userId;
            if ($id > 0) {
                $uniqueUserIds[$id] = $id;
            }
        }

        $uniqueDays = [];
        foreach ($days as $day) {
            if ($day === null || $day === '') {
                continue;
            }
            $dayYmd = $this->normalizeDay((string)$day);
            $uniqueDays[$dayYmd] = $dayYmd;
        }

        foreach ($uniqueUserIds as $userId) {
            foreach ($uniqueDays as $dayYmd) {
                $this->maybeRecord($userId, $dayYmd, $source, $actorUserId);
            }
        }
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

                $entity = $this->Ranges->newEntity([
                    'user_id' => $userId,
                    'offer_id' => (int)$segment['offer_id'],
                    'date_start' => $this->formatDateTime($segment['date_start']),
                    'date_end' => $this->formatDateTime($segment['date_end']),
                    'comment' => isset($segment['comment']) && $segment['comment'] !== ''
                        ? (string)$segment['comment']
                        : null,
                ]);
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
