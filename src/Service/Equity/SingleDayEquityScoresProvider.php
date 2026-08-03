<?php
declare(strict_types=1);

namespace App\Service\Equity;

use Cake\I18n\FrozenTime;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Fournit des scores d'équité "historique glissant" pour la génération 1‑jour.
 *
 * Objectif: rester robuste même si les offer_name des fixes sont virtuels (\"Base - Site\").
 * On calcule donc un score global par agent sur les offres de base correspondantes.
 *
 * NB: on reste volontairement sur un format legacy Dict[agent_id] => int pour compat et simplicité.
 */
class SingleDayEquityScoresProvider implements EquityScoresProviderInterface
{
    use LocatorAwareTrait;

    public function getFixedActivitiesEquityScores(array $agentsForJson, array $fixedActivities, FrozenTime $date, array $context = []): array
    {
        // Initialiser scores à 0
        $scores = [];
        $agentIds = [];
        foreach ($agentsForJson as $ag) {
            $aid = (int)($ag['id'] ?? 0);
            if ($aid > 0) {
                $scores[$aid] = 0;
                $agentIds[] = $aid;
            }
        }
        $agentIds = array_values(array_unique($agentIds));
        if (empty($agentIds)) {
            return [];
        }

        // Offres concernées (base offer) = uniquement celles marquées équité période
        $baseOfferNames = [];
        foreach ($fixedActivities as $fa) {
            if (empty($fa['period_equity_weight']) || empty($fa['offer_name'])) {
                continue;
            }
            $off = (string)$fa['offer_name'];
            $base = str_contains($off, ' - ') ? explode(' - ', $off, 2)[0] : $off;
            if ($base !== '') {
                $baseOfferNames[] = $base;
            }
        }
        $baseOfferNames = array_values(array_unique($baseOfferNames));
        if (empty($baseOfferNames)) {
            return $scores;
        }

        $Offers = $this->fetchTable('Offers');
        $Ranges = $this->fetchTable('Ranges');

        $offerIds = $Offers->find()
            ->select(['id'])
            ->where(['name IN' => $baseOfferNames])
            ->all()
            ->extract('id')
            ->map(fn($v) => (int)$v)
            ->toList();

        if (empty($offerIds)) {
            return $scores;
        }

        // Fenêtre d'historique: 30 jours glissants (alignement existant)
        $startDate = (clone $date)->subDays(30)->setTime(0, 0, 0);
        $endDate = (clone $date)->setTime(23, 59, 59);

        // Score simple: nombre de jours où l’agent a été planifié sur au moins un segment de ces offres.
        // (On évite une métrique trop fine ici; l’objectif est de briser les biais évidents.)
        $rows = $Ranges->find()
            ->select(['user_id', 'day' => 'DATE(Ranges.date_start)'])
            ->where([
                'Ranges.user_id IN' => $agentIds,
                'Ranges.offer_id IN' => $offerIds,
                'Ranges.date_start >=' => $startDate,
                'Ranges.date_start <=' => $endDate,
            ])
            ->group(['Ranges.user_id', 'DATE(Ranges.date_start)'])
            ->all();

        foreach ($rows as $r) {
            $uid = (int)$r->user_id;
            if (isset($scores[$uid])) {
                $scores[$uid] += 1;
            }
        }

        return $scores;
    }
}


