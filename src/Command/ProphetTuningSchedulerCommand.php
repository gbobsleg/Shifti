<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\ProphetOptunaConfig;
use App\Service\ProphetOptunaCronService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Enfile les jobs Optuna pour les offres éligibles (sans contrôle jour/heure).
 *
 * Usage manuel / debug : bin/cake prophet_tuning_scheduler
 * En prod, préférer le ticker : bin/cake prophet_tuning_scheduler_ticker
 */
class ProphetTuningSchedulerCommand extends Command
{
    use LocatorAwareTrait;

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $WfmSettings = $this->fetchTable('WfmSettings');
        $wfm = $WfmSettings->find()->contain([])->first();
        if (!$wfm) {
            $io->warning('[OptunaScheduler] Aucun profil WFM — abandon.');

            return Command::CODE_SUCCESS;
        }

        $optuna = ProphetOptunaConfig::fromStorage($wfm->optuna_settings_json ?? null);
        if (empty($optuna['cron_enabled'])) {
            $io->out('[OptunaScheduler] cron_enabled=false — rien à faire.');

            return Command::CODE_SUCCESS;
        }

        $cron = new ProphetOptunaCronService();
        $result = $cron->enqueueEligibleOffers($optuna);
        foreach ($result['messages'] as $msg) {
            $io->out('[OptunaScheduler] ' . $msg);
        }
        $io->out(sprintf(
            '[OptunaScheduler] Terminé — enqueued=%d skipped=%d.',
            $result['enqueued'],
            $result['skipped']
        ));

        return Command::CODE_SUCCESS;
    }
}
