<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 * @var \App\Model\Entity\UserRemoteWorkSetting $setting
 * @var array $fixedDays
 * @var string $timeStart
 * @var string $timeEnd
 * @var array $daysOfWeek
 */
?>
<?php $this->assign('title', 'Configuration Télétravail - ' . $user->full_name); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-house-door-fill text-info"></i>
            Configuration Télétravail - <?= h($user->full_name) ?>
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
        <?= $this->Form->create($setting, ['class' => 'needs-validation', 'novalidate' => true]) ?>
        <?= $this->Form->hidden('_csrfToken', ['value' => $this->request->getAttribute('csrfToken')]) ?>
        
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    <strong>Agent :</strong> <?= h($user->full_name) ?> (<?= h($user->user_code) ?>)<br>
                    <strong>Site :</strong> <?= h($user->site->name) ?>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12 mb-3">
                <label class="form-label"><i class="bi bi-toggle-on"></i> Type de télétravail</label>
                <?= $this->Form->control('remote_work_type', [
                    'type' => 'radio',
                    'options' => [
                        'none' => 'Aucun télétravail',
                        'fixed_days' => 'Jours fixes (ex: tous les mardis et jeudis)',
                        'flexible' => 'Flexible (nombre de jours par semaine, dates à définir au planning)',
                    ],
                    'label' => false,
                    'class' => 'form-check-input',
                    'id' => 'remote-work-type',
                    'templates' => [
                        'radioWrapper' => '<div class="form-check mb-2">{{label}}</div>',
                        'nestingLabel' => '{{input}}<label class="form-check-label ms-2" {{attrs}}>{{text}}</label>',
                    ],
                ]) ?>
            </div>
        </div>

        <div id="fixed-days-config" style="display: none;">
            <hr>
            <h5><i class="bi bi-calendar-check"></i> Configuration des jours fixes</h5>
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label">Jours de télétravail</label>
                    <div>
                        <?php foreach ($daysOfWeek as $dayNum => $dayName): ?>
                            <div class="form-check form-check-inline">
                                <?= $this->Form->checkbox('fixed_days[]', [
                                    'value' => $dayNum,
                                    'checked' => in_array($dayNum, $fixedDays),
                                    'id' => 'day_' . $dayNum,
                                    'class' => 'form-check-input',
                                    'hiddenField' => false
                                ]) ?>
                                <label class="form-check-label" for="day_<?= $dayNum ?>">
                                    <?= $dayName ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Heure de début</label>
                    <?= $this->Form->control('time_start', [
                        'type' => 'time',
                        'label' => false,
                        'class' => 'form-control',
                        'value' => $timeStart
                    ]) ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Heure de fin</label>
                    <?= $this->Form->control('time_end', [
                        'type' => 'time',
                        'label' => false,
                        'class' => 'form-control',
                        'value' => $timeEnd
                    ]) ?>
                </div>
            </div>
        </div>

        <div id="flexible-config" style="display: none;">
            <hr>
            <h5><i class="bi bi-calendar2-week"></i> Configuration flexible</h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nombre de jours par semaine</label>
                    <?= $this->Form->control('flexible_days_per_week', [
                        'type' => 'number',
                        'label' => false,
                        'class' => 'form-control',
                        'min' => 1,
                        'max' => 5
                    ]) ?>
                    <small class="text-muted">Les jours exacts seront définis au planning ou sur la page de gestion des jours de TAD</small>
                </div>
            </div>
        </div>

        <div id="dates-config" style="display: none;">
            <hr>
            <h5><i class="bi bi-calendar-range"></i> Période de validité</h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Date de début (optionnel)</label>
                    <?= $this->Form->control('start_date', [
                        'type' => 'date',
                        'label' => false,
                        'class' => 'form-control',
                        'value' => $startDate
                    ]) ?>
                    <small class="text-muted">Si vide, le télétravail commence immédiatement</small>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Date de fin (optionnel)</label>
                    <?= $this->Form->control('end_date', [
                        'type' => 'date',
                        'label' => false,
                        'class' => 'form-control',
                        'value' => $endDate
                    ]) ?>
                    <small class="text-muted">Si vide, le télétravail n'a pas de date de fin</small>
                </div>
            </div>
        </div>

        <hr>
        <div class="row">
            <div class="col-md-12 mb-3">
                <label class="form-label"><i class="bi bi-chat-left-text"></i> Notes (optionnel)</label>
                <?= $this->Form->control('notes', [
                    'type' => 'textarea',
                    'label' => false,
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Notes ou commentaires sur la configuration...'
                ]) ?>
            </div>
        </div>

        <div class="mt-4">
            <?= $this->Form->button(
                '<i class="bi bi-save mr-2"></i> Sauvegarder',
                ['type' => 'submit', 'class' => 'btn btn-success', 'escapeTitle' => false]
            ) ?>
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
    const typeRadios = document.querySelectorAll('input[name="remote_work_type"]');
    const fixedDaysConfig = document.getElementById('fixed-days-config');
    const flexibleConfig = document.getElementById('flexible-config');
    const datesConfig = document.getElementById('dates-config');
    
    function toggleConfig() {
        const selectedType = document.querySelector('input[name="remote_work_type"]:checked')?.value;
        
        fixedDaysConfig.style.display = selectedType === 'fixed_days' ? 'block' : 'none';
        flexibleConfig.style.display = selectedType === 'flexible' ? 'block' : 'none';
        // Toujours afficher la section des dates si un type de télétravail est sélectionné
        datesConfig.style.display = (selectedType === 'fixed_days' || selectedType === 'flexible') ? 'block' : 'none';
    }
    
    typeRadios.forEach(radio => {
        radio.addEventListener('change', toggleConfig);
    });
    
    // Initial state
    toggleConfig();
});
</script>
