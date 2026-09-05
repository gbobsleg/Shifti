<?php
declare(strict_types=1);

namespace App\View\Helper;

use App\View\Grid\BarRenderer;
use Cake\I18n\FrozenTime;
use Cake\View\Helper;

class GridsHelper extends Helper
{
    /**
     * Heures de début et fin de la grille (par défaut)
     * Ces valeurs peuvent être surchargées via setGridHours()
     */
    private int $gridStartHour = 8;
    private int $gridEndHour = 18;

    /**
     * Colonne vide non peignable de part et d’autre de la timeline.
     */
    private function gutterCell(string $tag): string
    {
        $tag = $tag === 'th' ? 'th' : 'td';

        return '<' . $tag . ' class="grids-gutter" aria-hidden="true"></' . $tag . '>';
    }

    /**
     * Définit les heures de début et fin de la grille
     */
    public function setGridHours(int $start, int $end): void
    {
        $this->gridStartHour = $start;
        $this->gridEndHour = $end;
    }

    /**
     * @return string|false
     */
    public function writeThTime(string $hoursOrMinutes, FrozenTime $beginOfDay): string|false
    {
        $date = clone $beginOfDay;

        if (($hoursOrMinutes != 'hours') && ($hoursOrMinutes != 'minutes')) {
            return false;
        }

        if ($hoursOrMinutes == 'hours') {
            $start = $date->setTime($this->gridStartHour, 0);
            $timestampStart = $start->getTimestamp();
            $end = $date->setTime($this->gridEndHour, 0);
            $timestampEnd = $end->getTimestamp();

            $ThTime = '';
            for ($i = $timestampStart; $i <= $timestampEnd; $i++) {
                $ThTime .= '<th colspan="4" scope="col" class="th_col1">' .
                    $start->format('H:i') .
                    '</th>';
                $start = $start->addHours(1);
                $i = $start->getTimestamp();
            }

            return $this->gutterCell('th') . $ThTime . $this->gutterCell('th');
        }

        if ($hoursOrMinutes == 'minutes') {
            $start = $date->setTime($this->gridStartHour, 0);
            $timestampStart = $start->getTimestamp();
            $end = $date->setTime($this->gridEndHour, 0);
            $timestampEnd = $end->getTimestamp();

            $ThTime = '';
            for ($i = $timestampStart; $i <= $timestampEnd; $i++) {
                $ThTime .= '<th colspan="1" scope="col" class="th_col2">' .
                    $start->format('i') .
                    '</th>';
                $start = $start->addMinutes(15);
                $i = $start->getTimestamp();
            }

            return $this->gutterCell('th') . $ThTime . $this->gutterCell('th');
        }
    }

    /**
     * Ligne Besoin / Réel : même nombre et ordre de cellules que writeTimeSlots (gouttières comprises).
     */
    public function writeLoadRow(string $kind, FrozenTime $beginOfDay): string
    {
        $isNeed = $kind === 'need';
        $label = $isNeed ? 'Besoin' : 'Réel';
        $rowClass = $isNeed ? 'grids-load-row grids-load-row--need' : 'grids-load-row grids-load-row--planned';

        return '<tr class="' . $rowClass . '" data-kind="' . ($isNeed ? 'need' : 'planned') . '" hidden>'
            . '<th scope="row" class="th_row site-column"></th>'
            . '<th scope="row" class="th_row grids-load-label">' . $label . '</th>'
            . $this->writeLoadSlots($beginOfDay)
            . '</tr>';
    }

    /**
     * Quarts de la ligne Besoin / Réel — même axe que collectTimeSlots, jamais de cellule hors grille.
     */
    public function writeLoadSlots(FrozenTime $beginOfDay): string
    {
        $date = clone $beginOfDay;
        $start = $date->setTime($this->gridStartHour, 0);
        $end = $date->setTime($this->gridEndHour - 1, 45);
        $html = $this->gutterCell('td');

        while ($start->getTimestamp() <= $end->getTimestamp()) {
            $html .= '<td class="grids-load-cell" data-slot="' . $start->format('H:i') . '"></td>';
            $start = $start->addMinutes(15);
        }

        return $html . $this->gutterCell('td');
    }

    /**
     * Retourne les créneaux d'indisponibilité pour un agent un jour donné
     * @return array [[start_timestamp, end_timestamp], ...]
     */
    public function getUserUnavailableSlots(object $user, FrozenTime $day): array
    {
        $unavailableSlots = [];
        $dayOfWeek = (int)$day->format('N'); // 1=lundi, 7=dimanche
        
        // 1. Récupérer la disponibilité contractuelle
        $availability = null;
        if (isset($user->user_availabilities) && is_iterable($user->user_availabilities)) {
            foreach ($user->user_availabilities as $avail) {
                if ($avail->day_of_week == $dayOfWeek) {
                    $availability = $avail;
                    break;
                }
            }
        }
        
        if (!$availability) {
            // Pas de config = disponible sur plage par défaut
            return [];
        }
        
        // 2. Calculer zones d'indispo (avant availability_start et après availability_end)
        $dayStart = $day->setTime($this->gridStartHour, 0);
        $dayEnd = $day->setTime($this->gridEndHour, 0);
        
        // Convertir les heures de disponibilité en objets Time si nécessaire
        $startTime = $availability->availability_start_time;
        $endTime = $availability->availability_end_time;
        
        // Si c'est une chaîne, parser en objet Time
        if (is_string($startTime)) {
            $startTime = FrozenTime::parse($startTime);
        }
        if (is_string($endTime)) {
            $endTime = FrozenTime::parse($endTime);
        }
        
        // Extraire les heures et minutes
        $startHour = (int)$startTime->format('H');
        $startMinute = (int)$startTime->format('i');
        $endHour = (int)$endTime->format('H');
        $endMinute = (int)$endTime->format('i');
        
        $availStart = $day->setTime($startHour, $startMinute);
        $availEnd = $day->setTime($endHour, $endMinute);
        
        // Zone avant disponibilité
        if ($dayStart < $availStart) {
            $unavailableSlots[] = [$dayStart->getTimestamp(), $availStart->getTimestamp()];
        }
        
        // Zone après disponibilité
        if ($availEnd < $dayEnd) {
            $unavailableSlots[] = [$availEnd->getTimestamp(), $dayEnd->getTimestamp()];
        }
        
        return $unavailableSlots;
    }

    /**
     * @return string
     */
    public function writeTimeSlots(object $user, FrozenTime $beginOfDay, string $rangesProperty = 'ranges'): string
    {
        $slots = $this->collectTimeSlots($user, $beginOfDay, $rangesProperty);
        $renderer = new BarRenderer();

        return $this->gutterCell('td') . $renderer->renderHtml($slots, (int)$user->id) . $this->gutterCell('td');
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function collectTimeSlots(object $user, FrozenTime $beginOfDay, string $rangesProperty): array
    {
        $date = clone $beginOfDay;
        $start = $date->setTime($this->gridStartHour, 0);
        $timestampStart = $start->getTimestamp();
        $end = $date->setTime($this->gridEndHour - 1, 45);
        $timestampEnd = $end->getTimestamp();
        $unavailableSlots = $this->getUserUnavailableSlots($user, $beginOfDay);
        $slots = [];

        while ($timestampStart <= $timestampEnd) {
            $start = $start->addMinutes(15);
            $newTimestampStart = $start->getTimestamp();
            $slotStart = date('Y-m-d H:i:s', $timestampStart);
            $slotEnd = date('Y-m-d H:i:s', $newTimestampStart);

            $existingRange = null;
            $remoteWorkCandidate = null;
            $ranges = $this->getRangesForDisplay($user, $rangesProperty);
            foreach ($ranges as $range) {
                if (
                    ($range->date_start->getTimestamp() <= $timestampStart)
                    && ($timestampStart < $range->date_end->getTimestamp())
                ) {
                    $isRemoteWork = isset($range->offer) && isset($range->offer->offer_type)
                        && $range->offer->offer_type === 'remote_work';
                    if ($isRemoteWork) {
                        if ($remoteWorkCandidate === null) {
                            $remoteWorkCandidate = $range;
                        }
                        continue;
                    }
                    $existingRange = $range;
                    break;
                }
            }
            if ($existingRange === null && $remoteWorkCandidate !== null) {
                $existingRange = $remoteWorkCandidate;
            }

            if ($existingRange) {
                if (isset($existingRange->offer) && $existingRange->offer_id != '0' && $existingRange->offer_id != 0) {
                    $offer = $existingRange->offer;
                    $name = (string)($offer->name ?? '');
                    $slots[] = [
                        'start' => $slotStart,
                        'end' => $slotEnd,
                        'offer_id' => (string)$existingRange->offer_id,
                        'range_id' => (string)$existingRange->id,
                        'offer_type' => (string)($offer->offer_type ?? 'normal'),
                        'color' => (string)($offer->color ?? ''),
                        'label' => $name,
                        'title' => $name . ' de ' . $existingRange->date_start->i18nFormat('HH:mm')
                            . ' à ' . $existingRange->date_end->i18nFormat('HH:mm')
                            . ' - ' . ($existingRange->comment ?? ''),
                        'unavailable' => false,
                    ];
                    $timestampStart = $newTimestampStart;
                    continue;
                }
                $slots[] = $this->emptySlot(
                    $slotStart,
                    $slotEnd,
                    (string)($existingRange->offer_id ?? '0'),
                    (string)($existingRange->id ?? '')
                );
                $timestampStart = $newTimestampStart;
                continue;
            }

            $isUnavailable = false;
            foreach ($unavailableSlots as [$slotUnavailStart, $slotUnavailEnd]) {
                if ($timestampStart >= $slotUnavailStart && $timestampStart < $slotUnavailEnd) {
                    $isUnavailable = true;
                    break;
                }
            }
            if ($isUnavailable) {
                $slots[] = [
                    'start' => $slotStart,
                    'end' => $slotEnd,
                    'offer_id' => '0',
                    'range_id' => '',
                    'offer_type' => '',
                    'color' => '',
                    'label' => '',
                    'title' => 'Agent non disponible selon son contrat',
                    'unavailable' => true,
                ];
                $timestampStart = $newTimestampStart;
                continue;
            }

            $slots[] = $this->emptySlot($slotStart, $slotEnd, '0', '');
            $timestampStart = $newTimestampStart;
        }

        return $slots;
    }

    /**
     * @return array<string,mixed>
     */
    private function emptySlot(string $start, string $end, string $offerId, string $rangeId): array
    {
        return [
            'start' => $start,
            'end' => $end,
            'offer_id' => $offerId,
            'range_id' => $rangeId,
            'offer_type' => '',
            'color' => '',
            'label' => '',
            'title' => '',
            'unavailable' => false,
        ];
    }

    /**
     * Retourne la liste des ranges à utiliser pour l'affichage, en permettant un mode brouillon.
     *
     * - Par défaut, on utilise `$user->ranges`
     * - En mode brouillon (ex: `$rangesProperty = 'draft_ranges'`), on affiche en priorité les brouillons,
     *   et on complète avec les ranges existants (pour montrer absences/télétravail si chargés).
     *
     * @return array<int, mixed>
     */
    private function getRangesForDisplay(object $user, string $rangesProperty): array
    {
        $primary = $this->iterableToArray($user->{$rangesProperty} ?? []);

        // Si on n'est pas en mode "ranges" classique, on complète avec les ranges existants
        // (utile pour afficher absences/télétravail dans la grille brouillon).
        if ($rangesProperty !== 'ranges') {
            $fallback = $this->iterableToArray($user->ranges ?? []);
            if (!empty($fallback)) {
                // Ordre important: les brouillons passent devant (ils "gagnent" à l'affichage).
                return array_merge($primary, $fallback);
            }
        }

        return $primary;
    }

    /**
     * Normalise un itérable CakePHP (ResultSet, Collection, etc.) en tableau PHP.
     *
     * @return array<int, mixed>
     */
    private function iterableToArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if ($value instanceof \Traversable) {
            return iterator_to_array($value, false);
        }
        return [];
    }

    /**
     * @return string
     */
    public function writeTrUser(object $user, array $offers, FrozenTime $beginOfDay, string $rangesProperty = 'ranges'): string
    {
        $date = clone $beginOfDay;

        $start = $date->setTime($this->gridStartHour, 0);
        $timestampStart = $start->getTimestamp();
        $end = $date->setTime($this->gridEndHour - 1, 45);
        $timestampEnd = $end->getTimestamp();

        $TrUser = '';

        $bg = '';
        $icon = '';
        $homework = '';
        $remoteStyle = '';
        $currentDayStart = $date->startOfDay();

        // Vérifier si agent en télétravail ce jour
        $remoteWorkInfo = $this->isUserInRemoteWork($user, $date);
        if ($remoteWorkInfo['is_remote']) {
            $bg = 'is-remote';
            $icon = '<i class="bi-house-door-fill pe-1"></i>';
            $homework = $remoteWorkInfo['info'];
            $remoteColor = (string)($remoteWorkInfo['color'] ?? '');
            if ($remoteColor !== '' && preg_match('/^#[0-9A-Fa-f]{3,8}$/', $remoteColor)) {
                $remoteStyle = ' style="background:' . $remoteColor . '"';
            }
        }

        // Palette de couleurs distinctes et subtiles pour les sites
        $colorPalette = [
            ['bg' => '#e3f2fd', 'text' => '#1565c0'], // Bleu clair
            ['bg' => '#f3e5f5', 'text' => '#7b1fa2'], // Violet
            ['bg' => '#e8f5e9', 'text' => '#2e7d32'], // Vert
            ['bg' => '#fff3e0', 'text' => '#e65100'], // Orange
            ['bg' => '#fce4ec', 'text' => '#c2185b'], // Rose
            ['bg' => '#e0f2f1', 'text' => '#00695c'], // Turquoise
            ['bg' => '#fff9c4', 'text' => '#f57f17'], // Jaune
            ['bg' => '#f1f8e9', 'text' => '#558b2f'], // Vert clair
            ['bg' => '#e1bee7', 'text' => '#6a1b9a'], // Violet foncé
            ['bg' => '#ffccbc', 'text' => '#d84315'], // Orange brûlé
        ];

        $colorIndex = ($user['site']['id'] - 1) % count($colorPalette);
        $colors = $colorPalette[$colorIndex];
        // $bgColor non utilisé
        $textColor = $colors['text'];

        $TrUser .= '<th scope="row" class="th_row text-center site-column" style="border-left: 2px solid ' . $textColor . '; color: ' . $textColor . '; width: 1%; white-space: nowrap;">
        ' . h($user['site']['name']) . '
        </th>';

        // Cellule Agent (sans trait coloré — réservé à la colonne Site)
        $TrUser .= '<th scope="row" class="th_row ' . $bg . '"' . $remoteStyle . ' data-bs-toggle="tooltip" data-placement="right" title="Site ' . h($user['site']['number']) . ' - ' . h($user->user_code) . $homework . '">
        ' . $icon . h($user->full_name) . '</th>';

        $TrUser .= $this->writeTimeSlots($user, $beginOfDay, $rangesProperty);

        return $TrUser;
    }

    /**
     * Vérifie si un utilisateur est en télétravail pour un jour donné
     * 
     * Se base UNIQUEMENT sur la présence de Ranges en base de données.
     * La configuration fixe (UserRemoteWorkSetting) n'est pas utilisée pour déterminer le statut.
     * 
     * @param object $user Utilisateur avec relations Ranges.Offers
     * @param FrozenTime $day Jour à vérifier
     * @return array ['is_remote' => bool, 'type' => 'fixed'|'flexible'|null, 'info' => string]
     */
    public function isUserInRemoteWork(object $user, FrozenTime $day): array
    {
        $currentDayStart = $day->startOfDay();
        $dayEnd = $day->endOfDay();
        
        // Vérifier si l'utilisateur a des ranges
        if (!empty($user->ranges)) {
            foreach ($user->ranges as $range) {
                // Vérifier que le range chevauche avec le jour donné
                // Le range chevauche si : date_start <= fin du jour ET date_end >= début du jour
                $rangeStart = $range->date_start instanceof \DateTimeInterface ? $range->date_start : new \Cake\I18n\FrozenTime($range->date_start);
                $rangeEnd = $range->date_end instanceof \DateTimeInterface ? $range->date_end : new \Cake\I18n\FrozenTime($range->date_end);
                
                // Vérifier le chevauchement
                if ($rangeStart <= $dayEnd && $rangeEnd >= $currentDayStart) {
                    // Le range chevauche avec le jour, vérifier l'offre
                    
                    // Vérifier que l'offre est chargée et qu'elle est de type remote_work
                    if (!isset($range->offer) || empty($range->offer)) {
                        continue;
                    }
                    
                    if (!isset($range->offer->offer_type) || $range->offer->offer_type !== 'remote_work') {
                        continue;
                    }
                    
                    $offerName = $range->offer->name;
                    
                    $type = 'flexible';
                    // Si le range a le préfixe AUTO, c'est un range fixe créé automatiquement
                    if (isset($range->comment) && is_string($range->comment) && strpos($range->comment, '[AUTO-TAD]') === 0) {
                        $type = 'fixed';
                    }
                    
                    return [
                        'is_remote' => true,
                        'type' => $type,
                        'info' => ' - ' . $offerName . ' du ' . $rangeStart->i18nFormat('dd/MM HH:mm') . ' au ' . $rangeEnd->i18nFormat('dd/MM HH:mm'),
                        'color' => (string)($range->offer->color ?? ''),
                    ];
                }
            }
        }
        
        // Aucun range de télétravail trouvé pour ce jour
        return [
            'is_remote' => false,
            'type' => null,
            'info' => '',
            'color' => '',
        ];
    }
}
