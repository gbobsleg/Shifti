<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Role $role
 */
?>
<?php $this->assign('title', 'Détails du Rôle : ' . h($role->name)); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="roles view content card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-shield-lock text-primary"></i>
            <?= h($role->name) ?>
        </h3>
        <div class="btn-toolbar">
            <?= $this->Html->link(
                '<i class="bi bi-pencil mr-1"></i> Modifier',
                ['action' => 'edit', $role->id],
                ['class' => 'btn btn-primary mr-2', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash mr-1"></i> Supprimer',
                ['action' => 'delete', $role->id],
                ['confirm' => 'Voulez-vous vraiment supprimer "' . h($role->name) . '" ?', 'class' => 'btn btn-danger mr-2', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list mr-1"></i> Liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div class="card-body">
        <?php // --- Informations du rôle --- ?>
        <div class="card border-primary mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-info-circle"></i> Informations du rôle
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-tag"></i> Nom</label>
                        <div><strong><?= h($role->name) ?></strong></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-sort-numeric-up"></i> Priorité</label>
                        <div>
                            <span class="badge badge-primary" style="font-size: 1rem;">
                                <?= $this->Number->format($role->priority) ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-calendar-plus"></i> Créé le</label>
                        <div><?= h($role->created ? $role->created->i18nFormat('dd/MM/yyyy HH:mm') : 'N/A') ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-calendar-check"></i> Modifié le</label>
                        <div><?= h($role->modified ? $role->modified->i18nFormat('dd/MM/yyyy HH:mm') : 'N/A') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <?php // --- Utilisateurs Associés --- ?>
        <div class="card border-success">
            <div class="card-header bg-success text-white">
                <i class="bi bi-people"></i> Utilisateurs Associés
                <?php if (!empty($role->users)): ?>
                    <span class="badge badge-light ml-2"><?= count($role->users) ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!empty($role->users)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm">
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
                                    <td>
                                        <span class="badge badge-secondary"><?= h($user->user_code) ?></span>
                                    </td>
                                    <td><strong><?= h($user->last_name) ?></strong></td>
                                    <td><?= h($user->first_name) ?></td>
                                    <td>
                                        <?php if (isset($user->site->name)): ?>
                                            <small class="text-muted">
                                                <i class="bi bi-geo-alt"></i> <?= h($user->site->name) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?= h($user->email) ?></small></td>
                                    <td class="actions">
                                        <div class="dropdown actions-dropdown" data-entity-id="<?= (int)$user->id ?>">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownUser<?= $user->id ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <i class="bi bi-three-dots-vertical"></i> Actions
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-right actions-dropdown-menu" data-entity-id="<?= (int)$user->id ?>" aria-labelledby="dropdownUser<?= $user->id ?>">
                                                <?= $this->Html->link(
                                                    '<i class="bi bi-eye mr-2"></i> Voir',
                                                    ['controller' => 'Users', 'action' => 'view', $user->id],
                                                    ['class' => 'dropdown-item', 'escape' => false]
                                                ) ?>
                                                <?= $this->Html->link(
                                                    '<i class="bi bi-pencil mr-2"></i> Modifier',
                                                    ['controller' => 'Users', 'action' => 'edit', $user->id],
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
                        <i class="bi bi-person-x" style="font-size: 3rem; color: #dee2e6;"></i>
                        <p class="text-muted mt-3">Aucun utilisateur associé à ce rôle.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php // --- Boutons d'action --- ?>
        <div class="mt-4">
            <?= $this->Html->link(
                '<i class="bi bi-pencil mr-2"></i> Modifier',
                ['action' => 'edit', $role->id],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-arrow-left mr-2"></i> Retour à la liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary ml-2', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash mr-2"></i> Supprimer',
                ['action' => 'delete', $role->id],
                [
                    'confirm' => 'Voulez-vous vraiment supprimer "' . h($role->name) . '" ?',
                    'class' => 'btn btn-outline-danger ml-2',
                    'escape' => false
                ]
            ) ?>
        </div>
    </div>
</div>
