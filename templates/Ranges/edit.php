<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Range $range
 * @var string[]|\Cake\Collection\CollectionInterface $users
 * @var string[]|\Cake\Collection\CollectionInterface $offers
 */
?>
<?php $this->assign('title', 'Modifier Plage Horaire #' . $range->id); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="ranges form content card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-pencil text-primary"></i>
            Modifier la Plage Horaire #<?= h($range->id) ?>
        </h3>
        <div>
            <?= $this->Html->link(
                '<i class="bi bi-x-circle mr-1"></i> Annuler',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div class="card-body">
        <?= $this->Form->create($range) ?>
        
        <div class="card border-primary mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-clock-history"></i> Informations de la plage
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-calendar-event"></i> Date et Heure de Début</label>
                        <?= $this->Form->control('date_start', [
                            'label' => false,
                            'class' => 'form-control',
                            'step' => 900
                        ]) ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-calendar-x"></i> Date et Heure de Fin</label>
                        <?= $this->Form->control('date_end', [
                            'label' => false,
                            'class' => 'form-control',
                            'step' => 900
                        ]) ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-person"></i> Utilisateur</label>
                        <?= $this->Form->control('user_id', [
                            'options' => $users,
                            'label' => false,
                            'class' => 'form-control',
                            'empty' => '-- Sélectionner un utilisateur --'
                        ]) ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-basket"></i> Offre</label>
                        <?= $this->Form->control('offer_id', [
                            'options' => $offers,
                            'label' => false,
                            'class' => 'form-control',
                            'empty' => '-- Sélectionner une offre --'
                        ]) ?>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-chat-left-text"></i> Commentaire</label>
                    <?= $this->Form->control('comment', [
                        'label' => false,
                        'class' => 'form-control',
                        'rows' => 3,
                        'placeholder' => 'Ajouter un commentaire...'
                    ]) ?>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <?= $this->Form->button('<i class="bi bi-save mr-2"></i> Sauvegarder', [
                'class' => 'btn btn-success mr-3',
                'escapeTitle' => false
            ]) ?>
            <?= $this->Html->link(
                '<i class="bi bi-x-circle mr-2"></i> Annuler',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
        
        <?= $this->Form->end() ?>
    </div>
</div>
