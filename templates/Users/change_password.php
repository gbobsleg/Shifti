<?php $this->assign('title', 'Changer mon mot de passe'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app users form content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-key"></i>
            Changer mon mot de passe
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-person-circle me-1"></i> Mon compte',
                ['action' => 'account'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <p class="text-muted">Votre nouveau mot de passe doit contenir au moins 8 caractères.</p>
    <?= $this->Form->create($user) ?>
    <section class="crud-section">
        <h2 class="crud-section-title">Modification du mot de passe</h2>
        <div class="mb-3">
            <label class="form-label">Mot de passe actuel</label>
            <?= $this->Form->control('current_password', [
                'type' => 'password',
                'label' => false,
                'required' => true,
                'autocomplete' => 'current-password',
                'value' => '',
                'class' => 'form-control'
            ]) ?>
        </div>
        <div class="mb-3">
            <label class="form-label">Nouveau mot de passe</label>
            <?= $this->Form->control('new_password', [
                'type' => 'password',
                'label' => false,
                'required' => true,
                'autocomplete' => 'new-password',
                'value' => '',
                'class' => 'form-control'
            ]) ?>
            <small class="text-muted">Au moins 8 caractères</small>
        </div>
        <div class="mb-3">
            <label class="form-label">Confirmer le nouveau mot de passe</label>
            <?= $this->Form->control('confirm_password', [
                'type' => 'password',
                'label' => false,
                'required' => true,
                'autocomplete' => 'new-password',
                'value' => '',
                'class' => 'form-control'
            ]) ?>
        </div>
    </section>
    <div class="crud-actions-bar">
        <?= $this->Form->button('<i class="bi bi-save me-2"></i> Mettre à jour', [
            'class' => 'btn btn-primary',
            'escapeTitle' => false
        ]) ?>
        <?= $this->Html->link(
            '<i class="bi bi-x-circle me-2"></i> Annuler',
            ['action' => 'account'],
            ['class' => 'btn btn-outline-secondary', 'escape' => false]
        ) ?>
    </div>
    <?= $this->Form->end() ?>
</div>
