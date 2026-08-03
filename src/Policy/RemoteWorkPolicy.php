<?php
declare(strict_types=1);

namespace App\Policy;

use Authorization\IdentityInterface;
use App\Resource\RemoteWorkResource;

class RemoteWorkPolicy
{
    /**
     * Vérifie si l'utilisateur peut accéder à l'index (gestion des jours de télétravail)
     */
    public function canIndex(IdentityInterface $identity, RemoteWorkResource $resource): bool
    {
        return $this->roleId($identity) <= 2; // Admin ou Manager (utilise la même logique que manageDays)
    }

    /**
     * Vérifie si l'utilisateur peut configurer le télétravail
     */
    public function canConfigure(IdentityInterface $identity, RemoteWorkResource $resource): bool
    {
        return $this->roleId($identity) <= 2; // Admin ou Manager
    }

    /**
     * Vérifie si l'utilisateur peut récupérer les settings en AJAX
     */
    public function canAjaxGetUserSettings(IdentityInterface $identity, RemoteWorkResource $resource): bool
    {
        return $this->roleId($identity) <= 2; // Admin ou Manager
    }

    /**
     * Vérifie si l'utilisateur peut gérer les jours de TAD flexible
     */
    public function canManageDays(IdentityInterface $identity, RemoteWorkResource $resource): bool
    {
        return $this->roleId($identity) <= 2; // Admin ou Manager
    }

    /**
     * Vérifie si l'utilisateur peut ajouter un jour de TAD flexible
     */
    public function canAddDay(IdentityInterface $identity, RemoteWorkResource $resource): bool
    {
        return $this->roleId($identity) <= 2; // Admin ou Manager
    }

    /**
     * Vérifie si l'utilisateur peut supprimer une configuration
     */
    public function canDelete(IdentityInterface $identity, RemoteWorkResource $resource): bool
    {
        return $this->roleId($identity) === 1; // Admin uniquement
    }

    /**
     * Récupère le role_id de l'identité
     */
    private function roleId(IdentityInterface $identity): int
    {
        if (method_exists($identity, 'get')) {
            $roleId = $identity->get('role_id');
            if ($roleId !== null) {
                return (int)$roleId;
            }
        }

        if (method_exists($identity, 'getOriginalData')) {
            $orig = $identity->getOriginalData();
            if (is_object($orig) && isset($orig->role_id)) {
                return (int)$orig->role_id;
            }
        }

        return 999; // Par défaut, refuser
    }
}
