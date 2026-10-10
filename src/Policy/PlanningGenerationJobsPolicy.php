<?php
declare(strict_types=1);

namespace App\Policy;

use App\Authorization\Capability;
use Authorization\IdentityInterface;

class PlanningGenerationJobsPolicy extends AbstractCapabilityPolicy
{
    public function canIndex(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::PLANNING_GENERER);
    }

    public function canAdd(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::PLANNING_GENERER);
    }

    public function canEdit(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::PLANNING_GENERER);
    }

    public function canView(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::PLANNING_GENERER);
    }

    public function canDelete(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::PLANNING_GENERER);
    }

    public function canStatus(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::PLANNING_GENERER);
    }

    public function canReport(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::PLANNING_GENERER);
    }

    public function canEquityReport(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::PLANNING_GENERER);
    }

    public function canDraft(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::PLANNING_GENERER);
    }

    public function canSaveDraft(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::PLANNING_GENERER);
    }

    public function canRetry(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::PLANNING_GENERER);
    }

    public function canPublish(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::PLANNING_PUBLIER);
    }

    public function canClearDraft(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::PLANNING_PUBLIER);
    }
}
