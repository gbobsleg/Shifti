<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Range[]|\Cake\Collection\CollectionInterface $ranges
 */
?>
<?php $this->assign('title', 'Liste des Plages Horaires'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php $this->Html->script('ranges-filters', ['block' => true]); ?>

<style>
.ranges .table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.08) !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.2s ease;
}
</style>

<div class="ranges index content card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="mb-0">
            <i class="bi bi-clock-history text-primary"></i> Liste des Plages Horaires
        </h3>
        <div>
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle mr-1"></i> Nouvelle Plage',
                ['action' => 'add'],
                ['class' => 'btn btn-success', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div class="card-body">
        <?php // --- Première ligne : Total + Cette semaine + Ce mois --- ?>
        <div class="row mb-3">
            <div class="col-md-4">
                <div class="card border-primary">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-clock-history text-primary" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['total'] ?></h3>
                        <small class="text-muted">Total</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-success">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-calendar-week text-success" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['this_week'] ?></h3>
                        <small class="text-muted">Cette semaine</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-info">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-calendar-month text-info" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['this_month'] ?></h3>
                        <small class="text-muted">Ce mois</small>
                    </div>
                </div>
            </div>
        </div>

        <?php // --- Deuxième ligne : Top 3 offres --- ?>
        <?php if (!empty($stats['top_offers'])): ?>
        <div class="row mb-4">
            <?php foreach ($stats['top_offers'] as $offerName => $count): ?>
            <div class="col-md-4">
                <div class="card border-warning">
                    <div class="card-body text-center py-2">
                        <i class="bi bi-basket text-warning" style="font-size: 1.5rem;"></i>
                        <h4 class="mb-0 mt-1"><?= $count ?></h4>
                        <small class="text-muted"><?= h($offerName) ?></small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php // --- Toolbar de filtrage --- ?>
        <?= $this->Form->create(null, ['type' => 'get', 'class' => 'filters-toolbar mb-4 p-3 bg-light border rounded']) ?>
            <div class="row">
                <div class="col-md-3 mb-2">
                    <label for="user-id" class="form-label small text-muted mb-1">
                        <i class="bi bi-person"></i> Agent
                    </label>
                    <?= $this->Form->select('user_id', $users, [
                        'empty' => 'Tous les agents',
                        'class' => 'form-control form-control-sm',
                        'value' => $this->request->getQuery('user_id'),
                        'id' => 'user-id'
                    ]) ?>
                </div>
                <div class="col-md-3 mb-2">
                    <label for="offer-id" class="form-label small text-muted mb-1">
                        <i class="bi bi-tag"></i> Offre
                    </label>
                    <?= $this->Form->select('offer_id', $offers, [
                        'empty' => 'Toutes les offres',
                        'class' => 'form-control form-control-sm',
                        'value' => $this->request->getQuery('offer_id'),
                        'id' => 'offer-id'
                    ]) ?>
                </div>
                <div class="col-md-2 mb-2">
                    <label for="date-start" class="form-label small text-muted mb-1">
                        <i class="bi bi-calendar-event"></i> Date de début
                    </label>
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
                        'id' => 'date-start'
                    ]) ?>
                </div>
                <div class="col-md-2 mb-2">
                    <label for="date-end" class="form-label small text-muted mb-1">
                        <i class="bi bi-calendar-x"></i> Date de fin
                    </label>
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
                        'id' => 'date-end'
                    ]) ?>
                </div>
                <div class="col-md-2 mb-2 d-flex flex-column align-items-stretch">
                    <?= $this->Form->button('<i class="bi bi-search"></i> Filtrer', [
                        'type' => 'submit',
                        'class' => 'btn btn-sm btn-primary mb-1',
                        'escapeTitle' => false
                    ]) ?>
                    <?= $this->Html->link('<i class="bi bi-arrow-counterclockwise"></i> Réinitialiser', 
                        ['action' => 'index'], 
                        ['class' => 'btn btn-sm btn-outline-secondary', 'escape' => false]
                    ) ?>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-12">
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i> 
                        <?= $this->Paginator->counter('{{count}} plage(s) au total, affichant {{current}} sur cette page') ?>
                    </small>
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
                <button type="submit" class="btn btn-sm btn-danger" id="bulkDeleteBtn" disabled>
                    <i class="bi bi-trash"></i> Supprimer la sélection
                </button>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm">
                <thead>
                <tr>
                    <?php if (count($ranges) > 0): ?>
                    <th style="width: 40px;">
                        <input type="checkbox" id="selectAll" title="Tout sélectionner">
                    </th>
                    <?php endif; ?>
                    <th scope="col"><?= $this->Paginator->sort('user_id', 'Utilisateur') ?></th>
                    <th scope="col"><?= $this->Paginator->sort('offer_id', 'Offre') ?></th>
                    <th scope="col"><?= $this->Paginator->sort('date_start', 'Période') ?></th>
                    <th scope="col"><?= $this->Paginator->sort('comment', 'Commentaire') ?></th>
                    <th scope="col"><?= $this->Paginator->sort('modified', 'Modifié le') ?></th>
                    <th scope="col" class="actions">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (count($ranges) === 0): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <div class="empty-state">
                                <i class="bi bi-clock-history" style="font-size: 4rem; color: #dee2e6;"></i>
                                <h4 class="mt-3 text-muted">Aucune plage trouvée</h4>
                                <p class="text-muted">
                                    <?php if ($this->request->getQuery()): ?>
                                        Aucune plage ne correspond aux critères de recherche.
                                    <?php else: ?>
                                        Commencez par créer votre première plage horaire.
                                    <?php endif; ?>
                                </p>
                                <?php if (!$this->request->getQuery()): ?>
                                    <?= $this->Html->link(
                                        '<i class="bi bi-plus-circle mr-2"></i> Créer la première plage',
                                        ['action' => 'add'],
                                        ['class' => 'btn btn-primary mt-2', 'escape' => false]
                                    ) ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($ranges as $range) : ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="ids[]" value="<?= (int)$range->id ?>" class="range-checkbox">
                        </td>
                        <td>
                            <?php if ($range->hasValue('user')): ?>
                                <strong><?= h($range->user->first_name . ' ' . $range->user->last_name) ?></strong>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($range->hasValue('offer')): ?>
                                <span class="badge" style="background-color: <?= h($range->offer->color) ?>; color: #fff;">
                                    <i class="bi bi-basket"></i> <?= h($range->offer->name) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span>
                                <?= h($range->date_start ? $range->date_start->i18nFormat('dd/MM/yy HH:mm') : '') ?>
                                <i class="bi bi-arrow-right"></i>
                                <?= h($range->date_end ? $range->date_end->i18nFormat('dd/MM/yy HH:mm') : '') ?>
                            </span>
                        </td>
                        <td>
                            <span class="text-muted"><?= h($range->comment ?: '-') ?></span>
                        </td>
                        <td>
                            <?php if ($range->modified): 
                                $now = new \Cake\I18n\FrozenTime();
                                $diff = $now->diffInDays($range->modified);
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
                                <span data-toggle="tooltip" title="<?= h($range->modified->i18nFormat('dd/MM/yyyy HH:mm')) ?>">
                                    <?= h($timeAgo) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <div class="dropdown actions-dropdown" data-entity-id="<?= (int)$range->id ?>">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownActions<?= $range->id ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="bi bi-three-dots-vertical"></i> Actions
                                </button>
                                <div class="dropdown-menu dropdown-menu-right actions-dropdown-menu" data-entity-id="<?= (int)$range->id ?>" aria-labelledby="dropdownActions<?= $range->id ?>">
                                    <?= $this->Html->link(
                                        '<i class="bi bi-eye mr-2"></i> Voir',
                                        ['action' => 'view', $range->id],
                                        ['class' => 'dropdown-item', 'escape' => false]
                                    ) ?>
                                    <?= $this->Html->link(
                                        '<i class="bi bi-pencil mr-2"></i> Modifier',
                                        ['action' => 'edit', $range->id],
                                        ['class' => 'dropdown-item', 'escape' => false]
                                    ) ?>
                                    <div class="dropdown-divider"></div>
                                    <a href="#" class="dropdown-item text-danger range-delete-link"
                                       data-confirm="Voulez-vous vraiment supprimer cette plage ?"
                                       data-url="<?= $this->Url->build(['action' => 'delete', $range->id]) ?>">
                                        <i class="bi bi-trash mr-2"></i> Supprimer
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($ranges) > 0): ?>
        <?= $this->Form->end() ?>
        <?php endif; ?>

        <div class="paginator mt-3">
            <ul class="pagination justify-content-center">
                <?= $this->Paginator->first('<< ' . 'Première') ?>
                <?= $this->Paginator->prev('< ' . 'Précédente') ?>
                <?= $this->Paginator->numbers() ?>
                <?= $this->Paginator->next('Suivante' . ' >') ?>
                <?= $this->Paginator->last('Dernière' . ' >>') ?>
            </ul>
            <p class="text-center"><?= $this->Paginator->counter('Page {{page}} sur {{pages}}, affichant {{current}} enregistrement(s) sur {{count}} au total') ?></p>
        </div>

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
