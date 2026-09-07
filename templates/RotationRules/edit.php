<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\RotationRule $rule
 * @var array $offers
 */
?>
<?php $this->assign('title', 'Modifier la règle de rotation'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app rotation-rules form crud-app-wide content">
    <div class="crud-header">
        <div>
            <h1>
                <i class="bi bi-pencil"></i>
                Modifier la règle de rotation
            </h1>
            <p class="crud-header-meta">
                Combien de fois, et à quels horaires, les agents doivent tenir une activité.
            </p>
        </div>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-x-circle me-1"></i> Annuler',
                ['action' => 'view', $rule->id],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <?= $this->Form->create($rule) ?>

    <section class="crud-section">
        <h2 class="crud-section-title">Paramètres généraux</h2>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Nom</label>
                <?= $this->Form->control('name', [
                    'label' => false,
                    'class' => 'form-control',
                    'placeholder' => 'Ex. : Téléphone 2 fois par semaine',
                ]) ?>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Fréquence</label>
                <?= $this->Form->control('period_type', [
                    'type' => 'select',
                    'options' => [
                        'WEEKLY' => 'Toutes les semaines',
                        'MONTHLY' => 'Tous les mois',
                    ],
                    'label' => false,
                    'class' => 'form-control',
                ]) ?>
            </div>
        </div>
        <div class="form-check mb-2">
            <?= $this->Form->control('exclusive_day', [
                'type' => 'checkbox',
                'checked' => $rule->exclusive_day ?? true,
                'label' => 'Un agent ne tient qu’une seule activité de cette règle le même jour',
                'class' => 'form-check-input',
                'templates' => [
                    'inputContainer' => '{{content}}',
                    'nestingLabel' => '{{hidden}}{{input}}<label class="form-check-label"{{attrs}}>{{text}}</label>',
                ],
            ]) ?>
        </div>
        <p class="form-text text-muted mb-0">
            Décochez pour autoriser deux activités le même jour si les horaires ne se chevauchent pas
            (ex. chat le matin, téléphone l’après-midi).
        </p>
        <?= $this->Form->hidden('offer_id') ?>
        <?= $this->Form->hidden('target_count') ?>
        <?= $this->Form->hidden('shift_duration') ?>
        <?= $this->Form->hidden('time_window_start') ?>
        <?= $this->Form->hidden('time_window_end') ?>
    </section>

    <?= $this->element('RotationRules/lines', compact('rule', 'offers', 'defaultTimeWindowStart', 'defaultTimeWindowEnd')) ?>

    <div class="crud-actions-bar">
        <?= $this->Form->button('<i class="bi bi-save me-2"></i> Enregistrer', [
            'class' => 'btn btn-primary',
            'escapeTitle' => false,
        ]) ?>
        <?= $this->Html->link(
            '<i class="bi bi-x-circle me-2"></i> Annuler',
            ['action' => 'view', $rule->id],
            ['class' => 'btn btn-outline-secondary', 'escape' => false]
        ) ?>
    </div>

    <?= $this->Form->end() ?>
</div>
