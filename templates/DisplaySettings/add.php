<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\DisplaySetting $displaySetting
 */
?>
<?php $this->assign('title', 'Nouveau Paramètre'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app displaySettings form content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-plus-circle"></i>
            Nouveau Paramètre d'Affichage
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-x-circle me-1"></i> Annuler',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <?= $this->Form->create($displaySetting) ?>
    <section class="crud-section">
        <h2 class="crud-section-title">Informations</h2>
        <div class="mb-3">
            <label class="form-label">Clé (unique)</label>
            <?= $this->Form->control('key', [
                'label' => false,
                'class' => 'form-control',
                'placeholder' => 'ex: grid_start_hour',
            ]) ?>
        </div>
        <div class="mb-3">
            <label class="form-label">Valeur</label>
            <?= $this->Form->control('value', [
                'label' => false,
                'class' => 'form-control',
                'placeholder' => 'ex: 8',
            ]) ?>
        </div>
        <div class="mb-3">
            <label class="form-label">Description</label>
            <?= $this->Form->control('description', [
                'label' => false,
                'class' => 'form-control',
                'type' => 'textarea',
                'rows' => 2,
                'placeholder' => 'Description du paramètre',
            ]) ?>
        </div>
        <div class="mb-3">
            <label class="form-label">Type</label>
            <?= $this->Form->control('type', [
                'label' => false,
                'class' => 'form-control',
                'options' => ['int' => 'Entier', 'string' => 'Chaîne', 'boolean' => 'Booléen'],
                'empty' => 'Sélectionnez un type',
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
