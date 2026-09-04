<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Role> $roles
 */
?>
<?php $this->assign('title', 'Liste des Rôles'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app roles index content">
    <div class="crud-header">
        <div>
            <h1>
                <i class="bi bi-shield-lock"></i>
                Rôles
            </h1>
            <p class="crud-header-meta"><?= $this->Paginator->counter('{{count}} rôles') ?></p>
        </div>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle me-1"></i> Nouveau Rôle',
                ['action' => 'add'],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-list-ul"></i> Raccourcis
                </button>
                <div class="dropdown-menu dropdown-menu-end">
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
            $columns = ['Priorité', 'Nom du Rôle', 'Utilisateurs', 'Créé le', 'Modifié le', 'Actions'];
            $colCount = count($columns);
            ?>
            <thead>
            <tr>
                <th scope="col"><?= $this->Paginator->sort('priority', $columns[0]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('name', $columns[1]) ?></th>
                <th scope="col"><?= h($columns[2]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('created', $columns[3]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('modified', $columns[4]) ?></th>
                <th scope="col" class="actions"><?= h($columns[5]) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($roles) === 0): ?>
                <tr>
                    <td colspan="<?= (int)$colCount ?>" class="crud-empty">
                        <p>Aucun rôle.</p>
                        <?= $this->Html->link(
                            '<i class="bi bi-plus-circle me-1"></i> Créer un rôle',
                            ['action' => 'add'],
                            ['class' => 'btn btn-sm btn-primary', 'escape' => false]
                        ) ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($roles as $role): ?>
                <tr>
                    <td><?= $this->Number->format($role->priority) ?></td>
                    <td>
                        <?= $this->Html->link(
                            $role->name,
                            ['action' => 'view', $role->id],
                            ['class' => 'crud-row-link']
                        ) ?>
                    </td>
                    <td><?= isset($role->users) ? count($role->users) : 0 ?></td>
                    <td>
                        <?php if ($role->created):
                            $now = new \Cake\I18n\FrozenTime();
                            $diff = $now->diffInDays($role->created);
                            if ($diff == 0) {
                                $timeAgo = "Aujourd'hui";
                            } elseif ($diff == 1) {
                                $timeAgo = 'Hier';
                            } elseif ($diff < 7) {
                                $timeAgo = 'Il y a ' . $diff . ' jours';
                            } elseif ($diff < 30) {
                                $weeks = (int)floor($diff / 7);
                                $timeAgo = 'Il y a ' . $weeks . ' semaine' . ($weeks > 1 ? 's' : '');
                            } else {
                                $months = (int)floor($diff / 30);
                                $timeAgo = 'Il y a ' . $months . ' mois';
                            }
                        ?>
                            <span data-bs-toggle="tooltip" title="<?= h($role->created->i18nFormat('dd/MM/yyyy HH:mm')) ?>">
                                <?= h($timeAgo) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($role->modified):
                            $now = new \Cake\I18n\FrozenTime();
                            $diff = $now->diffInDays($role->modified);
                            if ($diff == 0) {
                                $timeAgo = "Aujourd'hui";
                            } elseif ($diff == 1) {
                                $timeAgo = 'Hier';
                            } elseif ($diff < 7) {
                                $timeAgo = 'Il y a ' . $diff . ' jours';
                            } elseif ($diff < 30) {
                                $weeks = (int)floor($diff / 7);
                                $timeAgo = 'Il y a ' . $weeks . ' semaine' . ($weeks > 1 ? 's' : '');
                            } else {
                                $months = (int)floor($diff / 30);
                                $timeAgo = 'Il y a ' . $months . ' mois';
                            }
                        ?>
                            <span data-bs-toggle="tooltip" title="<?= h($role->modified->i18nFormat('dd/MM/yyyy HH:mm')) ?>">
                                <?= h($timeAgo) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <?= $this->Html->link(
                            '<i class="bi bi-pencil" aria-hidden="true"></i>',
                            ['action' => 'edit', $role->id],
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
                            ['action' => 'delete', $role->id],
                            [
                                'confirm' => 'Voulez-vous vraiment supprimer le rôle "' . h($role->name) . '" ?',
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
