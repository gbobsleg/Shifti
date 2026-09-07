<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\RotationRule $rule
 * @var array $offers
 * @var string $defaultTimeWindowStart
 * @var string $defaultTimeWindowEnd
 */
$lines = $rule->rotation_rule_lines ?? [];
$daysOptions = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', 7 => 'Dim'];
?>
<section class="crud-section">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="crud-section-title mb-0">Activités</h2>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-add-rotation-line">
            <i class="bi bi-plus-circle"></i> Ajouter une activité
        </button>
    </div>
    <p class="text-muted small mb-3">
        Chaque bloc est une activité. La première est placée en priorité.
        <strong>Objectif par agent</strong>&nbsp;: chacun doit faire un nombre de vacations
        (ex. 2 fois 3&nbsp;h de téléphone).
        <strong>Présence sur des plages</strong>&nbsp;: un nombre d’agents à positionner à des horaires précis
        (ex. chat 9&nbsp;h–12&nbsp;h et 14&nbsp;h–17&nbsp;h).
    </p>
    <div id="rotation-lines" data-next-index="<?= count($lines) ?>">
        <?php foreach ($lines as $idx => $line): ?>
            <?= $this->element('RotationRules/line_row', [
                'idx' => $idx,
                'line' => $line,
                'offers' => $offers,
                'daysOptions' => $daysOptions,
                'defaultTimeWindowStart' => $defaultTimeWindowStart,
                'defaultTimeWindowEnd' => $defaultTimeWindowEnd,
            ]) ?>
        <?php endforeach; ?>
    </div>
    <?php // <template> : hors de l’arbre du formulaire, donc non soumis. Un div.d-none enverrait une ligne fantôme. ?>
    <template id="rotation-line-template">
        <?= $this->element('RotationRules/line_row', [
            'idx' => '__INDEX__',
            'line' => null,
            'offers' => $offers,
            'daysOptions' => $daysOptions,
            'defaultTimeWindowStart' => $defaultTimeWindowStart,
            'defaultTimeWindowEnd' => $defaultTimeWindowEnd,
        ]) ?>
    </template>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const wrap = document.getElementById('rotation-lines');
    const tpl = document.getElementById('rotation-line-template');
    const addBtn = document.getElementById('btn-add-rotation-line');
    if (!wrap || !tpl || !addBtn) return;

    function toggleLineFields(card) {
        const typeSel = card.querySelector('[data-line-type]');
        if (!typeSel) return;
        const isQuota = typeSel.value === 'quota';
        card.querySelectorAll('[data-quota-fields]').forEach(el => { el.style.display = isQuota ? '' : 'none'; });
        card.querySelectorAll('[data-coverage-fields]').forEach(el => { el.style.display = isQuota ? 'none' : ''; });
        const hint = card.querySelector('[data-type-hint]');
        if (hint) {
            hint.textContent = isQuota
                ? 'Chaque agent assigné à cette règle doit réaliser le nombre de vacations indiqué.'
                : 'Le planning doit positionner le nombre d’agents indiqué sur chaque plage horaire.';
        }
    }

    wrap.querySelectorAll('.rotation-line-card').forEach(toggleLineFields);
    wrap.addEventListener('change', function (e) {
        if (e.target && e.target.matches('[data-line-type]')) {
            toggleLineFields(e.target.closest('.rotation-line-card'));
        }
    });
    addBtn.addEventListener('click', function () {
        const idx = parseInt(wrap.getAttribute('data-next-index') || '0', 10);
        const html = tpl.innerHTML.split('__INDEX__').join(String(idx));
        const div = document.createElement('div');
        div.innerHTML = html.trim();
        const card = div.firstElementChild;
        wrap.appendChild(card);
        wrap.setAttribute('data-next-index', String(idx + 1));
        const sort = card.querySelector('[name*="[sort_order]"]');
        if (sort) {
            sort.value = String(idx + 1);
        }
        const title = card.querySelector('[data-line-title]');
        if (title) {
            title.textContent = 'Activité ' + (idx + 1);
        }
        toggleLineFields(card);
    });
    wrap.addEventListener('click', function (e) {
        const rm = e.target.closest('[data-remove-line]');
        if (rm) {
            const card = rm.closest('.rotation-line-card');
            if (card && wrap.contains(card)) card.remove();
        }
        const addSlot = e.target.closest('[data-add-slot]');
        if (addSlot) {
            const card = addSlot.closest('.rotation-line-card');
            const slots = card.querySelector('[data-slots-rows]');
            const next = parseInt(card.getAttribute('data-slot-next') || '0', 10);
            const lineIdx = card.getAttribute('data-line-idx');
            const row = document.createElement('div');
            row.className = 'row g-2 align-items-end mb-2';
            row.innerHTML = '<div class="col-md-5"><label class="form-label">De</label><input type="time" name="rotation_rule_lines[' + lineIdx + '][rotation_rule_line_slots][' + next + '][start_time]" class="form-control"></div>'
                + '<div class="col-md-5"><label class="form-label">À</label><input type="time" name="rotation_rule_lines[' + lineIdx + '][rotation_rule_line_slots][' + next + '][end_time]" class="form-control"></div>'
                + '<div class="col-md-2"><button type="button" class="btn btn-outline-danger btn-sm" data-remove-slot>Retirer</button></div>';
            slots.appendChild(row);
            card.setAttribute('data-slot-next', String(next + 1));
        }
        const rmSlot = e.target.closest('[data-remove-slot]');
        if (rmSlot) {
            const row = rmSlot.closest('.row');
            if (row) row.remove();
        }
    });
});
</script>
