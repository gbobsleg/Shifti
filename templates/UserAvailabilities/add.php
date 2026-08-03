<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\UserAvailability $userAvailability
 * @var \App\Model\Entity\User[]|\Cake\Collection\CollectionInterface $users
 */
?>
<?php
$isEdit = $this->request->getParam('action') === 'edit';
$this->assign('title', $isEdit ? 'Éditer Disponibilité' : 'Nouvelle Disponibilité');
?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>



<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="mb-0">
            <i class="bi bi-plus-circle text-success"></i>
            <?= $isEdit ? 'Éditer Disponibilité' : 'Créer Disponibilité' ?>
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
        <?= $this->Form->create($userAvailability) ?>
        
        <div class="card border-primary mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-info-circle"></i> Informations
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-person"></i> Utilisateur</label>
                        <?= $this->Form->control('user_id', [
                            'options' => $users,
                            'label' => false,
                            'empty' => 'Choisir...',
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-calendar-week"></i> Jour de la Semaine</label>
                        <?= $this->Form->control('day_of_week', [
                            'options' => [
                                1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi',
                                5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'
                            ],
                            'label' => false,
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><i class="bi bi-clock"></i> Début Disponibilité</label>
                        <?= $this->Form->control('availability_start_time', [
                            'label' => false,
                            'type' => 'time',
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><i class="bi bi-clock-fill"></i> Fin Disponibilité</label>
                        <?= $this->Form->control('availability_end_time', [
                            'label' => false,
                            'type' => 'time',
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><i class="bi bi-clock-history"></i> Fin la plus tôt</label>
                        <?= $this->Form->control('earliest_end_time', [
                            'label' => false,
                            'type' => 'time',
                            'empty' => true,
                            'class' => 'form-control'
                        ]) ?>
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i> Optionnel
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <?= $this->Form->button('<i class="bi bi-save mr-2"></i> ' . ($isEdit ? 'Enregistrer' : 'Créer'), [
                'class' => 'btn btn-' . ($isEdit ? 'primary' : 'success') . ' mr-3',
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
