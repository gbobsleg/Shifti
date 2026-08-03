<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Role $role
 */
?>
<?php $this->assign('title', 'Ajouter un Rôle'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="roles form content card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-shield-plus text-success"></i>
            Ajouter un Rôle
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
        <?= $this->Form->create($role) ?>
        
        <div class="card border-success mb-4">
            <div class="card-header bg-success text-white">
                <i class="bi bi-shield-lock"></i> Informations du rôle
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-tag"></i> Nom du Rôle</label>
                    <?= $this->Form->control('name', [
                        'label' => false,
                        'class' => 'form-control',
                        'required' => true,
                        'placeholder' => 'Ex: Manager, Administrateur, Agent...'
                    ]) ?>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-sort-numeric-up"></i> Priorité</label>
                    <?= $this->Form->control('priority', [
                        'label' => false,
                        'class' => 'form-control',
                        'type' => 'number',
                        'required' => true,
                        'value' => 10
                    ]) ?>
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i> Plus le nombre est bas, plus la priorité est élevée
                    </small>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <?= $this->Form->button('<i class="bi bi-save mr-2"></i> Créer le rôle', [
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
