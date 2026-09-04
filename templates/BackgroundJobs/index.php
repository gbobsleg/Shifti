<?php
/**
 * @var \App\View\AppView $this
 * @var array $jobsSnapshot
 * @var array $history
 * @var array $filters
 * @var string $cancelOptunaUrlTemplate
 */
$this->assign('title', 'Tâches');
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
    'completed' => 'Terminé',
    'failed' => 'Échec',
    'cancelled' => 'Annulé',
    'finished' => 'Terminé',
    'finished_with_errors' => 'Terminé avec erreurs',
    'error' => 'Erreur',
    'infeasible' => 'Infaisable',
];
$statusBadge = static function (string $status): string {
    return match ($status) {
        'running' => 'bg-primary',
        'queued' => 'bg-warning',
        'completed', 'finished' => 'bg-success',
        'failed', 'error', 'infeasible', 'finished_with_errors' => 'bg-danger',
        'cancelled' => 'bg-secondary',
        default => 'bg-secondary',
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
</style>

<div class="crud-app background-jobs index content"
     id="background-jobs-root"
     data-url-status="<?= h($statusUrl) ?>"
     data-url-cancel-optuna="<?= h($cancelOptunaUrlTemplate) ?>"
     data-csrf-token="<?= h($csrfToken) ?>">
    <div class="crud-header">
        <div>
            <h1>
                <i class="bi bi-list-task"></i>
                File d’attente / Tâches
            </h1>
            <p class="crud-header-meta">
                <span data-bj-active-count><?= (int)($jobsSnapshot['active_count'] ?? 0) ?></span> actifs
                · Optuna <span data-bj-type-optuna><?= (int)($byType['optuna'] ?? 0) ?></span>
                · Prévisions <span data-bj-type-forecast><?= (int)($byType['forecast'] ?? 0) ?></span>
                · Plannings <span data-bj-type-planning><?= (int)($byType['planning'] ?? 0) ?></span>
                · actualisé <span data-bj-updated>—</span>
                · actualisation 6 s
            </p>
        </div>
    </div>

    <p class="small text-muted mb-3">
        Actifs (en file / en cours) en direct.
        Historique <?= (int)$historyDays ?> j filtrable / paginé (hors actualisation).
    </p>

    <section class="crud-section">
        <h2 class="crud-section-title">Actifs</h2>
        <div class="table-responsive mb-0">
            <table class="table table-sm table-hover crud-table mb-0">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Cible</th>
                        <th>Statut</th>
                        <th>Progression</th>
                        <th>Démarré</th>
                        <th>Terminé</th>
                        <th class="actions">Actions</th>
                    </tr>
                </thead>
                <tbody data-bj-active-tbody>
                    <?php if ($activeItems === []): ?>
                        <tr>
                            <td colspan="7" class="crud-empty">Aucune tâche active.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($activeItems as $item): ?>
                            <?= $this->element('BackgroundJobs/job_row', compact('item', 'typeLabels', 'statusBadge')) ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="crud-section">
        <h2 class="crud-section-title">Historique (<?= (int)$historyDays ?> j)</h2>

        <?= $this->Form->create(null, [
            'type' => 'get',
            'class' => 'filters-toolbar mb-3',
            'url' => ['controller' => 'BackgroundJobs', 'action' => 'index'],
        ]) ?>
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Type</label>
                    <?= $this->Form->select('type', $typeOptions, [
                        'class' => 'form-control form-control-sm',
                        'value' => $filters['type'] ?? '',
                    ]) ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Statut</label>
                    <?= $this->Form->select('status', $statusOptions, [
                        'class' => 'form-control form-control-sm',
                        'value' => $filters['status'] ?? '',
                    ]) ?>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <?= $this->Form->button('Filtrer', [
                        'type' => 'submit',
                        'class' => 'btn btn-sm btn-primary',
                    ]) ?>
                    <?= $this->Html->link('Réinitialiser', ['action' => 'index'], [
                        'class' => 'btn btn-sm btn-outline-secondary',
                    ]) ?>
                </div>
                <div class="col-md-3">
                    <p class="crud-header-meta mb-0">
                        <?= (int)$history['total'] ?> résultat(s)
                        · page <?= (int)$history['page'] ?> / <?= (int)$history['page_count'] ?>
                    </p>
                </div>
            </div>
        <?= $this->Form->end() ?>

        <div class="table-responsive">
            <table class="table table-sm table-hover crud-table mb-0">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Cible</th>
                        <th>Statut</th>
                        <th>Progression</th>
                        <th>Démarré</th>
                        <th>Terminé</th>
                        <th class="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($historyItems === []): ?>
                        <tr>
                            <td colspan="7" class="crud-empty">
                                Aucune tâche terminée sur <?= (int)$historyDays ?> j
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
            <div class="paginator">
                <nav aria-label="Pagination de l'historique">
                    <ul class="pagination justify-content-center mb-0">
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
            </div>
        <?php endif; ?>
    </section>
</div>
