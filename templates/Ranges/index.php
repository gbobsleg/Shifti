<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Range[]|\Cake\Collection\CollectionInterface $ranges
 */
?>
<?php $this->assign('title', 'Liste des Plages Horaires'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

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
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label for="user-id" class="form-label small text-muted mb-1">Agent</label>
                <?= $this->Form->select('user_id', $users, [
                    'empty' => 'Tous les agents',
                    'class' => 'form-control form-control-sm',
                    'value' => $this->request->getQuery('user_id'),
                    'id' => 'user-id',
                ]) ?>
            </div>
            <div class="col-md-3">
                <label for="offer-id" class="form-label small text-muted mb-1">Offre</label>
                <?= $this->Form->select('offer_id', $offers, [
                    'empty' => 'Toutes les offres',
                    'class' => 'form-control form-control-sm',
                    'value' => $this->request->getQuery('offer_id'),
                    'id' => 'offer-id',
                ]) ?>
            </div>
            <div class="col-md-2">
                <label for="date-start" class="form-label small text-muted mb-1">Date de début</label>
                <?php
                $dateStartValue = $this->request->getQuery('date_start');
                if (is_array($dateStartValue) && !empty($dateStartValue['year']) && !empty($dateStartValue['month']) && !empty($dateStartValue['day'])) {
                    $dateStartValue = sprintf('%04d-%02d-%02d', $dateStartValue['year'], $dateStartValue['month'], $dateStartValue['day']);
                } elseif (!is_string($dateStartValue) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStartValue)) {
                    $dateStartValue = null;
                }
                ?>
                <?= $this->Form->control('date_start', [
                    'type' => 'date',
                    'label' => false,
                    'class' => 'form-control form-control-sm',
                    'value' => $dateStartValue,
                    'id' => 'date-start',
                ]) ?>
            </div>
            <div class="col-md-2">
                <label for="date-end" class="form-label small text-muted mb-1">Date de fin</label>
                <?php
                $dateEndValue = $this->request->getQuery('date_end');
                if (is_array($dateEndValue) && !empty($dateEndValue['year']) && !empty($dateEndValue['month']) && !empty($dateEndValue['day'])) {
                    $dateEndValue = sprintf('%04d-%02d-%02d', $dateEndValue['year'], $dateEndValue['month'], $dateEndValue['day']);
                } elseif (!is_string($dateEndValue) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEndValue)) {
                    $dateEndValue = null;
                }
                ?>
                <?= $this->Form->control('date_end', [
                    'type' => 'date',
                    'label' => false,
                    'class' => 'form-control form-control-sm',
                    'value' => $dateEndValue,
                    'id' => 'date-end',
                ]) ?>
            </div>
            <div class="col-md-2 d-flex gap-2">
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
            $columns = ['Utilisateur', 'Offre', 'Période', 'Commentaire', 'Modifié le', 'Actions'];
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
                    <td>
                        <?php if ($range->modified):
                            $now = new \Cake\I18n\FrozenTime();
                            $diff = $now->diffInDays($range->modified);
                            if ($diff == 0) {
                                $timeAgo = "Aujourd'hui";
                            } elseif ($diff == 1) {
                                $timeAgo = 'Hier';
                            } elseif ($diff < 7) {
                                $timeAgo = 'Il y a ' . $diff . ' jours';
                            } elseif ($diff < 30) {
                                $weeks = (int)floor($diff / 7);
                                $timeAgo = 'Il y a ' . $weeks . ' semaine' . ($weeks > 1 ? 's' : '');
                            } else {
                                $months = (int)floor($diff / 30);
                                $timeAgo = 'Il y a ' . $months . ' mois';
                            }
                        ?>
                            <span data-bs-toggle="tooltip" title="<?= h($range->modified->i18nFormat('dd/MM/yyyy HH:mm')) ?>">
                                <?= h($timeAgo) ?>
                            </span>
                        <?php endif; ?>
                    </td>
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
