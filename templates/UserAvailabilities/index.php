<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\UserAvailability[]|\Cake\Collection\CollectionInterface $userAvailabilities
 */
?>
<?php $this->assign('title', 'Disponibilités des Agents'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php $this->Html->script('user-availabilities-filters', ['block' => true]); ?>

<style>
.user-availabilities .table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.08) !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.2s ease;
}
</style>

<div class="user-availabilities index content card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="mb-0">
            <i class="bi bi-clock text-primary"></i> Disponibilités des Agents
        </h3>
        <div class="btn-toolbar">
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle mr-1"></i> Nouvelle Disponibilité',
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
                </div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php // --- Cards de statistiques --- ?>
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-clock text-primary" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['total'] ?></h3>
                        <small class="text-muted">Total</small>
                    </div>
                </div>
            </div>
            <div class="col-md-9">
                <div class="card border-info">
                    <div class="card-header bg-info text-white">
                        <i class="bi bi-calendar-week"></i> Répartition par jour
                    </div>
                    <div class="card-body py-2">
                        <div class="row text-center">
                            <?php 
                            $dayLabels = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', 7 => 'Dim'];
                            foreach ($dayLabels as $dayNum => $dayLabel): 
                            ?>
                                <div class="col">
                                    <small class="text-muted d-block"><?= $dayLabel ?></small>
                                    <strong class="text-info"><?= $stats['by_day'][$dayNum] ?? 0 ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php // --- Toolbar de filtrage --- ?>
        <?= $this->Form->create(null, ['type' => 'get', 'class' => 'filters-toolbar mb-4 p-3 bg-light border rounded']) ?>
            <div class="row">
                <div class="col-md-5 mb-2">
                    <label for="user-id" class="form-label small text-muted mb-1">
                        <i class="bi bi-person"></i> Agent
                    </label>
                    <?= $this->Form->select('user_id', $users, [
                        'empty' => 'Tous les agents',
                        'class' => 'form-control form-control-sm',
                        'id' => 'user-id',
                        'value' => $this->request->getQuery('user_id')
                    ]) ?>
                </div>
                <div class="col-md-3 mb-2">
                    <label for="day-of-week" class="form-label small text-muted mb-1">
                        <i class="bi bi-calendar-week"></i> Jour
                    </label>
                    <?= $this->Form->select('day_of_week', [
                        1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi',
                        5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'
                    ], [
                        'empty' => 'Tous les jours',
                        'class' => 'form-control form-control-sm',
                        'id' => 'day-of-week',
                        'value' => $this->request->getQuery('day_of_week')
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
                        <?= $this->Paginator->counter('{{count}} disponibilité(s) au total, affichant {{current}} sur cette page') ?>
                    </small>
                </div>
            </div>
        <?= $this->Form->end() ?>
        
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm">
                <thead>
                <tr>
                    <th scope="col"><?= $this->Paginator->sort('user_id', 'Utilisateur') ?></th>
                    <th scope="col"><?= $this->Paginator->sort('day_of_week', 'Jour') ?></th>
                    <th scope="col"><?= $this->Paginator->sort('availability_start_time', 'Début Dispo') ?></th>
                    <th scope="col"><?= $this->Paginator->sort('availability_end_time', 'Fin Dispo') ?></th>
                    <th scope="col"><?= $this->Paginator->sort('earliest_end_time', 'Fin la plus tôt') ?></th>
                    <th scope="col" class="actions">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (count($userAvailabilities) === 0): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <div class="empty-state">
                                <i class="bi bi-clock" style="font-size: 4rem; color: #dee2e6;"></i>
                                <h4 class="mt-3 text-muted">Aucune disponibilité trouvée</h4>
                                <p class="text-muted">
                                    <?php if ($this->request->getQuery()): ?>
                                        Aucune disponibilité ne correspond aux critères de recherche.
                                    <?php else: ?>
                                        Commencez par créer la première disponibilité.
                                    <?php endif; ?>
                                </p>
                                <?php if (!$this->request->getQuery()): ?>
                                    <?= $this->Html->link(
                                        '<i class="bi bi-plus-circle mr-2"></i> Créer la première disponibilité',
                                        ['action' => 'add'],
                                        ['class' => 'btn btn-primary mt-2', 'escape' => false]
                                    ) ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($userAvailabilities as $userAvailability): ?>
                    <tr>
                        <td>
                            <?php if ($userAvailability->has('user')): ?>
                                <strong><?= h($userAvailability->user->full_name) ?></strong>
                            <?php else: ?>
                                <span class="text-muted">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-info">
                                <i class="bi bi-calendar"></i> <?= $this->DayOfWeek->format($userAvailability->day_of_week) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-success">
                                <i class="bi bi-clock"></i> <?= h($userAvailability->availability_start_time) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-danger">
                                <i class="bi bi-clock-fill"></i> <?= h($userAvailability->availability_end_time) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($userAvailability->earliest_end_time): ?>
                                <span class="badge badge-warning">
                                    <i class="bi bi-clock-history"></i> <?= h($userAvailability->earliest_end_time) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <div class="dropdown actions-dropdown" data-entity-id="<?= (int)$userAvailability->id ?>">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownActions<?= $userAvailability->id ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="bi bi-three-dots-vertical"></i> Actions
                                </button>
                                <div class="dropdown-menu dropdown-menu-right actions-dropdown-menu" data-entity-id="<?= (int)$userAvailability->id ?>" aria-labelledby="dropdownActions<?= $userAvailability->id ?>">
                                    <?= $this->Html->link(
                                        '<i class="bi bi-eye mr-2"></i> Voir',
                                        ['action' => 'view', $userAvailability->id],
                                        ['class' => 'dropdown-item', 'escape' => false]
                                    ) ?>
                                    <?= $this->Html->link(
                                        '<i class="bi bi-pencil mr-2"></i> Modifier',
                                        ['action' => 'edit', $userAvailability->id],
                                        ['class' => 'dropdown-item', 'escape' => false]
                                    ) ?>
                                    <div class="dropdown-divider"></div>
                                    <?= $this->Form->postLink(
                                        '<i class="bi bi-trash mr-2"></i> Supprimer',
                                        ['action' => 'delete', $userAvailability->id],
                                        [
                                            'confirm' => 'Voulez-vous vraiment supprimer cette disponibilité ?',
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
