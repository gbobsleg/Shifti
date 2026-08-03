<?php
declare(strict_types=1);

namespace App\Policy;

use Authorization\IdentityInterface;

class SitesPolicy
{
    private function isAdmin(IdentityInterface $identity): bool
    {
        $rid = (int)($identity->get('role_id') ?? 0);
        if (!$rid && method_exists($identity, 'getOriginalData')) {
            $orig = $identity->getOriginalData();
            if (is_object($orig) && isset($orig->role_id)) {
                $rid = (int)$orig->role_id;
            }
        }
        return $rid === 1;
    }

    public function canIndex(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->isAdmin($identity);
    }
    public function canView(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->isAdmin($identity);
    }
    public function canAdd(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->isAdmin($identity);
    }
    public function canEdit(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->isAdmin($identity);
    }
    public function canDelete(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->isAdmin($identity);
    }
}


