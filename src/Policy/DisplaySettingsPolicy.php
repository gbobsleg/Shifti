<?php
declare(strict_types=1);

namespace App\Policy;

use App\Authorization\Capability;
use Authorization\IdentityInterface;

class DisplaySettingsPolicy extends AbstractCapabilityPolicy
{
    public function canIndex(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::REF_AFFICHAGE);
    }

    public function canView(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::REF_AFFICHAGE);
    }

    public function canAdd(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::REF_AFFICHAGE);
    }

    public function canEdit(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::REF_AFFICHAGE);
    }

    public function canDelete(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::REF_AFFICHAGE);
    }
}
