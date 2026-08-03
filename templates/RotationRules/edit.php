<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\RotationRule $rule
 * @var array $offers
 */
?>
<?php $this->assign('title', 'Modifier la règle de rotation'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-pencil text-primary"></i>
            Modifier la règle de rotation
        </h3>
        <div>
            <?= $this->Html->link(
                '<i class="bi bi-x-circle mr-1"></i> Annuler',
                ['action' => 'view', $rule->id],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div class="card-body">
        <?= $this->Form->create($rule) ?>
        
        <?php // --- Section Informations générales --- ?>
        <div class="card border-primary mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-info-circle"></i> Informations générales
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-tag"></i> Nom de la règle</label>
                        <?= $this->Form->control('name', [
                            'label' => false,
                            'class' => 'form-control',
                            'placeholder' => 'Ex: GRC Hebdo TI'
                        ]) ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-briefcase"></i> Offre (optionnel)</label>
                        <?= $this->Form->control('offer_id', [
                            'type' => 'select',
                            'options' => ['' => '— Aucune (règle générique) —'] + $offers,
                            'label' => false,
                            'class' => 'form-control',
                            'empty' => false
                        ]) ?>
                        <small class="text-muted">L'offre sanctuarisée par cette règle (peut être laissée vide pour une règle générique)</small>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-calendar"></i> Type de période</label>
                        <?= $this->Form->control('period_type', [
                            'type' => 'select',
                            'options' => [
                                'WEEKLY' => 'Hebdomadaire',
                                'MONTHLY' => 'Mensuelle'
                            ],
                            'label' => false,
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-123"></i> Cible (nombre d'occurrences)</label>
                        <?= $this->Form->control('target_count', [
                            'type' => 'number',
                            'label' => false,
                            'class' => 'form-control',
                            'min' => 1
                        ]) ?>
                        <small class="text-muted">Nombre d'occurrences par période (ex: 3 par semaine, 12 par mois)</small>
                    </div>
                </div>
            </div>
        </div>

        <?php // --- Section Paramètres du shift --- ?>
        <div class="card border-info mb-4">
            <div class="card-header bg-info text-white">
                <i class="bi bi-clock"></i> Paramètres du shift
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><i class="bi bi-hourglass-split"></i> Durée (minutes)</label>
                        <?= $this->Form->control('shift_duration', [
                            'type' => 'number',
                            'label' => false,
                            'class' => 'form-control',
                            'min' => 1
                        ]) ?>
                        <small class="text-muted">Durée d'un shift en minutes (ex: 180 pour 3h)</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><i class="bi bi-clock-history"></i> Début fenêtre</label>
                        <?= $this->Form->control('time_window_start', [
                            'type' => 'time',
                            'label' => false,
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><i class="bi bi-clock"></i> Fin fenêtre</label>
                        <?= $this->Form->control('time_window_end', [
                            'type' => 'time',
                            'label' => false,
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                </div>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    <strong>Fenêtre horaire :</strong> Les shifts doivent commencer entre ces heures pour être comptabilisés.
                </div>
            </div>
        </div>

        <?= $this->Form->button('<i class="bi bi-check-circle mr-1"></i> Enregistrer', [
            'class' => 'btn btn-primary btn-lg',
            'escapeTitle' => false
        ]) ?>
        
        <?= $this->Form->end() ?>
    </div>
</div>
