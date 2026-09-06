<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Range[]|\Cake\Collection\CollectionInterface $ranges
 */
?>
<?php $this->assign('title', 'Liste des Plages Horaires'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php $this->Html->script('crud-filters', ['block' => true, 'timestamp' => 'force']); ?>
<?php $this->Html->script('ranges-filters', ['block' => true]); ?>

<div class="crud-app ranges index content">
    <div class="crud-header">
        <div>
            <h1>
                <i class="bi bi-clock-history"></i>
                Plages Horaires
            </h1>
            <p class="crud-header-meta"><?= $this->Paginator->counter('{{count}} plages') ?></p>
        </div>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle me-1"></i> Nouvelle Plage',
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
        } elseif (!is_string($dateStartValue) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStartValue)) {
            $dateStartValue = null;
        }
        $dateEndValue = $this->request->getQuery('date_end');
        if (is_array($dateEndValue) && !empty($dateEndValue['year']) && !empty($dateEndValue['month']) && !empty($dateEndValue['day'])) {
            $dateEndValue = sprintf('%04d-%02d-%02d', $dateEndValue['year'], $dateEndValue['month'], $dateEndValue['day']);
        } elseif (!is_string($dateEndValue) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEndValue)) {
            $dateEndValue = null;
        }
        ?>
        <div class="d-flex flex-wrap align-items-end gap-2">
            <div class="flex-grow-1" style="min-width: 12rem;">
                <label for="user-id" class="form-label small text-muted mb-1">Agent</label>
                <?= $this->Form->select('user_id', $users, [
                    'empty' => 'Tous les agents',
                    'class' => 'form-control form-control-sm',
                    'value' => $this->request->getQuery('user_id'),
                    'id' => 'user-id',
                ]) ?>
            </div>
            <div class="flex-grow-1" style="min-width: 10rem;">
                <label for="offer-id" class="form-label small text-muted mb-1">Offre</label>
                <?= $this->Form->select('offer_id', $offers, [
                    'empty' => 'Toutes les offres',
                    'class' => 'form-control form-control-sm',
                    'value' => $this->request->getQuery('offer_id'),
                    'id' => 'offer-id',
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

    <?php if (count($ranges) > 0): ?>
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
                <span class="text-muted small" id="selectedCount">0 plage(s) sélectionnée(s)</span>
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
            $columns = ['Utilisateur', 'Offre', 'Période', 'Commentaire', 'Maj', 'Actions'];
            $colCount = count($columns) + (count($ranges) > 0 ? 1 : 0);
            ?>
            <thead>
            <tr>
                <?php if (count($ranges) > 0): ?>
                <th style="width: 40px;">
                    <input type="checkbox" id="selectAll" title="Tout sélectionner">
                </th>
                <?php endif; ?>
                <th scope="col"><?= $this->Paginator->sort('user_id', $columns[0]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('offer_id', $columns[1]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('date_start', $columns[2]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('comment', $columns[3]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('modified', $columns[4]) ?></th>
                <th scope="col" class="actions"><?= h($columns[5]) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($ranges) === 0): ?>
                <tr>
                    <td colspan="<?= (int)$colCount ?>" class="crud-empty">
                        <p>Aucune plage.</p>
                        <?php if (!$this->request->getQuery()): ?>
                            <?= $this->Html->link(
                                '<i class="bi bi-plus-circle me-1"></i> Créer une plage',
                                ['action' => 'add'],
                                ['class' => 'btn btn-sm btn-primary', 'escape' => false]
                            ) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($ranges as $range): ?>
                <?php
                $userLabel = $range->hasValue('user')
                    ? $range->user->first_name . ' ' . $range->user->last_name
                    : '#' . $range->id;
                ?>
                <tr>
                    <td>
                        <input type="checkbox" name="ids[]" value="<?= (int)$range->id ?>" class="range-checkbox">
                    </td>
                    <td>
                        <?= $this->Html->link(
                            $userLabel,
                            ['action' => 'view', $range->id],
                            ['class' => 'crud-row-link']
                        ) ?>
                    </td>
                    <td>
                        <?php if ($range->hasValue('offer')): ?>
                            <span class="crud-color">
                                <span class="crud-swatch" style="background-color: <?= h($range->offer->color) ?>"></span>
                                <?= h($range->offer->name) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= h($range->date_start ? $range->date_start->i18nFormat('dd/MM/yy HH:mm') : '') ?>
                        <i class="bi bi-arrow-right"></i>
                        <?= h($range->date_end ? $range->date_end->i18nFormat('dd/MM/yy HH:mm') : '') ?>
                    </td>
                    <td><?= h($range->comment ?: '-') ?></td>
                    <td><?= $this->element('crud/maj_cell', ['entity' => $range]) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(
                            '<i class="bi bi-pencil" aria-hidden="true"></i>',
                            ['action' => 'edit', $range->id],
                            [
                                'class' => 'crud-action',
                                'escape' => false,
                                'title' => 'Modifier',
                                'aria-label' => 'Modifier',
                                'data-bs-toggle' => 'tooltip',
                            ]
                        ) ?>
                        <a href="#" class="crud-action crud-action-danger range-delete-link"
                           data-confirm="Voulez-vous vraiment supprimer cette plage ?"
                           data-url="<?= $this->Url->build(['action' => 'delete', $range->id]) ?>"
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
    <?php if (count($ranges) > 0): ?>
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
        if (e.target.closest('.range-delete-link')) {
            e.preventDefault();
            var link = e.target.closest('.range-delete-link');
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
