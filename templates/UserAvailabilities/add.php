<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\UserAvailability $userAvailability
 * @var \App\Model\Entity\User[]|\Cake\Collection\CollectionInterface $users
 */
?>
<?php $this->assign('title', 'Nouvelle Disponibilité'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app user-availabilities form content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-plus-circle"></i>
            Nouvelle Disponibilité
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
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Utilisateur</label>
                <?= $this->Form->control('user_id', [
                    'options' => $users,
                    'label' => false,
                    'empty' => 'Choisir...',
                    'class' => 'form-control',
                ]) ?>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Jour de la Semaine</label>
                <?= $this->Form->control('day_of_week', [
                    'options' => [
                        1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi',
                        5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche',
                    ],
                    'label' => false,
                    'class' => 'form-control',
                ]) ?>
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
        <?= $this->Form->button('<i class="bi bi-save me-2"></i> Créer', [
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
