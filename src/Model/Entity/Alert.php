<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Alert Entity
 *
 * @property int $id
 * @property \Cake\I18n\FrozenTime $date_start
 * @property \Cake\I18n\FrozenTime $date_end
 * @property string $content
 * @property int $priority
 */
class Alert extends Entity
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
        'content' => true,
        'priority' => true,
    ];
}
