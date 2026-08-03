<?php
declare(strict_types=1);

namespace App\View;
use Cake\View\View;

/**
 * Application View
 */
class AppView extends View
{
    /**
     * Initialization hook method.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadHelper('Grids');

        $this->loadHelper('Flash', [
            'className' => 'Cake\View\Helper\FlashHelper'
        ]);

        $this->loadHelper('Form', [
            'className' => 'BootstrapUI.Form',
            'bootstrapVersion' => '4',
            'useCdn' => false,
            'loadCss' => false,
            'loadJs' => false
        ]);

        $this->loadHelper('Paginator', [
            'className' => 'BootstrapUI.Paginator',
            'bootstrapVersion' => '4',
            'useCdn' => false,
            'loadCss' => false,
            'loadJs' => false
        ]);

        $this->loadHelper('DayOfWeek', [
            'className' => 'App\View\Helper\DayOfWeekHelper'
        ]);

        // Authentication Identity for user data and access to underlying identity
        $this->loadHelper('Authentication.Identity');
        // Authorization: on utilise l'identité décorée ($this->request->getAttribute('identity')->can()) côté vues
    }
}
