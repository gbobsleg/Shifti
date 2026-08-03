<?php
/**
 * Element: Disponibilités contractuelles (UserAvailabilities)
 *
 * @var \App\View\AppView $this
 * @var array<int,string> $days
 */
?>

<?php // --- Section Disponibilités Contractuelles --- ?>
<div class="card border-info mb-4 js-contractual-availabilities">
    <div class="card-header bg-info text-white">
        <i class="bi bi-calendar-week"></i> Disponibilités Contractuelles
    </div>
    <div class="card-body">
        <p class="text-muted">
            <i class="bi bi-info-circle"></i>
            Définissez les fenêtres de travail pour chaque jour. Mettez 00:00 à 00:00 pour un jour non travaillé.
        </p>

        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th style="width: 15%;">Jour</th>
                    <th style="width: 20%;">Disponible de</th>
                    <th style="width: 20%;">Disponible à</th>
                    <th style="width: 25%;">Fin la plus tôt (optionnelle)</th>
                    <th style="width: 20%;">Copier / Coller</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($days as $dayNum => $dayName): ?>
                    <?php $index = (int)$dayNum - 1; ?>
                    <tr data-ua-day="<?= (int)$dayNum ?>" data-ua-index="<?= (int)$index ?>">
                        <td class="fw-bold">
                            <i class="bi bi-calendar"></i> <?= h($dayName) ?>
                        </td>

                        <?= $this->Form->hidden("user_availabilities.{$index}.id") ?>
                        <?= $this->Form->hidden("user_availabilities.{$index}.day_of_week", ['value' => (int)$dayNum]) ?>

                        <td>
                            <?= $this->Form->control("user_availabilities.{$index}.availability_start_time", [
                                'label' => false,
                                'type' => 'time',
                                'class' => 'form-control form-control-sm js-ua-input',
                                'data-ua-field' => 'availability_start_time',
                            ]) ?>
                        </td>
                        <td>
                            <?= $this->Form->control("user_availabilities.{$index}.availability_end_time", [
                                'label' => false,
                                'type' => 'time',
                                'class' => 'form-control form-control-sm js-ua-input',
                                'data-ua-field' => 'availability_end_time',
                            ]) ?>
                        </td>
                        <td>
                            <?= $this->Form->control("user_availabilities.{$index}.earliest_end_time", [
                                'label' => false,
                                'type' => 'time',
                                'empty' => true,
                                'class' => 'form-control form-control-sm js-ua-input',
                                'data-ua-field' => 'earliest_end_time',
                            ]) ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group" aria-label="Copier / coller les disponibilités">
                                <button type="button" class="btn btn-outline-secondary js-ua-copy-btn" title="Copier les disponibilités de ce jour">
                                    <i class="bi bi-copy"></i> Copier
                                </button>
                                <button type="button" class="btn btn-outline-secondary js-ua-paste-btn" title="Coller les disponibilités copiées sur ce jour" disabled>
                                    <i class="bi bi-clipboard-check"></i> Coller
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.querySelector('.js-contractual-availabilities');
    if (!container) {
        return;
    }

    const fields = ['availability_start_time', 'availability_end_time', 'earliest_end_time'];
    let clipboard = null; // { availability_start_time, availability_end_time, earliest_end_time, fromDay }

    function findRowByDay(dayNum) {
        return container.querySelector('tr[data-ua-day="' + dayNum + '"]');
    }

    function getInput(row, fieldName) {
        return row.querySelector('.js-ua-input[data-ua-field="' + fieldName + '"]');
    }

    function enablePasteButtons() {
        const pasteButtons = container.querySelectorAll('.js-ua-paste-btn');
        pasteButtons.forEach(function (btn) {
            btn.disabled = clipboard === null;
        });
    }

    function setCopiedRowState(fromDay) {
        const rows = container.querySelectorAll('tr[data-ua-day]');
        rows.forEach(function (r) {
            r.classList.remove('table-info');
        });
        const copiedRow = findRowByDay(fromDay);
        if (copiedRow) {
            copiedRow.classList.add('table-info');
        }
    }

    function copyFromRow(fromDay) {
        const fromRow = findRowByDay(fromDay);
        if (!fromRow) {
            return;
        }

        const data = { fromDay: fromDay };
        fields.forEach(function (fieldName) {
            const input = getInput(fromRow, fieldName);
            data[fieldName] = input ? input.value : '';
        });

        clipboard = data;
        enablePasteButtons();
        setCopiedRowState(fromDay);
    }

    function pasteToRow(toDay) {
        if (!clipboard) {
            return;
        }

        const toRow = findRowByDay(toDay);
        if (!toRow) {
            return;
        }

        fields.forEach(function (fieldName) {
            const toInput = getInput(toRow, fieldName);
            if (!toInput) {
                return;
            }

            toInput.value = clipboard[fieldName] ?? '';
            toInput.dispatchEvent(new Event('input', { bubbles: true }));
            toInput.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    container.addEventListener('click', function (event) {
        const copyBtn = event.target.closest('.js-ua-copy-btn');
        if (copyBtn) {
            const row = copyBtn.closest('tr[data-ua-day]');
            if (!row) {
                return;
            }
            const fromDay = parseInt(row.dataset.uaDay, 10);
            if (!Number.isFinite(fromDay)) {
                return;
            }
            copyFromRow(fromDay);
            return;
        }

        const pasteBtn = event.target.closest('.js-ua-paste-btn');
        if (pasteBtn) {
            const row = pasteBtn.closest('tr[data-ua-day]');
            if (!row) {
                return;
            }
            const toDay = parseInt(row.dataset.uaDay, 10);
            if (!Number.isFinite(toDay)) {
                return;
            }
            pasteToRow(toDay);
        }
    });

    enablePasteButtons();
});
</script>


