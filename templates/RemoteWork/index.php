<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Range> $remoteWorkDays
 * @var array $users
 * @var array $stats
 * @var int $remoteWorkOfferId
 */
?>
<?php $this->assign('title', 'Gestion des Jours de Télétravail'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php $this->Html->script('remote-work-days-filters', ['block' => true]); ?>

<style>
.remote-work-days .table-hover tbody tr:hover {
    background-color: rgba(23, 162, 184, 0.08) !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.2s ease;
}
</style>

<div class="remote-work-days index content card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="mb-0">
            <i class="bi bi-house-door-fill text-info"></i> Gestion des Jours de Télétravail
        </h3>
        <div class="btn-toolbar">
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle mr-1"></i> Nouveau Jour',
                ['action' => 'add'],
                ['class' => 'btn btn-success mr-2', 'escape' => false]
            ) ?>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bi bi-three-dots-vertical"></i> Raccourcis
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                    <?= $this->Html->link(
                        '<i class="bi bi-people-fill mr-2"></i> Liste des Utilisateurs',
                        ['controller' => 'Users', 'action' => 'index'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                    <?= $this->Html->link(
                        '<i class="bi bi-tags-fill mr-2"></i> Liste des Offres',
                        ['controller' => 'Offers', 'action' => 'index'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php // --- Cards de statistiques --- ?>
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card border-primary">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-house-door-fill text-primary" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['total'] ?></h3>
                        <small class="text-muted">Total</small>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-info">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-calendar-event text-info" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['current_month'] ?></h3>
                        <small class="text-muted">Ce mois-ci</small>
                    </div>
                </div>
            </div>
        </div>

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
                <div class="col-md-2 mb-2">
                    <label for="range-type" class="form-label small text-muted mb-1">
                        <i class="bi bi-funnel"></i> Type
                    </label>
                    <?= $this->Form->select('range_type', [
                        'all' => 'Tous (fixes + flexibles)',
                        'flexible' => 'Flexibles uniquement',
                        'fixed' => 'Fixes uniquement',
                    ], [
                        'class' => 'form-control form-control-sm',
                        'value' => $rangeType ?? 'all',
                        'id' => 'range-type'
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
                    } elseif (!is_string($dateStartValue) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStartValue ?? '')) {
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
                    } elseif (!is_string($dateEndValue) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEndValue ?? '')) {
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
                        <?= $this->Paginator->counter('{{count}} jour(s) au total, affichant {{current}} sur cette page') ?>
                    </small>
                </div>
            </div>
        <?= $this->Form->end() ?>

        <?php if (count($remoteWorkDays) > 0): ?>
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
                <span class="text-muted small" id="selectedCount">0 jour(s) sélectionné(s)</span>
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
                    <?php if (count($remoteWorkDays) > 0): ?>
                    <th style="width: 40px;">
                        <input type="checkbox" id="selectAll" title="Tout sélectionner">
                    </th>
                    <?php endif; ?>
                    <th scope="col"><?= $this->Paginator->sort('id', 'ID') ?></th>
                    <th scope="col"><?= $this->Paginator->sort('user_id', 'Utilisateur') ?></th>
                    <th scope="col">Type</th>
                    <th scope="col"><?= $this->Paginator->sort('date_start', 'Début') ?></th>
                    <th scope="col"><?= $this->Paginator->sort('date_end', 'Fin') ?></th>
                    <th scope="col"><?= $this->Paginator->sort('comment', 'Commentaire') ?></th>
                    <th scope="col"><?= $this->Paginator->sort('modified', 'Modifié le') ?></th>
                    <th scope="col" class="actions"><?= 'Actions' ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (count($remoteWorkDays) === 0): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <div class="empty-state">
                                <i class="bi bi-house-door" style="font-size: 4rem; color: #dee2e6;"></i>
                                <h4 class="mt-3 text-muted">Aucun jour de télétravail trouvé</h4>
                                <p class="text-muted">
                                    <?php if ($this->request->getQuery()): ?>
                                        Aucun jour ne correspond aux critères de recherche.
                                    <?php else: ?>
                                        Commencez par créer votre premier jour de télétravail.
                                    <?php endif; ?>
                                </p>
                                <?php if (!$this->request->getQuery()): ?>
                                    <?= $this->Html->link(
                                        '<i class="bi bi-plus-circle mr-2"></i> Créer le premier jour',
                                        ['action' => 'add'],
                                        ['class' => 'btn btn-primary mt-2', 'escape' => false]
                                    ) ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($remoteWorkDays as $day) : ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="ids[]" value="<?= (int)$day->id ?>" class="range-checkbox">
                        </td>
                        <td><span class="badge badge-secondary"><?= $this->Number->format($day->id) ?></span></td>
                        <td>
                            <?php if ($day->hasValue('user')): ?>
                                <strong><?= h($day->user->last_name . ' ' . $day->user->first_name) ?></strong>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($day->comment && strpos($day->comment, '[AUTO-TAD]') === 0): ?>
                                <span class="badge badge-success">Fixe</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Flexible</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="d-inline-flex align-items-center"><i class="bi bi-clock mr-1"></i> <?= h($day->date_start ? $day->date_start->i18nFormat('dd/MM/yy HH:mm') : '') ?></span></td>
                        <td><span class="d-inline-flex align-items-center"><i class="bi bi-clock-fill mr-1"></i> <?= h($day->date_end ? $day->date_end->i18nFormat('dd/MM/yy HH:mm') : '') ?></span></td>
                        <td><span class="text-muted"><?= h($day->comment) ?></span></td>
                        <td>
                            <?php if ($day->modified): 
                                $now = new \Cake\I18n\FrozenTime();
                                $diff = $now->diffInDays($day->modified);
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
                                <span data-toggle="tooltip" title="<?= h($day->modified->i18nFormat('dd/MM/yyyy HH:mm')) ?>">
                                    <?= h($timeAgo) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <div class="dropdown actions-dropdown" data-entity-id="<?= (int)$day->id ?>">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownActions<?= $day->id ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="bi bi-three-dots-vertical"></i> Actions
                                </button>
                                <div class="dropdown-menu dropdown-menu-right actions-dropdown-menu" data-entity-id="<?= (int)$day->id ?>" aria-labelledby="dropdownActions<?= $day->id ?>">
                                    <?= $this->Html->link(
                                        '<i class="bi bi-eye mr-2"></i> Voir',
                                        ['controller' => 'Ranges', 'action' => 'view', $day->id],
                                        ['class' => 'dropdown-item', 'escape' => false]
                                    ) ?>
                                    <?= $this->Html->link(
                                        '<i class="bi bi-pencil mr-2"></i> Modifier',
                                        ['controller' => 'Ranges', 'action' => 'edit', $day->id],
                                        ['class' => 'dropdown-item', 'escape' => false]
                                    ) ?>
                                    <div class="dropdown-divider"></div>
                                    <?= $this->Form->postLink(
                                        '<i class="bi bi-trash mr-2"></i> Supprimer',
                                        ['controller' => 'Ranges', 'action' => 'delete', $day->id],
                                        [
                                            'confirm' => 'Voulez-vous vraiment supprimer ce jour de télétravail ?',
                                            'class' => 'dropdown-item text-danger',
                                            'escape' => false
                                        ]
                                    ) ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($remoteWorkDays) > 0): ?>
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
