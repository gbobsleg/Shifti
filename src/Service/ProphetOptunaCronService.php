<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\ProphetTuningJob;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Planning cron Optuna + estimation de durée d'une vague.
 */
class ProphetOptunaCronService
{
    use LocatorAwareTrait;

    /**
     * True si on doit enfiler maintenant (jour + heure Europe/Paris, pas déjà fait aujourd'hui).
     *
     * @param array<string, mixed> $optuna
     */
    public function shouldEnqueueNow(array $optuna, ?DateTimeImmutable $now = null): bool
    {
        if (empty($optuna['cron_enabled'])) {
            return false;
        }

        $tz = new DateTimeZone(ProphetOptunaConfig::CRON_TIMEZONE);
        $now = $now?->setTimezone($tz) ?? new DateTimeImmutable('now', $tz);

        $weekdays = ProphetOptunaConfig::normalizeWeekdays($optuna['cron_weekdays'] ?? [7]);
        $dow = (int)$now->format('N'); // 1=Lun … 7=Dim
        if (!in_array($dow, $weekdays, true)) {
            return false;
        }

        $hour = (int)($optuna['cron_hour'] ?? 2);
        $minute = (int)($optuna['cron_minute'] ?? 0);
        if ((int)$now->format('G') !== $hour || (int)$now->format('i') !== $minute) {
            return false;
        }

        $today = $now->format('Y-m-d');
        $last = $optuna['last_cron_enqueue_date'] ?? null;
        if (is_string($last) && $last === $today) {
            return false;
        }

        return true;
    }

    /**
     * Enfile les offres éligibles. Retourne stats.
     *
     * @param array<string, mixed> $optuna
     * @return array{enqueued: int, skipped: int, messages: list<string>}
     */
    public function enqueueEligibleOffers(array $optuna): array
    {
        $Offers = $this->fetchTable('Offers');
        $Jobs = $this->fetchTable('ProphetTuningJobs');

        $periodDays = max(1, (int)$optuna['cron_period_days']);
        $cutoff = (new DateTime())->modify(sprintf('-%d days', $periodDays));

        $offers = $Offers->find()
            ->select(['id', 'name', 'prophet_tuning_enabled', 'prophet_tuning_last_run_at'])
            ->where(['prophet_tuning_enabled' => true])
            ->contain([])
            ->all();

        $enqueued = 0;
        $skipped = 0;
        $messages = [];

        foreach ($offers as $offer) {
            $offerId = (int)$offer->id;

            $activeForOffer = $Jobs->find()
                ->where([
                    'offer_id' => $offerId,
                    'status IN' => [
                        ProphetTuningJob::STATUS_QUEUED,
                        ProphetTuningJob::STATUS_RUNNING,
                    ],
                ])
                ->contain([])
                ->count();

            if ($activeForOffer > 0) {
                $messages[] = sprintf('Offre #%d déjà queued/running — skip.', $offerId);
                $skipped++;
                continue;
            }

            $lastRun = $offer->prophet_tuning_last_run_at;
            if ($lastRun !== null) {
                $lastRunDt = $lastRun instanceof \DateTimeInterface
                    ? DateTime::parse($lastRun->format('Y-m-d H:i:s'))
                    : new DateTime((string)$lastRun);
                if ($lastRunDt > $cutoff) {
                    $messages[] = sprintf(
                        'Offre #%d : dernier run trop récent (< %d j) — skip.',
                        $offerId,
                        $periodDays
                    );
                    $skipped++;
                    continue;
                }
            }

            $job = $Jobs->newEntity([
                'offer_id' => $offerId,
                'created_by' => null,
                'trigger_type' => ProphetTuningJob::TRIGGER_CRON,
                'status' => ProphetTuningJob::STATUS_QUEUED,
                'config_snapshot_json' => $optuna,
                'auto_applied' => false,
                'progress_trials_done' => 0,
                'progress_trials_total' => (int)$optuna['n_trials'],
            ]);

            if (!$Jobs->save($job)) {
                $messages[] = sprintf('Échec enqueue offre #%d.', $offerId);
                $skipped++;
                continue;
            }

            $messages[] = sprintf('Job #%d enqueue (offre #%d — %s).', $job->id, $offerId, $offer->name);
            $enqueued++;
        }

        return compact('enqueued', 'skipped', 'messages');
    }

    /**
     * Marque la date d'enqueue cron du jour (anti double-fire) dans optuna_settings_json.
     */
    public function markCronEnqueuedToday(): void
    {
        $WfmSettings = $this->fetchTable('WfmSettings');
        $wfm = $WfmSettings->find()->contain([])->first();
        if (!$wfm) {
            return;
        }

        $optuna = ProphetOptunaConfig::fromStorage($wfm->optuna_settings_json ?? null);
        $tz = new DateTimeZone(ProphetOptunaConfig::CRON_TIMEZONE);
        $optuna['last_cron_enqueue_date'] = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
        $wfm->optuna_settings_json = $optuna;
        $WfmSettings->save($wfm);
    }

    public function countTuningEnabledOffers(): int
    {
        return (int)$this->fetchTable('Offers')->find()
            ->where(['prophet_tuning_enabled' => true])
            ->contain([])
            ->count();
    }

    /**
     * Moyenne secondes / trial d'après les jobs completed récents.
     */
    public function averageSecondsPerTrial(): array
    {
        $Jobs = $this->fetchTable('ProphetTuningJobs');
        $rows = $Jobs->find()
            ->select(['started_at', 'finished_at', 'progress_trials_total'])
            ->where([
                'status' => ProphetTuningJob::STATUS_COMPLETED,
                'started_at IS NOT' => null,
                'finished_at IS NOT' => null,
                'progress_trials_total >' => 0,
            ])
            ->orderDesc('id')
            ->limit(20)
            ->contain([])
            ->all();

        $samples = [];
        foreach ($rows as $row) {
            $start = $row->started_at;
            $end = $row->finished_at;
            if (!$start instanceof \DateTimeInterface || !$end instanceof \DateTimeInterface) {
                continue;
            }
            $trials = (int)$row->progress_trials_total;
            if ($trials < 1) {
                continue;
            }
            $sec = $end->getTimestamp() - $start->getTimestamp();
            if ($sec < 1) {
                continue;
            }
            // Inclut baseline : on divise par trials (légère sur-estimation acceptable)
            $samples[] = $sec / $trials;
        }

        if ($samples === []) {
            return [
                'seconds_per_trial' => ProphetOptunaConfig::FALLBACK_SECONDS_PER_TRIAL,
                'source' => 'fallback',
                'sample_count' => 0,
            ];
        }

        return [
            'seconds_per_trial' => array_sum($samples) / count($samples),
            'source' => 'history',
            'sample_count' => count($samples),
        ];
    }

    /**
     * Estimation d'une vague cron complète.
     *
     * @param array<string, mixed> $optuna
     * @return array<string, mixed>
     */
    public function estimateCronWave(array $optuna, ?int $enabledOffers = null): array
    {
        $enabled = $enabledOffers ?? $this->countTuningEnabledOffers();
        $trials = max(1, (int)($optuna['n_trials'] ?? 50));
        $avg = $this->averageSecondsPerTrial();
        $secPerTrial = (float)$avg['seconds_per_trial'];

        $totalSeconds = (int)round($enabled * $trials * $secPerTrial);
        $tz = new DateTimeZone(ProphetOptunaConfig::CRON_TIMEZONE);
        $hour = (int)($optuna['cron_hour'] ?? 2);
        $minute = (int)($optuna['cron_minute'] ?? 0);

        // Prochaine occurrence simplifiée : aujourd'hui à HH:MM si dans le futur, sinon demain (approx pour l'affichage)
        $now = new DateTimeImmutable('now', $tz);
        $start = $now->setTime($hour, $minute, 0);
        if ($start < $now) {
            $start = $start->modify('+1 day');
        }
        $end = $start->modify('+' . max(0, $totalSeconds) . ' seconds');

        $workdayStart = (int)($optuna['cron_workday_start_hour'] ?? 8);
        // Déborde si la fin estimée tombe un jour ouvré (Lun–Ven) à partir de l'heure de reprise
        $endDow = (int)$end->format('N');
        $endHour = (int)$end->format('G');
        $overflowRisk = ($endDow >= 1 && $endDow <= 5 && $endHour >= $workdayStart);

        return [
            'enabled_offers' => $enabled,
            'n_trials' => $trials,
            'seconds_per_trial' => round($secPerTrial, 1),
            'seconds_per_trial_source' => $avg['source'],
            'sample_count' => $avg['sample_count'],
            'total_seconds' => $totalSeconds,
            'total_human' => $this->formatDuration($totalSeconds),
            'estimated_start' => $start->format('Y-m-d H:i'),
            'estimated_end' => $end->format('Y-m-d H:i'),
            'overflow_risk' => $overflowRisk,
            'workday_start_hour' => $workdayStart,
            'timezone' => ProphetOptunaConfig::CRON_TIMEZONE,
        ];
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' s';
        }
        $m = intdiv($seconds, 60);
        if ($m < 60) {
            return $m . ' min';
        }
        $h = intdiv($m, 60);
        $rm = $m % 60;

        return $h . ' h' . ($rm > 0 ? ' ' . $rm . ' min' : '');
    }
}
