<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * OfferColorFamilyOffer Entity
 *
 * @property int $id
 * @property int $family_id
 * @property int $offer_id
 * @property int $position
 *
 * @property \App\Model\Entity\OfferColorFamily $offer_color_family
 * @property \App\Model\Entity\Offer $offer
 */
class OfferColorFamilyOffer extends Entity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'family_id' => true,
        'offer_id' => true,
        'position' => true,
    ];
}
