<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\UserAvailability $userAvailability
 * @var \App\Model\Entity\User[]|\Cake\Collection\CollectionInterface $users
 */
?>
<?php $this->assign('title', 'Éditer Disponibilité'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app user-availabilities form content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-pencil"></i>
            Éditer Disponibilité
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-x-circle me-1"></i> Annuler',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <?= $this->Form->create($userAvailability) ?>
    <section class="crud-section">
        <h2 class="crud-section-title">Informations</h2>
        <p class="text-muted">
            L'utilisateur et le jour ne peuvent pas être modifiés pour éviter les doublons. Seuls les horaires sont modifiables.
        </p>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Utilisateur</label>
                <div class="form-control bg-light" style="cursor: not-allowed;">
                    <?= h($userAvailability->user->full_name ?? '—') ?>
                </div>
                <?= $this->Form->hidden('user_id') ?>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Jour de la Semaine</label>
                <div class="form-control bg-light" style="cursor: not-allowed;">
                    <?php
                    $days = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];
                    echo h($days[$userAvailability->day_of_week] ?? '—');
                    ?>
                </div>
                <?= $this->Form->hidden('day_of_week') ?>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Début Disponibilité</label>
                <?= $this->Form->control('availability_start_time', [
                    'label' => false,
                    'type' => 'time',
                    'class' => 'form-control',
                ]) ?>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Fin Disponibilité</label>
                <?= $this->Form->control('availability_end_time', [
                    'label' => false,
                    'type' => 'time',
                    'class' => 'form-control',
                ]) ?>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Fin la plus tôt</label>
                <?= $this->Form->control('earliest_end_time', [
                    'label' => false,
                    'type' => 'time',
                    'empty' => true,
                    'class' => 'form-control',
                ]) ?>
                <small class="text-muted">Optionnel</small>
            </div>
        </div>
    </section>
    <div class="crud-actions-bar">
        <?= $this->Form->button('<i class="bi bi-save me-2"></i> Enregistrer', [
            'class' => 'btn btn-primary',
            'escapeTitle' => false,
        ]) ?>
        <?= $this->Html->link(
            '<i class="bi bi-x-circle me-2"></i> Annuler',
            ['action' => 'index'],
            ['class' => 'btn btn-outline-secondary', 'escape' => false]
        ) ?>
    </div>
    <?= $this->Form->end() ?>
</div>
