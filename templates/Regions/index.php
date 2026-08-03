<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Region> $regions
 */
?>
<?php $this->assign('title', 'Liste des Régions'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<style>
.regions .table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.08) !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.2s ease;
}
</style>

<div class="regions index content card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-diagram-3 text-primary"></i>
            Liste des Régions
        </h3>
        <div class="btn-toolbar">
            <div class="btn-group mr-2">
                <?= $this->Html->link(
                    '<i class="bi bi-plus-circle mr-1"></i> Nouvelle Région',
                    ['action' => 'add'],
                    ['class' => 'btn btn-success', 'escape' => false]
                ) ?>
            </div>
            <div class="btn-group">
                <?= $this->Html->link(
                    '<i class="bi bi-geo-alt-fill mr-1"></i> Sites',
                    ['controller' => 'Sites', 'action' => 'index'],
                    ['class' => 'btn btn-outline-secondary', 'escape' => false]
                ) ?>
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php // --- Cards de statistiques --- ?>
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-diagram-3 text-primary" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['total'] ?></h3>
                        <small class="text-muted">Total</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-geo-alt text-success" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['total_sites'] ?></h3>
                        <small class="text-muted">Sites</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-check-circle text-info" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['with_sites'] ?></h3>
                        <small class="text-muted">Avec sites</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-secondary">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-x-circle text-secondary" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['without_sites'] ?></h3>
                        <small class="text-muted">Sans sites</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm">
                <thead>
                <tr>
                    <th scope="col"><?= $this->Paginator->sort('name', 'Nom') ?></th>
                    <th scope="col"><?= $this->Paginator->sort('number', 'Numéro') ?></th>
                    <th scope="col">Sites</th>
                    <th scope="col" class="actions">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (count($regions) === 0): ?>
                    <tr>
                        <td colspan="4" class="text-center py-5">
                            <div class="empty-state">
                                <i class="bi bi-diagram-3" style="font-size: 4rem; color: #dee2e6;"></i>
                                <h4 class="mt-3 text-muted">Aucune région trouvée</h4>
                                <p class="text-muted">Commencez par créer votre première région.</p>
                                <?= $this->Html->link(
                                    '<i class="bi bi-plus-circle mr-2"></i> Créer la première région',
                                    ['action' => 'add'],
                                    ['class' => 'btn btn-primary mt-2', 'escape' => false]
                                ) ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($regions as $region) : ?>
                    <tr>
                        <td><strong><?= h($region->name) ?></strong></td>
                        <td>
                            <span class="badge badge-info"><?= h($region->number) ?></span>
                        </td>
                        <td>
                            <?php if (isset($region->sites) && count($region->sites) > 0): ?>
                                <span class="badge badge-success">
                                    <i class="bi bi-geo-alt"></i> <?= count($region->sites) ?> site<?= count($region->sites) > 1 ? 's' : '' ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">
                                    <i class="bi bi-dash-circle"></i> Aucun
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <div class="dropdown actions-dropdown" data-entity-id="<?= (int)$region->id ?>">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownActions<?= $region->id ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="bi bi-three-dots-vertical"></i> Actions
                                </button>
                                <div class="dropdown-menu dropdown-menu-right actions-dropdown-menu" data-entity-id="<?= (int)$region->id ?>" aria-labelledby="dropdownActions<?= $region->id ?>">
                                    <?= $this->Html->link(
                                        '<i class="bi bi-eye mr-2"></i> Voir',
                                        ['action' => 'view', $region->id],
                                        ['class' => 'dropdown-item', 'escape' => false]
                                    ) ?>
                                    <?= $this->Html->link(
                                        '<i class="bi bi-pencil mr-2"></i> Modifier',
                                        ['action' => 'edit', $region->id],
                                        ['class' => 'dropdown-item', 'escape' => false]
                                    ) ?>
                                    <div class="dropdown-divider"></div>
                                    <?= $this->Form->postLink(
                                        '<i class="bi bi-trash mr-2"></i> Supprimer',
                                        ['action' => 'delete', $region->id],
                                        [
                                            'confirm' => 'Voulez-vous vraiment supprimer la région "' . h($region->name) . '" ?',
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


