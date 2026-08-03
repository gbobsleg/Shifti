<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\ProphetOptunaConfig;
use App\Service\ProphetOptunaCronService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Boucle permanente : chaque minute, si jour+heure WFM matchent, enqueue les jobs cron.
 *
 * Usage: bin/cake prophet_tuning_scheduler_ticker
 *        bin/cake prophet_tuning_scheduler_ticker --once
 */
class ProphetTuningSchedulerTickerCommand extends Command
{
    use LocatorAwareTrait;

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);
        $parser
            ->addOption('once', [
                'boolean' => true,
                'default' => false,
                'help' => 'Une seule vérification puis quit.',
            ])
            ->addOption('sleep', [
                'short' => 's',
                'default' => 60,
                'help' => 'Secondes entre deux ticks (défaut 60).',
            ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $once = (bool)$args->getOption('once');
        $sleep = max(15, (int)$args->getOption('sleep'));
        $cron = new ProphetOptunaCronService();
        $WfmSettings = $this->fetchTable('WfmSettings');

        $io->out(sprintf(
            '[OptunaTicker] Démarrage (tz=%s, sleep=%ds).',
            ProphetOptunaConfig::CRON_TIMEZONE,
            $sleep
        ));

        while (true) {
            try {
                $wfm = $WfmSettings->find()->contain([])->first();
                if (!$wfm) {
                    $io->out('[OptunaTicker] Pas de WFM — attente.');
                } else {
                    $optuna = ProphetOptunaConfig::fromStorage($wfm->optuna_settings_json ?? null);
                    if ($cron->shouldEnqueueNow($optuna)) {
                        $io->out('[OptunaTicker] Créneau match — enqueue…');
                        $result = $cron->enqueueEligibleOffers($optuna);
                        foreach ($result['messages'] as $msg) {
                            $io->out('[OptunaTicker] ' . $msg);
                        }
                        $cron->markCronEnqueuedToday();
                        $io->out(sprintf(
                            '[OptunaTicker] Done enqueued=%d skipped=%d.',
                            $result['enqueued'],
                            $result['skipped']
                        ));
                    }
                }
            } catch (\Throwable $e) {
                $io->error('[OptunaTicker] ' . $e->getMessage());
            }

            if ($once) {
                $io->out('[OptunaTicker] Fin (--once).');

                return Command::CODE_SUCCESS;
            }

            sleep($sleep);
        }
    }
}
