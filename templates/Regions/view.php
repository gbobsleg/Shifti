<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Region $region
 */
?>
<?php $this->assign('title', 'Détails Région : ' . h($region->name)); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="regions view content card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-diagram-3 text-primary"></i>
            <?= h($region->name) ?>
        </h3>
        <div class="btn-toolbar">
            <?= $this->Html->link(
                '<i class="bi bi-pencil mr-1"></i> Modifier',
                ['action' => 'edit', $region->id],
                ['class' => 'btn btn-primary mr-2', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash mr-1"></i> Supprimer',
                ['action' => 'delete', $region->id],
                ['confirm' => 'Voulez-vous vraiment supprimer "' . h($region->name) . '" ?', 'class' => 'btn btn-danger mr-2', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list mr-1"></i> Liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div class="card-body">
        <?php // --- Informations de la région --- ?>
        <div class="card border-primary mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-info-circle"></i> Informations de la région
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-tag"></i> Nom</label>
                        <div><strong><?= h($region->name) ?></strong></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-hash"></i> Numéro</label>
                        <div>
                            <span class="badge badge-info" style="font-size: 1rem;">
                                <?= h($region->number) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php // --- Sites Associés --- ?>
        <div class="card border-success">
            <div class="card-header bg-success text-white">
                <i class="bi bi-geo-alt"></i> Sites Associés
                <?php if (!empty($region->sites)): ?>
                    <span class="badge badge-light ml-2"><?= count($region->sites) ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!empty($region->sites)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm">
                            <thead>
                            <tr>
                                <th scope="col">Nom</th>
                                <th scope="col">Numéro</th>
                                <th scope="col" class="actions">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($region->sites as $site): ?>
                                <tr>
                                    <td><strong><?= h($site->name) ?></strong></td>
                                    <td>
                                        <span class="badge badge-secondary"><?= h($site->number) ?></span>
                                    </td>
                                    <td class="actions">
                                        <div class="dropdown actions-dropdown" data-entity-id="<?= (int)$site->id ?>">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownSite<?= $site->id ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <i class="bi bi-three-dots-vertical"></i> Actions
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-right actions-dropdown-menu" data-entity-id="<?= (int)$site->id ?>" aria-labelledby="dropdownSite<?= $site->id ?>">
                                                <?= $this->Html->link(
                                                    '<i class="bi bi-eye mr-2"></i> Voir',
                                                    ['controller' => 'Sites', 'action' => 'view', $site->id],
                                                    ['class' => 'dropdown-item', 'escape' => false]
                                                ) ?>
                                                <?= $this->Html->link(
                                                    '<i class="bi bi-pencil mr-2"></i> Modifier',
                                                    ['controller' => 'Sites', 'action' => 'edit', $site->id],
                                                    ['class' => 'dropdown-item', 'escape' => false]
                                                ) ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-geo-x" style="font-size: 3rem; color: #dee2e6;"></i>
                        <p class="text-muted mt-3">Aucun site associé à cette région.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php // --- Boutons d'action --- ?>
        <div class="mt-4">
            <?= $this->Html->link(
                '<i class="bi bi-pencil mr-2"></i> Modifier',
                ['action' => 'edit', $region->id],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-arrow-left mr-2"></i> Retour à la liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary ml-2', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash mr-2"></i> Supprimer',
                ['action' => 'delete', $region->id],
                [
                    'confirm' => 'Voulez-vous vraiment supprimer "' . h($region->name) . '" ?',
                    'class' => 'btn btn-outline-danger ml-2',
                    'escape' => false
                ]
            ) ?>
        </div>
    </div>
</div>
