<?php
declare(strict_types=1);

namespace App\Policy;

use Authorization\IdentityInterface;

class BackgroundJobsPolicy
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

    private function isAdminOrManager(IdentityInterface $identity): bool
    {
        $rid = $this->roleId($identity);

        return $rid === 1 || $rid === 2;
    }

    public function canIndex(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->isAdminOrManager($identity);
    }

    public function canStatus(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->isAdminOrManager($identity);
    }
}
