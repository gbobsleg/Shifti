<?php
declare(strict_types=1);

namespace App\Policy;

use App\Authorization\Capability;
use Authorization\IdentityInterface;

class PagesPolicy extends AbstractCapabilityPolicy
{
    public function canAdmin(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::ADMINISTRATION_ACCEDER);
    }
}
