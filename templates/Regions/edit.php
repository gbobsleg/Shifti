<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Region $region
 */
?>
<?php $this->assign('title', 'Modifier Région : ' . h($region->name)); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app regions form content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-pencil"></i>
            Modifier la Région
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-x-circle me-1"></i> Annuler',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <?= $this->Form->create($region) ?>
    <section class="crud-section">
        <h2 class="crud-section-title">Informations de la région</h2>
        <div class="mb-3">
            <label class="form-label">Nom</label>
            <?= $this->Form->control('name', [
                'label' => false,
                'class' => 'form-control',
                'required' => true,
            ]) ?>
        </div>
        <div class="mb-3">
            <label class="form-label">Numéro</label>
            <?= $this->Form->control('number', [
                'label' => false,
                'class' => 'form-control',
                'required' => true,
            ]) ?>
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
