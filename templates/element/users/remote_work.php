<?php
/**
 * Element: Configuration Télétravail
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\UserRemoteWorkSetting $remoteWorkSetting
 * @var int[] $fixedDays
 * @var string|null $timeStart
 * @var string|null $timeEnd
 * @var string|null $startDate
 * @var string|null $endDate
 * @var array<int,string> $daysOfWeek
 * @var int|null $userId
 */
?>

<div class="card border-warning mb-4">
    <div class="card-header bg-warning text-dark">
        <i class="bi bi-house-door-fill"></i> Configuration Télétravail
    </div>
    <div class="card-body">
        <p class="text-muted">
            <i class="bi bi-info-circle"></i>
            Configurez le télétravail pour cet agent. Les jours de télétravail peuvent être gérés sur la <a href="<?= $this->Url->build(['controller' => 'RemoteWork', 'action' => 'index']) ?>">page de gestion des jours de télétravail</a>.
        </p>

        <?php if (!empty($remoteWorkSetting->id)): ?>
            <?= $this->Form->hidden('remote_work.id', ['value' => $remoteWorkSetting->id]) ?>
        <?php endif; ?>
        <?php if (!empty($userId)): ?>
            <?= $this->Form->hidden('remote_work.user_id', ['value' => (int)$userId]) ?>
        <?php endif; ?>

        <div class="row mb-3">
            <div class="col-md-12">
                <label class="form-label"><i class="bi bi-toggle-on"></i> Type de télétravail</label>
                <?= $this->Form->control('remote_work.remote_work_type', [
                    'type' => 'radio',
                    'options' => [
                        'none' => 'Aucun télétravail',
                        'fixed_days' => 'Jours fixes (ex: tous les mardis et jeudis)',
                        'flexible' => 'Flexible (nombre de jours par semaine, dates à définir au planning)',
                    ],
                    'label' => false,
                    'class' => 'form-check-input',
                    'id' => 'remote-work-type',
                    'default' => $remoteWorkSetting->remote_work_type ?? 'none',
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
                                <?= $this->Form->checkbox('remote_work.fixed_days[]', [
                                    'value' => $dayNum,
                                    'checked' => in_array($dayNum, $fixedDays, true),
                                    'id' => 'day_' . $dayNum,
                                    'class' => 'form-check-input',
                                    'hiddenField' => false,
                                ]) ?>
                                <label class="form-check-label" for="day_<?= (int)$dayNum ?>">
                                    <?= h($dayName) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Heure de début</label>
                    <?= $this->Form->control('remote_work.time_start', [
                        'type' => 'time',
                        'label' => false,
                        'class' => 'form-control',
                        'value' => $timeStart,
                        'id' => 'time-start',
                    ]) ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Heure de fin</label>
                    <?= $this->Form->control('remote_work.time_end', [
                        'type' => 'time',
                        'label' => false,
                        'class' => 'form-control',
                        'value' => $timeEnd,
                        'id' => 'time-end',
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
                    <?= $this->Form->control('remote_work.flexible_days_per_week', [
                        'type' => 'number',
                        'label' => false,
                        'class' => 'form-control',
                        'min' => 0,
                        'max' => 5,
                        'value' => $remoteWorkSetting->flexible_days_per_week ?? 0,
                        'id' => 'flexible-days-per-week',
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
                    <?= $this->Form->control('remote_work.start_date', [
                        'type' => 'date',
                        'label' => false,
                        'class' => 'form-control',
                        'value' => $startDate,
                        'empty' => true,
                    ]) ?>
                    <small class="text-muted">Si vide, le télétravail commence immédiatement</small>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Date de fin (optionnel)</label>
                    <?= $this->Form->control('remote_work.end_date', [
                        'type' => 'date',
                        'label' => false,
                        'class' => 'form-control',
                        'value' => $endDate,
                        'empty' => true,
                    ]) ?>
                    <small class="text-muted">Si vide, le télétravail n'a pas de date de fin</small>
                </div>
            </div>
        </div>

        <hr>
        <div class="row">
            <div class="col-md-12 mb-3">
                <label class="form-label"><i class="bi bi-chat-left-text"></i> Notes (optionnel)</label>
                <?= $this->Form->control('remote_work.notes', [
                    'type' => 'textarea',
                    'label' => false,
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Notes ou commentaires sur la configuration...',
                    'value' => $remoteWorkSetting->notes ?? '',
                ]) ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeRadios = document.querySelectorAll('input[name="remote_work[remote_work_type]"]');
    const fixedDaysConfig = document.getElementById('fixed-days-config');
    const flexibleConfig = document.getElementById('flexible-config');
    const datesConfig = document.getElementById('dates-config');

    if (!fixedDaysConfig || !flexibleConfig || !datesConfig) {
        return;
    }

    function toggleConfig() {
        const selectedType = document.querySelector('input[name="remote_work[remote_work_type]"]:checked')?.value;

        const isFixedDays = selectedType === 'fixed_days';
        const isFlexible = selectedType === 'flexible';

        // Afficher/masquer les sections
        fixedDaysConfig.style.display = isFixedDays ? 'block' : 'none';
        flexibleConfig.style.display = isFlexible ? 'block' : 'none';
        datesConfig.style.display = (isFixedDays || isFlexible) ? 'block' : 'none';

        // Désactiver la validation HTML5 pour les champs dans les sections cachées
        const fixedDaysInputs = fixedDaysConfig.querySelectorAll('input');
        fixedDaysInputs.forEach(input => {
            if (!isFixedDays) {
                input.removeAttribute('required');
                input.setAttribute('data-was-required', input.hasAttribute('required') ? 'true' : 'false');
            }
        });

        const flexibleInputs = flexibleConfig.querySelectorAll('input');
        flexibleInputs.forEach(input => {
            if (!isFlexible) {
                input.removeAttribute('required');
                input.setAttribute('data-was-required', input.hasAttribute('required') ? 'true' : 'false');
            }
        });
    }

    typeRadios.forEach(radio => {
        radio.addEventListener('change', toggleConfig);
    });

    toggleConfig();
});
</script>








