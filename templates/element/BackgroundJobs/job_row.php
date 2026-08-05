<?php
/**
 * @var \App\View\AppView $this
 * @var array $item
 * @var array $typeLabels
 * @var callable $statusBadge
 */
$status = (string)($item['status'] ?? '');
$type = (string)($item['type'] ?? '');
$rowClass = in_array($status, ['queued', 'running'], true) ? 'table-row-active' : '';
?>
<tr class="<?= h($rowClass) ?>">
    <td>
        <span class="badge badge-light border">
            <?= h($typeLabels[$type] ?? $type) ?>
        </span>
    </td>
    <td>
        <?= h((string)($item['label'] ?? '—')) ?>
        <?php if (!empty($item['error_message'])): ?>
            <div class="small text-danger mt-1"><?= h((string)$item['error_message']) ?></div>
        <?php endif; ?>
    </td>
    <td>
        <span class="badge <?= h($statusBadge($status)) ?>"><?= h($status) ?></span>
    </td>
    <td class="small"><?= h((string)($item['progress'] ?? '—')) ?></td>
    <td class="small text-nowrap"><?= h((string)($item['started_at'] ?? '—')) ?></td>
    <td class="small text-nowrap"><?= h((string)($item['finished_at'] ?? '—')) ?></td>
    <td>
        <a class="btn btn-sm btn-outline-primary" href="<?= h((string)($item['url'] ?? '#')) ?>">
            Ouvrir
        </a>
        <?php if (!empty($item['can_cancel'])): ?>
            <button type="button"
                    class="btn btn-sm btn-outline-danger ml-1"
                    data-bj-cancel-optuna="<?= (int)($item['id'] ?? 0) ?>">
                Annuler
            </button>
        <?php endif; ?>
    </td>
</tr>
