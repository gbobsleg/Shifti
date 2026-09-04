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
$label = (string)($item['label'] ?? '—');
$url = (string)($item['url'] ?? '#');
$statusLabels = [
    'running' => 'En cours',
    'queued' => 'En file',
    'completed' => 'Terminé',
    'finished' => 'Terminé',
    'failed' => 'Échec',
    'error' => 'Erreur',
    'infeasible' => 'Infaisable',
    'finished_with_errors' => 'Terminé avec erreurs',
    'cancelled' => 'Annulé',
];
$statusLabel = $statusLabels[$status] ?? $status;
?>
<tr class="<?= h($rowClass) ?>">
    <td><?= h($typeLabels[$type] ?? $type) ?></td>
    <td>
        <?= $this->Html->link(
            $label,
            $url,
            ['class' => 'crud-row-link']
        ) ?>
        <?php if (!empty($item['error_message'])): ?>
            <div class="small text-danger mt-1"><?= h((string)$item['error_message']) ?></div>
        <?php endif; ?>
    </td>
    <td>
        <span class="badge <?= h($statusBadge($status)) ?>"><?= h($statusLabel) ?></span>
    </td>
    <td class="small"><?= h((string)($item['progress'] ?? '—')) ?></td>
    <td class="small text-nowrap"><?= h((string)($item['started_at'] ?? '—')) ?></td>
    <td class="small text-nowrap"><?= h((string)($item['finished_at'] ?? '—')) ?></td>
    <td class="actions">
        <?= $this->Html->link(
            '<i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>',
            $url,
            [
                'class' => 'crud-action',
                'escape' => false,
                'title' => 'Ouvrir',
                'aria-label' => 'Ouvrir',
                'data-bs-toggle' => 'tooltip',
            ]
        ) ?>
        <?php if (!empty($item['can_cancel'])): ?>
            <button type="button"
                    class="crud-action crud-action-danger border-0 bg-transparent"
                    title="Annuler"
                    aria-label="Annuler"
                    data-bj-cancel-optuna="<?= (int)($item['id'] ?? 0) ?>">
                <i class="bi bi-x-circle" aria-hidden="true"></i>
            </button>
        <?php endif; ?>
    </td>
</tr>
