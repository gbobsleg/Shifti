<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 * @var \Cake\Collection\CollectionInterface|string[] $roles
 * @var \Cake\Collection\CollectionInterface|string[] $sites
 */
?>
<?php $this->assign('title', 'Ajouter un Utilisateur'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-person-plus-fill text-success"></i>
            Nouvel Utilisateur
        </h3>
        <div>
            <?= $this->Html->link(
                '<i class="bi bi-x-circle mr-1"></i> Annuler',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div class="card-body">
        <?= $this->Form->create($user) ?>
        
        <?php // --- Section Informations générales --- ?>
        <div class="card border-primary mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-info-circle"></i> Informations générales
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-shield-lock"></i> Rôle</label>
                        <?= $this->Form->control('role_id', [
                            'options' => $roles,
                            'label' => false,
                            'empty' => 'Choisir...',
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-person-badge"></i> Code Utilisateur</label>
                        <?= $this->Form->control('user_code', [
                            'label' => false,
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-person"></i> Nom</label>
                        <?= $this->Form->control('last_name', [
                            'label' => false,
                            'class' => 'form-control',
                            'id' => 'user-last-name'
                        ]) ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-person"></i> Prénom</label>
                        <?= $this->Form->control('first_name', [
                            'label' => false,
                            'class' => 'form-control',
                            'id' => 'user-first-name'
                        ]) ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-geo-alt"></i> Site</label>
                        <?= $this->Form->control('site_id', [
                            'options' => $sites,
                            'label' => false,
                            'empty' => 'Choisir...',
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-envelope"></i> Email</label>
                        <div class="input-group">
                            <?= $this->Form->email('email', [
                                'class' => 'form-control',
                                'id' => 'user-email'
                            ]) ?>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" id="btn-guess-email" title="Deviner l'email à partir du nom et prénom">
                                    <i class="bi bi-magic"></i>
                                </button>
                            </div>
                        </div>
                        <small class="text-muted">Cliquez sur <i class="bi bi-magic"></i> pour générer automatiquement</small>
                    </div>
                </div>
                <?php // Le mot de passe est géré par l'element users/password pour aligner add/edit ?>
            </div>
        </div>

        <?= $this->element('users/password', ['required' => true]) ?>

        <?= $this->element('users/contracts', [
            'userContracts' => $userContracts,
        ]) ?>

        <?= $this->element('users/skills', [
            'offers' => $offers,
            'userSkills' => $userSkills,
        ]) ?>

        <?= $this->element('users/contractual_availabilities', ['days' => $days]) ?>

        <?= $this->element('users/remote_work', [
            'remoteWorkSetting' => $remoteWorkSetting,
            'fixedDays' => $fixedDays,
            'timeStart' => $timeStart,
            'timeEnd' => $timeEnd,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'daysOfWeek' => $daysOfWeek,
            'userId' => null,
        ]) ?>

        <?php // --- Section Règle de rotation --- ?>
        <div class="card border-warning mb-4">
            <div class="card-header bg-warning text-dark">
                <i class="bi bi-arrow-repeat"></i> Règle de rotation
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-arrow-repeat"></i> Règle de rotation</label>
                        <?= $this->Form->control('rotation_rule.rotation_rule_id', [
                            'type' => 'select',
                            'options' => ['' => '— Aucune —'] + $rotationRules,
                            'label' => false,
                            'class' => 'form-control',
                            'value' => $selectedRotationRuleId,
                            'empty' => false
                        ]) ?>
                        <small class="text-muted">Assigner une règle de rotation à cet agent (1-1)</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-123"></i> Cible override (optionnel)</label>
                        <?= $this->Form->control('rotation_rule.target_count_override', [
                            'type' => 'number',
                            'label' => false,
                            'class' => 'form-control',
                            'min' => 1,
                            'value' => $rotationTargetOverride,
                            'placeholder' => 'Laisser vide pour utiliser la cible par défaut'
                        ]) ?>
                        <small class="text-muted">Pour gérer les temps partiels (ex: 2 au lieu de 3)</small>
                    </div>
                </div>
            </div>
        </div>

        <?php // --- Boutons d'action --- ?>
        <div class="mt-3">
            <?= $this->Form->button('<i class="bi bi-save mr-2"></i> Créer', [
                'class' => 'btn btn-success mr-3',
                'escapeTitle' => false
            ]) ?>
            <?= $this->Html->link(
                '<i class="bi bi-x-circle mr-2"></i> Annuler',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
        
        <?= $this->Form->end() ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var btnGuessEmail = document.getElementById('btn-guess-email');
    if (btnGuessEmail) {
        btnGuessEmail.addEventListener('click', function() {
            var lastName = document.getElementById('user-last-name').value.trim();
            var firstName = document.getElementById('user-first-name').value.trim();
            var emailField = document.getElementById('user-email');
            
            if (!lastName || !firstName) {
                alert('Veuillez renseigner le nom et le prénom avant de générer l\'email.');
                return;
            }
            
            // Fonction pour normaliser les caractères (retirer accents, etc.)
            function normalizeString(str) {
                return str
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '') // Retire les accents
                    .replace(/[^a-z0-9-]/g, '-')     // Remplace les caractères spéciaux par des tirets
                    .replace(/-+/g, '-')              // Évite les tirets multiples
                    .replace(/^-|-$/g, '');           // Retire les tirets en début/fin
            }
            
            var normalizedFirstName = normalizeString(firstName);
            var normalizedLastName = normalizeString(lastName);
            
            var guessedEmail = normalizedFirstName + '.' + normalizedLastName + '@example.com';
            emailField.value = guessedEmail;
        });
    }
});
</script>
