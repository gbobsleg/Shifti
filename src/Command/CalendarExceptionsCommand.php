<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\OperatingCalendarService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * bin/cake calendar_exceptions seed [--from Y] [--to Y]
 */
class CalendarExceptionsCommand extends Command
{
    public static function defaultName(): string
    {
        return 'calendar_exceptions';
    }

    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Calendrier d\'ouverture : jours fériés et fermetures.')
            ->addArgument('action', [
                'help' => 'seed : insère les fériés légaux absents',
                'required' => true,
                'choices' => ['seed'],
            ])
            ->addOption('from', [
                'help' => 'Première année (défaut : année courante - 1)',
                'default' => (string)((int)date('Y') - 1),
            ])
            ->addOption('to', [
                'help' => 'Dernière année (défaut : année courante + 3)',
                'default' => (string)((int)date('Y') + 3),
            ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $from = (int)$args->getOption('from');
        $to = (int)$args->getOption('to');
        $result = (new OperatingCalendarService())->seedLegalRange($from, $to);
        $io->out(sprintf(
            'Fériés %d–%d : %d insérés, %d déjà présents (non écrasés).',
            $from,
            $to,
            $result['inserted'],
            $result['skipped']
        ));

        return Command::CODE_SUCCESS;
    }
}
