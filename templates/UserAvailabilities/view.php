<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\UserAvailability $userAvailability
 */
$userLabel = $userAvailability->has('user')
    ? $userAvailability->user->full_name
    : 'Disponibilité #' . $userAvailability->id;
?>
<?php $this->assign('title', 'Détail Disponibilité'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app user-availabilities view content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-clock"></i>
            <?= h($userLabel) ?>
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-pencil me-1"></i> Modifier',
                ['action' => 'edit', $userAvailability->id],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list me-1"></i> Liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash me-1"></i> Supprimer',
                ['action' => 'delete', $userAvailability->id],
                [
                    'confirm' => 'Voulez-vous vraiment supprimer cette disponibilité ?',
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
                <dt>Utilisateur</dt>
                <dd>
                    <?php if ($userAvailability->has('user')): ?>
                        <?= $this->Html->link(
                            $userAvailability->user->full_name,
                            ['controller' => 'Users', 'action' => 'view', $userAvailability->user->id]
                        ) ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </dd>
            </div>
            <div>
                <dt>Jour de la Semaine</dt>
                <dd><?= $this->DayOfWeek->format($userAvailability->day_of_week) ?></dd>
            </div>
            <div>
                <dt>Début Disponibilité</dt>
                <dd><?= h($userAvailability->availability_start_time) ?></dd>
            </div>
            <div>
                <dt>Fin Disponibilité</dt>
                <dd><?= h($userAvailability->availability_end_time) ?></dd>
            </div>
            <div>
                <dt>Fin la plus tôt</dt>
                <dd><?= h($userAvailability->earliest_end_time ?: 'Non défini') ?></dd>
            </div>
        </dl>
    </section>
</div>
