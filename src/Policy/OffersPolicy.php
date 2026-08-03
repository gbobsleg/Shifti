<?php
declare(strict_types=1);

namespace App\Policy;

use Authorization\IdentityInterface;

class OffersPolicy
{
    private function roleId(IdentityInterface $identity): int
    {
        $rid = (int)($identity->get('role_id') ?? 0);
        if (!$rid && method_exists($identity, 'getOriginalData')) {
            $orig = $identity->getOriginalData();
            if (is_object($orig) && isset($orig->role_id)) {
                $rid = (int)$orig->role_id;
            }
        }
        return $rid;
    }

    public function canIndex(IdentityInterface $identity, mixed $resource): bool
    {
        $rid = $this->roleId($identity);
        return $rid === 1; // Admin uniquement
    }

    public function canView(IdentityInterface $identity, mixed $resource): bool
    {
        $rid = $this->roleId($identity);
        return $rid === 1; // Admin uniquement
    }

    public function canAdd(IdentityInterface $identity, mixed $resource): bool
    {
        $rid = $this->roleId($identity);
        return $rid === 1; // Admin uniquement
    }

    public function canEdit(IdentityInterface $identity, mixed $resource): bool
    {
        $rid = $this->roleId($identity);
        return $rid === 1; // Admin uniquement
    }

    public function canDelete(IdentityInterface $identity, mixed $resource): bool
    {
        $rid = $this->roleId($identity);
        return $rid === 1; // Admin uniquement
    }
}


