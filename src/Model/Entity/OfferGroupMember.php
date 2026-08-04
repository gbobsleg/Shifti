<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * OfferGroupMember Entity
 *
 * @property int $id
 * @property int $offer_group_id
 * @property int $offer_id
 * @property int $display_order
 * @property int|null $split_ratio_percent
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\OfferGroup $offer_group
 * @property \App\Model\Entity\Offer $offer
 */
class OfferGroupMember extends Entity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'offer_group_id' => true,
        'offer_id' => true,
        'display_order' => true,
        'split_ratio_percent' => true,
        'created' => true,
        'modified' => true,
        'offer_group' => true,
        'offer' => true,
    ];
}
