<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Alert $alert
 */
?>
<?php $this->assign('title', 'Modifier Alerte #' . $alert->id); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-pencil text-primary"></i>
            Modifier Alerte #<?= h($alert->id) ?>
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
        <?= $this->Form->create($alert) ?>
        
        <div class="card border-primary mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-info-circle"></i> Informations
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-calendar-event"></i> Date Début</label>
                        <?= $this->Form->control('date_start', [
                            'label' => false,
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-calendar-x"></i> Date Fin</label>
                        <?= $this->Form->control('date_end', [
                            'empty' => true,
                            'label' => false,
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-chat-left-text"></i> Contenu</label>
                    <?= $this->Form->control('content', [
                        'label' => false,
                        'rows' => 3,
                        'class' => 'form-control'
                    ]) ?>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-flag"></i> Priorité</label>
                    <?= $this->Form->control('priority', [
                        'label' => false,
                        'options' => [1 => '1 - Urgent', 2 => '2 - Important', 3 => '3 - Information'],
                        'class' => 'form-control'
                    ]) ?>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <?= $this->Form->button('<i class="bi bi-save mr-2"></i> Enregistrer', [
                'class' => 'btn btn-primary mr-3',
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
