<?php
declare(strict_types=1);

namespace App\Service;

use Cake\I18n\FrozenTime;
use Cake\ORM\Locator\LocatorAwareTrait;

class AgentAvailabilityService
{
    use LocatorAwareTrait;

    public function calculateEffectiveAvailability(object $agent, FrozenTime $date, object $availability): ?array
    {
        $contractStart = $this->normalizeTime($availability->availability_start_time ?? null);
        $contractEnd = $this->normalizeTime($availability->availability_end_time ?? null);
        
        if ($contractStart >= $contractEnd) {
            return null;
        }

        $Offers = $this->fetchTable('Offers');
        $Ranges = $this->fetchTable('Ranges');

        // Récupérer les IDs des offres de type "absence"
        $absenceOfferIds = $Offers->find('ByType', ['type' => 'absence'])
            ->all()
            ->extract('id')
            ->map(fn($id) => (int)$id)
            ->toList();

        if (empty($absenceOfferIds)) {
            // Pas d'absences définies dans le système, on renvoie le contrat complet
            return [
                'start' => $contractStart, // COMPATIBILITÉ LEGACY
                'end' => $contractEnd,     // COMPATIBILITÉ LEGACY
                'all_intervals' => [['start' => $contractStart, 'end' => $contractEnd]],
                'real_window_end' => $contractEnd
            ];
        }

        $dayStart = $date->format('Y-m-d') . ' 00:00:00';
        $dayEnd = $date->format('Y-m-d') . ' 23:59:59';

        // Récupérer les absences de l'agent pour ce jour
        $absences = $Ranges->find()
            ->where([
                'user_id' => (int)($agent->id ?? 0),
                'offer_id IN' => $absenceOfferIds,
                'date_start <=' => $dayEnd,
                'date_end >=' => $dayStart,
            ])
            ->all();

        // Conversion en minutes
        $contractStartMin = $this->parseToMinutes($contractStart);
        $contractEndMin = $this->parseToMinutes($contractEnd);
        
        $merged = [];
        foreach ($absences as $abs) {
            $s = max($contractStartMin, $this->parseToMinutes($abs->date_start->format('H:i:s')));
            $e = min($contractEndMin, $this->parseToMinutes($abs->date_end->format('H:i:s')));

            if ($s >= $e) continue;

            if (empty($merged) || $s > $merged[count($merged) - 1]['end']) {
                $merged[] = ['start' => $s, 'end' => $e];
            } else {
                $merged[count($merged) - 1]['end'] = max($merged[count($merged) - 1]['end'], $e);
            }
        }

        // Calcul des intervalles disponibles (inverse des absences)
        $availableIntervals = [];
        $current = $contractStartMin;
        
        foreach ($merged as $abs) {
            if ($current < $abs['start']) {
                $availableIntervals[] = [
                    'start' => $current,
                    'end' => $abs['start']
                ];
            }
            $current = max($current, $abs['end']);
        }
        
        if ($current < $contractEndMin) {
            $availableIntervals[] = [
                'start' => $current,
                'end' => $contractEndMin
            ];
        }

        if (empty($availableIntervals)) {
            return null;
        }

        // Formatage final
        $formattedIntervals = array_map(function($iv) {
            return [
                'start' => $this->minutesToTime($iv['start']),
                'end'   => $this->minutesToTime($iv['end'])
            ];
        }, $availableIntervals);

        // --- RETOUR HYBRIDE (C'est là que ça répare le crash) ---
        return [
            // 1. Clés LEGACY (Pour Passe 1 et autres services)
            // On renvoie le PREMIER intervalle comme avant, pour ne rien casser.
            'start' => $formattedIntervals[0]['start'],
            'end'   => $formattedIntervals[0]['end'],

            // 2. Clés BONUS (Pour Passe 2 / ScheduleDayGenerationService)
            'all_intervals' => $formattedIntervals,
            'real_window_end' => $contractEnd // La vraie fin de contrat, pas coupée par l'absence
        ];
    }

    private function normalizeTime(?string $t): string
    {
        if (!$t) return '00:00:00';
        return strlen($t) === 5 ? $t . ':00' : $t;
    }

    private function parseToMinutes(string $t): int
    {
        $parts = explode(':', $t);
        return (int)$parts[0] * 60 + (int)$parts[1];
    }

    private function minutesToTime(int $m): string
    {
        $h = floor($m / 60);
        $mn = $m % 60;
        return sprintf('%02d:%02d:00', $h, $mn);
    }
}