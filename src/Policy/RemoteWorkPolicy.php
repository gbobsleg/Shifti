<?php
declare(strict_types=1);

namespace App\Policy;

use App\Authorization\Capability;
use App\Resource\RemoteWorkResource;
use Authorization\IdentityInterface;

class RemoteWorkPolicy extends AbstractCapabilityPolicy
{
    public function canIndex(IdentityInterface $identity, RemoteWorkResource $resource): bool
    {
        return $this->has($identity, Capability::TELETRAVAIL_GERER);
    }

    public function canConfigure(IdentityInterface $identity, RemoteWorkResource $resource): bool
    {
        return $this->has($identity, Capability::TELETRAVAIL_GERER);
    }

    public function canAjaxGetUserSettings(IdentityInterface $identity, RemoteWorkResource $resource): bool
    {
        return $this->has($identity, Capability::TELETRAVAIL_GERER);
    }

    public function canAddDay(IdentityInterface $identity, RemoteWorkResource $resource): bool
    {
        return $this->has($identity, Capability::TELETRAVAIL_GERER);
    }

    public function canDelete(IdentityInterface $identity, RemoteWorkResource $resource): bool
    {
        return $this->has($identity, Capability::TELETRAVAIL_GERER);
    }
}
