<?php
declare(strict_types=1);

namespace App\Policy;

use App\Authorization\Capability;
use App\Resource\DataExportsResource;
use Authorization\IdentityInterface;

class DataExportsPolicy extends AbstractCapabilityPolicy
{
    public function canIndex(IdentityInterface $identity, DataExportsResource $resource): bool
    {
        return $this->has($identity, Capability::HISTORIQUE_CONSULTER);
    }

    public function canDownload(IdentityInterface $identity, DataExportsResource $resource): bool
    {
        return $this->has($identity, Capability::HISTORIQUE_CONSULTER);
    }
}
