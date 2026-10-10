<?php
declare(strict_types=1);

namespace App\Service\Authorization;

use App\Authorization\Capability;

/**
 * Calcule le périmètre de données visible pour une identité.
 * null signifie « pas de restriction ».
 */
class PerimeterService
{
    public function __construct(private ?PermissionService $permissions = null)
    {
        $this->permissions ??= new PermissionService();
    }

    /**
     * @return list<int>|null
     */
    public function visibleSiteIds(mixed $identity): ?array
    {
        if ($this->permissions->has($identity, Capability::PLANNING_CONSULTER_TOUS_SITES)) {
            return null;
        }

        $siteId = $this->siteId($identity);
        if ($siteId <= 0) {
            return [];
        }

        return [$siteId];
    }

    /**
     * @return list<int>|null
     */
    public function visibleAlertPriorities(mixed $identity): ?array
    {
        if ($this->permissions->has($identity, Capability::ALERTES_CONSULTER_TOUTES)) {
            return null;
        }

        return [3];
    }

    private function siteId(mixed $identity): int
    {
        if (is_object($identity) && method_exists($identity, 'get')) {
            $siteId = $identity->get('site_id');
            if ($siteId) {
                return (int)$siteId;
            }
        }

        if (is_object($identity) && method_exists($identity, 'getOriginalData')) {
            $original = $identity->getOriginalData();
            if (is_object($original) && isset($original->site_id)) {
                return (int)$original->site_id;
            }
            if (is_array($original) && isset($original['site_id'])) {
                return (int)$original['site_id'];
            }
        }

        return 0;
    }
}
