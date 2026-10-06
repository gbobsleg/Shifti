<?php
declare(strict_types=1);

namespace App\Service\OfferColors;

/**
 * Résultat d'une écriture du bandeau.
 */
class BoardOutcome
{
    public const OK = 'ok';

    public const ERROR = 'error';

    public const CONFLICT = 'conflict';

    public const MISSING = 'missing';

    public function __construct(
        public readonly string $status,
        public readonly string $message = '',
        public readonly int $serverRevision = 0,
    ) {
    }

    public static function ok(string $message): self
    {
        return new self(self::OK, $message);
    }

    public static function error(string $message): self
    {
        return new self(self::ERROR, $message);
    }

    public static function conflict(int $serverRevision): self
    {
        return new self(
            self::CONFLICT,
            'Poursuivre détruit le travail de quelqu\'un d\'autre. Cette version n\'est pas affichée.',
            $serverRevision,
        );
    }

    public static function missing(): self
    {
        return new self(self::MISSING, 'Cette palette a été supprimée.');
    }
}
