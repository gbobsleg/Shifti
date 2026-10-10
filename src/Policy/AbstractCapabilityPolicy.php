<?php
declare(strict_types=1);

namespace App\Policy;

use App\Service\Authorization\PermissionService;
use Authorization\IdentityInterface;

abstract class AbstractCapabilityPolicy
{
    private ?PermissionService $permissions;

    /**
     * @param \App\Service\Authorization\PermissionService|null $permissions Service injecté, ou null pour le charger.
     */
    public function __construct(?PermissionService $permissions = null)
    {
        $this->permissions = $permissions;
    }

    /**
     * Indique si l'identité possède la capacité.
     *
     * @param \Authorization\IdentityInterface $identity Identité connectée.
     * @param string $capability Code de capacité.
     * @return bool
     */
    protected function has(IdentityInterface $identity, string $capability): bool
    {
        return $this->permissions()->has($identity, $capability);
    }

    /**
     * Service de permissions, créé au premier usage.
     *
     * @return \App\Service\Authorization\PermissionService
     */
    private function permissions(): PermissionService
    {
        return $this->permissions ??= new PermissionService();
    }
}
