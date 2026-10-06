<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * OfferColorPresetItem Entity
 *
 * @property int $id
 * @property int $preset_id
 * @property int $offer_id
 * @property string|null $family_name
 * @property int|null $family_position
 * @property int|null $hue
 * @property bool $pastel
 * @property int|null $position
 *
 * @property \App\Model\Entity\OfferColorPreset $offer_color_preset
 * @property \App\Model\Entity\Offer $offer
 */
class OfferColorPresetItem extends Entity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'preset_id' => true,
        'offer_id' => true,
        'color' => true,
        'display_order' => true,
        'family_name' => true,
        'family_position' => true,
        'hue' => true,
        'pastel' => true,
        'position' => true,
    ];
}
