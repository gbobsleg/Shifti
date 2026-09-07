<?php
/**
 * @var \App\View\AppView $this
 * @var int|string $idx
 * @var \App\Model\Entity\RotationRuleLine|null $line
 * @var array $offers
 * @var array $daysOptions
 * @var string $defaultTimeWindowStart
 * @var string $defaultTimeWindowEnd
 */
$type = $line->line_type ?? 'quota';
$slots = $line->rotation_rule_line_slots ?? [];
$selectedDays = [];
if (!empty($line->days_of_week)) {
    $decoded = is_string($line->days_of_week) ? json_decode($line->days_of_week, true) : (array)$line->days_of_week;
    $selectedDays = array_map('intval', $decoded ?: []);
}
$slotCount = is_countable($slots) ? count($slots) : 0;
$wStart = $line->time_window_start ?? $defaultTimeWindowStart;
$wEnd = $line->time_window_end ?? $defaultTimeWindowEnd;
$lineNumber = is_numeric($idx) ? ((int)$idx + 1) : '';
$checkboxTemplates = [
    'inputContainer' => '{{content}}',
    'nestingLabel' => '{{hidden}}{{input}}<label class="form-check-label"{{attrs}}>{{text}}</label>',
];
?>
<div class="rotation-line-card border rounded p-3 mb-3" data-line-idx="<?= h((string)$idx) ?>" data-slot-next="<?= (int)$slotCount ?>">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h3 class="crud-subsection-title mb-0" data-line-title>Activité <?= h((string)$lineNumber) ?></h3>
        <button type="button" class="btn btn-sm btn-outline-danger" data-remove-line>Retirer</button>
    </div>
    <?php if (!empty($line->id)): ?>
        <?= $this->Form->hidden("rotation_rule_lines.$idx.id", ['value' => $line->id]) ?>
    <?php endif; ?>
    <?= $this->Form->hidden("rotation_rule_lines.$idx.sort_order", [
        'value' => $line->sort_order ?? (is_numeric($idx) ? (int)$idx + 1 : 1),
    ]) ?>

    <div class="row g-3">
        <div class="col-md-4">
            <?= $this->Form->control("rotation_rule_lines.$idx.line_type", [
                'type' => 'select',
                'options' => [
                    'quota' => 'Objectif par agent',
                    'coverage' => 'Présence sur des plages',
                ],
                'value' => $type,
                'label' => 'Mode',
                'class' => 'form-control',
                'data-line-type' => '1',
            ]) ?>
        </div>
        <div class="col-md-8">
            <?= $this->Form->control("rotation_rule_lines.$idx.offer_id", [
                'type' => 'select',
                'options' => ['' => '— Choisir une offre —'] + $offers,
                'value' => $line->offer_id ?? '',
                'label' => 'Offre concernée',
                'class' => 'form-control',
                'empty' => false,
            ]) ?>
        </div>
    </div>
    <p class="form-text text-muted mb-3" data-type-hint>
        <?= $type === 'quota'
            ? 'Chaque agent assigné à cette règle doit réaliser le nombre de vacations indiqué.'
            : 'Le planning doit positionner le nombre d’agents indiqué sur chaque plage horaire.' ?>
    </p>

    <div data-quota-fields>
        <div class="row g-3">
            <div class="col-md-3">
                <?= $this->Form->control("rotation_rule_lines.$idx.target_count", [
                    'type' => 'number',
                    'min' => 1,
                    'value' => $line->target_count ?? 2,
                    'label' => 'Nombre de vacations',
                    'class' => 'form-control',
                ]) ?>
            </div>
            <div class="col-md-3">
                <?= $this->Form->control("rotation_rule_lines.$idx.shift_duration", [
                    'type' => 'number',
                    'min' => 1,
                    'value' => $line->shift_duration ?? 180,
                    'label' => 'Durée (minutes)',
                    'class' => 'form-control',
                ]) ?>
                <p class="form-text text-muted mb-0">Ex. 180 = 3&nbsp;h</p>
            </div>
            <div class="col-md-3">
                <?= $this->Form->control("rotation_rule_lines.$idx.time_window_start", [
                    'type' => 'time',
                    'value' => $wStart,
                    'label' => 'Peut commencer entre',
                    'class' => 'form-control',
                ]) ?>
            </div>
            <div class="col-md-3">
                <?= $this->Form->control("rotation_rule_lines.$idx.time_window_end", [
                    'type' => 'time',
                    'value' => $wEnd,
                    'label' => 'et',
                    'class' => 'form-control',
                ]) ?>
            </div>
        </div>
        <div class="form-check mt-3">
            <?= $this->Form->control("rotation_rule_lines.$idx.fit_need_curve", [
                'type' => 'checkbox',
                'checked' => $line->fit_need_curve ?? true,
                'label' => 'Placer en priorité aux heures où le besoin est le plus fort',
                'class' => 'form-check-input',
                'templates' => $checkboxTemplates,
            ]) ?>
        </div>
    </div>

    <div data-coverage-fields>
        <div class="row g-3 mb-2">
            <div class="col-md-4">
                <?= $this->Form->control("rotation_rule_lines.$idx.quantity", [
                    'type' => 'number',
                    'min' => 1,
                    'value' => $line->quantity ?? 1,
                    'label' => 'Agents à positionner par plage',
                    'class' => 'form-control',
                ]) ?>
            </div>
            <div class="col-md-8">
                <label class="form-label d-block">Jours concernés</label>
                <?php foreach ($daysOptions as $num => $lab): ?>
                    <label class="form-check form-check-inline">
                        <input type="checkbox"
                               class="form-check-input"
                               name="rotation_rule_lines[<?= h((string)$idx) ?>][days_of_week_selected][]"
                               value="<?= (int)$num ?>"
                               <?= in_array((int)$num, $selectedDays, true) || ($selectedDays === [] && $num <= 5) ? 'checked' : '' ?>>
                        <span class="form-check-label"><?= h($lab) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="form-check mb-2">
            <?= $this->Form->control("rotation_rule_lines.$idx.equity_enabled", [
                'type' => 'checkbox',
                'checked' => $line->equity_enabled ?? true,
                'label' => 'Répartir équitablement entre les agents qui ont cette règle',
                'class' => 'form-check-input',
                'templates' => $checkboxTemplates,
            ]) ?>
        </div>
        <div class="form-check mb-3">
            <?= $this->Form->control("rotation_rule_lines.$idx.same_person_day_slots", [
                'type' => 'checkbox',
                'checked' => $line->same_person_day_slots ?? false,
                'label' => 'Le même agent assure toutes les plages de la journée',
                'class' => 'form-check-input',
                'templates' => $checkboxTemplates,
            ]) ?>
        </div>
        <label class="form-label">Plages horaires</label>
        <div data-slots-rows>
            <?php foreach ($slots as $sidx => $slot): ?>
                <div class="row g-2 align-items-end mb-2">
                    <div class="col-md-5">
                        <?= $this->Form->control("rotation_rule_lines.$idx.rotation_rule_line_slots.$sidx.start_time", [
                            'type' => 'time',
                            'value' => $slot->start_time ?? '',
                            'label' => 'De',
                            'class' => 'form-control',
                        ]) ?>
                    </div>
                    <div class="col-md-5">
                        <?= $this->Form->control("rotation_rule_lines.$idx.rotation_rule_line_slots.$sidx.end_time", [
                            'type' => 'time',
                            'value' => $slot->end_time ?? '',
                            'label' => 'À',
                            'class' => 'form-control',
                        ]) ?>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-outline-danger btn-sm" data-remove-slot>Retirer</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-add-slot>Ajouter une plage</button>
    </div>
</div>
