<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Range Entity
 *
 * @property int $id
 * @property \Cake\I18n\FrozenTime $start_date
 * @property \Cake\I18n\FrozenTime $end_date
 * @property int $user_id
 * @property int $offer_id
 * @property string|null $comment
 * @property \Cake\I18n\FrozenTime|null $created
 * @property \Cake\I18n\FrozenTime|null $modified
 *
 * @property \App\Model\Entity\User $user
 * @property \App\Model\Entity\Offer $offer
 */
class Range extends Entity
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
        'date_start' => true,
        'date_end' => true,
        'user_id' => true,
        'offer_id' => true,
        'comment' => true,
        'created' => true,
        'modified' => true,
        'user' => true,
        'offer' => true,
    ];
}
