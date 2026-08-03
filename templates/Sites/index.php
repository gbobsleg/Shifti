<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Site[]|\Cake\Collection\CollectionInterface $sites
 */
?>
<?php $this->assign('title', 'Liste des sites'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<style>
.sites .table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.08) !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.2s ease;
}
</style>

<div class="sites index content card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-geo-alt text-primary"></i>
            Liste des Sites
        </h3>
        <div class="btn-toolbar">
            <div class="btn-group mr-2">
                <?= $this->Html->link(
                    '<i class="bi bi-plus-circle mr-1"></i> Nouveau Site',
                    ['action' => 'add'],
                    ['class' => 'btn btn-success', 'escape' => false]
                ) ?>
            </div>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-toggle="dropdown">
                    <i class="bi bi-list-ul"></i> Raccourcis
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                    <?= $this->Html->link(
                        '<i class="bi bi-diagram-3 mr-2"></i> Régions',
                        ['controller' => 'Regions', 'action' => 'index'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                    <?= $this->Html->link(
                        '<i class="bi bi-people-fill mr-2"></i> Utilisateurs',
                        ['controller' => 'Users', 'action' => 'index'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php // --- Première ligne : Total + Utilisateurs --- ?>
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="card border-primary">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-geo-alt text-primary" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['total'] ?></h3>
                        <small class="text-muted">Total</small>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-success">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-people text-success" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['total_users'] ?></h3>
                        <small class="text-muted">Utilisateurs</small>
                    </div>
                </div>
            </div>
        </div>

        <?php // --- Deuxième ligne : Top 3 régions --- ?>
        <?php if (!empty($stats['top_regions'])): ?>
        <div class="row mb-4">
            <?php foreach ($stats['top_regions'] as $regionName => $count): ?>
            <div class="col-md-4">
                <div class="card border-info">
                    <div class="card-body text-center py-2">
                        <i class="bi bi-diagram-3 text-info" style="font-size: 1.5rem;"></i>
                        <h4 class="mb-0 mt-1"><?= $count ?></h4>
                        <small class="text-muted"><?= h($regionName) ?></small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="table-responsive">
    <table class="table table-striped table-hover table-sm">
        <thead>
        <tr>
            <th scope="col"><?= $this->Paginator->sort('name', 'Nom') ?></th>
            <th scope="col"><?= $this->Paginator->sort('number', 'Numéro') ?></th>
            <th scope="col"><?= $this->Paginator->sort('region_id', 'Région') ?></th>
            <th scope="col">Utilisateurs</th>
            <th scope="col" class="actions">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php if (count($sites) === 0): ?>
            <tr>
                <td colspan="5" class="text-center py-5">
                    <div class="empty-state">
                        <i class="bi bi-geo-alt" style="font-size: 4rem; color: #dee2e6;"></i>
                        <h4 class="mt-3 text-muted">Aucun site trouvé</h4>
                        <p class="text-muted">Commencez par créer votre premier site.</p>
                        <?= $this->Html->link(
                            '<i class="bi bi-plus-circle mr-2"></i> Créer le premier site',
                            ['action' => 'add'],
                            ['class' => 'btn btn-primary mt-2', 'escape' => false]
                        ) ?>
                    </div>
                </td>
            </tr>
        <?php endif; ?>
        <?php foreach ($sites as $site): ?>
            <tr>
                <td><strong><?= h($site->name) ?></strong></td>
                <td>
                    <span class="badge badge-info"><?= h($site->number) ?></span>
                </td>
                <td>
                    <?php if ($site->has('region')): ?>
                        <span class="badge badge-secondary">
                            <i class="bi bi-diagram-3"></i> <?= h($site->region->name) ?>
                        </span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (isset($site->users) && count($site->users) > 0): ?>
                        <span class="badge badge-success">
                            <i class="bi bi-people"></i> <?= count($site->users) ?>
                        </span>
                    <?php else: ?>
                        <span class="text-muted">
                            <i class="bi bi-dash-circle"></i> Aucun
                        </span>
                    <?php endif; ?>
                </td>
                <td class="actions">
                    <div class="dropdown actions-dropdown" data-entity-id="<?= (int)$site->id ?>">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownActions<?= $site->id ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="bi bi-three-dots-vertical"></i> Actions
                        </button>
                        <div class="dropdown-menu dropdown-menu-right actions-dropdown-menu" data-entity-id="<?= (int)$site->id ?>" aria-labelledby="dropdownActions<?= $site->id ?>">
                            <?= $this->Html->link(
                                '<i class="bi bi-eye mr-2"></i> Voir',
                                ['action' => 'view', $site->id],
                                ['class' => 'dropdown-item', 'escape' => false]
                            ) ?>
                            <?= $this->Html->link(
                                '<i class="bi bi-pencil mr-2"></i> Modifier',
                                ['action' => 'edit', $site->id],
                                ['class' => 'dropdown-item', 'escape' => false]
                            ) ?>
                            <div class="dropdown-divider"></div>
                            <?= $this->Form->postLink(
                                '<i class="bi bi-trash mr-2"></i> Supprimer',
                                ['action' => 'delete', $site->id],
                                [
                                    'confirm' => 'Voulez-vous vraiment supprimer "' . h($site->name) . '" ?',
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
