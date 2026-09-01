<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Datasource\ConnectionManager;
use Cake\I18n\FrozenTime;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Routing\Router;

/**
 * Agrège Optuna / prévisions / plannings pour la console Jobs.
 */
final class BackgroundJobsStatusService
{
    use LocatorAwareTrait;

    /** Aperçu badge / compat (heures). */
    public const HISTORY_HOURS = 24;

    /** Fenêtre historique page Jobs. */
    public const HISTORY_DAYS = 30;

    public const HISTORY_PAGE_SIZE = 25;

    public const BADGE_RECENT_LIMIT = 8;

    private const ACTIVE_STATUSES = ['queued', 'running'];

    private const OPTUNA_HISTORY_STATUSES = ['completed', 'failed', 'cancelled'];

    private const FORECAST_HISTORY_STATUSES = ['completed', 'failed'];

    private const PLANNING_HISTORY_STATUSES = [
        'finished',
        'finished_with_errors',
        'error',
        'infeasible',
    ];

    /**
     * Snapshot polling / badge : actifs + aperçu récents 24 h (max 8).
     *
     * @return array{
     *   success: bool,
     *   active_count: int,
     *   by_type: array{optuna: int, forecast: int, planning: int},
     *   items: list<array<string, mixed>>,
     *   recent: list<array<string, mixed>>
     * }
     */
    public function getActiveSnapshot(): array
    {
        $active = $this->fetchAllActive();
        usort($active, [$this, 'compareActive']);

        $byType = ['optuna' => 0, 'forecast' => 0, 'planning' => 0];
        foreach ($active as $item) {
            $type = (string)$item['type'];
            if (isset($byType[$type])) {
                $byType[$type]++;
            }
        }

        $recent = $this->fetchRecentPreview(self::BADGE_RECENT_LIMIT);

        return [
            'success' => true,
            'active_count' => count($active),
            'by_type' => $byType,
            'items' => $active,
            'recent' => $recent,
        ];
    }

    /**
     * @deprecated Utiliser getActiveSnapshot() — conservé pour compat.
     * @return array<string, mixed>
     */
    public function getSnapshot(): array
    {
        $snap = $this->getActiveSnapshot();
        // Ancien format : items = actifs + récents (badge / anciens JS)
        $snap['items'] = array_slice(
            array_merge($snap['items'], $snap['recent']),
            0,
            self::BADGE_RECENT_LIMIT
        );

        return $snap;
    }

    /**
     * Historique paginé (UNION ALL SQL, jamais fetch-all PHP).
     *
     * @param array{type?: string, status?: string} $filters
     * @return array{
     *   items: list<array<string, mixed>>,
     *   page: int,
     *   limit: int,
     *   total: int,
     *   page_count: int,
     *   filters: array{type: string, status: string},
     *   history_days: int
     * }
     */
    public function getHistoryPage(array $filters = [], int $page = 1, int $limit = self::HISTORY_PAGE_SIZE): array
    {
        $type = $this->normalizeTypeFilter($filters['type'] ?? '');
        $status = $this->normalizeStatusFilter($filters['status'] ?? '');
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $offset = ($page - 1) * $limit;

        $cutoff = FrozenTime::now()->subDays(self::HISTORY_DAYS)->format('Y-m-d H:i:s');
        $union = $this->buildHistoryUnionSql($type, $status, $cutoff);
        if ($union === null) {
            return [
                'items' => [],
                'page' => $page,
                'limit' => $limit,
                'total' => 0,
                'page_count' => 1,
                'filters' => ['type' => $type, 'status' => $status],
                'history_days' => self::HISTORY_DAYS,
            ];
        }

        $conn = ConnectionManager::get('default');

        $countRow = $conn->execute(
            'SELECT COUNT(*) AS cnt FROM (' . $union . ') AS bj_u'
        )->fetch('assoc');
        $total = (int)($countRow['cnt'] ?? 0);
        $pageCount = max(1, (int)ceil($total / $limit));
        if ($page > $pageCount) {
            $page = $pageCount;
            $offset = ($page - 1) * $limit;
        }

        $sql = 'SELECT * FROM (' . $union . ') AS bj_u'
            . ' ORDER BY bj_u.finished_at DESC, bj_u.job_id DESC'
            . ' LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;

        $rows = $conn->execute($sql)->fetchAll('assoc');
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->mapUnionRow($row);
        }

        return [
            'items' => $items,
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'page_count' => $pageCount,
            'filters' => ['type' => $type, 'status' => $status],
            'history_days' => self::HISTORY_DAYS,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAllActive(): array
    {
        return array_merge(
            $this->fetchOptunaActive(),
            $this->fetchForecastActive(),
            $this->fetchPlanningActive()
        );
    }

    /**
     * Aperçu récents pour badge (24 h, LIMIT SQL).
     *
     * @return list<array<string, mixed>>
     */
    private function fetchRecentPreview(int $limit): array
    {
        $cutoff = FrozenTime::now()->subHours(self::HISTORY_HOURS)->format('Y-m-d H:i:s');
        $union = $this->buildHistoryUnionSql('', '', $cutoff);
        if ($union === null) {
            return [];
        }

        $conn = ConnectionManager::get('default');
        $sql = 'SELECT * FROM (' . $union . ') AS bj_u'
            . ' ORDER BY bj_u.finished_at DESC, bj_u.job_id DESC'
            . ' LIMIT ' . (int)$limit;

        $rows = $conn->execute($sql)->fetchAll('assoc');
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->mapUnionRow($row);
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchOptunaActive(): array
    {
        $query = $this->fetchTable('ProphetTuningJobs')->find()
            ->select([
                'ProphetTuningJobs.id',
                'ProphetTuningJobs.offer_id',
                'ProphetTuningJobs.status',
                'ProphetTuningJobs.progress_trials_done',
                'ProphetTuningJobs.progress_trials_total',
                'ProphetTuningJobs.error_message',
                'ProphetTuningJobs.started_at',
                'ProphetTuningJobs.finished_at',
            ])
            ->contain(['Offers' => ['fields' => ['id', 'name']]])
            ->where(['ProphetTuningJobs.status IN' => self::ACTIVE_STATUSES]);

        $out = [];
        foreach ($query->all() as $row) {
            $offerName = $row->offer->name ?? null;
            $done = (int)($row->progress_trials_done ?? 0);
            $total = (int)($row->progress_trials_total ?? 0);
            $out[] = [
                'type' => 'optuna',
                'id' => (int)$row->id,
                'status' => (string)$row->status,
                'label' => $offerName !== null && $offerName !== ''
                    ? (string)$offerName
                    : sprintf('Offre #%d', (int)$row->offer_id),
                'progress' => sprintf('%d/%d trials', $done, $total),
                'started_at' => $this->fmtDateTime($row->started_at),
                'finished_at' => $this->fmtDateTime($row->finished_at),
                'error_message' => $this->truncateError($row->error_message),
                'url' => $this->jobUrl('optuna', (int)$row->id, (int)$row->offer_id),
                'can_cancel' => true,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchForecastActive(): array
    {
        $query = $this->fetchTable('ForecastScenarios')->find()
            ->select([
                'id',
                'name',
                'status',
                'progress_offers_done',
                'progress_offers_total',
                'progress_days_done',
                'progress_days_total',
                'error_message',
                'started_at',
                'finished_at',
            ])
            ->where(['status IN' => self::ACTIVE_STATUSES])
            ->contain([]);

        $out = [];
        foreach ($query->all() as $row) {
            $offersDone = (int)($row->progress_offers_done ?? 0);
            $offersTotal = (int)($row->progress_offers_total ?? 0);
            $daysDone = (int)($row->progress_days_done ?? 0);
            $daysTotal = (int)($row->progress_days_total ?? 0);
            $progress = $offersTotal > 0
                ? sprintf('%d/%d offres', $offersDone, $offersTotal)
                : sprintf('%d/%d jours', $daysDone, $daysTotal);

            $out[] = [
                'type' => 'forecast',
                'id' => (int)$row->id,
                'status' => (string)$row->status,
                'label' => (string)($row->name !== null && $row->name !== ''
                    ? $row->name
                    : sprintf('Scénario #%d', (int)$row->id)),
                'progress' => $progress,
                'started_at' => $this->fmtDateTime($row->started_at),
                'finished_at' => $this->fmtDateTime($row->finished_at),
                'error_message' => $this->truncateError($row->error_message),
                'url' => $this->jobUrl('forecast', (int)$row->id, (int)$row->id),
                'can_cancel' => false,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchPlanningActive(): array
    {
        $query = $this->fetchTable('PlanningGenerationJobs')->find()
            ->select([
                'id',
                'status',
                'start_date',
                'end_date',
                'total_days',
                'processed_days',
                'current_step',
                'error_message',
                'started_at',
                'finished_at',
            ])
            ->where(['status IN' => self::ACTIVE_STATUSES])
            ->contain([]);

        $out = [];
        foreach ($query->all() as $row) {
            $start = $this->fmtDate($row->start_date);
            $end = $this->fmtDate($row->end_date);
            $label = ($start && $end)
                ? sprintf('Planning %s → %s', $start, $end)
                : sprintf('Planning #%d', (int)$row->id);

            $processed = (int)($row->processed_days ?? 0);
            $total = (int)($row->total_days ?? 0);
            $progress = sprintf('%d/%d jours', $processed, $total);
            if (!empty($row->current_step)) {
                $progress .= ' · ' . (string)$row->current_step;
            }

            $out[] = [
                'type' => 'planning',
                'id' => (int)$row->id,
                'status' => (string)$row->status,
                'label' => $label,
                'progress' => $progress,
                'started_at' => $this->fmtDateTime($row->started_at),
                'finished_at' => $this->fmtDateTime($row->finished_at),
                'error_message' => $this->truncateError($row->error_message),
                'url' => $this->jobUrl('planning', (int)$row->id, (int)$row->id),
                'can_cancel' => false,
            ];
        }

        return $out;
    }

    /**
     * Construit le SQL UNION ALL. Null si aucune branche.
     * $cutoffUtc embarqué (datetime contrôlé) pour éviter :cutoff répété (PDO MySQL).
     * Textes forcés en utf8mb4_unicode_ci (évite HY000 1271 mix de collations).
     */
    private function buildHistoryUnionSql(string $typeFilter, string $statusFilter, string $cutoffUtc): ?string
    {
        $cutoffLit = "'" . str_replace(["\\", "'"], ["\\\\", "''"], $cutoffUtc) . "'";
        $branches = [];
        $t = fn (string $expr): string => $this->sqlUtf8Text($expr);

        if ($typeFilter === '' || $typeFilter === 'optuna') {
            $st = $this->statusesForType('optuna', $statusFilter);
            if ($st !== null) {
                $in = $this->sqlInList($st);
                $jobType = $t("'optuna'");
                $status = $t('j.status');
                $label = $t("COALESCE(NULLIF(o.name, ''), CONCAT('Offre #', j.offer_id))");
                $progress = $t("CONCAT(j.progress_trials_done, '/', j.progress_trials_total, ' trials')");
                $error = $t('LEFT(j.error_message, 200)');
                $branches[] = <<<SQL
SELECT
    {$jobType} AS job_type,
    j.id AS job_id,
    {$status} AS status,
    {$label} AS label,
    {$progress} AS progress,
    j.started_at AS started_at,
    j.finished_at AS finished_at,
    {$error} AS error_message,
    j.offer_id AS ref_id
FROM prophet_tuning_jobs j
LEFT JOIN offers o ON o.id = j.offer_id
WHERE j.status IN ({$in})
  AND j.finished_at IS NOT NULL
  AND j.finished_at >= {$cutoffLit}
SQL;
            }
        }

        if ($typeFilter === '' || $typeFilter === 'forecast') {
            $st = $this->statusesForType('forecast', $statusFilter);
            if ($st !== null) {
                $in = $this->sqlInList($st);
                $jobType = $t("'forecast'");
                $status = $t('f.status');
                $label = $t("COALESCE(NULLIF(f.name, ''), CONCAT('Scénario #', f.id))");
                $progress = $t(
                    "CASE
        WHEN f.progress_offers_total > 0
            THEN CONCAT(f.progress_offers_done, '/', f.progress_offers_total, ' offres')
        ELSE CONCAT(f.progress_days_done, '/', f.progress_days_total, ' jours')
    END"
                );
                $error = $t('LEFT(f.error_message, 200)');
                $branches[] = <<<SQL
SELECT
    {$jobType} AS job_type,
    f.id AS job_id,
    {$status} AS status,
    {$label} AS label,
    {$progress} AS progress,
    f.started_at AS started_at,
    f.finished_at AS finished_at,
    {$error} AS error_message,
    f.id AS ref_id
FROM forecast_scenarios f
WHERE f.status IN ({$in})
  AND f.finished_at IS NOT NULL
  AND f.finished_at >= {$cutoffLit}
SQL;
            }
        }

        if ($typeFilter === '' || $typeFilter === 'planning') {
            $st = $this->statusesForType('planning', $statusFilter);
            if ($st !== null) {
                $in = $this->sqlInList($st);
                $jobType = $t("'planning'");
                $status = $t('p.status');
                $label = $t(
                    "CASE
        WHEN p.start_date IS NOT NULL AND p.end_date IS NOT NULL
            THEN CONCAT('Planning ', p.start_date, ' → ', p.end_date)
        ELSE CONCAT('Planning #', p.id)
    END"
                );
                $progress = $t(
                    "CONCAT(
        p.processed_days, '/', p.total_days, ' jours',
        CASE WHEN p.current_step IS NOT NULL AND p.current_step <> ''
            THEN CONCAT(' · ', p.current_step) ELSE '' END
    )"
                );
                $error = $t('LEFT(p.error_message, 200)');
                $branches[] = <<<SQL
SELECT
    {$jobType} AS job_type,
    p.id AS job_id,
    {$status} AS status,
    {$label} AS label,
    {$progress} AS progress,
    p.started_at AS started_at,
    p.finished_at AS finished_at,
    {$error} AS error_message,
    p.id AS ref_id
FROM planning_generation_jobs p
WHERE p.status IN ({$in})
  AND p.finished_at IS NOT NULL
  AND p.finished_at >= {$cutoffLit}
SQL;
            }
        }

        if ($branches === []) {
            return null;
        }

        return implode("\nUNION ALL\n", $branches);
    }

    /**
     * Force charset/collation communs pour les colonnes texte du UNION.
     */
    private function sqlUtf8Text(string $expr): string
    {
        return 'CONVERT((' . $expr . ') USING utf8mb4) COLLATE utf8mb4_unicode_ci';
    }

    /**
     * @return list<string>|null null = branche à exclure
     */
    private function statusesForType(string $type, string $statusFilter): ?array
    {
        $all = match ($type) {
            'optuna' => self::OPTUNA_HISTORY_STATUSES,
            'forecast' => self::FORECAST_HISTORY_STATUSES,
            'planning' => self::PLANNING_HISTORY_STATUSES,
            default => [],
        };

        if ($statusFilter === '') {
            return $all;
        }
        if (!in_array($statusFilter, $all, true)) {
            return null;
        }

        return [$statusFilter];
    }

    /**
     * @param list<string> $values
     */
    private function sqlInList(array $values): string
    {
        $quoted = [];
        foreach ($values as $v) {
            $quoted[] = "'" . str_replace("'", "''", $v) . "'";
        }

        return $quoted !== [] ? implode(', ', $quoted) : "''";
    }

    private function normalizeTypeFilter(mixed $raw): string
    {
        $v = strtolower(trim((string)$raw));

        return in_array($v, ['optuna', 'forecast', 'planning'], true) ? $v : '';
    }

    private function normalizeStatusFilter(mixed $raw): string
    {
        $v = trim((string)$raw);
        $allowed = array_values(array_unique(array_merge(
            self::OPTUNA_HISTORY_STATUSES,
            self::FORECAST_HISTORY_STATUSES,
            self::PLANNING_HISTORY_STATUSES
        )));

        return in_array($v, $allowed, true) ? $v : '';
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapUnionRow(array $row): array
    {
        $type = (string)($row['job_type'] ?? '');
        $id = (int)($row['job_id'] ?? 0);
        $refId = (int)($row['ref_id'] ?? $id);
        $err = $row['error_message'] ?? null;
        if (is_string($err) && mb_strlen($err) >= 200) {
            $err .= '…';
        }

        $url = $this->jobUrl($type, $id, $refId);

        return [
            'type' => $type,
            'id' => $id,
            'status' => (string)($row['status'] ?? ''),
            'label' => (string)($row['label'] ?? '—'),
            'progress' => (string)($row['progress'] ?? '—'),
            'started_at' => $this->fmtDateTime($row['started_at'] ?? null),
            'finished_at' => $this->fmtDateTime($row['finished_at'] ?? null),
            'error_message' => $this->truncateError($err),
            'url' => $url,
            'can_cancel' => false,
        ];
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    private function compareActive(array $a, array $b): int
    {
        $rank = static function (string $status): int {
            return $status === 'running' ? 0 : 1;
        };

        $cmp = $rank((string)$a['status']) <=> $rank((string)$b['status']);
        if ($cmp !== 0) {
            return $cmp;
        }

        return ((int)$a['id']) <=> ((int)$b['id']);
    }

    private function fmtDateTime(mixed $value): ?string
    {
        return ProphetOptunaConfig::formatDateTimeForUi($value);
    }

    private function fmtDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string)$value;
    }

    private function truncateError(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $text = (string)$value;
        if (mb_strlen($text) > 200) {
            return mb_substr($text, 0, 200) . '…';
        }

        return $text;
    }

    /**
     * URL applicative (respecte App.base, ex. /shifti en local).
     */
    private function jobUrl(string $type, int $id, int $refId): string
    {
        return match ($type) {
            'optuna' => Router::url(['controller' => 'Offers', 'action' => 'edit', $refId])
                . '#prophet-tuning-section',
            'forecast' => Router::url(['controller' => 'ForecastScenarios', 'action' => 'view', $id]),
            'planning' => Router::url(['controller' => 'PlanningGenerationJobs', 'action' => 'view', $id]),
            default => '#',
        };
    }
}
