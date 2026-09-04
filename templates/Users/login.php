<?php $this->extend('/layout/login'); ?>

<div class="login-logo-container">
    <?= $this->element('logo', ['class' => 'login-logo']) ?>
</div>

<div class="crud-app form">
    <div class="crud-header">
        <h1>Connexion</h1>
    </div>

    <?= $this->Form->create(null, [
        'class' => 'needs-validation',
        'novalidate' => true,
    ]) ?>

    <div class="mb-3">
        <label class="form-label" for="email">Email</label>
        <?= $this->Form->control('email', [
            'type' => 'email',
            'class' => 'form-control',
            'required' => true,
            'placeholder' => 'votre.email@example.com',
            'label' => false,
            'autocomplete' => 'username',
            'id' => 'email',
            'templates' => ['inputContainer' => '{{content}}'],
        ]) ?>
    </div>

    <div class="mb-3">
        <label class="form-label" for="password">Mot de passe</label>
        <?= $this->Form->control('password', [
            'type' => 'password',
            'class' => 'form-control',
            'required' => true,
            'placeholder' => '••••••••',
            'label' => false,
            'autocomplete' => 'current-password',
            'id' => 'password',
            'templates' => ['inputContainer' => '{{content}}'],
        ]) ?>
    </div>

    <div class="crud-actions-bar">
        <?= $this->Form->button('Connexion', [
            'class' => 'btn btn-primary',
            'type' => 'submit',
        ]) ?>
    </div>

    <?= $this->Form->end() ?>
</div>
