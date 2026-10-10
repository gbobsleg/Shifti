<?php
declare(strict_types=1);

namespace App\Policy;

use App\Authorization\Capability;
use Authorization\IdentityInterface;

class HistoricalDataPolicy extends AbstractCapabilityPolicy
{
    public function canImport(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::HISTORIQUE_IMPORTER);
    }

    public function canVisualize(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::HISTORIQUE_CONSULTER);
    }

    public function canGetData(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->has($identity, Capability::HISTORIQUE_CONSULTER);
    }
}
