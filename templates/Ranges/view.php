<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Range $range
 */
?>
<?php $this->assign('title', 'Détails Plage Horaire #' . $range->id); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="ranges view content card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-clock-history text-primary"></i>
            Plage Horaire #<?= h($range->id) ?>
        </h3>
        <div class="btn-toolbar">
            <?= $this->Html->link(
                '<i class="bi bi-pencil mr-1"></i> Modifier',
                ['action' => 'edit', $range->id],
                ['class' => 'btn btn-primary mr-2', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash mr-1"></i> Supprimer',
                ['action' => 'delete', $range->id],
                ['confirm' => 'Voulez-vous vraiment supprimer cette plage ?', 'class' => 'btn btn-danger mr-2', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list mr-1"></i> Liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div class="card-body">
        <?php // --- Informations de la plage --- ?>
        <div class="card border-primary mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-info-circle"></i> Informations de la plage
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-person"></i> Utilisateur</label>
                        <div>
                            <?php if ($range->hasValue('user')): ?>
                                <?= $this->Html->link(
                                    '<strong>' . h($range->user->first_name . ' ' . $range->user->last_name) . '</strong>',
                                    ['controller' => 'Users', 'action' => 'view', $range->user->id],
                                    ['escape' => false]
                                ) ?>
                            <?php else: ?>
                                <span class="text-muted">Non défini</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-basket"></i> Offre</label>
                        <div>
                            <?php if ($range->hasValue('offer')): ?>
                                <?= $this->Html->link(
                                    '<span class="badge badge-secondary" style="font-size: 1rem;"><i class="bi bi-basket"></i> ' . h($range->offer->name) . '</span>',
                                    ['controller' => 'Offers', 'action' => 'view', $range->offer->id],
                                    ['escape' => false]
                                ) ?>
                            <?php else: ?>
                                <span class="text-muted">Non défini</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-calendar-event"></i> Date de début</label>
                        <div>
                            <?php if ($range->date_start): ?>
                                <span class="badge badge-success" style="font-size: 1rem;">
                                    <?= h($range->date_start->i18nFormat('dd/MM/yyyy HH:mm')) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-calendar-x"></i> Date de fin</label>
                        <div>
                            <?php if ($range->date_end): ?>
                                <span class="badge badge-danger" style="font-size: 1rem;">
                                    <?= h($range->date_end->i18nFormat('dd/MM/yyyy HH:mm')) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-chat-left-text"></i> Commentaire</label>
                        <div>
                            <?php if ($range->comment): ?>
                                <p class="border-left pl-3" style="border-left: 3px solid #007bff !important;">
                                    <?= h($range->comment) ?>
                                </p>
                            <?php else: ?>
                                <span class="text-muted">Aucun commentaire</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php // --- Métadonnées --- ?>
        <div class="card border-secondary">
            <div class="card-header bg-secondary text-white">
                <i class="bi bi-info-square"></i> Métadonnées
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-calendar-plus"></i> Créé le</label>
                        <div><?= h($range->created ? $range->created->i18nFormat('dd/MM/yyyy HH:mm') : 'N/A') ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-calendar-check"></i> Modifié le</label>
                        <div><?= h($range->modified ? $range->modified->i18nFormat('dd/MM/yyyy HH:mm') : 'N/A') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <?php // --- Boutons d'action --- ?>
        <div class="mt-4">
            <?= $this->Html->link(
                '<i class="bi bi-pencil mr-2"></i> Modifier',
                ['action' => 'edit', $range->id],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-arrow-left mr-2"></i> Retour à la liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary ml-2', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash mr-2"></i> Supprimer',
                ['action' => 'delete', $range->id],
                [
                    'confirm' => 'Voulez-vous vraiment supprimer cette plage ?',
                    'class' => 'btn btn-outline-danger ml-2',
                    'escape' => false
                ]
            ) ?>
        </div>
    </div>
</div>
