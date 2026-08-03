<?php
declare(strict_types=1);

namespace App\Policy;

use Authorization\Policy\RequestPolicyInterface;
use Authorization\Policy\ResultInterface;
use Authorization\Policy\Result;
use Cake\Http\ServerRequest;

/**
 * Politique d'autorisation basée sur les rôles pour les requêtes HTTP
 */
class RequestPolicy implements RequestPolicyInterface
{
    /**
     * Vérifier si une requête est autorisée selon le rôle de l'utilisateur
     */
    public function canAccess($identity, ServerRequest $request): ResultInterface
    {
        if (!$identity) {
            return new Result(false, 'Non authentifié');
        }

        // Modèle simple: toute requête d'un utilisateur authentifié est autorisée.
        // Les règles métier fines sont gérées par les policies de ressource.
        return new Result(true, 'Autorisé');
    }
}

