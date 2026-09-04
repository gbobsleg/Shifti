<?php
/**
 * Element: Télétravail (lecture seule)
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 */
$daysOfWeek = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];
$rw = $user->user_remote_work_setting ?? null;
?>

<section class="crud-section">
    <h2 class="crud-section-title">Télétravail</h2>
    <?php if (!$rw): ?>
        <p class="text-muted mb-0">Aucun télétravail configuré.</p>
    <?php else: ?>
        <dl class="crud-fields">
            <div>
                <dt>Type</dt>
                <dd>
                    <?php if ($rw->remote_work_type === 'none'): ?>
                        Aucun
                    <?php elseif ($rw->remote_work_type === 'fixed_days'): ?>
                        Jours fixes
                    <?php elseif ($rw->remote_work_type === 'flexible'): ?>
                        Flexible
                    <?php else: ?>
                        <?= h($rw->remote_work_type) ?>
                    <?php endif; ?>
                </dd>
            </div>
            <?php if ($rw->isFixedDays()): ?>
                <?php $fixedDays = $rw->getFixedDays(); ?>
                <?php $timeRanges = $rw->getTimeRanges(); ?>
                <div>
                    <dt>Jours</dt>
                    <dd>
                        <?php if (empty($fixedDays)): ?>
                            —
                        <?php else: ?>
                            <?= h(implode(', ', array_values(array_intersect_key($daysOfWeek, array_flip(array_map('intval', $fixedDays)))))) ?>
                        <?php endif; ?>
                    </dd>
                </div>
                <div>
                    <dt>Plage horaire</dt>
                    <dd>
                        <?php if (!empty($timeRanges)): ?>
                            <?php $start = $timeRanges[0]['start'] ?? null; ?>
                            <?php $end = $timeRanges[0]['end'] ?? null; ?>
                            <?= h((string)$start) ?> → <?= h((string)$end) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </dd>
                </div>
            <?php elseif ($rw->isFlexible()): ?>
                <div>
                    <dt>Jours / semaine</dt>
                    <dd><?= h((string)($rw->flexible_days_per_week ?? 0)) ?></dd>
                </div>
            <?php endif; ?>
            <div>
                <dt>Période</dt>
                <dd>
                    <?php
                    $startDate = $rw->start_date ? $rw->start_date->i18nFormat('dd/MM/yyyy') : null;
                    $endDate = $rw->end_date ? $rw->end_date->i18nFormat('dd/MM/yyyy') : null;
                    ?>
                    <?= h($startDate ?? '—') ?> → <?= h($endDate ?? '—') ?>
                </dd>
            </div>
            <?php if (!empty($rw->notes)): ?>
                <div>
                    <dt>Notes</dt>
                    <dd><?= nl2br(h((string)$rw->notes)) ?></dd>
                </div>
            <?php endif; ?>
        </dl>
    <?php endif; ?>
</section>
