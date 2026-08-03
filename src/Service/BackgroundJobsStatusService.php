<?php
declare(strict_types=1);

namespace App\Service;

use Cake\I18n\FrozenTime;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Agrège Optuna / prévisions / plannings pour la console Jobs.
 */
final class BackgroundJobsStatusService
{
    use LocatorAwareTrait;

    public const HISTORY_HOURS = 24;
    public const HISTORY_LIMIT = 50;

    private const ACTIVE_STATUSES = ['queued', 'running'];

    private const OPTUNA_HISTORY_STATUSES = ['completed', 'failed'];

    private const FORECAST_HISTORY_STATUSES = ['completed', 'failed'];

    private const PLANNING_HISTORY_STATUSES = [
        'finished',
        'finished_with_errors',
        'error',
        'infeasible',
    ];

    /**
     * @return array{
     *   success: bool,
     *   active_count: int,
     *   by_type: array{optuna: int, forecast: int, planning: int},
     *   items: list<array<string, mixed>>
     * }
     */
    public function getSnapshot(): array
    {
        $cutoff = FrozenTime::now()->subHours(self::HISTORY_HOURS);

        $optunaActive = $this->fetchOptuna(true, $cutoff);
        $forecastActive = $this->fetchForecast(true, $cutoff);
        $planningActive = $this->fetchPlanning(true, $cutoff);

        $optunaHistory = $this->fetchOptuna(false, $cutoff);
        $forecastHistory = $this->fetchForecast(false, $cutoff);
        $planningHistory = $this->fetchPlanning(false, $cutoff);

        $active = array_merge($optunaActive, $forecastActive, $planningActive);
        usort($active, [$this, 'compareActive']);

        $history = array_merge($optunaHistory, $forecastHistory, $planningHistory);
        usort($history, [$this, 'compareHistory']);
        if (count($history) > self::HISTORY_LIMIT) {
            $history = array_slice($history, 0, self::HISTORY_LIMIT);
        }

        $items = array_merge($active, $history);

        $byType = ['optuna' => 0, 'forecast' => 0, 'planning' => 0];
        foreach ($active as $item) {
            $type = (string)$item['type'];
            if (isset($byType[$type])) {
                $byType[$type]++;
            }
        }

        return [
            'success' => true,
            'active_count' => count($active),
            'by_type' => $byType,
            'items' => $items,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchOptuna(bool $active, FrozenTime $cutoff): array
    {
        $query = $this->fetchTable('ProphetTuningJobs')->find()
            ->select([
                'ProphetTuningJobs.id',
                'ProphetTuningJobs.offer_id',
                'ProphetTuningJobs.status',
                'ProphetTuningJobs.progress_trials_done',
                'ProphetTuningJobs.progress_trials_total',
                'ProphetTuningJobs.best_mae_so_far',
                'ProphetTuningJobs.error_message',
                'ProphetTuningJobs.started_at',
                'ProphetTuningJobs.finished_at',
            ])
            ->contain([
                'Offers' => ['fields' => ['id', 'name']],
            ]);

        if ($active) {
            $query->where(['ProphetTuningJobs.status IN' => self::ACTIVE_STATUSES]);
        } else {
            $query->where([
                'ProphetTuningJobs.status IN' => self::OPTUNA_HISTORY_STATUSES,
                'ProphetTuningJobs.finished_at >=' => $cutoff,
            ]);
        }

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
                'url' => sprintf('/offers/edit/%d#prophet-tuning-section', (int)$row->offer_id),
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchForecast(bool $active, FrozenTime $cutoff): array
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
            ->contain([]);

        if ($active) {
            $query->where(['status IN' => self::ACTIVE_STATUSES]);
        } else {
            $query->where([
                'status IN' => self::FORECAST_HISTORY_STATUSES,
                'finished_at >=' => $cutoff,
            ]);
        }

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
                'url' => sprintf('/forecast-scenarios/view/%d', (int)$row->id),
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchPlanning(bool $active, FrozenTime $cutoff): array
    {
        $query = $this->fetchTable('PlanningGenerationJobs')->find()
            ->select([
                'id',
                'status',
                'start_date',
                'end_date',
                'total_days',
                'processed_days',
                'current_day',
                'current_step',
                'error_message',
                'started_at',
                'finished_at',
            ])
            ->contain([]);

        if ($active) {
            $query->where(['status IN' => self::ACTIVE_STATUSES]);
        } else {
            $query->where([
                'status IN' => self::PLANNING_HISTORY_STATUSES,
                'finished_at >=' => $cutoff,
            ]);
        }

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
                'url' => sprintf('/planning-generation-jobs/view/%d', (int)$row->id),
            ];
        }

        return $out;
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

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    private function compareHistory(array $a, array $b): int
    {
        $fa = (string)($a['finished_at'] ?? '');
        $fb = (string)($b['finished_at'] ?? '');
        $cmp = $fb <=> $fa; // DESC
        if ($cmp !== 0) {
            return $cmp;
        }

        return ((int)$b['id']) <=> ((int)$a['id']);
    }

    private function fmtDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string)$value;
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
}
