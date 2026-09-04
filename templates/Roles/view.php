<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Role $role
 */
?>
<?php $this->assign('title', 'Détails du Rôle : ' . h($role->name)); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app roles view content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-shield-lock"></i>
            <?= h($role->name) ?>
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-pencil me-1"></i> Modifier',
                ['action' => 'edit', $role->id],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list me-1"></i> Liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash me-1"></i> Supprimer',
                ['action' => 'delete', $role->id],
                [
                    'confirm' => 'Voulez-vous vraiment supprimer "' . h($role->name) . '" ?',
                    'class' => 'btn btn-outline-danger',
                    'escape' => false,
                ]
            ) ?>
        </div>
    </div>

    <section class="crud-section">
        <h2 class="crud-section-title">Informations</h2>
        <dl class="crud-fields">
            <div>
                <dt>Nom</dt>
                <dd><?= h($role->name) ?></dd>
            </div>
            <div>
                <dt>Priorité</dt>
                <dd><?= $this->Number->format($role->priority) ?></dd>
            </div>
            <div>
                <dt>Créé le</dt>
                <dd><?= h($role->created ? $role->created->i18nFormat('dd/MM/yyyy HH:mm') : '—') ?></dd>
            </div>
            <div>
                <dt>Modifié le</dt>
                <dd><?= h($role->modified ? $role->modified->i18nFormat('dd/MM/yyyy HH:mm') : '—') ?></dd>
            </div>
        </dl>
    </section>

    <section class="crud-section">
        <h2 class="crud-section-title">
            Utilisateurs Associés
            <?php if (!empty($role->users)): ?>
                (<?= count($role->users) ?>)
            <?php endif; ?>
        </h2>
        <?php if (!empty($role->users)): ?>
            <div class="table-responsive">
                <table class="table table-hover table-sm crud-table">
                    <thead>
                    <tr>
                        <th scope="col">Code</th>
                        <th scope="col">Nom</th>
                        <th scope="col">Prénom</th>
                        <th scope="col">Site</th>
                        <th scope="col">Email</th>
                        <th scope="col" class="actions">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($role->users as $user): ?>
                        <tr>
                            <td><?= h($user->user_code) ?></td>
                            <td>
                                <?= $this->Html->link(
                                    $user->last_name,
                                    ['controller' => 'Users', 'action' => 'view', $user->id],
                                    ['class' => 'crud-row-link']
                                ) ?>
                            </td>
                            <td><?= h($user->first_name) ?></td>
                            <td><?= isset($user->site->name) ? h($user->site->name) : '—' ?></td>
                            <td><?= h($user->email) ?></td>
                            <td class="actions">
                                <?= $this->Html->link(
                                    '<i class="bi bi-pencil" aria-hidden="true"></i>',
                                    ['controller' => 'Users', 'action' => 'edit', $user->id],
                                    [
                                        'class' => 'crud-action',
                                        'escape' => false,
                                        'title' => 'Modifier',
                                        'aria-label' => 'Modifier',
                                        'data-bs-toggle' => 'tooltip',
                                    ]
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted mb-0">Aucun utilisateur associé à ce rôle.</p>
        <?php endif; ?>
    </section>
</div>
