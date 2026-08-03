<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Range $absence
 */
?>
<?php $this->assign('title', 'Détails Absence #' . $absence->id); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-calendar-x text-primary"></i>
            Absence #<?= h($absence->id) ?>
        </h3>
        <div class="btn-toolbar">
            <?= $this->Html->link(
                '<i class="bi bi-pencil mr-1"></i> Modifier',
                ['controller' => 'Ranges', 'action' => 'edit', $absence->id],
                ['class' => 'btn btn-primary mr-2', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash mr-1"></i> Supprimer',
                ['controller' => 'Ranges', 'action' => 'delete', $absence->id],
                ['confirm' => 'Voulez-vous vraiment supprimer cette absence ?', 'class' => 'btn btn-danger mr-2', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list mr-1"></i> Liste',
                ['controller' => 'Absences', 'action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div class="card-body">
        <div class="card border-primary mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-info-circle"></i> Informations
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="text-muted small mb-1"><i class="bi bi-person"></i> Utilisateur</label>
                    <div>
                        <?php if ($absence->hasValue('user')): ?>
                            <strong><?= h($absence->user->last_name . ' ' . $absence->user->first_name) ?></strong>
                        <?php else: ?>
                            <span class="text-muted">N/A</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="text-muted small mb-1"><i class="bi bi-tag"></i> Type d'Absence</label>
                    <div>
                        <?php if ($absence->hasValue('offer')): ?>
                            <span class="badge badge-info"><?= h($absence->offer->name) ?></span>
                        <?php else: ?>
                            <span class="text-muted">N/A</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-calendar-event"></i> Date Début</label>
                        <div><?= h($absence->date_start ? $absence->date_start->i18nFormat('dd/MM/yyyy HH:mm') : 'N/A') ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-calendar-x"></i> Date Fin</label>
                        <div><?= h($absence->date_end ? $absence->date_end->i18nFormat('dd/MM/yyyy HH:mm') : 'N/A') ?></div>
                    </div>
                </div>
                <?php if ($absence->comment): ?>
                <div>
                    <label class="text-muted small mb-1"><i class="bi bi-chat-left-text"></i> Commentaire</label>
                    <div><?= h($absence->comment) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-3">
            <?= $this->Html->link(
                '<i class="bi bi-pencil mr-2"></i> Modifier',
                ['controller' => 'Ranges', 'action' => 'edit', $absence->id],
                ['class' => 'btn btn-primary mr-3', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list-ul mr-2"></i> Retour à la liste',
                ['controller' => 'Absences', 'action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash mr-2"></i> Supprimer',
                ['controller' => 'Ranges', 'action' => 'delete', $absence->id],
                [
                    'confirm' => 'Voulez-vous vraiment supprimer cette absence ?',
                    'class' => 'btn btn-outline-danger float-right',
                    'escape' => false
                ]
            ) ?>
        </div>
    </div>
</div>
