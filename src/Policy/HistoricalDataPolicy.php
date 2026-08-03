<?php
declare(strict_types=1);

namespace App\Policy;

use Authorization\IdentityInterface;

/**
 * Policy pour HistoricalData
 * 
 * Gère les autorisations d'accès aux données historiques
 */
class HistoricalDataPolicy
{
    /**
     * Extrait le role_id de l'identité
     *
     * @param IdentityInterface $identity
     * @return int
     */
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

    /**
     * Import CSV : Administrateurs uniquement
     *
     * @param IdentityInterface $identity
     * @param mixed $resource
     * @return bool
     */
    public function canImport(IdentityInterface $identity, mixed $resource): bool
    {
        return $this->roleId($identity) === 1; // Admin uniquement
    }

    /**
     * Visualisation : Administrateurs et Managers
     *
     * @param IdentityInterface $identity
     * @param mixed $resource
     * @return bool
     */
    public function canVisualize(IdentityInterface $identity, mixed $resource): bool
    {
        $rid = $this->roleId($identity);
        return $rid === 1 || $rid === 2; // Admin ou Manager
    }

    /**
     * Récupération de données via AJAX : Administrateurs et Managers
     *
     * @param IdentityInterface $identity
     * @param mixed $resource
     * @return bool
     */
    public function canGetData(IdentityInterface $identity, mixed $resource): bool
    {
        $rid = $this->roleId($identity);
        return $rid === 1 || $rid === 2; // Admin ou Manager
    }
}

