<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * OfferColorPreset Entity
 *
 * @property int $id
 * @property string $name
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\OfferColorPresetItem[] $offer_color_preset_items
 */
class OfferColorPreset extends Entity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'name' => true,
        'offer_color_preset_items' => true,
    ];
}
