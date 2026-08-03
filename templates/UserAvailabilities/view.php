<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\UserAvailability $userAvailability
 */
?>
<?php $this->assign('title', 'Détail Disponibilité'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-clock text-primary"></i>
            Disponibilité #<?= $this->Number->format($userAvailability->id) ?>
        </h3>
        <div class="btn-toolbar">
            <?= $this->Html->link(
                '<i class="bi bi-pencil mr-1"></i> Modifier',
                ['action' => 'edit', $userAvailability->id],
                ['class' => 'btn btn-primary mr-2', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash mr-1"></i> Supprimer',
                ['action' => 'delete', $userAvailability->id],
                ['confirm' => 'Voulez-vous vraiment supprimer cette disponibilité ?', 'class' => 'btn btn-danger mr-2', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list mr-1"></i> Liste',
                ['action' => 'index'],
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
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-person"></i> Utilisateur</label>
                        <div>
                            <?php if ($userAvailability->has('user')): ?>
                                <?= $this->Html->link(
                                    '<strong>' . h($userAvailability->user->full_name) . '</strong>',
                                    ['controller' => 'Users', 'action' => 'view', $userAvailability->user->id],
                                    ['escape' => false]
                                ) ?>
                            <?php else: ?>
                                <span class="text-muted">N/A</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-calendar-week"></i> Jour de la Semaine</label>
                        <div>
                            <span class="badge badge-info" style="font-size: 1rem;">
                                <i class="bi bi-calendar"></i> <?= $this->DayOfWeek->format($userAvailability->day_of_week) ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-clock"></i> Début Disponibilité</label>
                        <div>
                            <span class="badge badge-success" style="font-size: 1rem;">
                                <?= h($userAvailability->availability_start_time) ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-clock-fill"></i> Fin Disponibilité</label>
                        <div>
                            <span class="badge badge-danger" style="font-size: 1rem;">
                                <?= h($userAvailability->availability_end_time) ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-clock-history"></i> Fin la plus tôt</label>
                        <div>
                            <?php if ($userAvailability->earliest_end_time): ?>
                                <span class="badge badge-warning" style="font-size: 1rem;">
                                    <?= h($userAvailability->earliest_end_time) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">Non défini</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
