<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Jeton du brouillon de couleurs, une seule ligne.
 *
 * @property int $id
 * @property int $revision
 * @property int|null $preset_id
 *
 * @property \App\Model\Entity\OfferColorPreset|null $offer_color_preset
 */
class OfferColorMetadata extends Entity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'revision' => true,
        'preset_id' => true,
    ];
}
