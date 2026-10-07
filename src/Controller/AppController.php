<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     0.2.9
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App\Controller;

use App\Service\PlanningDayHistoryService;
use Cake\Controller\Controller;
use Cake\Event\EventInterface;
use Cake\Log\Log;
use Throwable;

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @link https://book.cakephp.org/4/en/controllers.html#the-app-controller
 */
class AppController extends Controller
{
    /**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like loading components.
     *
     * e.g. `$this->loadComponent('FormProtection');`
     *
     * @return void
     * @throws \Exception
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Flash');
        $this->loadComponent('Ajax.Ajax');

        // Charger le composant d'authentification natif
        $this->loadComponent('Authentication.Authentication', [
            'unauthenticatedRedirect' => ['controller' => 'Users', 'action' => 'login'],
        ]);

        // Activer l'autorisation (AuthorizationPlugin)
        $this->loadComponent('Authorization.Authorization');

        /*
         * Enable the following component for recommended CakePHP form protection settings.
         * see https://book.cakephp.org/4/en/controllers/components/form-protection.html
         */
        //$this->loadComponent('FormProtection');
    }

    /**
     * Enforce request authorization via RequestPolicy, except for public auth actions.
     *
     * @return void
     */
    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);

        $controller = (string)$this->request->getParam('controller');
        $action = (string)$this->request->getParam('action');
        $plugin = (string)$this->request->getParam('plugin');

        // Actions publiques sans autorisation formelle
        if ($controller === 'Users' && in_array($action, ['login', 'logout'], true)) {
            $this->Authorization->skipAuthorization();
            return;
        }
        // DebugKit
        if ($plugin === 'DebugKit') {
            $this->Authorization->skipAuthorization();
            return;
        }

        // Si non authentifié, on laisse l'Authentication middleware gérer la redirection
        $identity = $this->request->getAttribute('identity') ?? $this->Authentication->getIdentity();
        if (!$identity) {
            $this->Authorization->skipAuthorization();
            return;
        }

        // Pour les utilisateurs authentifiés, on applique la RequestPolicy
        $this->Authorization->authorize($this->request, 'access');
    }

    /**
     * Identifiant de l'utilisateur connecté, ou null s'il est absent.
     */
    protected function currentUserId(): ?int
    {
        $identity = $this->request->getAttribute('identity');
        $actorUserId = null;
        if ($identity) {
            if (method_exists($identity, 'getIdentifier')) {
                $actorUserId = (int)$identity->getIdentifier();
            } elseif (method_exists($identity, 'get')) {
                $actorUserId = (int)$identity->get('id');
            } elseif (method_exists($identity, 'getOriginalData')) {
                $orig = $identity->getOriginalData();
                if (is_object($orig) && isset($orig->id)) {
                    $actorUserId = (int)$orig->id;
                }
            }
        }
        if ($actorUserId === null || $actorUserId <= 0) {
            return null;
        }

        return $actorUserId;
    }

    /**
     * @param list<array{user_id:int, day:string}> $pairs
     */
    protected function captureRangeBaseline(array $pairs): void
    {
        if ($pairs === []) {
            return;
        }
        try {
            (new PlanningDayHistoryService())->captureBaseline($pairs);
        } catch (Throwable $exception) {
            Log::error('PlanningDayHistory (baseline) échoué: ' . $exception->getMessage());
        }
    }

    /**
     * @param list<array{user_id:int, day:string}> $pairs
     */
    protected function recordRangeHistory(array $pairs, string $source): void
    {
        if ($pairs === []) {
            return;
        }
        try {
            (new PlanningDayHistoryService())->recordPairs($pairs, $source, $this->currentUserId());
        } catch (Throwable $exception) {
            Log::error('PlanningDayHistory (' . $source . ') échoué: ' . $exception->getMessage());
        }
    }
}
