<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Region $region
 */
?>
<?php $this->assign('title', 'Ajouter une Région'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="regions form content card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-plus-circle text-success"></i>
            Ajouter une Région
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
        <?= $this->Form->create($region) ?>
        
        <div class="card border-success mb-4">
            <div class="card-header bg-success text-white">
                <i class="bi bi-diagram-3"></i> Informations de la région
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-tag"></i> Nom</label>
                    <?= $this->Form->control('name', [
                        'label' => false,
                        'class' => 'form-control',
                        'required' => true,
                        'placeholder' => 'Ex: Île-de-France, Auvergne-Rhône-Alpes...'
                    ]) ?>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-hash"></i> Numéro</label>
                    <?= $this->Form->control('number', [
                        'label' => false,
                        'class' => 'form-control',
                        'required' => true,
                        'placeholder' => 'Ex: 01, 02, 03...'
                    ]) ?>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <?= $this->Form->button('<i class="bi bi-save mr-2"></i> Créer la région', [
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
