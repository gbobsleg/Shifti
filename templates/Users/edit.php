<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 * @var string[]|\Cake\Collection\CollectionInterface $roles
 * @var string[]|\Cake\Collection\CollectionInterface $sites
 */
?>
<?php $this->assign('title', 'Modifier Utilisateur : ' . h($user->full_name)); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-pencil text-primary"></i>
            Modifier <?= h($user->full_name) ?>
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
        
        <div class="row mb-4">
            <?php // --- Section Informations générales --- ?>
            <div class="col-md-8">
                <div class="card border-primary h-100">
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
                                    'class' => 'form-control'
                                ]) ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><i class="bi bi-person"></i> Prénom</label>
                                <?= $this->Form->control('first_name', [
                                    'label' => false,
                                    'class' => 'form-control'
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
                                <?= $this->Form->control('email', [
                                    'label' => false,
                                    'class' => 'form-control'
                                ]) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php // --- Section Mot de passe --- ?>
            <div class="col-md-4">
                <?= $this->element('users/password', ['required' => false]) ?>
            </div>
        </div>

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
            'userId' => (int)$user->id,
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
            <?= $this->Form->button('<i class="bi bi-save mr-2"></i> Enregistrer', [
                'class' => 'btn btn-primary mr-3',
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
