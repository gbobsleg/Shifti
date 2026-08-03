<?php
namespace App\Service;

use App\Model\Entity\WfmSetting;
use Cake\ORM\Locator\LocatorAwareTrait;
use DateTime;
use DateTimeInterface;

/**
 * Service de Prévision (Forecasting).
 * Renvoie un tableau au pas de 15 minutes entre day_start_time et day_end_time,
 * avec des clés "HH:MM:SS".
 */
class ForecastService
{
    use LocatorAwareTrait;
    private $HistoricalData;

    public function __construct()
    {
        $this->HistoricalData = $this->fetchTable('HistoricalData');
    }

    /**
     * Prédit le VA et la DMT pour chaque 15 minutes d'une journée et d'une offre.
     *
     * @param int $offerId
     * @param DateTimeInterface $dateToForecast
     * @param WfmSetting $settings
     * @param string|null $historyStart Date de début d'historique (Y-m-d) ou null
     * @param string|null $historyEnd   Date de fin d'historique (Y-m-d) ou null
     * @return array ex: ['09:00:00' => ['volume' => 50, 'dmt' => 300], ...]
     */
    public function getForecast(
        int $offerId,
        DateTimeInterface $dateToForecast,
        WfmSetting $settings,
        ?string $historyStart = null,
        ?string $historyEnd = null
    ): array
    {
        $forecastData = [];
        $dayOfWeek_N = (int)$dateToForecast->format('N'); // 1=Lundi, 7=Dimanche

        $startStr = (string)($settings->day_start_time ?? '');
        $endStr   = (string)($settings->day_end_time   ?? '');

        if ($startStr === '' || $endStr === '') {
            throw new \RuntimeException(
                "[ForecastService] day_start_time/day_end_time manquants dans WfmSettings. " .
                "Configure d'abord le profil WFM avant de lancer des prévisions."
            );
        }
        if (strlen($startStr) === 5) $startStr .= ':00';
        if (strlen($endStr) === 5)   $endStr   .= ':00';

        $current = new DateTime($dateToForecast->format('Y-m-d') . ' ' . $startStr);
        $end     = new DateTime($dateToForecast->format('Y-m-d') . ' ' . $endStr);

        // Avant la boucle: assure-toi d'avoir normalisé les bornes
        $this->floorToQuarterHour($current); // ex: 09:00:00
        $this->ceilToQuarterHour($end);      // ex: 17:00:00

        // OPTIMISATION: Une seule requête pour toute la journée avec GROUP BY
        $query = $this->HistoricalData->find();
        $query = $query
            ->select([
                'time_slot' => 'TIME(datetime_interval)',
                'avg_volume' => $query->func()->avg('call_volume'),
                'avg_dmt'    => $query->func()->avg('avg_handle_time_seconds'),
            ])
            ->where([
                'offer_id' => $offerId,
                'WEEKDAY(datetime_interval) + 1 =' => $dayOfWeek_N,
                'TIME(datetime_interval) >=' => $startStr,
                'TIME(datetime_interval) <' => $endStr,
            ]);

        // Limiter par la plage historique si fournie
        if ($historyStart !== null && $historyStart !== '') {
            $query->andWhere(['DATE(datetime_interval) >=' => $historyStart]);
        }
        if ($historyEnd !== null && $historyEnd !== '') {
            $query->andWhere(['DATE(datetime_interval) <=' => $historyEnd]);
        }

        $results = $query
            ->group('TIME(datetime_interval)')
            ->toArray();

        // Indexer les résultats par time_slot pour accès rapide
        $dataBySlot = [];
        foreach ($results as $row) {
            $dataBySlot[$row->time_slot] = [
                'volume' => (int) round((float)($row->avg_volume ?? 0)),
                'dmt'    => (int) round((float)($row->avg_dmt ?? 300)),
            ];
        }

        // Générer le squelette complet avec toutes les tranches de 15 min
        for ($t = clone $current; $t < $end; $t->modify('+15 minutes')) {
            $timeSlot = $t->format('H:i:s'); // "HH:MM:SS"

            $forecastData[$timeSlot] = $dataBySlot[$timeSlot] ?? [
                'volume' => 0,
                'dmt'    => 300, // DMT par défaut
            ];
        }

        return $forecastData;
    }

    private function floorToQuarterHour(DateTime $dt): void
    {
        $m = (int)$dt->format('i');
        $s = (int)$dt->format('s');
        $q = intdiv($m, 15) * 15;
        if ($m !== $q || $s !== 0) {
            $dt->setTime((int)$dt->format('H'), $q, 0);
        }
    }

    private function ceilToQuarterHour(DateTime $dt): void
    {
        $m = (int)$dt->format('i');
        $s = (int)$dt->format('s');
        if ($m % 15 !== 0 || $s !== 0) {
            $extra = 15 - ($m % 15);
            $dt->modify('+' . $extra . ' minutes')->setTime((int)$dt->format('H'), (int)$dt->format('i'), 0);
        }
    }
}
