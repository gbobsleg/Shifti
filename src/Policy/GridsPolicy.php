<?php
declare(strict_types=1);

namespace App\Policy;

use App\Authorization\Capability;
use Authorization\IdentityInterface;

class GridsPolicy extends AbstractCapabilityPolicy
{
    public function canIndex(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::PLANNING_CONSULTER);
    }

    public function canGetUsersBySite(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::PLANNING_CONSULTER);
    }

    public function canPlannedSeries(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::PLANNING_INDICATEURS);
    }

    public function canNeedSeries(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::PLANNING_INDICATEURS);
    }

    public function canAdd(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::PLANNING_MODIFIER);
    }

    public function canDayHistory(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::PLANNING_MODIFIER);
    }

    public function canRestoreDayHistory(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::PLANNING_MODIFIER);
    }
}
