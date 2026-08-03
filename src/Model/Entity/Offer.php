<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\I18n\FrozenDate;
use Cake\I18n\FrozenTime;
use Cake\ORM\Entity;
use DateTimeInterface;

/**
 * Offer Entity
 *
 * @property int $id
 * @property string $name
 * @property \Cake\I18n\FrozenTime $start_date
 * @property \Cake\I18n\FrozenTime|null $end_date
 * @property string $color
 * @property string $offer_type
 * @property int $display_order
 * @property bool $is_displayed_in_grid
 * @property bool $is_forecastable
 * @property string $default_forecast_method
 * @property bool $equity_enabled
 * @property bool $is_remote_work_compatible
 * @property array|null $prophet_default_settings_json
 * @property \Cake\I18n\FrozenTime|null $created
 * @property \Cake\I18n\FrozenTime|null $modified
 *
 * @property \App\Model\Entity\Range[] $ranges
 */
class Offer extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'name' => true,
        'start_date' => true,
        'end_date' => true,
        'color' => true,
        'offer_type' => true,
        'display_order' => true,
        'is_displayed_in_grid' => true,
        'is_forecastable' => true,
        'default_forecast_method' => true,
        'equity_enabled' => true,
        'is_remote_work_compatible' => true,
        'prophet_default_settings_json' => true,
        'created' => true,
        'modified' => true,
        'ranges' => true,
		'skills' => true,
		'users' => true
    ];

    protected function _setStartDate($value): ?FrozenTime
    {
        if ($value === null || $value === '') {
            return null;
        }

        $dateString = null;

        if ($value instanceof DateTimeInterface) {
            $dateString = $value->format('Y-m-d');
        } elseif ($value instanceof FrozenDate) {
            $dateString = $value->format('Y-m-d');
        } elseif (is_string($value)) {
            $dateString = substr($value, 0, 10);
        }

        if ($dateString === null) {
            return $value;
        }

        return new FrozenTime($dateString . ' 00:00:00');
    }

    protected function _setEndDate($value): ?FrozenTime
    {
        if ($value === null || $value === '') {
            return null;
        }

        $dateString = null;

        if ($value instanceof DateTimeInterface) {
            $dateString = $value->format('Y-m-d');
        } elseif ($value instanceof FrozenDate) {
            $dateString = $value->format('Y-m-d');
        } elseif (is_string($value)) {
            $dateString = substr($value, 0, 10);
        }

        if ($dateString === null) {
            return $value;
        }

        return new FrozenTime($dateString . ' 23:59:00');
    }

    /**
     * Helper methods pour vérifier le type d'offre
     */
    public function isNormal(): bool
    {
        return $this->offer_type === 'normal';
    }

    public function isAbsence(): bool
    {
        return $this->offer_type === 'absence';
    }

    public function isRemoteWork(): bool
    {
        return $this->offer_type === 'remote_work';
    }

    public function isPause(): bool
    {
        return $this->offer_type === 'pause';
    }

    public function isLunch(): bool
    {
        return $this->offer_type === 'lunch';
    }

    /**
     * Retourne le label lisible du type d'offre
     */
    public function getTypeLabel(): string
    {
        $labels = [
            'normal' => 'Normale',
            'absence' => 'Absence',
            'remote_work' => 'Télétravail',
            'pause' => 'Pause',
            'lunch' => 'Repas',
        ];

        return $labels[$this->offer_type] ?? 'Inconnu';
    }
}
