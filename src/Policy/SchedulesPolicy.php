<?php
declare(strict_types=1);

namespace App\Policy;

use App\Authorization\Capability;
use Authorization\IdentityInterface;

class SchedulesPolicy extends AbstractCapabilityPolicy
{
    public function canGenerate(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::PLANNING_GENERER);
    }
}
