<?php
declare(strict_types=1);

namespace App\Policy;

use App\Resource\PlanningEventMappingsResource;
use Authorization\IdentityInterface;

class PlanningEventMappingsPolicy
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

    public function canIndex(IdentityInterface $user, PlanningEventMappingsResource $resource): bool
    {
        $rid = $this->roleId($user);
        return $rid === 1 || $rid === 2; // Admin, Manager
    }

    public function canView(IdentityInterface $user, PlanningEventMappingsResource $resource): bool
    {
        $rid = $this->roleId($user);
        return $rid === 1 || $rid === 2; // Admin, Manager
    }

    public function canAdd(IdentityInterface $user, PlanningEventMappingsResource $resource): bool
    {
        $rid = $this->roleId($user);
        return $rid === 1 || $rid === 2; // Admin, Manager
    }

    public function canEdit(IdentityInterface $user, PlanningEventMappingsResource $resource): bool
    {
        $rid = $this->roleId($user);
        return $rid === 1 || $rid === 2; // Admin, Manager
    }

    public function canDelete(IdentityInterface $user, PlanningEventMappingsResource $resource): bool
    {
        $rid = $this->roleId($user);
        return $rid === 1 || $rid === 2; // Admin, Manager
    }
}

