<?php
/**
 * @var \App\View\AppView $this
 * @var array $jobsSnapshot
 * @var array $history
 * @var array $filters
 * @var string $cancelOptunaUrlTemplate
 */
$this->assign('title', 'Jobs');
$this->extend('/layout/TwitterBootstrap/dashtron_fullwidth');

$jobsSnapshot = $jobsSnapshot ?? [
    'success' => true,
    'active_count' => 0,
    'by_type' => ['optuna' => 0, 'forecast' => 0, 'planning' => 0],
    'items' => [],
];
$history = $history ?? [
    'items' => [],
    'page' => 1,
    'limit' => 25,
    'total' => 0,
    'page_count' => 1,
    'filters' => ['type' => '', 'status' => ''],
    'history_days' => 30,
];
$filters = $filters ?? ($history['filters'] ?? ['type' => '', 'status' => '']);
$activeItems = $jobsSnapshot['items'] ?? [];
$historyItems = $history['items'] ?? [];
$byType = $jobsSnapshot['by_type'] ?? ['optuna' => 0, 'forecast' => 0, 'planning' => 0];
$statusUrl = $this->Url->build(['controller' => 'BackgroundJobs', 'action' => 'status', '_ext' => 'json']);
$cancelOptunaUrlTemplate = $cancelOptunaUrlTemplate
    ?? $this->Url->build(['controller' => 'BackgroundJobs', 'action' => 'cancelOptuna', 0]);
$csrfToken = (string)$this->request->getAttribute('csrfToken');
$historyDays = (int)($history['history_days'] ?? 30);

$typeLabels = [
    'optuna' => 'Optuna',
    'forecast' => 'Prévision',
    'planning' => 'Planning',
];
$typeOptions = [
    '' => 'Tous les types',
    'optuna' => 'Optuna',
    'forecast' => 'Prévision',
    'planning' => 'Planning',
];
$statusOptions = [
    '' => 'Tous les statuts',
    'completed' => 'completed',
    'failed' => 'failed',
    'cancelled' => 'cancelled',
    'finished' => 'finished',
    'finished_with_errors' => 'finished_with_errors',
    'error' => 'error',
    'infeasible' => 'infeasible',
];
$statusBadge = static function (string $status): string {
    return match ($status) {
        'running' => 'badge-primary',
        'queued' => 'badge-warning',
        'completed', 'finished' => 'badge-success',
        'failed', 'error', 'infeasible', 'finished_with_errors' => 'badge-danger',
        'cancelled' => 'badge-secondary',
        default => 'badge-secondary',
    };
};

$this->Html->script('background-jobs-page', [
    'block' => true,
    'timestamp' => 'force',
]);

$queryBase = array_filter([
    'type' => $filters['type'] ?? '',
    'status' => $filters['status'] ?? '',
], static fn ($v) => $v !== '' && $v !== null);
?>

<style>
.table-row-active {
    background-color: rgba(255, 193, 7, 0.06);
}
.bj-counters .card {
    border-width: 2px;
}
</style>

<div class="card"
     id="background-jobs-root"
     data-url-status="<?= h($statusUrl) ?>"
     data-url-cancel-optuna="<?= h($cancelOptunaUrlTemplate) ?>"
     data-csrf-token="<?= h($csrfToken) ?>">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-list-task text-primary"></i>
            File d’attente / Jobs
        </h3>
        <span class="small text-muted">
            Actifs actualisés : <span data-bj-updated>—</span>
            · polling 6 s
        </span>
    </div>
    <div class="card-body">
        <p class="small text-muted mb-3">
            Actifs (<code>queued</code> / <code>running</code>) en live.
            Historique <?= (int)$historyDays ?> j filtrable / paginé (hors polling).
        </p>

        <div class="row mb-4 bj-counters">
            <div class="col-md-3 mb-2">
                <div class="card border-warning h-100">
                    <div class="card-body text-center py-3">
                        <div class="text-muted small">Actifs</div>
                        <h3 class="mb-0" data-bj-active-count><?= (int)($jobsSnapshot['active_count'] ?? 0) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-2">
                <div class="card border-secondary h-100">
                    <div class="card-body text-center py-3">
                        <div class="text-muted small">Optuna (actifs)</div>
                        <h3 class="mb-0" data-bj-type-optuna><?= (int)($byType['optuna'] ?? 0) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-2">
                <div class="card border-info h-100">
                    <div class="card-body text-center py-3">
                        <div class="text-muted small">Prévisions (actifs)</div>
                        <h3 class="mb-0" data-bj-type-forecast><?= (int)($byType['forecast'] ?? 0) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-2">
                <div class="card border-success h-100">
                    <div class="card-body text-center py-3">
                        <div class="text-muted small">Plannings (actifs)</div>
                        <h3 class="mb-0" data-bj-type-planning><?= (int)($byType['planning'] ?? 0) ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <h5 class="mb-2">
            <i class="bi bi-lightning-charge text-warning"></i>
            Actifs
        </h5>
        <div class="table-responsive mb-4">
            <table class="table table-sm table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Type</th>
                        <th>Cible</th>
                        <th>Statut</th>
                        <th>Progression</th>
                        <th>Démarré</th>
                        <th>Terminé</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody data-bj-active-tbody>
                    <?php if ($activeItems === []): ?>
                        <tr>
                            <td colspan="7" class="text-muted text-center py-4">
                                Aucun job actif.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($activeItems as $item): ?>
                            <?= $this->element('BackgroundJobs/job_row', compact('item', 'typeLabels', 'statusBadge')) ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <h5 class="mb-2">
            <i class="bi bi-clock-history text-secondary"></i>
            Historique (<?= (int)$historyDays ?> j)
        </h5>

        <?= $this->Form->create(null, [
            'type' => 'get',
            'class' => 'filters-toolbar mb-3 p-3 bg-light border rounded',
            'url' => ['controller' => 'BackgroundJobs', 'action' => 'index'],
        ]) ?>
            <div class="row align-items-end">
                <div class="col-md-3 mb-2">
                    <label class="form-label small text-muted mb-1">Type</label>
                    <?= $this->Form->select('type', $typeOptions, [
                        'class' => 'form-control form-control-sm',
                        'value' => $filters['type'] ?? '',
                    ]) ?>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label small text-muted mb-1">Statut</label>
                    <?= $this->Form->select('status', $statusOptions, [
                        'class' => 'form-control form-control-sm',
                        'value' => $filters['status'] ?? '',
                    ]) ?>
                </div>
                <div class="col-md-3 mb-2">
                    <?= $this->Form->button('<i class="bi bi-search"></i> Filtrer', [
                        'type' => 'submit',
                        'class' => 'btn btn-sm btn-primary',
                        'escapeTitle' => false,
                    ]) ?>
                    <?= $this->Html->link('Réinitialiser', ['action' => 'index'], [
                        'class' => 'btn btn-sm btn-outline-secondary ml-1',
                    ]) ?>
                </div>
                <div class="col-md-3 mb-2 text-md-right">
                    <span class="small text-muted">
                        <?= (int)$history['total'] ?> résultat(s)
                        · page <?= (int)$history['page'] ?> / <?= (int)$history['page_count'] ?>
                    </span>
                </div>
            </div>
        <?= $this->Form->end() ?>

        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Type</th>
                        <th>Cible</th>
                        <th>Statut</th>
                        <th>Progression</th>
                        <th>Démarré</th>
                        <th>Terminé</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($historyItems === []): ?>
                        <tr>
                            <td colspan="7" class="text-muted text-center py-4">
                                Aucun job terminé sur <?= (int)$historyDays ?> j
                                <?= ($filters['type'] ?? '') !== '' || ($filters['status'] ?? '') !== ''
                                    ? ' pour ces filtres'
                                    : '' ?>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($historyItems as $item): ?>
                            <?= $this->element('BackgroundJobs/job_row', compact('item', 'typeLabels', 'statusBadge')) ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ((int)$history['page_count'] > 1): ?>
            <?php
            $cur = (int)$history['page'];
            $pages = (int)$history['page_count'];
            $mkUrl = function (int $p) use ($queryBase) {
                return $this->Url->build([
                    'controller' => 'BackgroundJobs',
                    'action' => 'index',
                    '?' => array_merge($queryBase, ['page' => $p]),
                ]);
            };
            ?>
            <nav class="mt-3" aria-label="Pagination historique jobs">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <li class="page-item <?= $cur <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $cur <= 1 ? '#' : h($mkUrl($cur - 1)) ?>">Précédente</a>
                    </li>
                    <?php
                    $start = max(1, $cur - 2);
                    $end = min($pages, $cur + 2);
                    for ($p = $start; $p <= $end; $p++):
                    ?>
                        <li class="page-item <?= $p === $cur ? 'active' : '' ?>">
                            <a class="page-link" href="<?= h($mkUrl($p)) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $cur >= $pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $cur >= $pages ? '#' : h($mkUrl($cur + 1)) ?>">Suivante</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>
