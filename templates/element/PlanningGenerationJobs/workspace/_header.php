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
<div class="crud-header" id="workspace-header"
     data-job-id="<?= (int)$job->id ?>"
     data-job-status="<?= h($status) ?>">
    <div>
        <h1>Job #<?= (int)$job->id ?></h1>
        <p class="crud-header-meta">
            <?= h((string)$job->start_date) ?> → <?= h((string)$job->end_date) ?>
            <?php if (!empty($job->wfm_setting)): ?>
                | <?= h($job->wfm_setting->name ?? '') ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="crud-header-actions">
        <?= $this->Html->link(
            'Liste',
            ['action' => 'index'],
            ['class' => 'btn btn-outline-secondary', 'title' => 'Retour à la liste']
        ) ?>
        <span id="workspaceStatusBadge" class="badge bg-<?= $badge ?>">
            <i class="bi bi-<?= $icon ?>"></i> <?= h($status) ?>
        </span>
        <div class="d-flex align-items-center flex-wrap" style="gap: 0.5rem;" id="workspacePrimaryCta">
            <?php if ($canPublish): ?>
                <?= $this->Form->create(null, [
                    'url' => ['action' => 'publish', (int)$job->id],
                    'class' => 'd-flex align-items-center mb-0',
                ]) ?>
                    <?= $this->Form->hidden('publish_start', ['value' => $startVal]) ?>
                    <?= $this->Form->hidden('publish_end', ['value' => $endVal]) ?>
                    <?= $this->Form->button('Publier', [
                        'class' => 'btn btn-primary btn-sm',
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
                    Relancer
                </a>
            <?php elseif ($isRunning): ?>
                <button type="button" class="btn btn-outline-secondary btn-sm" disabled>
                    En cours…
                </button>
            <?php endif; ?>

            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="workspaceActions" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    Actions
                </button>
                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="workspaceActions" style="min-width: 16rem;">
                    <?php if ($canPublish): ?>
                        <h6 class="dropdown-header">Publier une plage</h6>
                        <?= $this->Form->create(null, [
                            'url' => ['action' => 'publish', (int)$job->id],
                            'class' => 'px-3 pb-2',
                        ]) ?>
                            <div class="mb-2">
                                <label class="small text-muted mb-0">Début</label>
                                <?= $this->Form->control('publish_start', [
                                    'label' => false,
                                    'type' => 'date',
                                    'class' => 'form-control form-control-sm',
                                    'value' => $startVal,
                                ]) ?>
                            </div>
                            <div class="mb-2">
                                <label class="small text-muted mb-0">Fin</label>
                                <?= $this->Form->control('publish_end', [
                                    'label' => false,
                                    'type' => 'date',
                                    'class' => 'form-control form-control-sm',
                                    'value' => $endVal,
                                ]) ?>
                            </div>
                            <?= $this->Form->button('Publier la plage', [
                                'class' => 'btn btn-primary btn-sm',
                                'escapeTitle' => false,
                            ]) ?>
                        <?= $this->Form->end() ?>
                        <div class="dropdown-divider"></div>
                    <?php endif; ?>
                    <a href="#"
                       class="dropdown-item job-retry-link"
                       data-job-id="<?= (int)$job->id ?>"
                       data-confirm="Relancer ce job ? Il sera remis en file d'attente et traité depuis le début."
                       data-url="<?= $this->Url->build(['action' => 'retry', (int)$job->id]) ?>">
                        Relancer
                    </a>
                    <?php if ($status !== 'running'): ?>
                        <?= $this->Html->link(
                            'Modifier',
                            ['action' => 'edit', (int)$job->id],
                            ['class' => 'dropdown-item']
                        ) ?>
                        <div class="dropdown-divider"></div>
                        <?= $this->Form->postLink(
                            'Supprimer le brouillon',
                            ['action' => 'clearDraft', (int)$job->id],
                            [
                                'class' => 'dropdown-item',
                                'confirm' => 'Supprimer le brouillon ? Le planning publié ne sera pas modifié.',
                            ]
                        ) ?>
                        <div class="dropdown-divider"></div>
                        <a href="#"
                           class="dropdown-item job-delete-link"
                           data-job-id="<?= (int)$job->id ?>"
                           data-confirm="Supprimer ce job ? Le brouillon et le détail des jours seront supprimés."
                           data-url="<?= $this->Url->build(['action' => 'delete', (int)$job->id]) ?>">
                            Supprimer le job
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
