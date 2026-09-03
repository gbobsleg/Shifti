<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * RotationRule Entity
 *
 * @property string $id
 * @property string $name
 * @property int|null $offer_id
 * @property string $period_type
 * @property int $target_count
 * @property int $shift_duration
 * @property \Cake\I18n\FrozenTime $time_window_start
 * @property \Cake\I18n\FrozenTime $time_window_end
 * @property \Cake\I18n\FrozenTime|null $created
 * @property \Cake\I18n\FrozenTime|null $modified
 * @property bool $exclusive_day
 *
 * @property \App\Model\Entity\Offer|null $offer
 * @property \App\Model\Entity\UsersRotationRule[] $users_rotation_rules
 * @property \App\Model\Entity\RotationRuleLine[] $rotation_rule_lines
 */
class RotationRule extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'id' => true,
        'name' => true,
        'offer_id' => true,
        'period_type' => true,
        'target_count' => true,
        'shift_duration' => true,
        'time_window_start' => true,
        'time_window_end' => true,
        'exclusive_day' => true,
        'created' => true,
        'modified' => true,
        'offer' => true,
        'users_rotation_rules' => true,
        'rotation_rule_lines' => true,
    ];
}
