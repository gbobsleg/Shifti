<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * UsersRotationRule Entity
 *
 * @property int $user_id
 * @property string $rotation_rule_id
 * @property int|null $target_count_override
 *
 * @property \App\Model\Entity\User $user
 * @property \App\Model\Entity\RotationRule $rotation_rule
 */
class UsersRotationRule extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'user_id' => true,
        'rotation_rule_id' => true,
        'target_count_override' => true,
        'user' => true,
        'rotation_rule' => true,
    ];
}
