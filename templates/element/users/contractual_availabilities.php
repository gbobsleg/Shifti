<?php
/**
 * Element: Disponibilités contractuelles (UserAvailabilities)
 *
 * @var \App\View\AppView $this
 * @var array<int,string> $days
 */
$uaHm = function (mixed $value): string {
    if ($value === null || $value === '') {
        return '';
    }
    if (is_object($value) && method_exists($value, 'format')) {
        return $value->format('H:i');
    }
    if (is_string($value) && preg_match('/^(\d{2}):(\d{2})/', $value, $m)) {
        return $m[1] . ':' . $m[2];
    }

    return '';
};
?>

<section class="crud-section js-contractual-availabilities">
    <h2 class="crud-section-title">Disponibilités contractuelles</h2>
    <p class="text-muted">
        Décochez « Travaille » pour un jour non travaillé. « Fin la plus tôt » peut rester vide.
    </p>
    <div class="table-responsive">
        <table class="table table-hover table-sm crud-table">
            <thead>
            <tr>
                <th>Jour</th>
                <th>Travaille</th>
                <th>Disponible de</th>
                <th>Disponible à</th>
                <th>Fin la plus tôt (optionnelle)</th>
                <th>Copier / Coller</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($days as $dayNum => $dayName): ?>
                <?php
                $index = (int)$dayNum - 1;
                $row = $user->user_availabilities[$index] ?? null;
                $startHm = $uaHm($row->availability_start_time ?? null);
                $endHm = $uaHm($row->availability_end_time ?? null);
                $earliestHm = $uaHm($row->earliest_end_time ?? null);
                $works = !($startHm === '00:00' && $endHm === '00:00');
                if (!$works) {
                    $startHm = '';
                    $endHm = '';
                    $earliestHm = '';
                }
                ?>
                <tr data-ua-day="<?= (int)$dayNum ?>" data-ua-index="<?= (int)$index ?>">
                    <td><?= h($dayName) ?></td>
                    <?= $this->Form->hidden("user_availabilities.{$index}.id") ?>
                    <?= $this->Form->hidden("user_availabilities.{$index}.day_of_week", ['value' => (int)$dayNum]) ?>
                    <td>
                        <input type="hidden" name="user_availabilities[<?= (int)$index ?>][works]" value="0">
                        <input type="checkbox"
                               name="user_availabilities[<?= (int)$index ?>][works]"
                               value="1"
                               class="form-check-input js-ua-works"
                               <?= $works ? 'checked' : '' ?>
                               aria-label="Travaille le <?= h($dayName) ?>">
                    </td>
                    <td>
                        <input type="time"
                               name="user_availabilities[<?= (int)$index ?>][availability_start_time]"
                               class="form-control form-control-sm js-ua-input"
                               data-ua-field="availability_start_time"
                               autocomplete="off"
                               value="<?= h($startHm) ?>"
                               <?= $works ? '' : 'disabled' ?>>
                    </td>
                    <td>
                        <input type="time"
                               name="user_availabilities[<?= (int)$index ?>][availability_end_time]"
                               class="form-control form-control-sm js-ua-input"
                               data-ua-field="availability_end_time"
                               autocomplete="off"
                               value="<?= h($endHm) ?>"
                               <?= $works ? '' : 'disabled' ?>>
                    </td>
                    <td>
                        <input type="time"
                               name="user_availabilities[<?= (int)$index ?>][earliest_end_time]"
                               class="form-control form-control-sm js-ua-input"
                               data-ua-field="earliest_end_time"
                               autocomplete="off"
                               value="<?= h($earliestHm) ?>"
                               <?= $works ? '' : 'disabled' ?>>
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
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.querySelector('.js-contractual-availabilities');
    if (!container) {
        return;
    }

    const fields = ['availability_start_time', 'availability_end_time', 'earliest_end_time'];
    let clipboard = null;

    function findRowByDay(dayNum) {
        return container.querySelector('tr[data-ua-day="' + dayNum + '"]');
    }

    function getInput(row, fieldName) {
        return row.querySelector('.js-ua-input[data-ua-field="' + fieldName + '"]');
    }

    function applyWorksState(row) {
        const works = row.querySelector('.js-ua-works');
        const checked = !!(works && works.checked);
        fields.forEach(function (fieldName) {
            const input = getInput(row, fieldName);
            if (!input) {
                return;
            }
            input.disabled = !checked;
            if (!checked) {
                input.value = '';
            }
        });
    }

    function enablePasteButtons() {
        container.querySelectorAll('.js-ua-paste-btn').forEach(function (btn) {
            btn.disabled = clipboard === null;
        });
    }

    function setCopiedRowState(fromDay) {
        container.querySelectorAll('tr[data-ua-day]').forEach(function (r) {
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
        const works = fromRow.querySelector('.js-ua-works');
        const data = { fromDay: fromDay, works: !!(works && works.checked) };
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
        const works = toRow.querySelector('.js-ua-works');
        if (works) {
            works.checked = !!clipboard.works;
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
        applyWorksState(toRow);
    }

    container.addEventListener('change', function (event) {
        const works = event.target.closest('.js-ua-works');
        if (!works) {
            return;
        }
        const row = works.closest('tr[data-ua-day]');
        if (row) {
            applyWorksState(row);
        }
    });

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

    container.querySelectorAll('tr[data-ua-day]').forEach(applyWorksState);
    enablePasteButtons();
});
</script>
