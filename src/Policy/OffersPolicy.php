<?php
declare(strict_types=1);

namespace App\Policy;

use App\Authorization\Capability;
use Authorization\IdentityInterface;

class OffersPolicy extends AbstractCapabilityPolicy
{
    public function canIndex(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::OFFRES_GERER);
    }

    public function canView(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::OFFRES_GERER);
    }

    public function canAdd(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::OFFRES_GERER);
    }

    public function canEdit(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::OFFRES_GERER);
    }

    public function canDelete(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::OFFRES_GERER);
    }

    public function canTuneStart(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::OFFRES_OPTIMISER);
    }

    public function canTuneStatus(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::OFFRES_OPTIMISER);
    }

    public function canTuneApply(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::OFFRES_OPTIMISER);
    }

    public function canTuneReject(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::OFFRES_OPTIMISER);
    }

    public function canTuneRollback(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::OFFRES_OPTIMISER);
    }

    public function canTuneCancel(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::OFFRES_OPTIMISER);
    }
}
