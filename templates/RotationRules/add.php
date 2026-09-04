<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\RotationRule $rule
 * @var array $offers
 * @var string $defaultTimeWindowStart
 * @var string $defaultTimeWindowEnd
 */
?>
<?php $this->assign('title', 'Nouvelle règle de rotation'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app rotation-rules form content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-plus-circle"></i>
            Nouvelle règle de rotation
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-x-circle me-1"></i> Annuler',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <?= $this->Form->create($rule) ?>

    <section class="crud-section">
        <h2 class="crud-section-title">Informations générales</h2>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Nom de la règle</label>
                <?= $this->Form->control('name', [
                    'label' => false,
                    'class' => 'form-control',
                    'placeholder' => 'Ex: GRC Hebdo TI',
                ]) ?>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Offre (optionnel)</label>
                <?= $this->Form->control('offer_id', [
                    'type' => 'select',
                    'options' => ['' => '— Aucune (règle générique) —'] + $offers,
                    'label' => false,
                    'class' => 'form-control',
                    'empty' => false,
                ]) ?>
                <small class="text-muted">L'offre sanctuarisée par cette règle (peut être laissée vide pour une règle générique)</small>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Type de période</label>
                <?= $this->Form->control('period_type', [
                    'type' => 'select',
                    'options' => [
                        'WEEKLY' => 'Hebdomadaire',
                        'MONTHLY' => 'Mensuelle',
                    ],
                    'label' => false,
                    'class' => 'form-control',
                    'default' => 'WEEKLY',
                ]) ?>
            </div>
            <div class="col-md-6 mb-3">
                <?= $this->Form->control('exclusive_day', [
                    'type' => 'checkbox',
                    'checked' => $rule->exclusive_day ?? true,
                    'label' => 'Un seul shift (duty) par agent et par jour',
                ]) ?>
                <small class="text-muted d-block">Décochez pour autoriser livechat + téléphonie le même jour s’ils ne se chevauchent pas.</small>
            </div>
        </div>
        <?= $this->Form->hidden('target_count', ['value' => 1]) ?>
        <?= $this->Form->hidden('shift_duration', ['value' => 180]) ?>
        <?= $this->Form->hidden('time_window_start', ['value' => $defaultTimeWindowStart]) ?>
        <?= $this->Form->hidden('time_window_end', ['value' => $defaultTimeWindowEnd]) ?>
    </section>

    <?= $this->element('RotationRules/lines', compact('rule', 'offers', 'defaultTimeWindowStart', 'defaultTimeWindowEnd')) ?>

    <div class="crud-actions-bar">
        <?= $this->Form->button('<i class="bi bi-save me-2"></i> Créer la règle', [
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
