<?php
declare(strict_types=1);

namespace App\Command;

use App\Model\Entity\ForecastScenario;
use App\Service\ForecastService;
use App\Service\WfmCalculatorService;
use App\Service\WfmScenarioService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Worker CLI : exécute les scénarios de prévision en tâche de fond (file, 1 actif).
 */
class ForecastScenarioWorkerCommand extends Command
{
    use LocatorAwareTrait;

    protected function buildOptionParser(\Cake\Console\ConsoleOptionParser $parser): \Cake\Console\ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);

        $parser
            ->addOption('once', [
                'boolean' => true,
                'default' => false,
                'help' => 'Traite au plus un scénario puis quitte.',
            ])
            ->addOption('sleep', [
                'short' => 's',
                'default' => 2,
                'help' => 'Délai (en secondes) entre deux boucles quand il n’y a pas de scénario.',
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

        $Scenarios = $this->fetchTable('ForecastScenarios');
        $WfmSettings = $this->fetchTable('WfmSettings');

        $io->out('[ForecastWorker] Démarrage.');

        while (true) {
            $scenario = $Scenarios->find()
                ->where(['status' => ForecastScenario::STATUS_QUEUED])
                ->orderAsc('created')
                ->first();

            if (!$scenario) {
                if ($once) {
                    $io->out('[ForecastWorker] Aucun scénario en attente. Fin.');

                    return Command::CODE_SUCCESS;
                }
                sleep($sleepSeconds);
                continue;
            }

            $scenarioId = (int)$scenario->id;
            $now = new \DateTimeImmutable();

            // Blindage 1 : réservation atomique queued → running
            $reserved = $Scenarios->updateAll(
                [
                    'status' => 'running',
                    'started_at' => $now->format('Y-m-d H:i:s'),
                    'finished_at' => null,
                    'error_message' => null,
                    'modified' => $now->format('Y-m-d H:i:s'),
                ],
                [
                    'id' => $scenarioId,
                    'status' => ForecastScenario::STATUS_QUEUED,
                ],
            );

            if ($reserved === 0) {
                $io->out("[ForecastWorker] Scénario #{$scenarioId} déjà réservé — skip.");
                continue;
            }

            $io->out("[ForecastWorker] Scénario #{$scenarioId} réservé.");

            try {
                $wfm = $WfmSettings->find()->first();
                if (!$wfm) {
                    throw new \RuntimeException('Aucun profil WfmSettings trouvé.');
                }

                $forecastService = new ForecastService();
                $calculatorService = new WfmCalculatorService($forecastService);
                $scenarioService = new WfmScenarioService($forecastService, $calculatorService);
                $scenarioService->runScenario($scenarioId, $wfm);

                $io->out("[ForecastWorker] Scénario #{$scenarioId} terminé.");
            } catch (\Throwable $e) {
                $failNow = new \DateTimeImmutable();
                $Scenarios->updateAll(
                    [
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                        'finished_at' => $failNow->format('Y-m-d H:i:s'),
                        'modified' => $failNow->format('Y-m-d H:i:s'),
                    ],
                    ['id' => $scenarioId],
                );
                $io->err("[ForecastWorker] Scénario #{$scenarioId} échoué: " . $e->getMessage());
            }

            if ($once) {
                return Command::CODE_SUCCESS;
            }
        }
    }
}
