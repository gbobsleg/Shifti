<?php
namespace App\Service;

use App\Model\Entity\WfmSetting;
use App\Service\ForecastService;
use Cake\ORM\Locator\LocatorAwareTrait;
use DateTimeInterface;

/**
 * Service de Calcul WFM.
 * Calcule la courbe de besoin nette par offre, au pas de 15 minutes,
 * avec des clés "HH:MM:SS".
 */
class WfmCalculatorService
{
    use LocatorAwareTrait;
    private $ForecastService;
    private $Offers;

    public function __construct(ForecastService $forecastService)
    {
        $this->ForecastService = $forecastService;
        $this->Offers = $this->fetchTable('Offers');
    }

    /**
     * Génére la courbe de besoin (agents nets) pour toutes les offres actives.
     *
     * @param DateTimeInterface $date
     * @param WfmSetting $settings
     * @return array ex: ['Support' => ['09:00:00'=>12, '09:15:00'=>10, ...], ...]
     */
    public function generateNeedCurve(DateTimeInterface $date, WfmSetting $settings): array
    {
        $needCurve = [];
        $intervalSeconds = 15 * 60;

        // Shrinkage: agents_nets = ceil(agents_théoriques / (1 - shrinkage))
        $shrinkagePct = max(0.0, min(99.0, (float)$settings->shrinkage_percent));
        $shrinkageFactor = 1.0 - ($shrinkagePct / 100.0);
        if ($shrinkageFactor <= 0.0) {
            $shrinkageFactor = 0.01; // garde-fou
        }

        $offers = $this->Offers->find('all')->where([
            'end_date IS' => null,
            'is_forecastable' => true,
        ]);

        foreach ($offers as $offer) {
            // Prévision horaire au pas de 15 min, bornée par les settings
            $forecast = $this->ForecastService->getForecast((int)$offer->id, $date, $settings);

            $needCurve[$offer->name] = [];

            foreach ($forecast as $timeSlot => $data) {
                $volume = (int)($data['volume'] ?? 0);
                $aht    = (int)($data['dmt'] ?? 300);

                if ($volume <= 0 || $aht <= 0) {
                    $needCurve[$offer->name][$timeSlot] = 0;
                    continue;
                }

                // Charge (Erlangs) sur l'intervalle de 15 minutes
                $workloadErlangs = ($volume * $aht) / $intervalSeconds;

                // Agents théoriques via Erlang C
                $agentsTheoriques = $this->calculateErlangC(
                    (float)$workloadErlangs,
                    ((float)$settings->service_level_percent) / 100.0,
                    (int)$settings->service_level_seconds,
                    (int)$aht
                );

                // Application du shrinkage
                $agentsNets = (int)ceil($agentsTheoriques / $shrinkageFactor);

                $needCurve[$offer->name][$timeSlot] = max(0, $agentsNets);
            }
        }

        return $needCurve;
    }

    /**
     * Calcule la courbe de besoin pour une offre donnée et une date (pas 15 min).
     * Retourne un tableau ["HH:MM:SS" => agents_nets].
     */
    public function generateNeedForOffer(DateTimeInterface $date, WfmSetting $settings, int $offerId): array
    {
        $intervalSeconds = 15 * 60;

        $shrinkagePct = max(0.0, min(99.0, (float)$settings->shrinkage_percent));
        $shrinkageFactor = 1.0 - ($shrinkagePct / 100.0);
        if ($shrinkageFactor <= 0.0) {
            $shrinkageFactor = 0.01;
        }

        // Prévision pour cette offre
        $forecast = $this->ForecastService->getForecast((int)$offerId, $date, $settings);
        $need = [];

        foreach ($forecast as $timeSlot => $data) {
            $volume = (int)($data['volume'] ?? 0);
            $aht    = (int)($data['dmt'] ?? 300);

            if ($volume <= 0 || $aht <= 0) {
                $need[$timeSlot] = 0;
                continue;
            }

            $workloadErlangs = ($volume * $aht) / $intervalSeconds;

            $agentsTheoriques = $this->calculateErlangC(
                (float)$workloadErlangs,
                ((float)$settings->service_level_percent) / 100.0,
                (int)$settings->service_level_seconds,
                (int)$aht
            );

            $need[$timeSlot] = max(0, (int)ceil($agentsTheoriques / $shrinkageFactor));
        }

        return $need;
    }

    /**
     * Erlang C — nombre d'agents théoriques requis pour atteindre le QS.
     */
    public function calculateErlangC(float $traffic, float $serviceLevel, int $targetTime, int $avgHandleTime): int
    {
        if ($traffic <= 0.0 || $avgHandleTime <= 0) {
            return 0;
        }

        $agents = (int)floor($traffic) + 1;

        // borne de sécurité
        $cap = (int)ceil($traffic) + 200;

        while ($agents <= $cap) {
            $probabilityOfWait = $this->getProbabilityOfWait($traffic, $agents);
            $calculatedServiceLevel = $this->getServiceLevel($probabilityOfWait, $agents, $traffic, $targetTime, $avgHandleTime);

            if ($calculatedServiceLevel >= $serviceLevel) {
                return $agents;
            }

            $agents++;
        }

        return $agents;
    }

    /**
     * Probabilité d'attente (partie d'Erlang C).
     */
    private function getProbabilityOfWait(float $traffic, int $agents): float
    {
        if ($agents <= 0) return 1.0;
        if ($traffic <= 0.0) return 0.0;
        if ($agents <= $traffic) return 1.0;

        $erlangB = $this->getErlangB($traffic, $agents);
        return $erlangB / (1.0 - ($traffic / $agents) * (1.0 - $erlangB));
    }

    /**
     * Niveau de service (partie d'Erlang C).
     */
    private function getServiceLevel(float $probabilityOfWait, int $agents, float $traffic, int $targetTime, int $avgHandleTime): float
    {
        if ($avgHandleTime <= 0) return 1.0;

        $exp = exp(-($agents - $traffic) * ($targetTime / $avgHandleTime));
        return 1.0 - ($probabilityOfWait * $exp);
    }

    /**
     * Erlang B (utile au calcul de C).
     */
    private function getErlangB(float $traffic, int $agents): float
    {
        if ($traffic <= 0.0) return 0.0;

        $invB = 1.0;
        for ($i = 1; $i <= $agents; $i++) {
            $invB = 1.0 + ($i / $traffic) * $invB;
        }
        return 1.0 / $invB;
    }
}
