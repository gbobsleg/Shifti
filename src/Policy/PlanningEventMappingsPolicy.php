<?php
declare(strict_types=1);

namespace App\Policy;

use App\Authorization\Capability;
use App\Resource\PlanningEventMappingsResource;
use Authorization\IdentityInterface;

class PlanningEventMappingsPolicy extends AbstractCapabilityPolicy
{
    public function canIndex(IdentityInterface $identity, PlanningEventMappingsResource $resource): bool
    {
        return $this->has($identity, Capability::REF_CORRESPONDANCES);
    }

    public function canView(IdentityInterface $identity, PlanningEventMappingsResource $resource): bool
    {
        return $this->has($identity, Capability::REF_CORRESPONDANCES);
    }

    public function canAdd(IdentityInterface $identity, PlanningEventMappingsResource $resource): bool
    {
        return $this->has($identity, Capability::REF_CORRESPONDANCES);
    }

    public function canEdit(IdentityInterface $identity, PlanningEventMappingsResource $resource): bool
    {
        return $this->has($identity, Capability::REF_CORRESPONDANCES);
    }

    public function canDelete(IdentityInterface $identity, PlanningEventMappingsResource $resource): bool
    {
        return $this->has($identity, Capability::REF_CORRESPONDANCES);
    }
}
