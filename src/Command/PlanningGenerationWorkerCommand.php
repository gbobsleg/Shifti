<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\ScheduleDayGenerationService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\I18n\FrozenDate;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Worker CLI: exécute les jobs de génération de planning en tâche de fond.
 */
class PlanningGenerationWorkerCommand extends Command
{
    use LocatorAwareTrait;

    protected function buildOptionParser(\Cake\Console\ConsoleOptionParser $parser): \Cake\Console\ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);

        $parser
            ->addOption('once', [
                'boolean' => true,
                'default' => false,
                'help' => 'Traite au plus un job puis quitte.',
            ])
            ->addOption('sleep', [
                'short' => 's',
                'default' => 2,
                'help' => 'Délai (en secondes) entre deux boucles quand il n’y a pas de job.',
            ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $once = (bool)$args->getOption('once');
        $sleepSeconds = (int)$args->getOption('sleep');
        if ($sleepSeconds < 1) {
            $sleepSeconds = 1;
        }

        $Jobs = $this->fetchTable('PlanningGenerationJobs');
        $Days = $this->fetchTable('PlanningGenerationJobDays');

        $io->out('[PlanningWorker] Démarrage.');

        while (true) {
            $job = $Jobs->find()
                ->where(['status' => 'queued'])
                ->orderAsc('created')
                ->first();

            if (!$job) {
                if ($once) {
                    $io->out('[PlanningWorker] Aucun job en attente. Fin.');
                    return Command::CODE_SUCCESS;
                }
                sleep($sleepSeconds);
                continue;
            }

            // Tenter de "réserver" le job (évite que 2 workers prennent le même).
            $now = new \DateTimeImmutable();
            $reserved = $Jobs->updateAll(
                [
                    'status' => 'running',
                    'current_step' => 'starting',
                    'current_day' => null,
                    'eta_seconds' => null,
                    'error_message' => null,
                    'started_at' => $now->format('Y-m-d H:i:s'),
                    'finished_at' => null,
                    'modified' => $now->format('Y-m-d H:i:s'),
                ],
                ['id' => (int)$job->id, 'status' => 'queued'],
            );

            if ($reserved === 0) {
                // Un autre worker l'a pris.
                continue;
            }

            $jobId = (int)$job->id;
            $io->out("[PlanningWorker] Job #{$jobId} réservé.");

            $generationService = new ScheduleDayGenerationService();

            $jobDays = $Days->find()
                ->where(['job_id' => $jobId])
                ->orderAsc('date')
                ->all()
                ->toList();

            // Réinitialiser les fichiers de debug solveur à chaque nouveau job
            $logsDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'logs';
            if (is_dir($logsDir)) {
                $debugSolver = $logsDir . DIRECTORY_SEPARATOR . 'debug_solver_data.json';
                $debugAgent95 = $logsDir . DIRECTORY_SEPARATOR . 'debug_agent_95_lite.json';
                $debugDuel = $logsDir . DIRECTORY_SEPARATOR . 'debug_duel_95_vs_101.json';
                if (is_file($debugSolver)) {
                    @unlink($debugSolver);
                }
                if (is_file($debugAgent95)) {
                    @unlink($debugAgent95);
                }
                if (is_file($debugDuel)) {
                    @unlink($debugDuel);
                }
            }

            $hasErrors = false;

            foreach ($jobDays as $day) {
                $dayStatus = (string)($day->status ?? '');
                if (in_array($dayStatus, ['ok', 'infeasible', 'error'], true)) {
                    continue;
                }

                /** @var \Cake\I18n\FrozenDate $d */
                $d = $day->date instanceof FrozenDate ? $day->date : new FrozenDate((string)$day->date);
                $dateKey = $d->format('Y-m-d');

                $Jobs->updateAll(
                    [
                        'current_day' => $dateKey,
                        'current_step' => 'day_generation',
                        'modified' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                    ],
                    ['id' => $jobId],
                );

                $startedAt = microtime(true);

                try {
                    $result = $generationService->generateDayForJob($jobId, $d);

                    $day->status = (string)($result['status'] ?? 'error');
                    $day->report_json = !empty($result['report_json']) ? (string)$result['report_json'] : null;
                    $day->error_message = !empty($result['error_message']) ? (string)$result['error_message'] : null;
                } catch (\Throwable $e) {
                    $day->status = 'error';
                    $day->error_message = $e->getMessage();
                    $day->report_json = null;
                }

                $durationMs = (int)round((microtime(true) - $startedAt) * 1000);
                $day->duration_ms = $durationMs;
                $Days->saveOrFail($day);

                if ($day->status !== 'ok') {
                    $hasErrors = true;
                }

                // Recalcul progression + ETA
                $doneCount = $Days->find()
                    ->where(['job_id' => $jobId, 'status IN' => ['ok', 'infeasible', 'error']])
                    ->count();
                $total = $Days->find()->where(['job_id' => $jobId])->count();

                $avgRow = $Days->find()
                    ->select(['avg_ms' => $Days->find()->func()->avg('duration_ms')])
                    ->where(['job_id' => $jobId, 'duration_ms IS NOT' => null])
                    ->enableHydration(false)
                    ->first();
                $avgMs = is_array($avgRow) && isset($avgRow['avg_ms']) ? (float)$avgRow['avg_ms'] : 0.0;

                $remaining = max(0, $total - $doneCount);
                $etaSeconds = $avgMs > 0 ? (int)round(($avgMs * $remaining) / 1000) : null;

                $Jobs->updateAll(
                    [
                        'processed_days' => $doneCount,
                        'total_days' => $total,
                        'eta_seconds' => $etaSeconds,
                        'modified' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                    ],
                    ['id' => $jobId],
                );

                $diagExcl = null;
                $diagWarn = null;
                if (!empty($day->report_json)) {
                    $decoded = json_decode((string)$day->report_json, true);
                    if (is_array($decoded) && isset($decoded['diagnostics']) && is_array($decoded['diagnostics'])) {
                        $diag = $decoded['diagnostics'];
                        $diagExcl = isset($diag['excluded_agents']) && is_array($diag['excluded_agents']) ? count($diag['excluded_agents']) : null;
                        $diagWarn = isset($diag['warnings']) && is_array($diag['warnings']) ? count($diag['warnings']) : null;
                    }
                }
                $extra = '';
                if ($diagExcl !== null || $diagWarn !== null) {
                    $extra = ' - diag(excl=' . (int)($diagExcl ?? 0) . ', warn=' . (int)($diagWarn ?? 0) . ')';
                }
                $io->out("[PlanningWorker] Job #{$jobId} - {$dateKey} => {$day->status} ({$durationMs} ms){$extra}");
            }

            $finalStatus = $hasErrors ? 'finished_with_errors' : 'finished';
            $now = new \DateTimeImmutable();
            $Jobs->updateAll(
                [
                    'status' => $finalStatus,
                    'current_step' => null,
                    'current_day' => null,
                    'eta_seconds' => 0,
                    'finished_at' => $now->format('Y-m-d H:i:s'),
                    'modified' => $now->format('Y-m-d H:i:s'),
                ],
                ['id' => $jobId],
            );

            $io->out("[PlanningWorker] Job #{$jobId} terminé: {$finalStatus}");

            if ($once) {
                return Command::CODE_SUCCESS;
            }
        }
    }
}


