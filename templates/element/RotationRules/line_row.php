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
?>
<section class="crud-section rotation-line-card" data-line-idx="<?= h((string)$idx) ?>" data-slot-next="<?= (int)$slotCount ?>">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h3 class="crud-subsection-title mb-0">Ligne <?= is_numeric($idx) ? ((int)$idx + 1) : '' ?></h3>
        <button type="button" class="btn btn-sm btn-outline-danger" data-remove-line>Retirer</button>
    </div>
        <?php if (!empty($line->id)): ?>
            <?= $this->Form->hidden("rotation_rule_lines.$idx.id", ['value' => $line->id]) ?>
        <?php endif; ?>
        <div class="form-row">
            <div class="col-md-3 mb-2">
                <?= $this->Form->control("rotation_rule_lines.$idx.line_type", [
                    'type' => 'select',
                    'options' => ['quota' => 'Quota agent', 'coverage' => 'Couverture'],
                    'value' => $type,
                    'label' => 'Type',
                    'class' => 'form-control',
                    'data-line-type' => '1',
                ]) ?>
            </div>
            <div class="col-md-3 mb-2">
                <?= $this->Form->control("rotation_rule_lines.$idx.sort_order", [
                    'type' => 'number',
                    'min' => 1,
                    'value' => $line->sort_order ?? 1,
                    'label' => 'Rang (1 = prioritaire)',
                    'class' => 'form-control',
                ]) ?>
            </div>
            <div class="col-md-6 mb-2">
                <?= $this->Form->control("rotation_rule_lines.$idx.offer_id", [
                    'type' => 'select',
                    'options' => ['' => '— Offre —'] + $offers,
                    'value' => $line->offer_id ?? '',
                    'label' => 'Offre',
                    'class' => 'form-control',
                    'empty' => false,
                ]) ?>
            </div>
        </div>

        <div data-quota-fields>
            <div class="form-row">
                <div class="col-md-3 mb-2">
                    <?= $this->Form->control("rotation_rule_lines.$idx.target_count", [
                        'type' => 'number',
                        'min' => 1,
                        'value' => $line->target_count ?? 2,
                        'label' => 'Cible / période',
                        'class' => 'form-control',
                    ]) ?>
                </div>
                <div class="col-md-3 mb-2">
                    <?= $this->Form->control("rotation_rule_lines.$idx.shift_duration", [
                        'type' => 'number',
                        'min' => 1,
                        'value' => $line->shift_duration ?? 180,
                        'label' => 'Durée (min)',
                        'class' => 'form-control',
                    ]) ?>
                </div>
                <div class="col-md-3 mb-2">
                    <?= $this->Form->control("rotation_rule_lines.$idx.time_window_start", [
                        'type' => 'time',
                        'value' => $wStart,
                        'label' => 'Début fenêtre',
                        'class' => 'form-control',
                    ]) ?>
                </div>
                <div class="col-md-3 mb-2">
                    <?= $this->Form->control("rotation_rule_lines.$idx.time_window_end", [
                        'type' => 'time',
                        'value' => $wEnd,
                        'label' => 'Fin fenêtre',
                        'class' => 'form-control',
                    ]) ?>
                </div>
            </div>
            <?= $this->Form->control("rotation_rule_lines.$idx.fit_need_curve", [
                'type' => 'checkbox',
                'checked' => $line->fit_need_curve ?? true,
                'label' => 'Coller à la courbe de besoin',
            ]) ?>
        </div>

        <div data-coverage-fields>
            <div class="form-row">
                <div class="col-md-3 mb-2">
                    <?= $this->Form->control("rotation_rule_lines.$idx.quantity", [
                        'type' => 'number',
                        'min' => 1,
                        'value' => $line->quantity ?? 1,
                        'label' => 'Effectif visé / plage',
                        'class' => 'form-control',
                    ]) ?>
                </div>
                <div class="col-md-9 mb-2">
                    <label class="d-block">Jours</label>
                    <?php foreach ($daysOptions as $num => $lab): ?>
                        <label class="me-2">
                            <input type="checkbox"
                                   name="rotation_rule_lines[<?= h((string)$idx) ?>][days_of_week_selected][]"
                                   value="<?= (int)$num ?>"
                                   <?= in_array((int)$num, $selectedDays, true) || ($selectedDays === [] && $num <= 5) ? 'checked' : '' ?>>
                            <?= h($lab) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?= $this->Form->control("rotation_rule_lines.$idx.equity_enabled", [
                'type' => 'checkbox',
                'checked' => $line->equity_enabled ?? true,
                'label' => 'Équité entre agents du modèle',
            ]) ?>
            <?= $this->Form->control("rotation_rule_lines.$idx.same_person_day_slots", [
                'type' => 'checkbox',
                'checked' => $line->same_person_day_slots ?? false,
                'label' => 'Même agent sur toutes les plages du jour',
            ]) ?>
            <label class="mt-2">Plages horaires</label>
            <div data-slots-rows>
                <?php foreach ($slots as $sidx => $slot): ?>
                    <div class="form-row align-items-end mb-2">
                        <div class="col-md-5">
                            <?= $this->Form->control("rotation_rule_lines.$idx.rotation_rule_line_slots.$sidx.start_time", [
                                'type' => 'time',
                                'value' => $slot->start_time ?? '',
                                'label' => false,
                                'class' => 'form-control',
                            ]) ?>
                        </div>
                        <div class="col-md-5">
                            <?= $this->Form->control("rotation_rule_lines.$idx.rotation_rule_line_slots.$sidx.end_time", [
                                'type' => 'time',
                                'value' => $slot->end_time ?? '',
                                'label' => false,
                                'class' => 'form-control',
                            ]) ?>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-outline-danger btn-sm" data-remove-slot>&times;</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-add-slot>Ajouter une plage</button>
        </div>
</section>
