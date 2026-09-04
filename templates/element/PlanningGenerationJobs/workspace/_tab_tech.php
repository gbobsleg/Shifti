<?php
/**
 * Onglet Technique : diagnostics + performance (extrait du rapport).
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\PlanningGenerationJob $job
 * @var array $stats
 * @var array $topWarnings
 * @var list<array> $excludedAgentsList
 * @var list<array> $excludedByReason
 * @var array $excludedSummary
 * @var array $allWarnings
 * @var array $durationData
 */
$stats = $stats ?? [
    'total_excluded_agents' => 0,
    'total_warnings' => 0,
    'total_duration_ms' => 0,
    'avg_duration_ms' => 0,
    'total_segments' => 0,
    'days_ok' => 0,
];
$topWarnings = $topWarnings ?? [];
$excludedAgentsList = $excludedAgentsList ?? [];
$excludedByReason = $excludedByReason ?? [];
$excludedSummary = $excludedSummary ?? [
    'agents_total' => 0,
    'agents_actionable' => 0,
    'agents_expected' => 0,
    'day_agent_total' => 0,
];
$allWarnings = $allWarnings ?? [];
$durationData = $durationData ?? [];

$hasExcludedAgents = !empty($excludedAgentsList);
$hasWarnings = !empty($allWarnings);
$sectionsCount = ($hasExcludedAgents ? 1 : 0) + ($hasWarnings ? 1 : 0);
$useCollapse = $sectionsCount > 1;
$diagBadgeCount = (int)($excludedSummary['agents_actionable'] ?? 0) + count($allWarnings);
?>
<section class="crud-section">
    <ul class="nav nav-tabs crud-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link active" id="ws-diagnostics-tab" data-bs-toggle="tab" href="#ws-diagnostics" role="tab">
                    Diagnostics
                    <?php if ($diagBadgeCount > 0): ?>
                        <span class="badge bg-warning badge-count ms-1">
                            <?= $diagBadgeCount ?>
                        </span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="ws-performance-tab" data-bs-toggle="tab" href="#ws-performance" role="tab">
                    Performance
                </a>
            </li>
        </ul>
        <div class="tab-content pt-3">
            <div class="tab-pane fade show active" id="ws-diagnostics" role="tabpanel">
                <?php if ($hasExcludedAgents): ?>
                    <section class="crud-section" id="excluded-agents-panel">
                        <h2 class="crud-section-title<?= $useCollapse ? ' workspace-collapse-toggle' : '' ?>"
                             <?php if ($useCollapse): ?>
                             data-bs-toggle="collapse"
                             data-bs-target="#excluded-agents-collapse"
                             aria-expanded="false"
                             role="button"
                             <?php endif; ?>>
                                Agents exclus — <?= (int)$excludedSummary['agents_total'] ?> agents
                                <small class="text-muted font-weight-normal ms-1">
                                    (<?= (int)$excludedSummary['agents_actionable'] ?> à corriger
                                    / <?= (int)$excludedSummary['agents_expected'] ?> attendus)
                                </small>
                            <?php if ($useCollapse): ?>
                                <i class="bi bi-chevron-down"></i>
                            <?php endif; ?>
                        </h2>
                        <div class="<?= $useCollapse ? 'collapse' : '' ?>" id="excluded-agents-collapse">
                                <?php if ((int)$excludedSummary['day_agent_total'] > 0): ?>
                                    <p class="text-muted small mb-3">
                                        <?= (int)$excludedSummary['day_agent_total'] ?> exclusions jour×agent au total
                                        (même agent compté chaque jour d’exclusion).
                                    </p>
                                <?php endif; ?>

                                <!-- Synthèse par raison -->
                                <div class="mb-3" id="excluded-reason-summary">
                                    <div class="d-flex flex-wrap" style="gap: 0.5rem;">
                                        <?php foreach ($excludedByReason as $reasonRow): ?>
                                            <?php
                                            $isActionable = ($reasonRow['category'] ?? '') === 'actionable';
                                            $reasonKey = (string)$reasonRow['reason'];
                                            ?>
                                            <button type="button"
                                                    class="btn btn-sm excluded-reason-chip <?= $isActionable ? 'btn-outline-danger' : 'btn-outline-secondary' ?>"
                                                    data-filter-reason="<?= h($reasonKey) ?>"
                                                    title="Filtrer la liste sur cette raison">
                                                <span class="badge bg-<?= $isActionable ? 'danger' : 'secondary' ?> me-1">
                                                    <?= $isActionable ? 'À corriger' : 'Attendu' ?>
                                                </span>
                                                <?= h($reasonKey) ?>
                                                <strong class="ms-1"><?= (int)$reasonRow['agent_count'] ?></strong>
                                                <span class="text-muted">agents</span>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Filtres rapides -->
                                <div class="mb-3 d-flex flex-wrap align-items-center" style="gap: 0.35rem;" id="excluded-agents-filters">
                                    <span class="text-muted small me-1">Filtrer :</span>
                                    <button type="button" class="btn btn-sm btn-primary excluded-filter-chip active" data-filter-category="all">
                                        Tous
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger excluded-filter-chip" data-filter-category="actionable">
                                        À corriger (<?= (int)$excludedSummary['agents_actionable'] ?>)
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary excluded-filter-chip" data-filter-category="expected">
                                        Attendu (<?= (int)$excludedSummary['agents_expected'] ?>)
                                    </button>
                                    <button type="button" class="btn btn-sm btn-link text-muted d-none" id="excluded-filter-clear-reason">
                                        Effacer le filtre raison
                                    </button>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-sm table-striped table-hover align-middle" id="excluded-agents-table">
                                        <thead class="thead-light">
                                            <tr>
                                                <th style="width: 2rem;"></th>
                                                <th>Agent</th>
                                                <th>Site</th>
                                                <th>Catégorie</th>
                                                <th>Raisons</th>
                                                <th class="text-end">Jours</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($excludedAgentsList as $agent): ?>
                                                <?php
                                                $agentId = (int)$agent['id'];
                                                $collapseId = 'excl-agent-' . $agentId;
                                                $primaryCat = (string)($agent['primary_category'] ?? 'actionable');
                                                $reasonsCsv = implode('|', $agent['reasons'] ?? []);
                                                $isActionable = $primaryCat === 'actionable';
                                                ?>
                                                <tr class="excluded-agent-row"
                                                    data-agent-category="<?= h($primaryCat) ?>"
                                                    data-agent-reasons="<?= h($reasonsCsv) ?>">
                                                    <td class="text-center">
                                                        <button type="button"
                                                                class="btn btn-sm btn-link p-0 excluded-agent-toggle"
                                                                data-bs-toggle="collapse"
                                                                data-bs-target="#<?= h($collapseId) ?>"
                                                                aria-expanded="false"
                                                                aria-controls="<?= h($collapseId) ?>"
                                                                title="Voir le détail par jour">
                                                            <i class="bi bi-chevron-down"></i>
                                                        </button>
                                                    </td>
                                                    <td>
                                                        <strong>#<?= $agentId ?></strong>
                                                        <?= h((string)$agent['name']) ?>
                                                    </td>
                                                    <td><?= h((string)$agent['site']) ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= $isActionable ? 'danger' : 'secondary' ?>">
                                                            <?= $isActionable ? 'À corriger' : 'Attendu' ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php foreach (($agent['reasons'] ?? []) as $r): ?>
                                                            <?php $rCat = ($r === 'Agent en congé complet pour cette date') ? 'expected' : 'actionable'; ?>
                                                            <span class="badge bg-<?= $rCat === 'actionable' ? 'danger' : 'secondary' ?> me-1 mb-1">
                                                                <?= h($r) ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    </td>
                                                    <td class="text-end"><?= (int)$agent['day_count'] ?></td>
                                                </tr>
                                                <tr class="excluded-agent-detail-row"
                                                    data-agent-category="<?= h($primaryCat) ?>"
                                                    data-agent-reasons="<?= h($reasonsCsv) ?>">
                                                    <td colspan="6" class="p-0 border-0">
                                                        <div class="collapse" id="<?= h($collapseId) ?>">
                                                            <div class="p-3 bg-light border-bottom">
                                                                <table class="table table-sm mb-0">
                                                                    <thead>
                                                                        <tr>
                                                                            <th style="width: 10rem;">Date</th>
                                                                            <th>Raison</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php foreach (($agent['days'] ?? []) as $dayRow): ?>
                                                                            <?php
                                                                            $dayReason = (string)($dayRow['reason'] ?? '');
                                                                            $dayCat = $dayReason === 'Agent en congé complet pour cette date'
                                                                                ? 'expected'
                                                                                : 'actionable';
                                                                            ?>
                                                                            <tr>
                                                                                <td><?= h((string)($dayRow['date'] ?? '')) ?></td>
                                                                                <td>
                                                                                    <span class="badge bg-<?= $dayCat === 'actionable' ? 'danger' : 'secondary' ?>">
                                                                                        <?= h($dayReason) ?>
                                                                                    </span>
                                                                                </td>
                                                                            </tr>
                                                                        <?php endforeach; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <p class="text-muted small mb-0" id="excluded-agents-empty-filter" style="display: none;">
                                    Aucun agent pour ce filtre.
                                </p>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($hasWarnings): ?>
                    <section class="crud-section">
                        <h2 class="crud-section-title<?= $useCollapse ? ' workspace-collapse-toggle' : '' ?>"
                             <?php if ($useCollapse): ?>
                             data-bs-toggle="collapse"
                             data-bs-target="#warnings-collapse"
                             aria-expanded="false"
                             role="button"
                             <?php endif; ?>>
                                Warnings (<?= count($allWarnings) ?> types, <?= (int)$stats['total_warnings'] ?> occurrences)
                            <?php if ($useCollapse): ?>
                                <i class="bi bi-chevron-down"></i>
                            <?php endif; ?>
                        </h2>
                        <div class="<?= $useCollapse ? 'collapse' : '' ?>" id="warnings-collapse">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Message</th>
                                                <th class="text-end">Occurrences</th>
                                                <th>Dates concernées</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($topWarnings as $warning): ?>
                                                <tr>
                                                    <td><?= h($warning['message']) ?></td>
                                                    <td class="text-end"><?= (int)$warning['count'] ?>×</td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?= implode(', ', array_slice($warning['dates'], 0, 3)) ?>
                                                            <?php if (count($warning['dates']) > 3): ?>
                                                                <span class="text-muted">(+<?= count($warning['dates']) - 3 ?> autres)</span>
                                                            <?php endif; ?>
                                                        </small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (!$hasExcludedAgents && !$hasWarnings): ?>
                    <p class="text-muted mb-0">Aucun problème détecté dans les diagnostics.</p>
                <?php endif; ?>
            </div>

            <div class="tab-pane fade" id="ws-performance" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <section class="crud-section">
                            <h2 class="crud-section-title">Évolution de la durée</h2>
                                <?php if (!empty($durationData)): ?>
                                    <?php
                                    $maxDuration = max(array_column($durationData, 'duration'));
                                    $maxDuration = $maxDuration > 0 ? $maxDuration : 1;
                                    ?>
                                    <div class="d-flex flex-column" style="gap: 0.5rem;">
                                        <?php foreach ($durationData as $item): ?>
                                            <?php
                                            $percentage = ($item['duration'] / $maxDuration) * 100;
                                            $dateObj = $item['date'] ?? null;
                                            if ($dateObj instanceof \Cake\I18n\FrozenDate || $dateObj instanceof \Cake\I18n\FrozenTime) {
                                                $dateFormatted = $dateObj->i18nFormat('dd/MM/yyyy');
                                            } elseif ($dateObj instanceof \DateTimeInterface) {
                                                $dateFormatted = $dateObj->format('d/m/Y');
                                            } else {
                                                $dateFormatted = (string)$dateObj;
                                            }
                                            ?>
                                            <div class="d-flex align-items-center">
                                                <div class="text-muted small" style="width: 100px; flex-shrink: 0;">
                                                    <?= h($dateFormatted) ?>
                                                </div>
                                                <div class="flex-grow-1 mx-2">
                                                    <div class="progress" style="height: 24px;">
                                                        <div class="progress-bar bg-info"
                                                             role="progressbar"
                                                             style="width: <?= $percentage ?>%"
                                                             title="<?= h($dateFormatted) ?> : <?= number_format($item['duration'], 2) ?>s">
                                                            <span class="small" style="line-height: 24px; padding: 0 8px;">
                                                                <?= number_format($item['duration'], 2) ?>s
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="mt-3 text-center">
                                        <small class="text-muted">
                                            Durée maximale : <?= number_format($maxDuration, 2) ?>s
                                        </small>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted mb-0">
                                        <i class="bi bi-info-circle"></i>
                                        Aucune donnée de durée disponible.
                                    </p>
                                <?php endif; ?>
                        </section>
                    </div>
                    <div class="col-md-6">
                        <section class="crud-section">
                            <h2 class="crud-section-title">Statistiques de performance</h2>
                                <dl class="crud-fields">
                                    <div>
                                        <dt>Durée totale</dt>
                                        <dd><?= number_format(($stats['total_duration_ms'] ?? 0) / 1000, 2) ?>s</dd>
                                    </div>
                                    <div>
                                        <dt>Durée moyenne</dt>
                                        <dd><?= ($stats['avg_duration_ms'] ?? 0) > 0 ? number_format($stats['avg_duration_ms'] / 1000, 2) : '—' ?>s</dd>
                                    </div>
                                    <div>
                                        <dt>Durée min</dt>
                                        <dd>
                                        <?php
                                        $durations = array_column($durationData, 'duration');
                                        echo !empty($durations) ? number_format(min($durations), 2) . 's' : '—';
                                        ?>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Durée max</dt>
                                        <dd><?= !empty($durations) ? number_format(max($durations), 2) . 's' : '—' ?></dd>
                                    </div>
                                    <div>
                                        <dt>Segments par jour OK</dt>
                                        <dd><?= ($stats['days_ok'] ?? 0) > 0 ? number_format(($stats['total_segments'] ?? 0) / $stats['days_ok'], 0) : '—' ?></dd>
                                    </div>
                                </dl>
                        </section>
                    </div>
                </div>
            </div>
        </div>
</section>
