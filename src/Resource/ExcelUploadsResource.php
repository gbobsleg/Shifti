<?php
declare(strict_types=1);

namespace App\Resource;

use Authorization\AuthorizationServiceInterface;
use Authorization\IdentityInterface;
use Authorization\Policy\Exception\MissingPolicyException;
use Authorization\Policy\ResultInterface;

class ExcelUploadsResource
{
    public function canUpload(IdentityInterface $user, ExcelUploadsResource $resource): bool
    {
        return true;
    }

    public function canPreview(IdentityInterface $user, ExcelUploadsResource $resource): bool
    {
        return true;
    }

    public function canProcess(IdentityInterface $user, ExcelUploadsResource $resource): bool
    {
        return true;
    }
}


