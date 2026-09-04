<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Site[]|\Cake\Collection\CollectionInterface $sites
 */
?>
<?php $this->assign('title', 'Liste des sites'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app sites index content">
    <div class="crud-header">
        <div>
            <h1>
                <i class="bi bi-geo-alt"></i>
                Sites
            </h1>
            <p class="crud-header-meta"><?= $this->Paginator->counter('{{count}} sites') ?></p>
        </div>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle me-1"></i> Nouveau Site',
                ['action' => 'add'],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-list-ul"></i> Raccourcis
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?= $this->Html->link(
                        '<i class="bi bi-diagram-3 me-2"></i> Régions',
                        ['controller' => 'Regions', 'action' => 'index'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                    <?= $this->Html->link(
                        '<i class="bi bi-people-fill me-2"></i> Utilisateurs',
                        ['controller' => 'Users', 'action' => 'index'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-sm crud-table">
            <?php
            $columns = ['Nom', 'Numéro', 'Région', 'Utilisateurs', 'Actions'];
            $colCount = count($columns);
            ?>
            <thead>
            <tr>
                <th scope="col"><?= $this->Paginator->sort('name', $columns[0]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('number', $columns[1]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('region_id', $columns[2]) ?></th>
                <th scope="col"><?= h($columns[3]) ?></th>
                <th scope="col" class="actions"><?= h($columns[4]) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($sites) === 0): ?>
                <tr>
                    <td colspan="<?= (int)$colCount ?>" class="crud-empty">
                        <p>Aucun site.</p>
                        <?= $this->Html->link(
                            '<i class="bi bi-plus-circle me-1"></i> Créer un site',
                            ['action' => 'add'],
                            ['class' => 'btn btn-sm btn-primary', 'escape' => false]
                        ) ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($sites as $site): ?>
                <tr>
                    <td>
                        <?= $this->Html->link(
                            $site->name,
                            ['action' => 'view', $site->id],
                            ['class' => 'crud-row-link']
                        ) ?>
                    </td>
                    <td><?= h($site->number) ?></td>
                    <td><?= $site->has('region') ? h($site->region->name) : '—' ?></td>
                    <td><?= isset($site->users) ? count($site->users) : 0 ?></td>
                    <td class="actions">
                        <?= $this->Html->link(
                            '<i class="bi bi-pencil" aria-hidden="true"></i>',
                            ['action' => 'edit', $site->id],
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
                            ['action' => 'delete', $site->id],
                            [
                                'confirm' => 'Voulez-vous vraiment supprimer "' . h($site->name) . '" ?',
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
