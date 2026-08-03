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
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         3.3.4
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App\Controller;

use Cake\Event\EventInterface;

/**
 * Error Handling Controller
 *
 * Controller used by ExceptionRenderer to render error responses.
 */
class ErrorController extends AppController
{
    /**
     * Initialization hook method.
     *
     * @return void
     */
    public function initialize(): void
    {
        $this->loadComponent('RequestHandler');
    }

    /**
     * beforeFilter callback.
     *
     * @param \Cake\Event\EventInterface $event Event.
     * @return \Cake\Http\Response|null|void
     */
    public function beforeFilter(EventInterface $event)
    {
    }

    /**
     * beforeRender callback.
     *
     * @param \Cake\Event\EventInterface $event Event.
     * @return \Cake\Http\Response|null|void
     */
    public function beforeRender(EventInterface $event)
    {
        parent::beforeRender($event);
        $this->viewBuilder()->setTemplatePath('Error');

        // JSON/AJAX forbidden handling
        $acceptsJson = $this->request->accepts('application/json');
        $isAjax = $this->request->is('ajax');
        $status = $this->response->getStatusCode();
        if (($acceptsJson || $isAjax) && ($status === 403)) {
            $message = $this->viewBuilder()->getVar('message') ?? 'Forbidden';
            $this->viewBuilder()->setClassName('Json');
            $this->set(['success' => false, 'message' => (string)$message]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);
            return;
        }

        // HTML 403 → template dédié avec design applicatif
        if ($status === 403) {
            $this->viewBuilder()->setTemplate('error403');
        }
    }

    /**
     * afterFilter callback.
     *
     * @param \Cake\Event\EventInterface $event Event.
     * @return \Cake\Http\Response|null|void
     */
    public function afterFilter(EventInterface $event)
    {
    }
}
