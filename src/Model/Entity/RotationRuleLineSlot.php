<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $id
 * @property int $rotation_rule_line_id
 * @property \Cake\I18n\FrozenTime $start_time
 * @property \Cake\I18n\FrozenTime $end_time
 * @property int|null $position
 * @property \App\Model\Entity\RotationRuleLine $rotation_rule_line
 */
class RotationRuleLineSlot extends Entity
{
    protected array $_accessible = [
        'rotation_rule_line_id' => true,
        'start_time' => true,
        'end_time' => true,
        'position' => true,
        'created' => true,
        'modified' => true,
        'rotation_rule_line' => true,
    ];
}
