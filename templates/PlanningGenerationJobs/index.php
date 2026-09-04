<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\ResultSetInterface|\Cake\Collection\CollectionInterface $jobs
 */
$jobCount = is_countable($jobs) ? count($jobs) : iterator_count($jobs);
?>
<?php $this->assign('title', 'Générations de planning'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php $this->Html->script('planning-generation-jobs-filters', ['block' => true]); ?>

<style>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.spinning {
    animation: spin 1s linear infinite;
}
</style>

<div class="crud-app planning-generation-jobs index content">
    <div class="crud-header">
        <div>
            <h1>
                <i class="bi bi-list-task"></i>
                Générations de planning
            </h1>
            <p class="crud-header-meta"><?= (int)$jobCount ?> générations</p>
        </div>
        <div class="crud-header-actions">
            <button type="button" class="btn btn-outline-secondary" id="refreshBtn" title="Actualiser les statuts">
                <i class="bi bi-arrow-clockwise"></i> Actualiser
            </button>
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle me-1"></i> Nouvelle génération',
                ['action' => 'add'],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
        </div>
    </div>

                <?= $this->Form->create(null, ['type' => 'get', 'class' => 'filters-toolbar mb-3']) ?>
                <div class="row">
                    <div class="col-md-2">
                        <?= $this->Form->control('status', [
                            'label' => 'Statut',
                            'type' => 'select',
                            'options' => [
                                '' => 'Tous',
                                'finished' => 'Terminés',
                                'running' => 'En cours',
                                'error' => 'Erreurs',
                                'queued' => 'En attente',
                            ],
                            'class' => 'form-control form-control-sm',
                            'value' => $this->request->getQuery('status'),
                            'empty' => false,
                        ]) ?>
                    </div>
                    <div class="col-md-2">
                        <?= $this->Form->control('date_start', [
                            'label' => 'A partir de (période)',
                            'type' => 'date',
                            'class' => 'form-control form-control-sm',
                            'value' => $this->request->getQuery('date_start'),
                        ]) ?>
                    </div>
                    <div class="col-md-2">
                        <?= $this->Form->control('date_end', [
                            'label' => 'Jusqu\'à (période)',
                            'type' => 'date',
                            'class' => 'form-control form-control-sm',
                            'value' => $this->request->getQuery('date_end'),
                        ]) ?>
                    </div>
                    <div class="col-md-2">
                        <?= $this->Form->control('created_from', [
                            'label' => 'Créé du',
                            'type' => 'date',
                            'class' => 'form-control form-control-sm',
                            'value' => $this->request->getQuery('created_from'),
                        ]) ?>
                    </div>
                    <div class="col-md-2">
                        <?= $this->Form->control('created_to', [
                            'label' => 'Créé au',
                            'type' => 'date',
                            'class' => 'form-control form-control-sm',
                            'value' => $this->request->getQuery('created_to'),
                        ]) ?>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="btn-group w-100">
                            <?= $this->Form->button('<i class="bi bi-search"></i> Filtrer', [
                                'type' => 'submit',
                                'class' => 'btn btn-sm btn-primary',
                                'escapeTitle' => false
                            ]) ?>
                            <?= $this->Html->link(
                                '<i class="bi bi-x-circle"></i> Réinitialiser',
                                ['action' => 'index'],
                                ['class' => 'btn btn-sm btn-outline-secondary', 'escape' => false]
                            ) ?>
                        </div>
                    </div>
                </div>
                <?= $this->Form->end() ?>

                <?php if (empty($jobs) || (is_countable($jobs) && count($jobs) === 0)): ?>
                    <p class="crud-empty mb-0">Aucune génération.</p>
                <?php else: ?>
                    <!-- Actions en masse -->
                    <?php
                    $bulkActionUrl = ['action' => 'bulkDelete'];
                    // Préserver les paramètres de filtres dans l'URL
                    $queryParams = $this->request->getQueryParams();
                    if (!empty($queryParams)) {
                        $bulkActionUrl['?'] = $queryParams;
                    }
                    ?>
                    <?= $this->Form->create(null, ['url' => $bulkActionUrl, 'id' => 'bulkActionsForm', 'class' => 'mb-3']) ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center" style="gap: 0.5rem;">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllBtn">
                                <i class="bi bi-check-square"></i> Tout sélectionner
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllBtn" style="display: none;">
                                <i class="bi bi-square"></i> Tout désélectionner
                            </button>
                            <span class="text-muted small" id="selectedCount">0 job(s) sélectionné(s)</span>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-sm btn-danger" id="bulkDeleteBtn" disabled>
                                <i class="bi bi-trash"></i> Supprimer sélectionné(s)
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover table-sm crud-table align-middle">
                            <thead>
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" id="selectAll" title="Tout sélectionner">
                                </th>
                                <th>ID</th>
                                <th>Période</th>
                                <th>Profil WFM</th>
                                <th>Passes</th>
                                <th>Statut</th>
                                <th class="text-end">Avancement</th>
                                <th>Créé le</th>
                                <th class="text-end">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($jobs as $job): ?>
                                <?php
                                $status = (string)$job->status;
                                $statusLabels = [
                                    'finished' => 'Terminé',
                                    'finished_with_errors' => 'Terminé avec erreurs',
                                    'running' => 'En cours',
                                    'queued' => 'En attente',
                                    'error' => 'Erreur',
                                    'infeasible' => 'Infaisable',
                                ];
                                $statusLabel = $statusLabels[$status] ?? $status;
                                $badge = 'secondary';
                                $icon = 'clock';
                                if ($status === 'finished' || $status === 'finished_with_errors') {
                                    $badge = 'success';
                                    $icon = 'check-circle';
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

                                $processedDays = (int)$job->processed_days;
                                $totalDays = (int)$job->total_days;
                                $progress = $totalDays > 0 ? round(($processedDays / $totalDays) * 100) : 0;

                                $createdDate = $job->created ?? null;
                                $createdFormatted = '';
                                if ($createdDate) {
                                    if ($createdDate instanceof \Cake\I18n\FrozenTime || $createdDate instanceof \Cake\I18n\FrozenDate) {
                                        $createdFormatted = $createdDate->i18nFormat('dd/MM/yyyy HH:mm');
                                    } elseif ($createdDate instanceof \DateTimeInterface) {
                                        $createdFormatted = $createdDate->format('d/m/Y H:i');
                                    }
                                }

                                // Décodage des options pour les passes
                                $options = [];
                                if (!empty($job->options_json)) {
                                    $decoded = json_decode((string)$job->options_json, true);
                                    if (is_array($decoded)) {
                                        $options = $decoded;
                                    }
                                }
                                $ignoreFixed = !empty($options['ignore_fixed_activities']);
                                $ignoreRotation = !empty($options['ignore_rotation']);
                                $ignoreForecast = !empty($options['ignore_forecast_solver']);
                                $debugSolvers = !empty($options['debug_solvers']);
                                ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="ids[]" value="<?= (int)$job->id ?>" class="job-checkbox" <?= (string)$job->status === 'running' ? 'disabled title="Génération en cours"' : '' ?>>
                                    </td>
                                    <td>
                                        <?= $this->Html->link(
                                            '#' . (int)$job->id,
                                            ['action' => 'view', (int)$job->id],
                                            ['class' => 'crud-row-link']
                                        ) ?>
                                    </td>
                                    <td>
                                        <?= h((string)$job->start_date) ?> → <?= h((string)$job->end_date) ?>
                                    </td>
                                    <td><?= h($job->wfm_setting->name ?? '') ?></td>
                                    <td>
                                        <span class="badge bg-<?= $ignoreFixed ? 'secondary' : 'success' ?>" title="Passe 1 : Activités fixes">
                                            P1 <?= $ignoreFixed ? '<i class="bi bi-x"></i>' : '<i class="bi bi-check"></i>' ?>
                                        </span>
                                        <span class="badge bg-<?= $ignoreRotation ? 'secondary' : 'success' ?>" title="Passe 1.5 : Rotations">
                                            P1.5 <?= $ignoreRotation ? '<i class="bi bi-x"></i>' : '<i class="bi bi-check"></i>' ?>
                                        </span>
                                        <span class="badge bg-<?= $ignoreForecast ? 'secondary' : 'success' ?>" title="Passe 2 : Prévisions">
                                            P2 <?= $ignoreForecast ? '<i class="bi bi-x"></i>' : '<i class="bi bi-check"></i>' ?>
                                        </span>
                                        <?php if ($debugSolvers): ?>
                                        <span class="badge bg-warning" title="Mode débogage activé">
                                            <i class="bi bi-bug"></i>
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $badge ?>">
                                            <i class="bi bi-<?= $icon ?>"></i> <?= h($statusLabel) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex flex-column align-items-end">
                                            <span class="small text-muted mb-1">
                                                <?= $processedDays ?> / <?= $totalDays ?> jours
                                            </span>
                                            <div class="progress" style="width: 100px; height: 8px;">
                                                <div class="progress-bar bg-<?= $badge === 'success' ? 'success' : ($badge === 'danger' ? 'danger' : 'info') ?>" 
                                                     role="progressbar" 
                                                     style="width: <?= $progress ?>%"
                                                     aria-valuenow="<?= $progress ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($createdFormatted): ?>
                                            <small class="text-muted"><?= h($createdFormatted) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions text-end">
                                        <div class="dropup actions-dropdown" data-entity-id="<?= (int)$job->id ?>">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownActions<?= $job->id ?>" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <i class="bi bi-three-dots-vertical"></i> Actions
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end actions-dropdown-menu" data-entity-id="<?= (int)$job->id ?>" aria-labelledby="dropdownActions<?= $job->id ?>">
                                                <?= $this->Html->link(
                                                    '<i class="bi bi-folder2-open me-2"></i> Ouvrir',
                                                    ['action' => 'view', (int)$job->id],
                                                    ['class' => 'dropdown-item', 'escape' => false]
                                                ) ?>
                                                <div class="dropdown-divider"></div>
                                                <a href="#" 
                                                   class="dropdown-item text-primary job-retry-link" 
                                                   data-job-id="<?= (int)$job->id ?>"
                                                   data-confirm="Relancer cette génération ? Elle sera remise en file d'attente et traitée depuis le début."
                                                   data-url="<?= $this->Url->build(['action' => 'retry', (int)$job->id]) ?>">
                                                    <i class="bi bi-arrow-clockwise me-2"></i> Relancer
                                                </a>
                                                <?php if ($status !== 'running'): ?>
                                                    <?= $this->Html->link(
                                                        '<i class="bi bi-pencil me-2"></i> Modifier',
                                                        ['action' => 'edit', (int)$job->id],
                                                        ['class' => 'dropdown-item text-warning', 'escape' => false]
                                                    ) ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a href="#" 
                                                       class="dropdown-item text-danger job-delete-link" 
                                                       data-job-id="<?= (int)$job->id ?>"
                                                       data-confirm="Supprimer cette génération ? Le brouillon et le détail des jours seront supprimés."
                                                       data-url="<?= $this->Url->build(['action' => 'delete', (int)$job->id]) ?>">
                                                        <i class="bi bi-trash me-2"></i> Supprimer
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?= $this->Form->end() ?>
                <?php endif; ?>
</div>

<?php
$csrfToken = $this->request->getAttribute('csrfToken');
?>
<?php $this->Html->scriptStart(['block' => true]); ?>
(function() {
    const csrfToken = <?= json_encode($csrfToken) ?>;
    
    // Gestionnaire pour les liens de relance
    document.addEventListener('click', function(e) {
        if (e.target.closest('.job-retry-link')) {
            e.preventDefault();
            const link = e.target.closest('.job-retry-link');
            const confirmMsg = link.getAttribute('data-confirm');
            const url = link.getAttribute('data-url');
            
            if (confirm(confirmMsg)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = url;
                form.style.display = 'none';
                
                // Ajouter le token CSRF
                if (csrfToken) {
                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = '_csrfToken';
                    csrfInput.value = csrfToken;
                    form.appendChild(csrfInput);
                }
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Gestionnaire pour les liens de suppression
        if (e.target.closest('.job-delete-link')) {
            e.preventDefault();
            const link = e.target.closest('.job-delete-link');
            const confirmMsg = link.getAttribute('data-confirm');
            const url = link.getAttribute('data-url');
            
            if (confirm(confirmMsg)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = url;
                form.style.display = 'none';
                
                // Ajouter le token CSRF
                if (csrfToken) {
                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = '_csrfToken';
                    csrfInput.value = csrfToken;
                    form.appendChild(csrfInput);
                }
                
                document.body.appendChild(form);
                form.submit();
            }
        }
    });
})();
<?php $this->Html->scriptEnd(); ?>


