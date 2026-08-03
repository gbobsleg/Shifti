<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Site $site
 * @var \Cake\Collection\CollectionInterface|string[] $regions
 */
?>
<?php $this->assign('title', 'Ajouter un Site'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="sites form content card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-plus-circle text-success"></i>
            Ajouter un Site
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
        <?= $this->Form->create($site) ?>
        
        <div class="card border-success mb-4">
            <div class="card-header bg-success text-white">
                <i class="bi bi-geo-alt"></i> Informations du site
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-tag"></i> Nom</label>
                    <?= $this->Form->control('name', [
                        'label' => false,
                        'class' => 'form-control',
                        'required' => true,
                        'placeholder' => 'Ex: Paris - Opéra, Lyon - Part-Dieu...'
                    ]) ?>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-hash"></i> Numéro</label>
                    <?= $this->Form->control('number', [
                        'label' => false,
                        'class' => 'form-control',
                        'required' => true,
                        'placeholder' => 'Ex: 001, 002, 003...'
                    ]) ?>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-diagram-3"></i> Région</label>
                    <?= $this->Form->control('region_id', [
                        'options' => $regions,
                        'label' => false,
                        'class' => 'form-control',
                        'empty' => '-- Sélectionner une région --'
                    ]) ?>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <?= $this->Form->button('<i class="bi bi-save mr-2"></i> Créer le site', [
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
