<?php
declare(strict_types=1);

namespace App\Policy;

use App\Service\Authorization\PermissionService;
use Authorization\IdentityInterface;

abstract class AbstractCapabilityPolicy
{
    private ?PermissionService $permissions;

    public function __construct(?PermissionService $permissions = null)
    {
        $this->permissions = $permissions;
    }

    protected function has(IdentityInterface $identity, string $capability): bool
    {
        return $this->permissions()->has($identity, $capability);
    }

    private function permissions(): PermissionService
    {
        return $this->permissions ??= new PermissionService();
    }
}
