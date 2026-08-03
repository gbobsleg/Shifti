<?php
/**
 * @var \App\View\AppView $this
 * @var array $jobsSnapshot
 */
$this->assign('title', 'Jobs');
$this->extend('/layout/TwitterBootstrap/dashtron_fullwidth');

$jobsSnapshot = $jobsSnapshot ?? [
    'success' => true,
    'active_count' => 0,
    'by_type' => ['optuna' => 0, 'forecast' => 0, 'planning' => 0],
    'items' => [],
];
$items = $jobsSnapshot['items'] ?? [];
$byType = $jobsSnapshot['by_type'] ?? ['optuna' => 0, 'forecast' => 0, 'planning' => 0];
$statusUrl = $this->Url->build(['controller' => 'BackgroundJobs', 'action' => 'status', '_ext' => 'json']);

$typeLabels = [
    'optuna' => 'Optuna',
    'forecast' => 'Prévision',
    'planning' => 'Planning',
];
$statusBadge = static function (string $status): string {
    return match ($status) {
        'running' => 'badge-primary',
        'queued' => 'badge-warning',
        'completed', 'finished' => 'badge-success',
        'failed', 'error', 'infeasible', 'finished_with_errors' => 'badge-danger',
        default => 'badge-secondary',
    };
};

$this->Html->script('background-jobs-page', ['block' => true]);
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
     data-url-status="<?= h($statusUrl) ?>">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-list-task text-primary"></i>
            File d’attente / Jobs
        </h3>
        <span class="small text-muted">
            Actualisé : <span data-bj-updated>—</span>
            · polling 6 s
        </span>
    </div>
    <div class="card-body">
        <p class="small text-muted mb-3">
            Vue consolidée Optuna, prévisions et plannings.
            Actifs (<code>queued</code> / <code>running</code>) + historique 24 h (max 50).
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
                <tbody data-bj-tbody>
                    <?php if ($items === []): ?>
                        <tr>
                            <td colspan="7" class="text-muted text-center py-4">
                                Aucun job actif ni historique récent (24 h).
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $status = (string)($item['status'] ?? '');
                            $type = (string)($item['type'] ?? '');
                            $rowClass = in_array($status, ['queued', 'running'], true) ? 'table-row-active' : '';
                            ?>
                            <tr class="<?= h($rowClass) ?>">
                                <td>
                                    <span class="badge badge-light border">
                                        <?= h($typeLabels[$type] ?? $type) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= h((string)($item['label'] ?? '—')) ?>
                                    <?php if (!empty($item['error_message'])): ?>
                                        <div class="small text-danger mt-1"><?= h((string)$item['error_message']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= h($statusBadge($status)) ?>"><?= h($status) ?></span>
                                </td>
                                <td class="small"><?= h((string)($item['progress'] ?? '—')) ?></td>
                                <td class="small text-nowrap"><?= h((string)($item['started_at'] ?? '—')) ?></td>
                                <td class="small text-nowrap"><?= h((string)($item['finished_at'] ?? '—')) ?></td>
                                <td>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= h((string)($item['url'] ?? '#')) ?>">
                                        Ouvrir
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
