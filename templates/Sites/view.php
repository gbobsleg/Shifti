<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Site $site
 * @var array $availableUsers
 */
?>
<?php $this->assign('title', 'Détails Site : ' . h($site->name)); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="sites view content card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-geo-alt text-primary"></i>
            <?= h($site->name) ?>
        </h3>
        <div class="btn-toolbar">
            <?= $this->Html->link(
                '<i class="bi bi-pencil mr-1"></i> Modifier',
                ['action' => 'edit', $site->id],
                ['class' => 'btn btn-primary mr-2', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash mr-1"></i> Supprimer',
                ['action' => 'delete', $site->id],
                ['confirm' => 'Voulez-vous vraiment supprimer "' . h($site->name) . '" ?', 'class' => 'btn btn-danger mr-2', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list mr-1"></i> Liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div class="card-body">
        <?php // --- Informations du site --- ?>
        <div class="card border-primary mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-info-circle"></i> Informations du site
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-tag"></i> Nom</label>
                        <div><strong><?= h($site->name) ?></strong></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-hash"></i> Numéro</label>
                        <div>
                            <span class="badge badge-info" style="font-size: 1rem;">
                                <?= h($site->number) ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-diagram-3"></i> Région</label>
                        <div>
                            <?php if ($site->has('region')): ?>
                                <?= $this->Html->link(
                                    '<span class="badge badge-secondary" style="font-size: 1rem;"><i class="bi bi-diagram-3"></i> ' . h($site->region->name) . '</span>',
                                    ['controller' => 'Regions', 'action' => 'view', $site->region->id],
                                    ['escape' => false]
                                ) ?>
                            <?php else: ?>
                                <span class="text-muted">Non définie</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php // --- Utilisateurs Associés --- ?>
        <div class="card border-success">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <span>
                    <i class="bi bi-people"></i> Utilisateurs Associés
                    <?php if (!empty($site->users)): ?>
                        <span class="badge badge-light ml-2"><?= count($site->users) ?></span>
                    <?php endif; ?>
                </span>
                <?php if (!empty($availableUsers->toArray())): ?>
                    <button type="button" class="btn btn-sm btn-light" data-toggle="modal" data-target="#addUserModal">
                        <i class="bi bi-person-plus"></i> Ajouter
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!empty($site->users)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm">
                            <thead>
                            <tr>
                                <th scope="col">Code</th>
                                <th scope="col">Nom</th>
                                <th scope="col">Prénom</th>
                                <th scope="col">Email</th>
                                <th scope="col" class="actions">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($site->users as $user): ?>
                                <tr>
                                    <td>
                                        <span class="badge badge-secondary"><?= h($user->user_code) ?></span>
                                    </td>
                                    <td><strong><?= h($user->last_name) ?></strong></td>
                                    <td><?= h($user->first_name) ?></td>
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
                                                <div class="dropdown-divider"></div>
                                                <?= $this->Form->postLink(
                                                    '<i class="bi bi-person-dash mr-2 text-danger"></i> Retirer du site',
                                                    ['action' => 'removeUser', $site->id, $user->id],
                                                    [
                                                        'class' => 'dropdown-item text-danger',
                                                        'escape' => false,
                                                        'confirm' => 'Retirer ' . h($user->first_name) . ' ' . h($user->last_name) . ' de ce site ?'
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
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-person-x" style="font-size: 3rem; color: #dee2e6;"></i>
                        <p class="text-muted mt-3">Aucun utilisateur associé à ce site.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php // --- Boutons d'action --- ?>
        <div class="mt-3">
            <?= $this->Html->link(
                '<i class="bi bi-pencil mr-2"></i> Modifier',
                ['action' => 'edit', $site->id],
                ['class' => 'btn btn-primary mr-3', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list-ul mr-2"></i> Retour à la liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash mr-2"></i> Supprimer',
                ['action' => 'delete', $site->id],
                [
                    'confirm' => 'Voulez-vous vraiment supprimer "' . h($site->name) . '" ?',
                    'class' => 'btn btn-outline-danger float-right',
                    'escape' => false
                ]
            ) ?>
        </div>
    </div>
</div>

<?php // --- Modale Ajouter Utilisateur --- ?>
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <?= $this->Form->create(null, ['url' => ['action' => 'assignUser', $site->id]]) ?>
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="addUserModalLabel">
                    <i class="bi bi-person-plus"></i> Ajouter un utilisateur
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Fermer">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">
                    Sélectionnez un utilisateur à rattacher au site <strong><?= h($site->name) ?></strong>.
                </p>
                <?= $this->Form->control('user_id', [
                    'type' => 'select',
                    'options' => $availableUsers,
                    'label' => 'Utilisateur',
                    'class' => 'form-control',
                    'empty' => '— Choisir un utilisateur —',
                    'required' => true
                ]) ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Annuler
                </button>
                <?= $this->Form->button('<i class="bi bi-check-circle"></i> Ajouter', [
                    'class' => 'btn btn-success',
                    'escapeTitle' => false
                ]) ?>
            </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>
