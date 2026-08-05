<?php
declare(strict_types=1);

namespace App\Controller;

use App\Resource\BackgroundJobsResource;
use App\Service\BackgroundJobsStatusService;
use Cake\Routing\Router;

/**
 * Console Jobs — agrégation Optuna / prévision / planning.
 */
class BackgroundJobsController extends AppController
{
    /**
     * Page HTML Jobs : actifs (polling) + historique 30 j filtrable / paginé.
     */
    public function index()
    {
        $this->Authorization->authorize(new BackgroundJobsResource(), 'index');
        $this->request->allowMethod(['get']);

        $service = new BackgroundJobsStatusService();
        $filters = [
            'type' => (string)$this->request->getQuery('type', ''),
            'status' => (string)$this->request->getQuery('status', ''),
        ];
        $page = max(1, (int)$this->request->getQuery('page', 1));

        $activeSnapshot = $service->getActiveSnapshot();
        $history = $service->getHistoryPage(
            $filters,
            $page,
            BackgroundJobsStatusService::HISTORY_PAGE_SIZE
        );

        $this->set('jobsSnapshot', $activeSnapshot);
        $this->set('history', $history);
        $this->set('filters', $history['filters']);
        $this->set(
            'cancelOptunaUrlTemplate',
            Router::url(['controller' => 'BackgroundJobs', 'action' => 'cancelOptuna', 0])
        );
    }

    /**
     * Snapshot JSON : actifs + aperçu récents (badge / polling page).
     */
    public function status()
    {
        $this->Authorization->authorize(new BackgroundJobsResource(), 'status');
        $this->request->allowMethod(['get']);

        $snapshot = (new BackgroundJobsStatusService())->getActiveSnapshot();

        return $this->response
            ->withType('application/json')
            ->withStringBody((string)json_encode($snapshot, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Annule un job Optuna queued/running (par id).
     *
     * @param string|null $id ProphetTuningJob id
     */
    public function cancelOptuna($id = null)
    {
        $this->Authorization->authorize(new BackgroundJobsResource(), 'cancelOptuna');
        $this->request->allowMethod(['post']);

        $Jobs = $this->fetchTable('ProphetTuningJobs');
        $result = $Jobs->cancelActiveJob(
            (int)$id,
            'Annulé depuis la console Jobs.'
        );

        $wantsJson = $this->request->is('ajax')
            || str_contains((string)$this->request->getHeaderLine('Accept'), 'application/json')
            || $this->request->getParam('_ext') === 'json';

        if ($wantsJson) {
            return $this->response
                ->withStatus($result['ok'] ? 200 : 409)
                ->withType('application/json')
                ->withStringBody((string)json_encode([
                    'success' => $result['ok'],
                    'message' => $result['message'],
                    'job_id' => $result['job_id'],
                ], JSON_UNESCAPED_UNICODE));
        }

        if ($result['ok']) {
            $this->Flash->success($result['message']);
        } else {
            $this->Flash->error($result['message']);
        }

        return $this->redirect(['action' => 'index']);
    }
}
