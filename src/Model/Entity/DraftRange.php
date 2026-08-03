<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * DraftRange Entity
 *
 * @property int $id
 * @property int $job_id
 * @property int $user_id
 * @property int $offer_id
 * @property \Cake\I18n\FrozenTime $date_start
 * @property \Cake\I18n\FrozenTime $date_end
 * @property string|null $comment
 * @property string|null $source
 * @property \Cake\I18n\FrozenTime|null $created
 * @property \Cake\I18n\FrozenTime|null $modified
 *
 * @property \App\Model\Entity\User $user
 * @property \App\Model\Entity\Offer $offer
 * @property \App\Model\Entity\PlanningGenerationJob $planning_generation_job
 */
class DraftRange extends Entity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'job_id' => true,
        'user_id' => true,
        'offer_id' => true,
        'date_start' => true,
        'date_end' => true,
        'comment' => true,
        'source' => true,
        'created' => true,
        'modified' => true,
        'user' => true,
        'offer' => true,
        'planning_generation_job' => true,
    ];
}


