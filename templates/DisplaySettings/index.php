<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\DisplaySetting> $displaySettings
 */
?>
<?php $this->assign('title', 'Paramètres d\'Affichage'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="displaySettings index content card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="mb-0">
            <i class="bi bi-sliders text-primary"></i> Paramètres d'Affichage
        </h3>
        <div class="btn-toolbar">
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle mr-1"></i> Nouveau Paramètre',
                ['action' => 'add'],
                ['class' => 'btn btn-success mr-2', 'escape' => false]
            ) ?>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bi bi-three-dots-vertical"></i> Raccourcis
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                    <?= $this->Html->link(
                        '<i class="bi bi-speedometer2 mr-2"></i> Tableau de bord',
                        ['controller' => 'Pages', 'action' => 'display', 'admin'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm">
                <thead>
                    <tr>
                        <th scope="col"><?= $this->Paginator->sort('key', 'Clé') ?></th>
                        <th scope="col">Valeur</th>
                        <th scope="col">Description</th>
                        <th scope="col"><?= $this->Paginator->sort('type', 'Type') ?></th>
                        <th scope="col" class="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($displaySettings) === 0): ?>
                    <tr>
                        <td colspan="5" class="text-center py-5">
                            <div class="empty-state">
                                <i class="bi bi-sliders" style="font-size: 4rem; color: #dee2e6;"></i>
                                <h4 class="mt-3 text-muted">Aucun paramètre trouvé</h4>
                                <p class="text-muted">Commencez par créer le premier paramètre.</p>
                                <?= $this->Html->link(
                                    '<i class="bi bi-plus-circle mr-2"></i> Créer le premier paramètre',
                                    ['action' => 'add'],
                                    ['class' => 'btn btn-primary mt-2', 'escape' => false]
                                ) ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($displaySettings as $displaySetting): ?>
                    <tr>
                        <td><strong><?= h($displaySetting->key) ?></strong></td>
                        <td>
                            <span class="badge badge-info">
                                <?= h($displaySetting->value) ?>
                            </span>
                        </td>
                        <td class="text-muted"><?= h($displaySetting->description) ?></td>
                        <td>
                            <span class="badge badge-secondary">
                                <?= h($displaySetting->type) ?>
                            </span>
                        </td>
                        <td class="actions">
                            <div class="dropdown actions-dropdown" data-entity-id="<?= (int)$displaySetting->id ?>">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownActions<?= $displaySetting->id ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="bi bi-three-dots-vertical"></i> Actions
                                </button>
                                <div class="dropdown-menu dropdown-menu-right actions-dropdown-menu" data-entity-id="<?= (int)$displaySetting->id ?>" aria-labelledby="dropdownActions<?= $displaySetting->id ?>">
                                    <?= $this->Html->link(
                                        '<i class="bi bi-eye mr-2"></i> Voir',
                                        ['action' => 'view', $displaySetting->id],
                                        ['class' => 'dropdown-item', 'escape' => false]
                                    ) ?>
                                    <?= $this->Html->link(
                                        '<i class="bi bi-pencil mr-2"></i> Modifier',
                                        ['action' => 'edit', $displaySetting->id],
                                        ['class' => 'dropdown-item', 'escape' => false]
                                    ) ?>
                                    <div class="dropdown-divider"></div>
                                    <?= $this->Form->postLink(
                                        '<i class="bi bi-trash mr-2"></i> Supprimer',
                                        ['action' => 'delete', $displaySetting->id],
                                        [
                                            'confirm' => 'Voulez-vous vraiment supprimer ce paramètre ?',
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