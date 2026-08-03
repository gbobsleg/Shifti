<?php
declare(strict_types=1);

namespace App\Controller;

use App\Resource\BackgroundJobsResource;
use App\Service\BackgroundJobsStatusService;

/**
 * Console Jobs — agrégation Optuna / prévision / planning.
 */
class BackgroundJobsController extends AppController
{
    /**
     * Page HTML Jobs (polling 5–8 s côté JS).
     */
    public function index()
    {
        $this->Authorization->authorize(new BackgroundJobsResource(), 'index');
        $this->request->allowMethod(['get']);

        $snapshot = (new BackgroundJobsStatusService())->getSnapshot();
        $this->set('jobsSnapshot', $snapshot);
    }

    /**
     * Snapshot JSON pour page Jobs + badge navbar.
     */
    public function status()
    {
        $this->Authorization->authorize(new BackgroundJobsResource(), 'status');
        $this->request->allowMethod(['get']);

        $snapshot = (new BackgroundJobsStatusService())->getSnapshot();

        return $this->response
            ->withType('application/json')
            ->withStringBody((string)json_encode($snapshot, JSON_UNESCAPED_UNICODE));
    }
}
