<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $id
 * @property string $rotation_rule_id
 * @property string $line_type
 * @property int|null $offer_id
 * @property int $sort_order
 * @property int|null $target_count
 * @property int|null $shift_duration
 * @property \Cake\I18n\FrozenTime|null $time_window_start
 * @property \Cake\I18n\FrozenTime|null $time_window_end
 * @property bool $fit_need_curve
 * @property int|null $quantity
 * @property bool $equity_enabled
 * @property bool $same_person_day_slots
 * @property array|null $days_of_week
 * @property int|null $quota_flag
 * @property \App\Model\Entity\RotationRule $rotation_rule
 * @property \App\Model\Entity\Offer|null $offer
 * @property \App\Model\Entity\RotationRuleLineSlot[] $rotation_rule_line_slots
 */
class RotationRuleLine extends Entity
{
    protected array $_accessible = [
        'id' => true,
        'rotation_rule_id' => true,
        'line_type' => true,
        'offer_id' => true,
        'sort_order' => true,
        'target_count' => true,
        'shift_duration' => true,
        'time_window_start' => true,
        'time_window_end' => true,
        'fit_need_curve' => true,
        'quantity' => true,
        'equity_enabled' => true,
        'same_person_day_slots' => true,
        'days_of_week' => true,
        'quota_flag' => true,
        'created' => true,
        'modified' => true,
        'rotation_rule' => true,
        'offer' => true,
        'rotation_rule_line_slots' => true,
    ];
}
