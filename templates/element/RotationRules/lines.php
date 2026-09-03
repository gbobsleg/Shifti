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
<div class="card border-info mb-4">
    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
        <span><i class="bi bi-layers"></i> Lignes d’activité (priorité = ordre)</span>
        <button type="button" class="btn btn-sm btn-light" id="btn-add-rotation-line">
            <i class="bi bi-plus-circle"></i> Ajouter une ligne
        </button>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Rang 1 protégé d’abord. Quota = cible par agent (ex. 2×3 h téléphonie).
            Couverture = need + équité (ex. livechat 9–12 / 14–17).
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
        <?php // <template> : hors de l’arbre du formulaire, donc non soumis. Un div.d-none enverrait une ligne quota fantôme. ?>
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
    </div>
</div>
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
            row.className = 'form-row align-items-end mb-2';
            row.innerHTML = '<div class="col-md-5"><input type="time" name="rotation_rule_lines[' + lineIdx + '][rotation_rule_line_slots][' + next + '][start_time]" class="form-control"></div>'
                + '<div class="col-md-5"><input type="time" name="rotation_rule_lines[' + lineIdx + '][rotation_rule_line_slots][' + next + '][end_time]" class="form-control"></div>'
                + '<div class="col-md-2"><button type="button" class="btn btn-outline-danger btn-sm" data-remove-slot>&times;</button></div>';
            slots.appendChild(row);
            card.setAttribute('data-slot-next', String(next + 1));
        }
        const rmSlot = e.target.closest('[data-remove-slot]');
        if (rmSlot) {
            const row = rmSlot.closest('.form-row');
            if (row) row.remove();
        }
    });
});
</script>
