<?php
declare(strict_types=1);

namespace App\View\Helper;

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

            return $ThTime;
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

            return $ThTime;
        }
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
        $date = clone $beginOfDay;

        $start = $date->setTime($this->gridStartHour, 0);
        $timestampStart = $start->getTimestamp();
        $end = $date->setTime($this->gridEndHour - 1, 45);
        $timestampEnd = $end->getTimestamp();

        $timeSlots = '';
        
        // Calculer les indisponibilités basées sur user_availabilities
        $unavailableSlots = $this->getUserUnavailableSlots($user, $beginOfDay);

        while ($timestampStart <= $timestampEnd) {
            $start = $start->addMinutes(15);
            $newTimestampStart = $start->getTimestamp();
            
            // PRIORITÉ 1 : Vérifier si un range existe déjà pour ce créneau
            $existingRange = null;
            $remoteWorkCandidate = null;
            $ranges = $this->getRangesForDisplay($user, $rangesProperty);
            foreach ($ranges as $range) {
                if (
                    ($range->date_start->getTimestamp() <= $timestampStart)
                    && ($timestampStart < $range->date_end->getTimestamp())
                ) {
                    // Ne pas laisser le télétravail masquer une activité "réelle".
                    // On retient le télétravail comme candidat de secours, et on continue à chercher
                    // un range non-remote_work qui couvrirait aussi ce créneau.
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
            
            // Si un range existe, l'afficher (même si offer_id=0 pour "blanc")
            if ($existingRange) {
                // Vérifier que ce n'est pas une offre système à exclure (télétravail uniquement)
                $isExcludedOffer = isset($existingRange->offer) && isset($existingRange->offer->offer_type) 
                    && $existingRange->offer->offer_type === 'remote_work';
                
                if ($isExcludedOffer) {
                    // Télétravail : ne pas afficher (géré ailleurs avec icône)
                    $Td = '<td class="td_quarter" style="background:white" ';
                    $Td .= 'data-user-id="' . $user->id . '" ';
                    $Td .= 'data-start="' . date('Y-m-d H:i:s', $timestampStart) . '" ';
                    $Td .= 'data-end="' . date('Y-m-d H:i:s', $newTimestampStart) . '" ';
                    $Td .= 'data-offer-id="' . $existingRange->offer_id . '" ';
                    $Td .= 'data-range-id="' . $existingRange->id . '"';
                    $Td .= '>';
                } elseif (isset($existingRange->offer) && $existingRange->offer_id != '0' && $existingRange->offer_id != 0) {
                    // Range avec offre : afficher avec la couleur de l'offre
                    $Td = '<td class="td_quarter" style="background-color:' . $existingRange['offer']['color'] . '"
                    data-toggle="tooltip" data-placement="top" title="' . h($existingRange['offer']['name']) . ' de ' . $existingRange->date_start->i18nFormat('HH:mm') . ' à ' . $existingRange->date_end->i18nFormat('HH:mm') . ' - ' . ($existingRange->comment ?? '') . '"
                    data-user-id="' . $user->id . '"
                    data-start="' . date('Y-m-d H:i:s', $timestampStart) . '"
                    data-end="' . date('Y-m-d H:i:s', $newTimestampStart) . '"
                    data-offer-id="' . $existingRange->offer_id . '"
                    data-range-id="' . $existingRange->id . '"
                    >';
                } else {
                    // Range avec offer_id=0 ou sans offre : case blanche (débloquée manuellement ou range vide)
                    $Td = '<td class="td_quarter" style="background:white" ';
                    $Td .= 'data-user-id="' . $user->id . '" ';
                    $Td .= 'data-start="' . date('Y-m-d H:i:s', $timestampStart) . '" ';
                    $Td .= 'data-end="' . date('Y-m-d H:i:s', $newTimestampStart) . '" ';
                    $Td .= 'data-offer-id="' . ($existingRange->offer_id ?? '0') . '" ';
                    $Td .= 'data-range-id="' . $existingRange->id . '"';
                    $Td .= '>';
                }
                $timeSlots .= $Td . '</td>';
                $timestampStart = $newTimestampStart;
                continue;
            }
            
            // PRIORITÉ 2 : Si aucun range n'existe, vérifier si créneau est dans une zone d'indispo
            $isUnavailable = false;
            foreach ($unavailableSlots as [$slotStart, $slotEnd]) {
                if ($timestampStart >= $slotStart && $timestampStart < $slotEnd) {
                    $isUnavailable = true;
                    break;
                }
            }

            // Si indisponible, afficher en grisé
            if ($isUnavailable) {
                $Td = '<td class="td_quarter td_unavailable" 
                    style="background: repeating-linear-gradient(45deg, #f0f0f0, #f0f0f0 10px, #e0e0e0 10px, #e0e0e0 20px);" 
                    data-toggle="tooltip" 
                    data-placement="top"
                    title="Agent non disponible selon son contrat"
                    data-user-id="' . $user->id . '" 
                    data-start="' . date('Y-m-d H:i:s', $timestampStart) . '" 
                    data-end="' . date('Y-m-d H:i:s', $newTimestampStart) . '" 
                    data-offer-id="0" 
                    data-range-id="">
                    </td>';
                $timeSlots .= $Td;
                $timestampStart = $newTimestampStart;
                continue;
            }

            // PRIORITÉ 3 : Case disponible (blanc)
            $Td = '<td class="td_quarter" style="background:white" ';
            $Td .= 'data-user-id="' . $user->id . '" ';
            $Td .= 'data-start="' . date('Y-m-d H:i:s', $timestampStart) . '" ';
            $Td .= 'data-end="' . date('Y-m-d H:i:s', $newTimestampStart) . '" ';
            $Td .= 'data-offer-id="0" ';
            $Td .= 'data-range-id=""';
            $Td .= '>';

            $Td .= '</td>';
            $timeSlots .= $Td;
            $timestampStart = $newTimestampStart;
        }

        return $timeSlots;
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
        $currentDayStart = $date->startOfDay();

        // Vérifier si agent en télétravail ce jour
        $remoteWorkInfo = $this->isUserInRemoteWork($user, $date);
        if ($remoteWorkInfo['is_remote']) {
            $bg = 'bg-info text-white';
            $icon = '<i class="bi-house-door-fill pr-2" style="color: black"></i>';
            $homework = $remoteWorkInfo['info'];
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

        // Cellule Site — bordures G/D colorées en vrai border (pas box-shadow inset :
        // l'inset est coupé par les traits haut/bas ; border-right inline bat le !important CSS)
        $TrUser .= '<th scope="row" class="th_row text-center site-column" style="border-left: 4px solid ' . $textColor . ' !important; border-right: 4px solid ' . $textColor . ' !important; color: ' . $textColor . '; font-weight: 600; width: 1%; white-space: nowrap;">
        ' . h($user['site']['name']) . '
        </th>';

        // Cellule Agent (sans trait coloré — réservé à la colonne Site)
        $TrUser .= '<th scope="row" class="th_row ' . $bg . '" data-toggle="tooltip" data-placement="right" title="Site ' . h($user['site']['number']) . ' - ' . h($user->user_code) . $homework . '">
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
                        'info' => ' - ' . $offerName . ' du ' . $rangeStart->i18nFormat('dd/MM HH:mm') . ' au ' . $rangeEnd->i18nFormat('dd/MM HH:mm')
                    ];
                }
            }
        }
        
        // Aucun range de télétravail trouvé pour ce jour
        return [
            'is_remote' => false,
            'type' => null,
            'info' => ''
        ];
    }
}
