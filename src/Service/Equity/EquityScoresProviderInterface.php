<?php
declare(strict_types=1);

namespace App\Service\Equity;

use Cake\I18n\FrozenTime;

interface EquityScoresProviderInterface
{
    /**
     * Retourne le payload equity_scores pour la Passe 1 (solve-fixed-activities).
     * Peut être au format:
     * - Dict[offer_name][agent_id] => int (nouveau format)
     * - Dict[agent_id] => int (legacy global)
     *
     * @param array<int,array<string,mixed>> $agentsForJson
     * @param array<int,array<string,mixed>> $fixedActivities
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function getFixedActivitiesEquityScores(array $agentsForJson, array $fixedActivities, FrozenTime $date, array $context = []): array;
}


