<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Role $role
 */
?>
<?php $this->assign('title', 'Modifier Rôle : ' . h($role->name)); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app roles form content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-pencil"></i>
            Modifier le Rôle
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-x-circle me-1"></i> Annuler',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <?= $this->Form->create($role) ?>
    <section class="crud-section">
        <h2 class="crud-section-title">Informations du rôle</h2>
        <div class="mb-3">
            <label class="form-label">Nom du Rôle</label>
            <?= $this->Form->control('name', [
                'label' => false,
                'class' => 'form-control',
                'required' => true,
            ]) ?>
        </div>
        <div class="mb-3">
            <label class="form-label">Priorité</label>
            <?= $this->Form->control('priority', [
                'label' => false,
                'class' => 'form-control',
                'type' => 'number',
                'required' => true,
            ]) ?>
            <small class="text-muted">Plus le nombre est bas, plus la priorité est élevée</small>
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
