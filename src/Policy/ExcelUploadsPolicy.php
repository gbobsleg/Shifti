<?php
declare(strict_types=1);

namespace App\Policy;

use App\Authorization\Capability;
use App\Resource\ExcelUploadsResource;
use Authorization\IdentityInterface;

class ExcelUploadsPolicy extends AbstractCapabilityPolicy
{
    public function canUpload(IdentityInterface $identity, ExcelUploadsResource $resource): bool
    {
        return $this->has($identity, Capability::IMPORT_PLANNING);
    }

    public function canPreview(IdentityInterface $identity, ExcelUploadsResource $resource): bool
    {
        return $this->has($identity, Capability::IMPORT_PLANNING);
    }

    public function canProcess(IdentityInterface $identity, ExcelUploadsResource $resource): bool
    {
        return $this->has($identity, Capability::IMPORT_PLANNING);
    }
}
