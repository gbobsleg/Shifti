<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Site $site
 * @var array $availableUsers
 */
?>
<?php $this->assign('title', 'Détails Site : ' . h($site->name)); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app sites view content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-geo-alt"></i>
            <?= h($site->name) ?>
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-pencil me-1"></i> Modifier',
                ['action' => 'edit', $site->id],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list me-1"></i> Liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash me-1"></i> Supprimer',
                ['action' => 'delete', $site->id],
                [
                    'confirm' => 'Voulez-vous vraiment supprimer "' . h($site->name) . '" ?',
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
                <dd><?= h($site->name) ?></dd>
            </div>
            <div>
                <dt>Numéro</dt>
                <dd><?= h($site->number) ?></dd>
            </div>
            <div>
                <dt>Région</dt>
                <dd>
                    <?php if ($site->has('region')): ?>
                        <?= $this->Html->link(
                            $site->region->name,
                            ['controller' => 'Regions', 'action' => 'view', $site->region->id]
                        ) ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </dd>
            </div>
        </dl>
    </section>

    <section class="crud-section">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="crud-section-title mb-0">
                Utilisateurs associés
                <?php if (!empty($site->users)): ?>
                    (<?= count($site->users) ?>)
                <?php endif; ?>
            </h2>
            <?php if (!empty($availableUsers->toArray())): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="bi bi-person-plus"></i> Ajouter
                </button>
            <?php endif; ?>
        </div>
        <?php if (!empty($site->users)): ?>
            <div class="table-responsive">
                <table class="table table-hover table-sm crud-table">
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
                            <td><?= h($user->user_code) ?></td>
                            <td>
                                <?= $this->Html->link(
                                    $user->last_name,
                                    ['controller' => 'Users', 'action' => 'view', $user->id],
                                    ['class' => 'crud-row-link']
                                ) ?>
                            </td>
                            <td><?= h($user->first_name) ?></td>
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
                                <?= $this->Form->postLink(
                                    '<i class="bi bi-person-dash" aria-hidden="true"></i>',
                                    ['action' => 'removeUser', $site->id, $user->id],
                                    [
                                        'class' => 'crud-action crud-action-danger',
                                        'escape' => false,
                                        'title' => 'Retirer du site',
                                        'aria-label' => 'Retirer du site',
                                        'data-bs-toggle' => 'tooltip',
                                        'confirm' => 'Retirer ' . h($user->first_name) . ' ' . h($user->last_name) . ' de ce site ?',
                                    ]
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted mb-0">Aucun utilisateur associé à ce site.</p>
        <?php endif; ?>
    </section>
</div>

<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <?= $this->Form->create(null, ['url' => ['action' => 'assignUser', $site->id]]) ?>
            <div class="modal-header">
                <h5 class="modal-title" id="addUserModalLabel">Ajouter un utilisateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
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
                    'required' => true,
                ]) ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                <?= $this->Form->button('Ajouter', [
                    'class' => 'btn btn-primary',
                    'escapeTitle' => false,
                ]) ?>
            </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>
