<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Range> $absences // Type hinté comme Range, on garde pour l'instant
 */
?>
<?php $this->assign('title', 'Liste des Absences'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php $this->Html->script('absences-filters', ['block' => true]); ?>

<div class="crud-app absences index content">
    <div class="crud-header">
        <div>
            <h1>
                <i class="bi bi-calendar-x"></i>
                Absences
            </h1>
            <p class="crud-header-meta"><?= $this->Paginator->counter('{{count}} absences') ?></p>
        </div>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle me-1"></i> Nouvelle Absence',
                ['action' => 'add'],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-list-ul"></i> Raccourcis
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?= $this->Html->link(
                        '<i class="bi bi-people-fill me-2"></i> Liste des Utilisateurs',
                        ['controller' => 'Users', 'action' => 'index'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                    <?= $this->Html->link(
                        '<i class="bi bi-tags-fill me-2"></i> Liste des Offres',
                        ['controller' => 'Offers', 'action' => 'index'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                </div>
            </div>
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
            <div class="col-md-2">
                <label for="offer-id" class="form-label small text-muted mb-1">Type d'absence</label>
                <?= $this->Form->select('offer_id', $offers, [
                    'empty' => 'Tous les types',
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
                } elseif (!is_string($dateStartValue) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStartValue ?? '')) {
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
                } elseif (!is_string($dateEndValue) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEndValue ?? '')) {
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
            <div class="col-md-3 d-flex gap-2">
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

    <?php if (count($absences) > 0): ?>
    <?php
    $bulkActionUrl = ['controller' => 'Ranges', 'action' => 'bulkDelete'];
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
            <span class="text-muted small" id="selectedCount">0 absence(s) sélectionnée(s)</span>
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
            $columns = ['Utilisateur', 'Type d\'Absence', 'Début', 'Fin', 'Commentaire', 'Modifié le', 'Actions'];
            $colCount = count($columns) + (count($absences) > 0 ? 1 : 0);
            ?>
            <thead>
            <tr>
                <?php if (count($absences) > 0): ?>
                <th scope="col" style="width: 40px;">
                    <input type="checkbox" id="selectAll" title="Tout sélectionner">
                </th>
                <?php endif; ?>
                <th scope="col"><?= $this->Paginator->sort('user_id', $columns[0]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('offer_id', $columns[1]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('date_start', $columns[2]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('date_end', $columns[3]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('comment', $columns[4]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('modified', $columns[5]) ?></th>
                <th scope="col" class="actions"><?= h($columns[6]) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($absences) === 0): ?>
                <tr>
                    <td colspan="<?= (int)$colCount ?>" class="crud-empty">
                        <p>Aucune absence.</p>
                        <?php if (!$this->request->getQuery()): ?>
                            <?= $this->Html->link(
                                '<i class="bi bi-plus-circle me-1"></i> Créer une absence',
                                ['action' => 'add'],
                                ['class' => 'btn btn-sm btn-primary', 'escape' => false]
                            ) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($absences as $absence) : ?>
                <?php
                $userLabel = $absence->hasValue('user')
                    ? $absence->user->last_name . ' ' . $absence->user->first_name
                    : '#' . $absence->id;
                ?>
                <tr>
                    <td>
                        <input type="checkbox" name="ids[]" value="<?= (int)$absence->id ?>" class="range-checkbox">
                    </td>
                    <td>
                        <?= $this->Html->link(
                            $userLabel,
                            ['controller' => 'Ranges', 'action' => 'view', $absence->id],
                            ['class' => 'crud-row-link']
                        ) ?>
                    </td>
                    <td><?= $absence->hasValue('offer') ? h($absence->offer->name) : '' ?></td>
                    <td><?= h($absence->date_start ? $absence->date_start->i18nFormat('dd/MM/yy HH:mm') : '') ?></td>
                    <td><?= h($absence->date_end ? $absence->date_end->i18nFormat('dd/MM/yy HH:mm') : '') ?></td>
                    <td><?= h($absence->comment) ?></td>
                    <td>
                        <?php if ($absence->modified):
                            $now = new \Cake\I18n\FrozenTime();
                            $diff = $now->diffInDays($absence->modified);
                            $timeAgo = '';
                            if ($diff == 0) {
                                $timeAgo = "Aujourd'hui";
                            } elseif ($diff == 1) {
                                $timeAgo = 'Hier';
                            } elseif ($diff < 7) {
                                $timeAgo = 'Il y a ' . $diff . ' jours';
                            } elseif ($diff < 30) {
                                $weeks = floor($diff / 7);
                                $timeAgo = 'Il y a ' . $weeks . ' semaine' . ($weeks > 1 ? 's' : '');
                            } else {
                                $months = floor($diff / 30);
                                $timeAgo = 'Il y a ' . $months . ' mois';
                            }
                        ?>
                            <span data-bs-toggle="tooltip" title="<?= h($absence->modified->i18nFormat('dd/MM/yyyy HH:mm')) ?>">
                                <?= h($timeAgo) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <?= $this->Html->link(
                            '<i class="bi bi-pencil" aria-hidden="true"></i>',
                            ['controller' => 'Ranges', 'action' => 'edit', $absence->id],
                            [
                                'class' => 'crud-action',
                                'escape' => false,
                                'title' => 'Modifier',
                                'aria-label' => 'Modifier',
                                'data-bs-toggle' => 'tooltip',
                            ]
                        ) ?>
                        <?= $this->Form->postLink(
                            '<i class="bi bi-trash" aria-hidden="true"></i>',
                            ['controller' => 'Ranges', 'action' => 'delete', $absence->id],
                            [
                                'confirm' => 'Voulez-vous vraiment supprimer cette absence ?',
                                'class' => 'crud-action crud-action-danger',
                                'escape' => false,
                                'title' => 'Supprimer',
                                'aria-label' => 'Supprimer',
                                'data-bs-toggle' => 'tooltip',
                            ]
                        ) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (count($absences) > 0): ?>
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
