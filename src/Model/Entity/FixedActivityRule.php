<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class FixedActivityRule extends Entity
{
    protected array $_accessible = [
        'offer_id' => true,
        'start_time' => true,
        'end_time' => true,
        'quantity' => true,
        'priority' => true,
        'active' => true,
        'days_of_week' => true,
        'is_splittable' => true,
        'equity_enabled' => true,
        'equity_strength' => true,
        'lunch_overlap_allowed' => true,
        'lunch_attach_mode' => true,
        'site_mode' => true,
        'sites' => true,
        'fixed_activity_blocks' => true,
        'incompatible_offers' => true,
        'offer' => true,
        'equity_group_id' => true,
        'created' => true,
        'modified' => true,
    ];
}


