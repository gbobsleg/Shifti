<?php
declare(strict_types=1);

namespace App\Service\Equity;

use Cake\I18n\FrozenTime;

/**
 * Fournit des scores d'équité basés sur l'état de période (job multi‑jours).
 *
 * Attend dans $context:
 * - activities: array<string,array<int,int>> (equity_state_json['activities'])
 * - legacy_global: array<int,int> (fallback anciens jobs)
 */
class JobPeriodEquityScoresProvider implements EquityScoresProviderInterface
{
    public function getFixedActivitiesEquityScores(array $agentsForJson, array $fixedActivities, FrozenTime $date, array $context = []): array
    {
        $equityStateActivities = is_array($context['activities'] ?? null) ? $context['activities'] : [];
        $legacyGlobal = is_array($context['legacy_global'] ?? null) ? $context['legacy_global'] : [];

        $equitableOfferNames = [];
        foreach ($fixedActivities as $fa) {
            if (!empty($fa['period_equity_weight']) && !empty($fa['offer_name'])) {
                $equitableOfferNames[] = (string)$fa['offer_name'];
            }
        }
        $equitableOfferNames = array_values(array_unique($equitableOfferNames));
        if (empty($equitableOfferNames)) {
            return [];
        }

        $scores = [];
        foreach ($equitableOfferNames as $offerName) {
            foreach ($agentsForJson as $ag) {
                $aid = (int)($ag['id'] ?? 0);
                if ($aid <= 0) {
                    continue;
                }
                $scores[$offerName][$aid] = (int)($equityStateActivities[$offerName][$aid] ?? $legacyGlobal[$aid] ?? 0);
            }
        }

        return $scores;
    }
}


