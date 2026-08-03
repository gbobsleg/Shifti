<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * PlanningGenerationJobDay Entity
 *
 * @property int $id
 * @property int $job_id
 * @property \Cake\I18n\FrozenDate $date
 * @property string $status
 * @property int|null $duration_ms
 * @property string|null $error_message
 * @property string|null $report_json
 * @property \Cake\I18n\FrozenTime|null $created
 * @property \Cake\I18n\FrozenTime|null $modified
 *
 * @property \App\Model\Entity\PlanningGenerationJob $planning_generation_job
 */
class PlanningGenerationJobDay extends Entity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'job_id' => true,
        'date' => true,
        'status' => true,
        'duration_ms' => true,
        'error_message' => true,
        'report_json' => true,
        'created' => true,
        'modified' => true,
        'planning_generation_job' => true,
    ];
}


