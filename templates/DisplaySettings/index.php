<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\DisplaySetting> $displaySettings
 */
?>
<?php $this->assign('title', 'Paramètres d\'Affichage'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app displaySettings index content">
    <div class="crud-header">
        <div>
            <h1>
                <i class="bi bi-sliders"></i>
                Paramètres d'Affichage
            </h1>
            <p class="crud-header-meta"><?= $this->Paginator->counter('{{count}} paramètres') ?></p>
        </div>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle me-1"></i> Nouveau Paramètre',
                ['action' => 'add'],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-list-ul"></i> Raccourcis
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?= $this->Html->link(
                        '<i class="bi bi-speedometer2 me-2"></i> Tableau de bord',
                        ['controller' => 'Pages', 'action' => 'display', 'admin'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-sm crud-table">
            <?php
            $columns = ['Clé', 'Valeur', 'Description', 'Type', 'Actions'];
            $colCount = count($columns);
            ?>
            <thead>
            <tr>
                <th scope="col"><?= $this->Paginator->sort('key', $columns[0]) ?></th>
                <th scope="col"><?= h($columns[1]) ?></th>
                <th scope="col"><?= h($columns[2]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('type', $columns[3]) ?></th>
                <th scope="col" class="actions"><?= h($columns[4]) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($displaySettings) === 0): ?>
                <tr>
                    <td colspan="<?= (int)$colCount ?>" class="crud-empty">
                        <p>Aucun paramètre.</p>
                        <?= $this->Html->link(
                            '<i class="bi bi-plus-circle me-1"></i> Créer un paramètre',
                            ['action' => 'add'],
                            ['class' => 'btn btn-sm btn-primary', 'escape' => false]
                        ) ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($displaySettings as $displaySetting): ?>
                <tr>
                    <td>
                        <?= $this->Html->link(
                            $displaySetting->key,
                            ['action' => 'view', $displaySetting->id],
                            ['class' => 'crud-row-link']
                        ) ?>
                    </td>
                    <td><?= h($displaySetting->value) ?></td>
                    <td><?= h($displaySetting->description) ?></td>
                    <td><?= h($displaySetting->type) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(
                            '<i class="bi bi-pencil" aria-hidden="true"></i>',
                            ['action' => 'edit', $displaySetting->id],
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
                            ['action' => 'delete', $displaySetting->id],
                            [
                                'confirm' => 'Voulez-vous vraiment supprimer ce paramètre ?',
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
