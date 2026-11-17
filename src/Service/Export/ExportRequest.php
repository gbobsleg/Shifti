<?php
declare(strict_types=1);

namespace App\Service\Export;

/**
 * Filtres déjà validés d'un téléchargement.
 */
final class ExportRequest
{
    /**
     * @param list<int> $offerIds
     */
    public function __construct(
        public readonly string $type,
        public readonly array $offerIds,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly string $granularity,
        public readonly ?int $scenarioId = null,
    ) {
    }
}
