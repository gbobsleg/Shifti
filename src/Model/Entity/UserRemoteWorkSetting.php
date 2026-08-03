<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\I18n\FrozenDate;
use Cake\ORM\Entity;
use DateTimeInterface;

/**
 * UserRemoteWorkSetting Entity
 *
 * @property int $id
 * @property int $user_id
 * @property string $remote_work_type
 * @property \Cake\I18n\FrozenDate|null $start_date
 * @property \Cake\I18n\FrozenDate|null $end_date
 * @property array|null $fixed_days_json
 * @property int $flexible_days_per_week
 * @property string|null $notes
 * @property \Cake\I18n\FrozenTime|null $created
 * @property \Cake\I18n\FrozenTime|null $modified
 *
 * @property \App\Model\Entity\User $user
 */
class UserRemoteWorkSetting extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'user_id' => true,
        'remote_work_type' => true,
        'start_date' => true,
        'end_date' => true,
        'fixed_days_json' => true,
        'flexible_days_per_week' => true,
        'notes' => true,
        'created' => true,
        'modified' => true,
        'user' => true,
    ];

    /**
     * Mutateur pour encoder automatiquement en JSON
     */
    protected function _setFixedDaysJson($value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return $value;
    }
    
    /**
     * Mutateur pour convertir start_date en FrozenDate
     */
    protected function _setStartDate($value): ?FrozenDate
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof FrozenDate) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return FrozenDate::createFromFormat('Y-m-d', $value->format('Y-m-d'));
        }

        if (is_string($value)) {
            // Format attendu: Y-m-d
            $dateString = substr($value, 0, 10);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateString)) {
                return FrozenDate::parse($dateString);
            }
        }

        return $value;
    }
    
    /**
     * Mutateur pour convertir end_date en FrozenDate
     */
    protected function _setEndDate($value): ?FrozenDate
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof FrozenDate) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return FrozenDate::createFromFormat('Y-m-d', $value->format('Y-m-d'));
        }

        if (is_string($value)) {
            // Format attendu: Y-m-d
            $dateString = substr($value, 0, 10);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateString)) {
                return FrozenDate::parse($dateString);
            }
        }

        return $value;
    }
    
    /**
     * Méthode pour obtenir le JSON décodé (au lieu d'un accesseur automatique)
     * Cela évite que l'accesseur ne décode lors de la sauvegarde
     */
    public function getFixedDaysJsonDecoded(): ?array
    {
        $rawValue = $this->get('fixed_days_json');
        if ($rawValue === null || $rawValue === '') {
            return null;
        }
        
        if (is_string($rawValue)) {
            $decoded = json_decode($rawValue, true);
            return is_array($decoded) ? $decoded : null;
        }
        
        return is_array($rawValue) ? $rawValue : null;
    }

    /**
     * Vérifie si le télétravail est activé
     */
    public function isEnabled(): bool
    {
        return $this->remote_work_type !== 'none';
    }

    /**
     * Vérifie si c'est un télétravail à jours fixes
     */
    public function isFixedDays(): bool
    {
        return $this->remote_work_type === 'fixed_days';
    }

    /**
     * Vérifie si c'est un télétravail flexible
     */
    public function isFlexible(): bool
    {
        return $this->remote_work_type === 'flexible';
    }

    /**
     * Retourne les jours de télétravail (1=lundi, 7=dimanche)
     */
    public function getFixedDays(): array
    {
        if (!$this->isFixedDays()) {
            return [];
        }
        
        $decoded = $this->getFixedDaysJsonDecoded();
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded['days'] ?? [];
    }

    /**
     * Retourne les plages horaires pour les jours fixes
     */
    public function getTimeRanges(): array
    {
        if (!$this->isFixedDays()) {
            return [];
        }
        
        $decoded = $this->getFixedDaysJsonDecoded();
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded['time_ranges'] ?? [];
    }
}
