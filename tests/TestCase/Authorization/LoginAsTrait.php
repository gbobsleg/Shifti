<?php
declare(strict_types=1);

namespace App\Test\TestCase\Authorization;

use Cake\ORM\TableRegistry;

/**
 * Ouvre une session authentifiée pour les tests d'intégration.
 *
 * La classe utilisatrice doit aussi utiliser IntegrationTestTrait.
 */
trait LoginAsTrait
{
    protected function loginAs(int $userId): void
    {
        $user = TableRegistry::getTableLocator()->get('Users')->get($userId);
        $this->session(['Auth' => $user]);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
    }
}
