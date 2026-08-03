<?php
declare(strict_types=1);

namespace App\Policy;

use App\Resource\ExcelUploadsResource;
use Authorization\IdentityInterface;

class ExcelUploadsPolicy
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

    public function canUpload(IdentityInterface $user, ExcelUploadsResource $resource): bool
    {
        $rid = $this->roleId($user);
        return $rid === 1 || $rid === 2; // Admin, Manager
    }

    public function canPreview(IdentityInterface $user, ExcelUploadsResource $resource): bool
    {
        $rid = $this->roleId($user);
        return $rid === 1 || $rid === 2; // Admin, Manager
    }

    public function canProcess(IdentityInterface $user, ExcelUploadsResource $resource): bool
    {
        $rid = $this->roleId($user);
        return $rid === 1 || $rid === 2; // Admin, Manager
    }
}

