<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Alert $alert
 */
?>
<?php $this->assign('title', 'Modifier Alerte #' . $alert->id); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app alerts form content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-pencil"></i>
            Modifier Alerte #<?= h($alert->id) ?>
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-x-circle me-1"></i> Annuler',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <?= $this->Form->create($alert) ?>
    <section class="crud-section">
        <h2 class="crud-section-title">Informations</h2>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Date Début</label>
                <?= $this->Form->control('date_start', [
                    'label' => false,
                    'class' => 'form-control',
                ]) ?>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Date Fin</label>
                <?= $this->Form->control('date_end', [
                    'empty' => true,
                    'label' => false,
                    'class' => 'form-control',
                ]) ?>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Contenu</label>
            <?= $this->Form->control('content', [
                'label' => false,
                'rows' => 3,
                'class' => 'form-control',
            ]) ?>
        </div>
        <div class="mb-3">
            <label class="form-label">Priorité</label>
            <?= $this->Form->control('priority', [
                'label' => false,
                'options' => [1 => '1 - Urgent', 2 => '2 - Important', 3 => '3 - Information'],
                'class' => 'form-control',
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
