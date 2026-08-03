<?php $this->assign('title', 'Changer mon mot de passe'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-key text-primary"></i>
            Changer mon mot de passe
        </h3>
        <div>
            <?= $this->Html->link(
                '<i class="bi bi-person-circle mr-1"></i> Mon compte',
                ['action' => 'account'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div class="card-body">
        <div class="alert alert-info mb-4">
            <i class="bi bi-info-circle"></i>
            <strong>Sécurité :</strong> Votre nouveau mot de passe doit contenir au moins 8 caractères.
        </div>

        <?= $this->Form->create($user) ?>
        
        <div class="card border-warning mb-4">
            <div class="card-header bg-warning text-dark">
                <i class="bi bi-shield-lock"></i> Modification du mot de passe
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-lock"></i> Mot de passe actuel</label>
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
                    <label class="form-label"><i class="bi bi-key"></i> Nouveau mot de passe</label>
                    <?= $this->Form->control('new_password', [
                        'type' => 'password',
                        'label' => false,
                        'required' => true,
                        'autocomplete' => 'new-password',
                        'value' => '',
                        'class' => 'form-control'
                    ]) ?>
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i> Au moins 8 caractères
                    </small>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-check-circle"></i> Confirmer le nouveau mot de passe</label>
                    <?= $this->Form->control('confirm_password', [
                        'type' => 'password',
                        'label' => false,
                        'required' => true,
                        'autocomplete' => 'new-password',
                        'value' => '',
                        'class' => 'form-control'
                    ]) ?>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <?= $this->Form->button('<i class="bi bi-save mr-2"></i> Mettre à jour', [
                'class' => 'btn btn-success mr-3',
                'escapeTitle' => false
            ]) ?>
            <?= $this->Html->link(
                '<i class="bi bi-x-circle mr-2"></i> Annuler',
                ['action' => 'account'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
        
        <?= $this->Form->end() ?>
    </div>
</div>
