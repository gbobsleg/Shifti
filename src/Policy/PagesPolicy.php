<?php
declare(strict_types=1);

namespace App\Policy;

use Authorization\IdentityInterface;

class PagesPolicy
{
    public function canAdmin(IdentityInterface $identity, mixed $resource): bool
    {
        $roleId = (int)($identity->get('role_id') ?? 0);
        if ($roleId === 1) {
            return true;
        }

        $roleName = null;
        if (method_exists($identity, 'getOriginalData')) {
            $roleName = $identity->getOriginalData()->role->name ?? null;
        }

        return $roleName === 'Administrateur';
    }
}


