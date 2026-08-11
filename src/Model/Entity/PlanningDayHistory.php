<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * PlanningDayHistory Entity
 *
 * @property int $id
 * @property int $user_id
 * @property \Cake\I18n\Date|\Cake\I18n\FrozenDate $day
 * @property array $snapshot
 * @property string $content_hash
 * @property string $source
 * @property int|null $actor_user_id
 * @property \Cake\I18n\DateTime|\Cake\I18n\FrozenTime|null $created
 *
 * @property \App\Model\Entity\User $user
 * @property \App\Model\Entity\User|null $actor_user
 */
class PlanningDayHistory extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'user_id' => true,
        'day' => true,
        'snapshot' => true,
        'content_hash' => true,
        'source' => true,
        'actor_user_id' => true,
        'created' => true,
        'user' => true,
        'actor_user' => true,
    ];
}
