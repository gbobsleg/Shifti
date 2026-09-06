<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Alert> $alerts
 */
?>
<?php $this->assign('title', 'Liste des Alertes'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php $this->Html->script('crud-filters', ['block' => true, 'timestamp' => 'force']); ?>
<?php $this->Html->script('alerts-filters', ['block' => true, 'timestamp' => 'force']); ?>

<div class="crud-app alerts index content">
    <div class="crud-header">
        <div>
            <h1>
                <i class="bi bi-bell"></i>
                Alertes
            </h1>
            <p class="crud-header-meta"><?= $this->Paginator->counter('{{count}} alertes') ?></p>
        </div>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle me-1"></i> Nouvelle Alerte',
                ['action' => 'add'],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
        </div>
    </div>

    <?= $this->Form->create(null, ['type' => 'get', 'class' => 'filters-toolbar mb-3']) ?>
        <?php
        $dateStartValue = $this->request->getQuery('date_start');
        if (is_array($dateStartValue) && !empty($dateStartValue['year']) && !empty($dateStartValue['month']) && !empty($dateStartValue['day'])) {
            $dateStartValue = sprintf('%04d-%02d-%02d', $dateStartValue['year'], $dateStartValue['month'], $dateStartValue['day']);
        } elseif (!is_string($dateStartValue) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStartValue ?? '')) {
            $dateStartValue = null;
        }
        $dateEndValue = $this->request->getQuery('date_end');
        if (is_array($dateEndValue) && !empty($dateEndValue['year']) && !empty($dateEndValue['month']) && !empty($dateEndValue['day'])) {
            $dateEndValue = sprintf('%04d-%02d-%02d', $dateEndValue['year'], $dateEndValue['month'], $dateEndValue['day']);
        } elseif (!is_string($dateEndValue) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEndValue ?? '')) {
            $dateEndValue = null;
        }
        ?>
        <div class="d-flex flex-wrap align-items-end gap-2">
            <div class="flex-grow-1" style="min-width: 12rem;">
                <label for="content" class="form-label small text-muted mb-1">Contenu</label>
                <?= $this->Form->text('content', [
                    'class' => 'form-control form-control-sm',
                    'placeholder' => 'Rechercher dans le contenu...',
                    'value' => $this->request->getQuery('content'),
                    'id' => 'content',
                    'autocomplete' => 'off',
                ]) ?>
            </div>
            <div style="min-width: 10rem;">
                <label for="priority" class="form-label small text-muted mb-1">Priorité</label>
                <?= $this->Form->select('priority', [
                    1 => '1 - Urgent',
                    2 => '2 - Important',
                    3 => '3 - Information',
                ], [
                    'empty' => 'Toutes les priorités',
                    'class' => 'form-control form-control-sm',
                    'value' => $this->request->getQuery('priority'),
                    'id' => 'priority',
                ]) ?>
            </div>
            <div style="width: 10.5rem;">
                <label for="date-start" class="form-label small text-muted mb-1">Date de début</label>
                <?= $this->Form->text('date_start', [
                    'type' => 'date',
                    'class' => 'form-control form-control-sm',
                    'value' => $dateStartValue,
                    'id' => 'date-start',
                ]) ?>
            </div>
            <div style="width: 10.5rem;">
                <label for="date-end" class="form-label small text-muted mb-1">Date de fin</label>
                <?= $this->Form->text('date_end', [
                    'type' => 'date',
                    'class' => 'form-control form-control-sm',
                    'value' => $dateEndValue,
                    'id' => 'date-end',
                ]) ?>
            </div>
            <div>
                <label class="form-label small mb-1 d-block" aria-hidden="true">&nbsp;</label>
                <div class="d-flex gap-2">
                    <?= $this->Form->button('Filtrer', [
                        'type' => 'submit',
                        'class' => 'btn btn-sm btn-primary',
                    ]) ?>
                    <?= $this->Html->link(
                        'Réinitialiser',
                        ['action' => 'index'],
                        ['class' => 'btn btn-sm btn-outline-secondary']
                    ) ?>
                </div>
            </div>
        </div>
    <?= $this->Form->end() ?>

    <?php if (count($alerts) > 0): ?>
        <?php
        $bulkActionUrl = ['action' => 'bulkDelete'];
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
                <span class="text-muted small" id="selectedCount">0 alerte(s) sélectionnée(s)</span>
            </div>
            <div>
                <button type="submit" class="btn btn-sm btn-outline-danger" id="bulkDeleteBtn" disabled>
                    <i class="bi bi-trash"></i> Supprimer la sélection
                </button>
            </div>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-hover table-sm crud-table">
            <?php
            $columns = ['Contenu', 'Début', 'Fin', 'Priorité', 'Actions'];
            $colCount = count($columns) + (count($alerts) > 0 ? 1 : 0);
            ?>
            <thead>
            <tr>
                <?php if (count($alerts) > 0): ?>
                <th style="width: 40px;">
                    <input type="checkbox" id="selectAll" title="Tout sélectionner">
                </th>
                <?php endif; ?>
                <th scope="col"><?= $this->Paginator->sort('content', $columns[0]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('date_start', $columns[1]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('date_end', $columns[2]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('priority', $columns[3]) ?></th>
                <th scope="col" class="actions"><?= h($columns[4]) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($alerts) === 0): ?>
                <tr>
                    <td colspan="<?= (int)$colCount ?>" class="crud-empty">
                        <p>Aucune alerte.</p>
                        <?php if (!$this->request->getQuery()): ?>
                            <?= $this->Html->link(
                                '<i class="bi bi-plus-circle me-1"></i> Créer une alerte',
                                ['action' => 'add'],
                                ['class' => 'btn btn-sm btn-primary', 'escape' => false]
                            ) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($alerts as $alert) : ?>
                <?php
                $priorityLabel = 'Information';
                if ($alert->priority == 1) {
                    $priorityLabel = 'Urgent';
                } elseif ($alert->priority == 2) {
                    $priorityLabel = 'Important';
                }
                $contentLabel = $alert->content !== null && $alert->content !== ''
                    ? $alert->content
                    : '#' . $alert->id;
                ?>
                <tr>
                    <td>
                        <input type="checkbox" name="ids[]" value="<?= (int)$alert->id ?>" class="alert-checkbox">
                    </td>
                    <td>
                        <?= $this->Html->link(
                            $contentLabel,
                            ['action' => 'view', $alert->id],
                            ['class' => 'crud-row-link']
                        ) ?>
                    </td>
                    <td><?= h($alert->date_start ? $alert->date_start->i18nFormat('dd/MM/yyyy') : '') ?></td>
                    <td><?= h($alert->date_end ? $alert->date_end->i18nFormat('dd/MM/yyyy') : '') ?></td>
                    <td><?= h($priorityLabel) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(
                            '<i class="bi bi-pencil" aria-hidden="true"></i>',
                            ['action' => 'edit', $alert->id],
                            [
                                'class' => 'crud-action',
                                'escape' => false,
                                'title' => 'Modifier',
                                'aria-label' => 'Modifier',
                                'data-bs-toggle' => 'tooltip',
                            ]
                        ) ?>
                        <a href="#" class="crud-action crud-action-danger alert-delete-link"
                           data-confirm="Voulez-vous vraiment supprimer cette alerte ?"
                           data-url="<?= $this->Url->build(['action' => 'delete', $alert->id]) ?>"
                           title="Supprimer"
                           aria-label="Supprimer"
                           data-bs-toggle="tooltip">
                            <i class="bi bi-trash" aria-hidden="true"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (count($alerts) > 0): ?>
        <?= $this->Form->end() ?>
    <?php endif; ?>

    <div class="paginator">
        <ul class="pagination justify-content-center">
            <?= $this->Paginator->first('<< ' . 'Première') ?>
            <?= $this->Paginator->prev('< ' . 'Précédente') ?>
            <?= $this->Paginator->numbers() ?>
            <?= $this->Paginator->next('Suivante' . ' >') ?>
            <?= $this->Paginator->last('Dernière' . ' >>') ?>
        </ul>
        <p><?= $this->Paginator->counter('Page {{page}} sur {{pages}}, affichant {{current}} sur {{count}}') ?></p>
    </div>
</div>

<?php $csrfToken = $this->request->getAttribute('csrfToken'); ?>
<?php $this->Html->scriptStart(['block' => true]); ?>
(function() {
    var csrfToken = <?= json_encode($csrfToken) ?>;
    document.addEventListener('click', function(e) {
        if (e.target.closest('.alert-delete-link')) {
            e.preventDefault();
            var link = e.target.closest('.alert-delete-link');
            var confirmMsg = link.getAttribute('data-confirm');
            var url = link.getAttribute('data-url');
            if (confirm(confirmMsg)) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = url;
                form.style.display = 'none';
                if (csrfToken) {
                    var csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = '_csrfToken';
                    csrfInput.value = csrfToken;
                    form.appendChild(csrfInput);
                }
                var methodInput = document.createElement('input');
                methodInput.type = 'hidden';
                methodInput.name = '_method';
                methodInput.value = 'DELETE';
                form.appendChild(methodInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
    });
})();
<?php $this->Html->scriptEnd(); ?>
