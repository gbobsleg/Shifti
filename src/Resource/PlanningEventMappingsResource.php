<?php
declare(strict_types=1);

namespace App\Resource;

use Authorization\IdentityInterface;

class PlanningEventMappingsResource
{
    public function canIndex(IdentityInterface $user, PlanningEventMappingsResource $resource): bool
    {
        return true;
    }

    public function canView(IdentityInterface $user, PlanningEventMappingsResource $resource): bool
    {
        return true;
    }

    public function canAdd(IdentityInterface $user, PlanningEventMappingsResource $resource): bool
    {
        return true;
    }

    public function canEdit(IdentityInterface $user, PlanningEventMappingsResource $resource): bool
    {
        return true;
    }

    public function canDelete(IdentityInterface $user, PlanningEventMappingsResource $resource): bool
    {
        return true;
    }
}


