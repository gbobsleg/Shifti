<?php
declare(strict_types=1);

namespace App\Policy;

use App\Authorization\Capability;
use Authorization\IdentityInterface;

class BackgroundJobsPolicy extends AbstractCapabilityPolicy
{
    public function canIndex(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::JOBS_SUPERVISER);
    }

    public function canStatus(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::JOBS_SUPERVISER);
    }

    public function canCancelOptuna(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::JOBS_SUPERVISER);
    }
}
