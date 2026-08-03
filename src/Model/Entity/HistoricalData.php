<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * HistoricalData Entity
 *
 * @property int $id
 * @property int $offer_id
 * @property \Cake\I18n\FrozenTime $datetime_interval
 * @property int $call_volume
 * @property int $avg_handle_time_seconds
 *
 * @property \App\Model\Entity\Offer $offer
 */
class HistoricalData extends Entity
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
        'offer_id' => true,
        'datetime_interval' => true,
        'call_volume' => true,
        'avg_handle_time_seconds' => true,
        'offer' => true,
    ];
}
