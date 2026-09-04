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
<?php $this->Html->script('users-form-tabs', ['block' => true]); ?>

<div class="crud-app users form crud-app-wide content">
    <div class="crud-header">
        <div>
            <h1>
                <i class="bi bi-pencil"></i>
                Modifier <?= h($user->full_name) ?>
            </h1>
            <?= $this->element('users/header_dates', ['user' => $user]) ?>
        </div>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-x-circle me-1"></i> Annuler',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <?= $this->Form->create($user) ?>
    <?= $this->element('users/tabs_nav') ?>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="user-tab-identity" role="tabpanel" aria-labelledby="user-tab-identity-btn">
            <section class="crud-section">
                <h2 class="crud-section-title">Informations générales</h2>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Rôle</label>
                        <?= $this->Form->control('role_id', [
                            'options' => $roles,
                            'label' => false,
                            'empty' => 'Choisir...',
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Code Utilisateur</label>
                        <?= $this->Form->control('user_code', [
                            'label' => false,
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nom</label>
                        <?= $this->Form->control('last_name', [
                            'label' => false,
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Prénom</label>
                        <?= $this->Form->control('first_name', [
                            'label' => false,
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Site</label>
                        <?= $this->Form->control('site_id', [
                            'options' => $sites,
                            'label' => false,
                            'empty' => 'Choisir...',
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email</label>
                        <?= $this->Form->control('email', [
                            'label' => false,
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                </div>
            </section>
            <?= $this->element('users/password', ['required' => false]) ?>
        </div>

        <div class="tab-pane fade" id="user-tab-contracts" role="tabpanel" aria-labelledby="user-tab-contracts-btn">
            <?= $this->element('users/contracts', [
                'userContracts' => $userContracts,
            ]) ?>
            <?= $this->element('users/contractual_availabilities', ['days' => $days]) ?>
        </div>

        <div class="tab-pane fade" id="user-tab-remote" role="tabpanel" aria-labelledby="user-tab-remote-btn">
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
        </div>

        <div class="tab-pane fade" id="user-tab-skills" role="tabpanel" aria-labelledby="user-tab-skills-btn">
            <?= $this->element('users/skills', [
                'offers' => $offers,
                'userSkills' => $userSkills,
            ]) ?>
        </div>

        <div class="tab-pane fade" id="user-tab-rotation" role="tabpanel" aria-labelledby="user-tab-rotation-btn">
            <section class="crud-section">
                <h2 class="crud-section-title">Règle de rotation</h2>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Règle de rotation</label>
                        <?= $this->Form->control('rotation_rule.rotation_rule_id', [
                            'type' => 'select',
                            'options' => ['' => '— Aucune —'] + $rotationRules,
                            'label' => false,
                            'class' => 'form-control',
                            'value' => $selectedRotationRuleId,
                            'empty' => false
                        ]) ?>
                        <small class="text-muted">Modèle de rotation (1-1). L’éligibilité de chaque ligne (tel, livechat…) dépend des compétences (offres) de l’agent.</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Cible personnalisée (optionnel)</label>
                        <?= $this->Form->control('rotation_rule.target_count_override', [
                            'type' => 'number',
                            'label' => false,
                            'class' => 'form-control',
                            'min' => 1,
                            'value' => $rotationTargetOverride,
                            'placeholder' => 'Laisser vide pour utiliser la cible par défaut'
                        ]) ?>
                        <small class="text-muted">Surcharge la cible de la ligne quota du modèle (ex: 2 au lieu de 3). Sans effet sur les lignes couverture.</small>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <div class="crud-actions-bar">
        <?= $this->Form->button('<i class="bi bi-save me-2"></i> Enregistrer', [
            'class' => 'btn btn-primary',
            'escapeTitle' => false
        ]) ?>
        <?= $this->Html->link(
            '<i class="bi bi-x-circle me-2"></i> Annuler',
            ['action' => 'index'],
            ['class' => 'btn btn-outline-secondary', 'escape' => false]
        ) ?>
    </div>

    <?= $this->Form->end() ?>
</div>
