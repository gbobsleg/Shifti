<?php
/**
 * Element: champ Mot de passe (create vs edit)
 *
 * @var \App\View\AppView $this
 * @var bool $required
 */
$required = (bool)($required ?? false);
?>

<section class="crud-section">
    <h2 class="crud-section-title">Mot de passe<?= $required ? '' : ' (optionnel)' ?></h2>
    <?= $this->Form->control('password', [
        'label' => false,
        'class' => 'form-control',
        'type' => 'password',
        'required' => $required,
        'autocomplete' => 'new-password',
        'value' => '',
    ]) ?>
    <?php if ($required): ?>
        <small class="text-muted">Le mot de passe doit contenir au moins 8 caractères.</small>
    <?php else: ?>
        <small class="text-muted">Laissez vide pour conserver le mot de passe actuel.</small>
    <?php endif; ?>
</section>
