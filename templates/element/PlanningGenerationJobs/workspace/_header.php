<?php
/**
 * En-tête du workspace génération de planning.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\PlanningGenerationJob $job
 */
$status = (string)$job->status;
$badge = 'secondary';
$icon = 'clock';
if ($status === 'finished') {
    $badge = 'success';
    $icon = 'check-circle';
} elseif ($status === 'finished_with_errors') {
    $badge = 'warning';
    $icon = 'exclamation-triangle';
} elseif ($status === 'running') {
    $badge = 'info';
    $icon = 'arrow-repeat';
} elseif ($status === 'queued') {
    $badge = 'warning';
    $icon = 'hourglass-split';
} elseif ($status === 'error' || $status === 'infeasible') {
    $badge = 'danger';
    $icon = $status === 'error' ? 'x-circle' : 'exclamation-triangle';
}

$canPublish = in_array($status, ['finished', 'finished_with_errors'], true);
$isRunning = $status === 'running' || $status === 'queued';
$isError = $status === 'error' || $status === 'infeasible';

$startVal = '';
if ($job->start_date instanceof \DateTimeInterface) {
    $startVal = $job->start_date->format('Y-m-d');
} else {
    $raw = (string)$job->start_date;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        $startVal = $raw;
    } elseif (strpos($raw, '/') !== false) {
        $dt = \DateTime::createFromFormat('d/m/Y', $raw);
        if ($dt) {
            $startVal = $dt->format('Y-m-d');
        }
    }
}
$endVal = '';
if ($job->end_date instanceof \DateTimeInterface) {
    $endVal = $job->end_date->format('Y-m-d');
} else {
    $raw = (string)$job->end_date;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        $endVal = $raw;
    } elseif (strpos($raw, '/') !== false) {
        $dt = \DateTime::createFromFormat('d/m/Y', $raw);
        if ($dt) {
            $endVal = $dt->format('Y-m-d');
        }
    }
}
?>
<div class="card shadow mb-3" id="workspace-header"
     data-job-id="<?= (int)$job->id ?>"
     data-job-status="<?= h($status) ?>">
    <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap" style="gap: 0.75rem;">
        <div class="d-flex align-items-center" style="gap: 1rem;">
            <?= $this->Html->link(
                '<i class="bi bi-arrow-left"></i>',
                ['action' => 'index'],
                ['class' => 'btn btn-sm btn-outline-secondary', 'escape' => false, 'title' => 'Retour à la liste']
            ) ?>
            <div>
                <h3 class="mb-0">
                    <i class="bi bi-calendar2-week text-primary"></i>
                    Job #<?= (int)$job->id ?>
                </h3>
                <small class="text-muted">
                    <?= h((string)$job->start_date) ?> → <?= h((string)$job->end_date) ?>
                    <?php if (!empty($job->wfm_setting)): ?>
                        | <?= h($job->wfm_setting->name ?? '') ?>
                    <?php endif; ?>
                </small>
            </div>
            <span id="workspaceStatusBadge" class="badge badge-<?= $badge ?>" style="font-size: 0.9rem; padding: 0.5rem 0.75rem;">
                <i class="bi bi-<?= $icon ?>"></i> <?= h($status) ?>
            </span>
        </div>

        <div class="d-flex align-items-center flex-wrap" style="gap: 0.5rem;" id="workspacePrimaryCta">
            <?php if ($canPublish): ?>
                <?= $this->Form->create(null, [
                    'url' => ['action' => 'publish', (int)$job->id],
                    'class' => 'd-flex align-items-center mb-0',
                ]) ?>
                    <?= $this->Form->hidden('publish_start', ['value' => $startVal]) ?>
                    <?= $this->Form->hidden('publish_end', ['value' => $endVal]) ?>
                    <?= $this->Form->button('<i class="bi bi-check2-circle"></i> Publier', [
                        'class' => 'btn btn-success btn-sm',
                        'escapeTitle' => false,
                        'onclick' => 'return confirm("Publier le brouillon sur toute la période du job ? Les jours en échec seront exclus.");',
                    ]) ?>
                <?= $this->Form->end() ?>
            <?php elseif ($isError): ?>
                <a href="#"
                   class="btn btn-primary btn-sm job-retry-link"
                   data-job-id="<?= (int)$job->id ?>"
                   data-confirm="Relancer ce job ? Il sera remis en file d'attente et traité depuis le début."
                   data-url="<?= $this->Url->build(['action' => 'retry', (int)$job->id]) ?>">
                    <i class="bi bi-arrow-clockwise"></i> Relancer
                </a>
            <?php elseif ($isRunning): ?>
                <button type="button" class="btn btn-secondary btn-sm" disabled>
                    <i class="bi bi-hourglass-split"></i> En cours…
                </button>
            <?php endif; ?>

            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="workspaceActions" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bi bi-three-dots-vertical"></i> Actions
                </button>
                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="workspaceActions" style="min-width: 16rem;">
                    <?php if ($canPublish): ?>
                        <h6 class="dropdown-header">Publier une plage</h6>
                        <?= $this->Form->create(null, [
                            'url' => ['action' => 'publish', (int)$job->id],
                            'class' => 'px-3 pb-2',
                        ]) ?>
                            <div class="form-group mb-2">
                                <label class="small text-muted mb-0">Début</label>
                                <?= $this->Form->control('publish_start', [
                                    'label' => false,
                                    'type' => 'date',
                                    'class' => 'form-control form-control-sm',
                                    'value' => $startVal,
                                ]) ?>
                            </div>
                            <div class="form-group mb-2">
                                <label class="small text-muted mb-0">Fin</label>
                                <?= $this->Form->control('publish_end', [
                                    'label' => false,
                                    'type' => 'date',
                                    'class' => 'form-control form-control-sm',
                                    'value' => $endVal,
                                ]) ?>
                            </div>
                            <?= $this->Form->button('<i class="bi bi-check2-circle"></i> Publier la plage', [
                                'class' => 'btn btn-success btn-sm btn-block',
                                'escapeTitle' => false,
                            ]) ?>
                        <?= $this->Form->end() ?>
                        <div class="dropdown-divider"></div>
                    <?php endif; ?>
                    <a href="#"
                       class="dropdown-item text-primary job-retry-link"
                       data-job-id="<?= (int)$job->id ?>"
                       data-confirm="Relancer ce job ? Il sera remis en file d'attente et traité depuis le début."
                       data-url="<?= $this->Url->build(['action' => 'retry', (int)$job->id]) ?>">
                        <i class="bi bi-arrow-clockwise mr-2"></i> Relancer
                    </a>
                    <?php if ($status !== 'running'): ?>
                        <?= $this->Html->link(
                            '<i class="bi bi-pencil mr-2"></i> Modifier',
                            ['action' => 'edit', (int)$job->id],
                            ['class' => 'dropdown-item text-warning', 'escape' => false]
                        ) ?>
                        <div class="dropdown-divider"></div>
                        <?= $this->Form->postLink(
                            '<i class="bi bi-trash mr-2"></i> Supprimer le brouillon',
                            ['action' => 'clearDraft', (int)$job->id],
                            [
                                'class' => 'dropdown-item text-danger',
                                'escape' => false,
                                'confirm' => 'Supprimer le brouillon ? Le planning publié ne sera pas modifié.',
                            ]
                        ) ?>
                        <div class="dropdown-divider"></div>
                        <a href="#"
                           class="dropdown-item text-danger job-delete-link"
                           data-job-id="<?= (int)$job->id ?>"
                           data-confirm="Supprimer ce job ? Le brouillon et le détail des jours seront supprimés."
                           data-url="<?= $this->Url->build(['action' => 'delete', (int)$job->id]) ?>">
                            <i class="bi bi-trash mr-2"></i> Supprimer le job
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
