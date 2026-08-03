<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * UserAvailability Entity
 *
 * @property int $id
 * @property int $user_id
 * @property int $day_of_week
 * @property \Cake\I18n\Time $availability_start_time
 * @property \Cake\I18n\Time $availability_end_time
 * @property \Cake\I18n\Time|null $earliest_end_time
 *
 * @property \App\Model\Entity\User $user
 */
class UserAvailability extends Entity
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
        'user_id' => true,
        'day_of_week' => true,
        'availability_start_time' => true,
        'availability_end_time' => true,
        'earliest_end_time' => true,
        'user' => true,
    ];
}
