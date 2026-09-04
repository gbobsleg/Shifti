<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Region> $regions
 */
?>
<?php $this->assign('title', 'Liste des Régions'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app regions index content">
    <div class="crud-header">
        <div>
            <h1>
                <i class="bi bi-diagram-3"></i>
                Régions
            </h1>
            <p class="crud-header-meta"><?= $this->Paginator->counter('{{count}} régions') ?></p>
        </div>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle me-1"></i> Nouvelle Région',
                ['action' => 'add'],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-list-ul"></i> Raccourcis
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?= $this->Html->link(
                        '<i class="bi bi-geo-alt-fill me-2"></i> Sites',
                        ['controller' => 'Sites', 'action' => 'index'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-sm crud-table">
            <?php
            $columns = ['Nom', 'Numéro', 'Sites', 'Actions'];
            $colCount = count($columns);
            ?>
            <thead>
            <tr>
                <th scope="col"><?= $this->Paginator->sort('name', $columns[0]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('number', $columns[1]) ?></th>
                <th scope="col"><?= h($columns[2]) ?></th>
                <th scope="col" class="actions"><?= h($columns[3]) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($regions) === 0): ?>
                <tr>
                    <td colspan="<?= (int)$colCount ?>" class="crud-empty">
                        <p>Aucune région.</p>
                        <?= $this->Html->link(
                            '<i class="bi bi-plus-circle me-1"></i> Créer une région',
                            ['action' => 'add'],
                            ['class' => 'btn btn-sm btn-primary', 'escape' => false]
                        ) ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($regions as $region): ?>
                <tr>
                    <td>
                        <?= $this->Html->link(
                            $region->name,
                            ['action' => 'view', $region->id],
                            ['class' => 'crud-row-link']
                        ) ?>
                    </td>
                    <td><?= h($region->number) ?></td>
                    <td><?= isset($region->sites) ? count($region->sites) : 0 ?></td>
                    <td class="actions">
                        <?= $this->Html->link(
                            '<i class="bi bi-pencil" aria-hidden="true"></i>',
                            ['action' => 'edit', $region->id],
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
                            ['action' => 'delete', $region->id],
                            [
                                'confirm' => 'Voulez-vous vraiment supprimer la région "' . h($region->name) . '" ?',
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
