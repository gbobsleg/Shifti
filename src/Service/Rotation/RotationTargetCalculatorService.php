<?php
declare(strict_types=1);

namespace App\Service\Rotation;

use Cake\I18n\FrozenDate;
use Cake\I18n\FrozenTime;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Service de calcul des cibles de rotation pour les agents.
 * 
 * Gère deux types de périodes :
 * - WEEKLY : Cible hebdomadaire avec proratisation selon absences JOURNÉE COMPLÈTE uniquement
 * - MONTHLY : Cible mensuelle avec prise en compte de l'historique réel (Ranges)
 */
class RotationTargetCalculatorService
{
    use LocatorAwareTrait;

    /**
     * Heure de début de journée (depuis wfm_settings)
     * Format "HH:MM:SS"
     */
    private string $dayStartTime = '09:00:00';

    /**
     * Heure de fin de journée (depuis wfm_settings)
     * Format "HH:MM:SS"
     */
    private string $dayEndTime = '17:00:00';

    /**
     * Configure les paramètres de journée depuis wfm_settings.
     * 
     * @param string $dayStartTime Heure de début (ex: "09:00:00")
     * @param string $dayEndTime Heure de fin (ex: "17:00:00")
     * @return self
     */
    public function setDayBoundaries(string $dayStartTime, string $dayEndTime): self
    {
        $this->dayStartTime = $dayStartTime;
        $this->dayEndTime = $dayEndTime;
        return $this;
    }

    /**
     * Calcule le nombre effectif de blocs à planifier pour un agent sur une période donnée.
     * 
     * @param int $userId ID de l'utilisateur
     * @param string $rotationRuleId UUID de la règle de rotation
     * @param FrozenDate $periodStart Date de début de la période de génération
     * @param FrozenDate $periodEnd Date de fin de la période de génération
     * @param int|null $lineTargetCount Cible de la ligne quota (sinon rotation_rules.target_count)
     * @return int Nombre de blocs à planifier (arrondi)
     */
    public function calculateTargetForUser(
        int $userId,
        string $rotationRuleId,
        FrozenDate $periodStart,
        FrozenDate $periodEnd,
        ?FrozenDate $contractStart = null,
        ?FrozenDate $contractEnd = null,
        ?int $lineTargetCount = null
    ): int {
        $RotationRules = $this->fetchTable('RotationRules');
        $UsersRotationRules = $this->fetchTable('UsersRotationRules');

        // Récupérer la règle
        $rule = $RotationRules->get($rotationRuleId, ['contain' => ['Offers']]);
        if (!$rule) {
            return 0;
        }

        // Récupérer l'association user/rule
        $userRule = $UsersRotationRules->find()
            ->where([
                'user_id' => $userId,
                'rotation_rule_id' => $rotationRuleId,
            ])
            ->first();

        // Déterminer la cible (override ou valeur par défaut)
        $target = $userRule && $userRule->target_count_override !== null
            ? (int)$userRule->target_count_override
            : (int)($lineTargetCount ?? $rule->target_count);

        if ($target <= 0) {
            return 0;
        }

        // Algorithme selon le type de période
        if ($rule->period_type === 'WEEKLY') {
            return $this->calculateWeeklyTarget($userId, $target, $periodStart, $periodEnd, $contractStart, $contractEnd);
        } elseif ($rule->period_type === 'MONTHLY') {
            return $this->calculateMonthlyTarget(
                $userId,
                $target,
                $rule->offer_id,
                $periodStart,
                $periodEnd,
                $rule->time_window_start,
                $rule->time_window_end,
                $rule->shift_duration,
                $contractStart,
                $contractEnd
            );
        }

        return 0;
    }

    /**
     * Calcule la cible hebdomadaire avec proratisation selon les absences.
     * 
     * Formule : Round(Cible * (Jours Ouvrés Période - Absences) / Jours Ouvrés Période)
     */
    private function calculateWeeklyTarget(
        int $userId,
        int $target,
        FrozenDate $periodStart,
        FrozenDate $periodEnd,
        ?FrozenDate $contractStart = null,
        ?FrozenDate $contractEnd = null
    ): int {
        // Calculer les jours ouvrés de la période (restreinte par le contrat)
        $workingDays = $this->getWorkingDays($periodStart, $periodEnd, $contractStart, $contractEnd);
        
        if ($workingDays <= 0) {
            return 0;
        }

        // Récupérer les absences de l'agent sur la période
        $absenceDays = $this->getAbsenceDays($userId, $periodStart, $periodEnd);

        // Calculer le ratio
        $ratio = ($workingDays - $absenceDays) / $workingDays;
        if ($ratio < 0) {
            $ratio = 0;
        }

        // Retourner arrondi
        return (int)round($target * $ratio);
    }

    /**
     * Calcule la cible mensuelle avec prise en compte de l'historique réel.
     * 
     * Algorithme :
     * 1. Déterminer le début du mois courant
     * 2. Calculer les shifts déjà effectués depuis le début du mois jusqu'à la veille
     * 3. Calculer le Reste à Faire = Max(0, Cible Mensuelle - Shifts Déjà Faits)
     * 4. Appliquer la proratisation sur le Reste à Faire par rapport aux jours restants
     */
    private function calculateMonthlyTarget(
        int $userId,
        int $targetMonthly,
        ?int $offerId,
        FrozenDate $periodStart,
        FrozenDate $periodEnd,
        string $windowStart,
        string $windowEnd,
        int $minDuration,
        ?FrozenDate $contractStart = null,
        ?FrozenDate $contractEnd = null
    ): int {
        // 1. Déterminer le début du mois courant
        $monthStart = $periodStart->startOfMonth();

        // 2. Calculer les shifts déjà effectués (finalisés) depuis le début du mois jusqu'à la veille
        $beforePeriodStart = $periodStart->subDays(1);
        $shiftsAlreadyDone = $this->countCompletedShiftsInMonth(
            $userId,
            $offerId,
            $monthStart,
            $beforePeriodStart,
            $windowStart,
            $windowEnd,
            $minDuration
        );

        // 3. Calculer le Reste à Faire
        $remainingTarget = max(0, $targetMonthly - $shiftsAlreadyDone);

        if ($remainingTarget <= 0) {
            return 0;
        }

        // 4. Appliquer la proratisation sur le Reste à Faire
        // Calculer les jours ouvrés restants dans la période de génération (restreint par contrat)
        $workingDaysRemaining = $this->getWorkingDays($periodStart, $periodEnd, $contractStart, $contractEnd);
        
        // Calculer les jours ouvrés totaux du mois (aussi restreint par contrat ?)
        // Pour être juste, on devrait calculer les jours ouvrés du mois *sous contrat*.
        $monthEnd = $monthStart->endOfMonth();
        $workingDaysTotalMonth = $this->getWorkingDays($monthStart, $monthEnd, $contractStart, $contractEnd);

        if ($workingDaysTotalMonth <= 0) {
            return 0;
        }

        // Calculer le ratio
        $ratio = $workingDaysRemaining / $workingDaysTotalMonth;
        if ($ratio < 0) {
            $ratio = 0;
        }

        // Retourner arrondi
        return (int)round($remainingTarget * $ratio);
    }

    /**
     * Compte les jours ouvrés (exclut les weekends).
     * 
     * @param FrozenDate $start Date de début
     * @param FrozenDate $end Date de fin (inclusive)
     * @param FrozenDate|null $contractStart Début du contrat (optionnel)
     * @param FrozenDate|null $contractEnd Fin du contrat (optionnel)
     * @return int Nombre de jours ouvrés
     */
    private function getWorkingDays(FrozenDate $start, FrozenDate $end, ?FrozenDate $contractStart = null, ?FrozenDate $contractEnd = null): int
    {
        // Restreindre la période [start, end] à l'intersection avec [contractStart, contractEnd]
        if ($contractStart && $contractStart > $start) {
            $start = $contractStart;
        }
        if ($contractEnd && $contractEnd < $end) {
            $end = $contractEnd;
        }

        if ($start > $end) {
            return 0;
        }

        $count = 0;
        $current = $start;
        
        while ($current <= $end) {
            if (!$current->isWeekend()) {
                $count++;
            }
            $current = $current->addDays(1);
        }
        
        return $count;
    }

    /**
     * Compte les jours d'absence JOURNÉE COMPLÈTE de l'agent sur la période.
     * 
     * Une absence est considérée "journée complète" uniquement si :
     * - L'heure de début == day_start_time (wfm_settings)
     * - L'heure de fin == day_end_time (wfm_settings)
     * 
     * Les réunions partielles (ex: 13h-15h) ne sont PAS comptées.
     * Le solveur Python gérera ces cas via le système de shortfall.
     * 
     * @param int $userId ID de l'utilisateur
     * @param FrozenDate $start Date de début
     * @param FrozenDate $end Date de fin (inclusive)
     * @return int Nombre de jours d'absence journée complète
     */
    private function getAbsenceDays(int $userId, FrozenDate $start, FrozenDate $end): int
    {
        $Ranges = $this->fetchTable('Ranges');
        $Offers = $this->fetchTable('Offers');

        // Recuperer les IDs des offres de type 'absence' ou 'meeting'
        $absenceOfferIds = $Offers->find()
            ->select(['id'])
            ->where(['offer_type IN' => ['absence', 'meeting']])
            ->all()
            ->extract('id')
            ->toList();

        if (empty($absenceOfferIds)) {
            return 0;
        }

        // Récupérer les ranges d'absence qui chevauchent la période
        $startTime = new FrozenTime($start->format('Y-m-d 00:00:00'));
        $endTime = new FrozenTime($end->format('Y-m-d 23:59:59'));

        $absenceRanges = $Ranges->find()
            ->where([
                'user_id' => $userId,
                'offer_id IN' => $absenceOfferIds,
                'date_start <=' => $endTime,
                'date_end >=' => $startTime,
            ])
            ->all();

        // Compter les jours uniques d'absence JOURNÉE COMPLÈTE uniquement
        $fullDayAbsences = [];
        
        // Normaliser les heures de référence (sans secondes pour tolérance)
        $refStartHHMM = substr($this->dayStartTime, 0, 5); // "09:00"
        $refEndHHMM = substr($this->dayEndTime, 0, 5);     // "17:00"
        
        foreach ($absenceRanges as $range) {
            $rangeStart = new FrozenTime($range->date_start);
            $rangeEnd = new FrozenTime($range->date_end);
            
            // Extraire les heures de l'absence (HH:MM)
            $absenceStartHHMM = $rangeStart->format('H:i');
            $absenceEndHHMM = $rangeEnd->format('H:i');
            
            // Vérifier si c'est une absence "journée complète"
            // Tolérance : on compare HH:MM (ignore les secondes)
            $isFullDay = ($absenceStartHHMM === $refStartHHMM && $absenceEndHHMM === $refEndHHMM);
            
            if (!$isFullDay) {
                // Absence partielle (ex: réunion 13h-15h) → Ne pas compter
                // Le solveur Python gérera via le système de shortfall
                continue;
            }
            
            // C'est une journée complète → Compter chaque jour concerné
            $current = $rangeStart->startOfDay();
            $rangeEndDay = $rangeEnd->startOfDay();
            
            while ($current <= $rangeEndDay) {
                $dateKey = $current->format('Y-m-d');
                // Vérifier que le jour est dans la période et n'est pas un weekend
                $date = new FrozenDate($dateKey);
                if ($date >= $start && $date <= $end && !$date->isWeekend()) {
                    $fullDayAbsences[$dateKey] = true;
                }
                $current = $current->addDays(1);
            }
        }

        return count($fullDayAbsences);
    }

    /**
     * Compte les shifts finalisés dans Ranges pour l'agent, l'offre, la période et la fenêtre horaire.
     * 
     * Un shift est compté si :
     * - date_start et date_end sont dans la plage [monthStart, beforePeriodStart]
     * - La durée du range est >= minDuration
     * - L'heure de début est dans la fenêtre [windowStart, windowEnd]
     * 
     * @param int $userId ID de l'utilisateur
     * @param int|null $offerId ID de l'offre (null si règle générique)
     * @param FrozenDate $monthStart Début du mois
     * @param FrozenDate $beforePeriodStart Veille du début de la période de génération
     * @param string $windowStart Heure de début de la fenêtre (format "HH:MM:SS")
     * @param string $windowEnd Heure de fin de la fenêtre (format "HH:MM:SS")
     * @param int $minDuration Durée minimale en minutes
     * @return int Nombre de shifts déjà effectués
     */
    private function countCompletedShiftsInMonth(
        int $userId,
        ?int $offerId,
        FrozenDate $monthStart,
        FrozenDate $beforePeriodStart,
        string $windowStart,
        string $windowEnd,
        int $minDuration
    ): int {
        $Ranges = $this->fetchTable('Ranges');

        $query = $Ranges->find()
            ->where([
                'user_id' => $userId,
                'date_start >=' => $monthStart->format('Y-m-d 00:00:00'),
                'date_start <' => $beforePeriodStart->format('Y-m-d 23:59:59'),
            ]);

        if ($offerId !== null) {
            $query->where(['offer_id' => $offerId]);
        }

        $ranges = $query->all();

        $count = 0;
        foreach ($ranges as $range) {
            $startTime = new FrozenTime($range->date_start);
            $endTime = new FrozenTime($range->date_end);

            // Vérifier que la durée est >= minDuration
            $durationMinutes = (int)(($endTime->getTimestamp() - $startTime->getTimestamp()) / 60);
            if ($durationMinutes < $minDuration) {
                continue;
            }

            // Vérifier que l'heure de début est dans la fenêtre
            $startHour = $startTime->format('H:i:s');
            if ($startHour >= $windowStart && $startHour <= $windowEnd) {
                $count++;
            }
        }

        return $count;
    }
}
