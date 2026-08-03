<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * PlanningEventMapping Entity
 *
 * @property int $id
 * @property string|null $keywords
 * @property string|null $color_code
 * @property int $offer_id
 * @property int $priority
 * @property \Cake\I18n\FrozenTime $created
 * @property \Cake\I18n\FrozenTime $modified
 *
 * @property \App\Model\Entity\Offer $offer
 */
class PlanningEventMapping extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'keywords' => true,
        'color_code' => true,
        'offer_id' => true,
        'priority' => true,
        'created' => true,
        'modified' => true,
        'offer' => true,
    ];
}
