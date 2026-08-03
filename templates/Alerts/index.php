<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Alert> $alerts
 */
?>
<?php $this->assign('title', 'Liste des Alertes'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php $this->Html->script('alerts-filters', ['block' => true]); ?>

<style>
.alerts .table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.08) !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.2s ease;
}
</style>

<div class="alerts index content card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="mb-0">
            <i class="bi bi-bell text-primary"></i> Liste des Alertes
        </h3>
        <div>
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle mr-1"></i> Nouvelle Alerte',
                ['action' => 'add'],
                ['class' => 'btn btn-success', 'escape' => false]
            ) ?>
        </div>
    </div>
        <div class="card-body"> <?php // Ajout card-body ?>
            <?php // --- Cards de statistiques --- ?>
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card border-primary">
                        <div class="card-body text-center py-3">
                            <i class="bi bi-bell text-primary" style="font-size: 2rem;"></i>
                            <h3 class="mb-0 mt-2"><?= $stats['total'] ?></h3>
                            <small class="text-muted">Total</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-success">
                        <div class="card-body text-center py-3">
                            <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                            <h3 class="mb-0 mt-2"><?= $stats['active'] ?></h3>
                            <small class="text-muted">Actives</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card border-danger">
                        <div class="card-body text-center py-3">
                            <i class="bi bi-exclamation-triangle text-danger" style="font-size: 2rem;"></i>
                            <h3 class="mb-0 mt-2"><?= $stats['urgent'] ?></h3>
                            <small class="text-muted">Urgent</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card border-warning">
                        <div class="card-body text-center py-3">
                            <i class="bi bi-flag text-warning" style="font-size: 2rem;"></i>
                            <h3 class="mb-0 mt-2"><?= $stats['important'] ?></h3>
                            <small class="text-muted">Important</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card border-info">
                        <div class="card-body text-center py-3">
                            <i class="bi bi-info-circle text-info" style="font-size: 2rem;"></i>
                            <h3 class="mb-0 mt-2"><?= $stats['info'] ?></h3>
                            <small class="text-muted">Info</small>
                        </div>
                    </div>
                </div>
            </div>

            <?php // --- Toolbar de filtrage --- ?>
            <?= $this->Form->create(null, ['type' => 'get', 'class' => 'filters-toolbar mb-4 p-3 bg-light border rounded']) ?>
                <div class="row">
                    <div class="col-md-5 mb-2">
                        <label for="content" class="form-label small text-muted mb-1">
                            <i class="bi bi-search"></i> Contenu
                        </label>
                        <?= $this->Form->text('content', [
                            'class' => 'form-control form-control-sm',
                            'placeholder' => 'Rechercher dans le contenu...',
                            'value' => $this->request->getQuery('content')
                        ]) ?>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label for="priority" class="form-label small text-muted mb-1">
                            <i class="bi bi-flag"></i> Priorité
                        </label>
                        <?= $this->Form->select('priority', [
                            1 => '1 - Urgent',
                            2 => '2 - Important',
                            3 => '3 - Information'
                        ], [
                            'empty' => 'Toutes les priorités',
                            'class' => 'form-control form-control-sm',
                            'value' => $this->request->getQuery('priority')
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
                            <?= $this->Paginator->counter('{{count}} alerte(s) au total, affichant {{current}} sur cette page') ?>
                        </small>
                    </div>
                </div>
            <?= $this->Form->end() ?>
            
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm"> <?php // Ajout classes Bootstrap ?>
                    <thead>
                    <tr>
                        <th scope="col"><?= $this->Paginator->sort('id', 'ID') ?></th>
                        <th scope="col"><?= $this->Paginator->sort('date_start', 'Début') ?></th> <?php // Label FR ?>
                        <th scope="col"><?= $this->Paginator->sort('date_end', 'Fin') ?></th> <?php // Label FR ?>
                        <th scope="col"><?= $this->Paginator->sort('content', 'Contenu') ?></th> <?php // Label FR ?>
                        <th scope="col"><?= $this->Paginator->sort('priority', 'Priorité') ?></th> <?php // Label FR ?>
                        <th scope="col" class="actions"><?= 'Actions' ?></th> <?php // Label FR ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (count($alerts) === 0): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="empty-state">
                                    <i class="bi bi-bell" style="font-size: 4rem; color: #dee2e6;"></i>
                                    <h4 class="mt-3 text-muted">Aucune alerte trouvée</h4>
                                    <p class="text-muted">
                                        <?php if ($this->request->getQuery()): ?>
                                            Aucune alerte ne correspond aux critères de recherche.
                                        <?php else: ?>
                                            Commencez par créer votre première alerte.
                                        <?php endif; ?>
                                    </p>
                                    <?php if (!$this->request->getQuery()): ?>
                                        <?= $this->Html->link(
                                            '<i class="bi bi-plus-circle mr-2"></i> Créer la première alerte',
                                            ['action' => 'add'],
                                            ['class' => 'btn btn-primary mt-2', 'escape' => false]
                                        ) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($alerts as $alert) : ?>
                        <tr>
                            <td><span class="badge badge-secondary"><?= $this->Number->format($alert->id) ?></span></td>
                            <td><span class="d-inline-flex align-items-center"><i class="bi bi-calendar mr-1"></i> <?= h($alert->date_start ? $alert->date_start->i18nFormat('dd/MM/yyyy') : '') ?></span></td>
                            <td><span class="d-inline-flex align-items-center"><i class="bi bi-calendar mr-1"></i> <?= h($alert->date_end ? $alert->date_end->i18nFormat('dd/MM/yyyy') : '') ?></span></td>
                            <td><?= h($alert->content) ?></td>
                            <td>
                                <?php
                                $priorityBadge = 'badge-info';
                                $priorityIcon = 'bi-info-circle';
                                $priorityLabel = 'Info';
                                if ($alert->priority == 1) {
                                    $priorityBadge = 'badge-danger';
                                    $priorityIcon = 'bi-exclamation-triangle';
                                    $priorityLabel = 'Urgent';
                                } elseif ($alert->priority == 2) {
                                    $priorityBadge = 'badge-warning';
                                    $priorityIcon = 'bi-flag';
                                    $priorityLabel = 'Important';
                                }
                                ?>
                                <span class="badge <?= $priorityBadge ?>">
                                    <i class="bi <?= $priorityIcon ?>"></i> <?= $priorityLabel ?>
                                </span>
                            </td>
                            <td class="actions">
                                <div class="dropdown actions-dropdown" data-entity-id="<?= (int)$alert->id ?>">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownActions<?= $alert->id ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="bi bi-three-dots-vertical"></i> Actions
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right actions-dropdown-menu" data-entity-id="<?= (int)$alert->id ?>" aria-labelledby="dropdownActions<?= $alert->id ?>">
                                        <?= $this->Html->link(
                                            '<i class="bi bi-eye mr-2"></i> Voir',
                                            ['action' => 'view', $alert->id],
                                            ['class' => 'dropdown-item', 'escape' => false]
                                        ) ?>
                                        <?= $this->Html->link(
                                            '<i class="bi bi-pencil mr-2"></i> Modifier',
                                            ['action' => 'edit', $alert->id],
                                            ['class' => 'dropdown-item', 'escape' => false]
                                        ) ?>
                                        <div class="dropdown-divider"></div>
                                        <?= $this->Form->postLink(
                                            '<i class="bi bi-trash mr-2"></i> Supprimer',
                                            ['action' => 'delete', $alert->id],
                                            [
                                                'confirm' => 'Voulez-vous vraiment supprimer cette alerte ?',
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

        </div> <?php // Fin card-body ?>
    </div> <?php // Fin content card ?>
