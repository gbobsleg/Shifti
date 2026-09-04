<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Site $site
 * @var \Cake\Collection\CollectionInterface|string[] $regions
 */
?>
<?php $this->assign('title', 'Ajouter un Site'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app sites form content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-plus-circle"></i>
            Ajouter un Site
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-x-circle me-1"></i> Annuler',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <?= $this->Form->create($site) ?>
    <section class="crud-section">
        <h2 class="crud-section-title">Informations du site</h2>
        <div class="mb-3">
            <label class="form-label">Nom</label>
            <?= $this->Form->control('name', [
                'label' => false,
                'class' => 'form-control',
                'required' => true,
                'placeholder' => 'Ex: Paris - Opéra, Lyon - Part-Dieu...',
            ]) ?>
        </div>
        <div class="mb-3">
            <label class="form-label">Numéro</label>
            <?= $this->Form->control('number', [
                'label' => false,
                'class' => 'form-control',
                'required' => true,
                'placeholder' => 'Ex: 001, 002, 003...',
            ]) ?>
        </div>
        <div class="mb-3">
            <label class="form-label">Région</label>
            <?= $this->Form->control('region_id', [
                'options' => $regions,
                'label' => false,
                'class' => 'form-control',
                'empty' => '-- Sélectionner une région --',
            ]) ?>
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
