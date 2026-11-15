<?php
declare(strict_types=1);

namespace App\Policy;

use App\Authorization\Capability;
use Authorization\IdentityInterface;

class CalendarExceptionsPolicy extends AbstractCapabilityPolicy
{
    public function canIndex(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::REF_WFM);
    }

    public function canAdd(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::REF_WFM);
    }

    public function canEdit(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::REF_WFM);
    }

    public function canDelete(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::REF_WFM);
    }

    public function canSeed(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::REF_WFM);
    }

    public function canToggle(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::REF_WFM);
    }
}
