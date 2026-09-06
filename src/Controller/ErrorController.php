<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Event\EventInterface;
use Cake\Http\Exception\MissingControllerException;
use Cake\Http\Exception\NotFoundException;
use Cake\Routing\Exception\MissingRouteException;
use Cake\View\View;

/**
 * Error Handling Controller
 *
 * Controller used by ExceptionRenderer to render error responses.
 */
class ErrorController extends AppController
{
    /**
     * @return void
     */
    public function initialize(): void
    {
        $this->loadComponent('RequestHandler');
        $this->viewBuilder()->setClassName(View::class);
    }

    /**
     * @param \Cake\Event\EventInterface $event Event.
     * @return \Cake\Http\Response|null|void
     */
    public function beforeFilter(EventInterface $event)
    {
    }

    /**
     * @param \Cake\Event\EventInterface $event Event.
     * @return \Cake\Http\Response|null|void
     */
    public function beforeRender(EventInterface $event)
    {
        parent::beforeRender($event);
        $this->viewBuilder()->setTemplatePath('Error');

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

        if ($status === 403) {
            $this->viewBuilder()->setTemplate('error403');

            return;
        }

        $error = $this->viewBuilder()->getVar('error');
        $isNotFound = $status === 404
            || $error instanceof NotFoundException
            || $error instanceof RecordNotFoundException
            || $error instanceof MissingControllerException
            || $error instanceof MissingRouteException;

        if ($isNotFound) {
            $this->viewBuilder()->setTemplate('error400');
            $this->response = $this->response->withStatus(404);
        }
    }

    /**
     * @param \Cake\Event\EventInterface $event Event.
     * @return \Cake\Http\Response|null|void
     */
    public function afterFilter(EventInterface $event)
    {
    }
}
