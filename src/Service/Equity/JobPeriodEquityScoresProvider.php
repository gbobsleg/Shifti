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

        // Construire un mapping offer_name → base_offer_name à partir des $fixedActivities
        $offerToBaseName = [];
        foreach ($fixedActivities as $fa) {
            if (!empty($fa['offer_name'])) {
                $offerToBaseName[(string)$fa['offer_name']] = $fa['base_offer_name'] ?? (string)$fa['offer_name'];
            }
        }

        // Lookup avec base_offer_name (et fallback offer_name si absent du mapping)
        $scores = [];
        foreach ($equitableOfferNames as $offerName) {
            $baseName = $offerToBaseName[$offerName] ?? $offerName;
            foreach ($agentsForJson as $ag) {
                $aid = (int)($ag['id'] ?? 0);
                if ($aid <= 0) {
                    continue;
                }
                $scores[$offerName][$aid] = (int)(
                    $equityStateActivities[$baseName][$aid] ?? $legacyGlobal[$aid] ?? 0
                );
            }
        }

        return $scores;
    }
}


