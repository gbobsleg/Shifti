<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * OfferColorFamily Entity
 *
 * @property int $id
 * @property string $name
 * @property int $position
 * @property int|null $hue
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\OfferColorFamilyOffer[] $offer_color_family_offers
 */
class OfferColorFamily extends Entity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'name' => true,
        'position' => true,
        'hue' => true,
        'offer_color_family_offers' => true,
    ];
}
