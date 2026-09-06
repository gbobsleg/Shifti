<?php
declare(strict_types=1);

namespace App\Error;

use Cake\Controller\Controller;
use Cake\Error\Renderer\WebExceptionRenderer;
use Cake\View\View;
use Throwable;

class AppExceptionRenderer extends WebExceptionRenderer
{
    protected function _getController(): Controller
    {
        $controller = parent::_getController();
        $controller->viewBuilder()
            ->setClassName(View::class)
            ->setTemplatePath('Error')
            ->setLayout('error');

        return $controller;
    }

    protected function _template(Throwable $exception, string $method, int $code): string
    {
        if ($code === 403) {
            return $this->template = 'error403';
        }

        if ($code < 500) {
            return $this->template = 'error400';
        }

        return $this->template = 'error500';
    }
}
