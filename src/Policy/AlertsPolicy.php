<?php
declare(strict_types=1);

namespace App\Policy;

use App\Authorization\Capability;
use Authorization\IdentityInterface;

class AlertsPolicy extends AbstractCapabilityPolicy
{
    public function canIndex(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::ALERTES_GERER);
    }

    public function canView(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::ALERTES_GERER);
    }

    public function canAdd(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::ALERTES_GERER);
    }

    public function canEdit(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::ALERTES_GERER);
    }

    public function canDelete(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::ALERTES_GERER);
    }
}
