<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class FixedActivityBlock extends Entity
{
    protected array $_accessible = [
        'fixed_activity_rule_id' => true,
        'start_time' => true,
        'end_time' => true,
        'position' => true,
        'created' => true,
        'modified' => true,
        'fixed_activity_rule' => true,
    ];
}


