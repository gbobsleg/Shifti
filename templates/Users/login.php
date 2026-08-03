<?php $this->extend('/layout/login'); ?>

<style>
.login-card {
    animation: fadeInUp 0.5s ease;
}
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.login-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}
</style>

<div class="login-logo-container">
    <?= $this->element('logo', ['class' => 'login-logo']) ?>
</div>

<div class="card shadow-lg login-card">
    <div class="card-header bg-primary text-white text-center py-4">
        <i class="bi bi-shield-lock-fill login-icon"></i>
        <h3 class="mb-0">Connexion</h3>
        <p class="mb-0 mt-2"><small>Accès sécurisé au système de planification</small></p>
    </div>
    <div class="card-body p-4">
        <?= $this->Form->create(null, [
            'class' => 'needs-validation',
            'novalidate' => true
        ]) ?>
        
        <div class="form-group mb-3">
            <label class="form-label">
                <i class="bi bi-envelope"></i> Email
            </label>
            <?= $this->Form->control('email', [
                'type' => 'email',
                'class' => 'form-control form-control-lg',
                'required' => true,
                'placeholder' => 'votre.email@example.com',
                'label' => false,
                'autocomplete' => 'username'
            ]) ?>
        </div>
        
        <div class="form-group mb-4">
            <label class="form-label">
                <i class="bi bi-key"></i> Mot de passe
            </label>
            <?= $this->Form->control('password', [
                'type' => 'password',
                'class' => 'form-control form-control-lg',
                'required' => true,
                'placeholder' => '••••••••',
                'label' => false,
                'autocomplete' => 'current-password'
            ]) ?>
        </div>
        
        <div class="d-grid gap-2">
            <?= $this->Form->button(
                '<i class="bi bi-box-arrow-in-right mr-2"></i> Se connecter',
                [
                    'class' => 'btn btn-primary btn-lg',
                    'type' => 'submit',
                    'escapeTitle' => false
                ]
            ) ?>
        </div>
        
        <?= $this->Form->end() ?>
    </div>
    <div class="card-footer text-center bg-light">
        <small class="text-muted">
            <i class="bi bi-shield-check"></i>
            Connexion sécurisée - Toutes les données sont protégées
        </small>
    </div>
</div>
