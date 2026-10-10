<?php
declare(strict_types=1);

namespace App\Policy;

use App\Authorization\Capability;
use Authorization\IdentityInterface;

class WfmSettingsPolicy extends AbstractCapabilityPolicy
{
    /**
     * @param \Authorization\IdentityInterface $identity Identité connectée.
     * @param mixed $resource Ressource WFM.
     * @return bool
     */
    public function canIndex(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::REF_WFM);
    }

    /**
     * @param \Authorization\IdentityInterface $identity Identité connectée.
     * @param mixed $resource Ressource WFM.
     * @return bool
     */
    public function canView(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::REF_WFM);
    }

    /**
     * @param \Authorization\IdentityInterface $identity Identité connectée.
     * @param mixed $resource Ressource WFM.
     * @return bool
     */
    public function canAdd(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::REF_WFM);
    }

    /**
     * @param \Authorization\IdentityInterface $identity Identité connectée.
     * @param mixed $resource Ressource WFM.
     * @return bool
     */
    public function canEdit(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::REF_WFM);
    }

    /**
     * @param \Authorization\IdentityInterface $identity Identité connectée.
     * @param mixed $resource Ressource WFM.
     * @return bool
     */
    public function canDelete(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::REF_WFM);
    }
}
