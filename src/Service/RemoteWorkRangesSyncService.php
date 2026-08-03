<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\UserRemoteWorkSetting;
use App\Model\Table\OffersTable;
use App\Model\Table\RangesTable;
use App\Model\Table\UsersTable;
use Cake\I18n\FrozenDate;
use Cake\I18n\FrozenTime;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Service de synchronisation des ranges de télétravail
 */
class RemoteWorkRangesSyncService
{
    use LocatorAwareTrait;
    
    private const AUTO_COMMENT_PREFIX = '[AUTO-TAD]';

    /**
     * Récupère l'ID de l'offre de type remote_work
     */
    public function getRemoteWorkOfferId(?OffersTable $offersTable = null): ?int
    {
        if ($offersTable === null) {
            $tableLocator = $this->getTableLocator();
            $offersTable = $tableLocator->get('Offers');
        }
        
        $offer = $offersTable->find('ByType', ['type' => 'remote_work'])->first();
        
        if (!$offer) {
            Log::error('Aucune offre de type remote_work trouvée dans la base de données');
            return null;
        }
        
        return $offer->id;
    }

    /**
     * Vérifie si un range doit être créé pour un jour donné
     */
    public function shouldCreateRangeForDay(FrozenDate $day, UserRemoteWorkSetting $setting): bool
    {
        // Vérifier les dates de validité
        if ($setting->start_date && $day < $setting->start_date) {
            return false;
        }
        
        if ($setting->end_date && $day > $setting->end_date) {
            return false;
        }
        
        // Vérifier si c'est un jour fixe configuré
        if (!$setting->isFixedDays()) {
            return false;
        }
        
        $fixedDays = $setting->getFixedDays();
        $dayOfWeek = (int)$day->format('N'); // 1=lundi, 7=dimanche
        
        return in_array($dayOfWeek, $fixedDays, true);
    }

    /**
     * Synchronise les ranges de télétravail pour un utilisateur
     * 
     * @param int $userId ID de l'utilisateur
     * @param UserRemoteWorkSetting $setting Configuration du télétravail
     * @return array Statistiques de synchronisation ['created' => int, 'deleted' => int, 'errors' => array]
     */
    public function syncUserRemoteWorkRanges(int $userId, UserRemoteWorkSetting $setting): array
    {
        $stats = ['created' => 0, 'deleted' => 0, 'errors' => []];
        
        // Si pas de télétravail fixe, supprimer tous les ranges auto-créés
        if (!$setting->isFixedDays()) {
            return $this->deleteAutoCreatedRanges($userId, $stats);
        }
        
        $tableLocator = $this->getTableLocator();
        $offersTable = $tableLocator->get('Offers');
        $rangesTable = $tableLocator->get('Ranges');
        
        $remoteWorkOfferId = $this->getRemoteWorkOfferId();
        if (!$remoteWorkOfferId) {
            $stats['errors'][] = 'Offre de télétravail introuvable';
            return $stats;
        }
        
        // Déterminer la période de synchronisation
        $startDate = $setting->start_date ? clone $setting->start_date : FrozenDate::today();
        if ($setting->end_date) {
            $endDate = clone $setting->end_date;
        } else {
            $endDate = clone $startDate;
            $endDate = $endDate->addMonths(6); // 6 mois par défaut si pas de fin
        }
        
        // Récupérer les plages horaires configurées
        $timeRanges = $setting->getTimeRanges();
        if (empty($timeRanges)) {
            $stats['errors'][] = 'Aucune plage horaire configurée pour le télétravail fixe';
            return $stats;
        }
        
        $timeRange = $timeRanges[0]; // Utiliser la première plage horaire
        $timeStart = $timeRange['start'] ?? '09:00:00';
        $timeEnd = $timeRange['end'] ?? '17:00:00';
        
        // Récupérer les ranges existants auto-créés pour cet utilisateur
        $existingRanges = $rangesTable->find()
            ->where([
                'user_id' => $userId,
                'offer_id' => $remoteWorkOfferId,
                'comment LIKE' => self::AUTO_COMMENT_PREFIX . '%'
            ])
            ->all();
        
        $existingRangesByDate = [];
        foreach ($existingRanges as $range) {
            $dayKey = $range->date_start->format('Y-m-d');
            $existingRangesByDate[$dayKey] = $range;
        }
        
        // Parcourir chaque jour dans la période
        $currentDate = clone $startDate;
        $daysToCreate = [];
        $daysToKeep = [];
        
        while ($currentDate <= $endDate) {
            $dayKey = $currentDate->format('Y-m-d');
            
            if ($this->shouldCreateRangeForDay($currentDate, $setting)) {
                if (isset($existingRangesByDate[$dayKey])) {
                    // Range existe déjà, le garder
                    $daysToKeep[] = $dayKey;
                } else {
                    // Range à créer
                    $daysToCreate[] = clone $currentDate;
                }
            }
            
            $currentDate = $currentDate->modify('+1 day');
        }
        
        // Créer les ranges manquants
        foreach ($daysToCreate as $day) {
            try {
                $rangeStart = FrozenTime::parse($day->format('Y-m-d') . ' ' . $timeStart);
                $rangeEnd = FrozenTime::parse($day->format('Y-m-d') . ' ' . $timeEnd);
                
                $range = $rangesTable->newEntity([
                    'user_id' => $userId,
                    'offer_id' => $remoteWorkOfferId,
                    'date_start' => $rangeStart,
                    'date_end' => $rangeEnd,
                    'comment' => self::AUTO_COMMENT_PREFIX . ' ' . date('Y-m-d H:i:s')
                ]);
                
                if ($rangesTable->save($range)) {
                    $stats['created']++;
                } else {
                    $stats['errors'][] = 'Erreur création range pour ' . $day->format('Y-m-d') . ': ' . json_encode($range->getErrors());
                }
            } catch (\Exception $e) {
                $stats['errors'][] = 'Exception création range pour ' . $day->format('Y-m-d') . ': ' . $e->getMessage();
            }
        }
        
        // Supprimer les ranges auto-créés qui ne correspondent plus à la config
        foreach ($existingRangesByDate as $dayKey => $range) {
            if (!in_array($dayKey, $daysToKeep, true)) {
                try {
                    if ($rangesTable->delete($range)) {
                        $stats['deleted']++;
                    }
                } catch (\Exception $e) {
                    $stats['errors'][] = 'Exception suppression range pour ' . $dayKey . ': ' . $e->getMessage();
                }
            }
        }
        
        return $stats;
    }

    /**
     * Supprime tous les ranges auto-créés pour un utilisateur
     */
    private function deleteAutoCreatedRanges(int $userId, array $stats): array
    {
        $tableLocator = $this->getTableLocator();
        $rangesTable = $tableLocator->get('Ranges');
        $remoteWorkOfferId = $this->getRemoteWorkOfferId();
        
        if (!$remoteWorkOfferId) {
            return $stats;
        }
        
        $rangesToDelete = $rangesTable->find()
            ->where([
                'user_id' => $userId,
                'offer_id' => $remoteWorkOfferId,
                'comment LIKE' => self::AUTO_COMMENT_PREFIX . '%'
            ])
            ->all();
        
        foreach ($rangesToDelete as $range) {
            try {
                if ($rangesTable->delete($range)) {
                    $stats['deleted']++;
                }
            } catch (\Exception $e) {
                $stats['errors'][] = 'Exception suppression range ID ' . $range->id . ': ' . $e->getMessage();
            }
        }
        
        return $stats;
    }
}
