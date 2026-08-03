<?php
/**
 * Element: Télétravail (lecture seule)
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 */
?>

<?php
$daysOfWeek = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];
$rw = $user->user_remote_work_setting ?? null;
?>

<div class="card border-warning mb-4">
    <div class="card-header bg-warning text-dark">
        <i class="bi bi-house-door-fill"></i> Configuration Télétravail
    </div>
    <div class="card-body">
        <?php if (!$rw): ?>
            <p class="text-muted mb-0"><i class="bi bi-info-circle"></i> Aucun télétravail configuré.</p>
        <?php else: ?>
            <div class="mb-2">
                <strong>Type :</strong>
                <?php if ($rw->remote_work_type === 'none'): ?>
                    <span class="badge bg-secondary">Aucun</span>
                <?php elseif ($rw->remote_work_type === 'fixed_days'): ?>
                    <span class="badge bg-info text-dark">Jours fixes</span>
                <?php elseif ($rw->remote_work_type === 'flexible'): ?>
                    <span class="badge bg-primary">Flexible</span>
                <?php else: ?>
                    <span class="badge bg-secondary"><?= h($rw->remote_work_type) ?></span>
                <?php endif; ?>
            </div>

            <?php if ($rw->isFixedDays()): ?>
                <?php $fixedDays = $rw->getFixedDays(); ?>
                <?php $timeRanges = $rw->getTimeRanges(); ?>

                <div class="mb-2">
                    <strong>Jours :</strong>
                    <?php if (empty($fixedDays)): ?>
                        <span class="text-muted">—</span>
                    <?php else: ?>
                        <?= h(implode(', ', array_values(array_intersect_key($daysOfWeek, array_flip(array_map('intval', $fixedDays)))))) ?>
                    <?php endif; ?>
                </div>

                <div class="mb-2">
                    <strong>Plage horaire :</strong>
                    <?php if (!empty($timeRanges)): ?>
                        <?php $start = $timeRanges[0]['start'] ?? null; ?>
                        <?php $end = $timeRanges[0]['end'] ?? null; ?>
                        <span><?= h((string)$start) ?> → <?= h((string)$end) ?></span>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </div>
            <?php elseif ($rw->isFlexible()): ?>
                <div class="mb-2">
                    <strong>Jours / semaine :</strong> <?= h((string)($rw->flexible_days_per_week ?? 0)) ?>
                </div>
            <?php endif; ?>

            <div class="mb-2">
                <strong>Période :</strong>
                <?php
                $startDate = $rw->start_date ? $rw->start_date->i18nFormat('dd/MM/yyyy') : null;
                $endDate = $rw->end_date ? $rw->end_date->i18nFormat('dd/MM/yyyy') : null;
                ?>
                <span>
                    <?= h($startDate ?? '—') ?> → <?= h($endDate ?? '—') ?>
                </span>
            </div>

            <?php if (!empty($rw->notes)): ?>
                <div class="mb-0">
                    <strong>Notes :</strong>
                    <div class="text-muted"><?= nl2br(h((string)$rw->notes)) ?></div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>








