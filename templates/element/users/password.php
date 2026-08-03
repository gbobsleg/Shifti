<?php
/**
 * Element: champ Mot de passe (create vs edit)
 *
 * @var \App\View\AppView $this
 * @var bool $required
 */
?>

<?php $required = (bool)($required ?? false); ?>

<div class="card border-secondary h-100">
    <div class="card-header bg-secondary text-white">
        <i class="bi bi-key"></i> Mot de passe
    </div>
    <div class="card-body">
        <label class="form-label"><i class="bi bi-key"></i> Mot de passe<?= $required ? '' : ' (optionnel)' ?></label>
        <?= $this->Form->control('password', [
            'label' => false,
            'class' => 'form-control',
            'type' => 'password',
            'required' => $required,
            'autocomplete' => 'new-password',
            'value' => '',
        ]) ?>

        <?php if ($required): ?>
            <small class="text-muted">
                <i class="bi bi-info-circle"></i>
                Le mot de passe doit contenir au moins 8 caractères.
            </small>
        <?php else: ?>
            <small class="text-muted">
                <i class="bi bi-info-circle"></i>
                Laissez vide pour conserver le mot de passe actuel.
            </small>
        <?php endif; ?>
    </div>
</div>








