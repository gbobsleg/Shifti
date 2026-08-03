<?php
/**
 * @deprecated Redirection depuis le controller vers view?tab=qualite.
 * Conservé pour référence ; n'est plus servi.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\PlanningGenerationJob $job
 * @var \Cake\Datasource\ResultSetInterface|\Cake\Collection\CollectionInterface $days
 * @var array $stats
 * @var float $successRate
 * @var int $healthScore
 * @var array $topExcludedAgents
 * @var array $topWarnings
 * @var array $allExcludedAgents
 * @var array $allWarnings
 * @var array $allPreSolverDiagnostics
 * @var array $daysData
 * @var array $durationData
 * @var array $statusTimeline
 */
?>
<?php $this->assign('title', 'Rapport job #' . (int)$job->id); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<style>
.kpi-card {
    transition: transform 0.2s, box-shadow 0.2s;
    border-left: 4px solid;
}
.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
}
.kpi-value {
    font-size: 2.5rem;
    font-weight: 700;
    line-height: 1;
}
.kpi-label {
    font-size: 0.9rem;
    color: #6c757d;
    margin-top: 0.5rem;
}
.health-score {
    font-size: 3rem;
    font-weight: 700;
}
.timeline-bar {
    height: 8px;
    border-radius: 4px;
    transition: height 0.2s;
}
.timeline-bar:hover {
    height: 12px;
}
.chart-container {
    position: relative;
    height: 200px;
}
.nav-tabs .nav-link {
    border: none;
    border-bottom: 3px solid transparent;
    color: #6c757d;
    font-weight: 500;
}
.nav-tabs .nav-link:hover {
    border-bottom-color: #dee2e6;
    color: #495057;
}
.nav-tabs .nav-link.active {
    border-bottom-color: #0d6efd;
    color: #0d6efd;
    background: transparent;
}
.badge-count {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}
.pass-badge {
    font-size: 0.65rem;
    padding: 0.2rem 0.4rem;
    margin-left: 0.25rem;
    cursor: default;
}
</style>

<div class="row">
    <div class="col-12">
        <!-- Header avec actions -->
        <div class="card shadow mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center" style="gap: 1rem;">
                    <?= $this->Html->link(
                        '<i class="bi bi-arrow-left"></i>',
                        ['action' => 'index'],
                        ['class' => 'btn btn-sm btn-outline-secondary', 'escape' => false, 'title' => 'Retour à la liste des jobs']
                    ) ?>
                    <div>
                        <h3 class="mb-0">
                            <i class="bi bi-clipboard-data text-primary"></i> 
                            Rapport job #<?= (int)$job->id ?>
                        </h3>
                        <small class="text-muted">
                            Période : <?= h((string)$job->start_date) ?> → <?= h((string)$job->end_date) ?>
                            | Créé par <?= h($job->user->first_name ?? '') ?> <?= h($job->user->last_name ?? '') ?>
                        </small>
                    </div>
                </div>
                <div class="d-flex" style="gap: 0.5rem;">
                    <?= $this->Html->link(
                        '<i class="bi bi-eye"></i> Suivi',
                        ['action' => 'view', (int)$job->id],
                        ['class' => 'btn btn-sm btn-outline-secondary', 'escape' => false]
                    ) ?>
                    <?= $this->Html->link(
                        '<i class="bi bi-file-earmark-text"></i> Brouillon',
                        ['action' => 'draft', (int)$job->id],
                        ['class' => 'btn btn-sm btn-outline-primary', 'escape' => false]
                    ) ?>
                    <?= $this->Html->link(
                        '<i class="bi bi-graph-up"></i> Rapport équité',
                        ['action' => 'equityReport', (int)$job->id],
                        ['class' => 'btn btn-sm btn-outline-primary', 'escape' => false]
                    ) ?>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card shadow kpi-card border-left-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="kpi-value text-success"><?= number_format($stats['days_ok']) ?></div>
                                <div class="kpi-label">Jours OK</div>
                            </div>
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 2.5rem; opacity: 0.2;"></i>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">
                                <?= $stats['total_days'] > 0 ? number_format(($stats['days_ok'] / $stats['total_days']) * 100, 1) : 0 ?>% de réussite
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow kpi-card border-left-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="kpi-value text-info"><?= number_format($successRate, 1) ?>%</div>
                                <div class="kpi-label">Taux de succès</div>
                            </div>
                            <i class="bi bi-percent text-info" style="font-size: 2.5rem; opacity: 0.2;"></i>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">
                                <?= $stats['days_ok'] ?> / <?= $stats['total_days'] ?> jours
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow kpi-card border-left-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="kpi-value text-warning">
                                    <?= $stats['avg_duration_ms'] > 0 ? number_format($stats['avg_duration_ms'] / 1000, 1) : '—' ?>
                                </div>
                                <div class="kpi-label">Durée moyenne (s)</div>
                            </div>
                            <i class="bi bi-clock-history text-warning" style="font-size: 2.5rem; opacity: 0.2;"></i>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">
                                Total : <?= number_format($stats['total_duration_ms'] / 1000, 1) ?>s
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow kpi-card border-left-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="kpi-value text-primary"><?= number_format($stats['total_segments']) ?></div>
                                <div class="kpi-label">Segments générés</div>
                            </div>
                            <i class="bi bi-list-task text-primary" style="font-size: 2.5rem; opacity: 0.2;"></i>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">
                                <?= $stats['days_ok'] > 0 ? number_format($stats['total_segments'] / $stats['days_ok'], 0) : 0 ?> par jour OK
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Score de santé -->
        <div class="card shadow mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="mb-1">
                            <i class="bi bi-heart-pulse"></i> Score de santé global
                        </h5>
                        <p class="text-muted mb-0">
                            Indicateur basé sur le taux de succès, les erreurs et les warnings
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <?php
                        $healthColor = 'success';
                        $healthIcon = 'check-circle';
                        if ($healthScore < 50) {
                            $healthColor = 'danger';
                            $healthIcon = 'x-circle';
                        } elseif ($healthScore < 75) {
                            $healthColor = 'warning';
                            $healthIcon = 'exclamation-triangle';
                        }
                        ?>
                        <div class="health-score text-<?= $healthColor ?>">
                            <?= $healthScore ?>
                            <small style="font-size: 1.5rem;">/100</small>
                        </div>
                        <div class="progress mt-2" style="height: 8px;">
                            <div class="progress-bar bg-<?= $healthColor ?>" 
                                 role="progressbar" 
                                 style="width: <?= $healthScore ?>%"
                                 aria-valuenow="<?= $healthScore ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation par onglets -->
        <div class="card shadow">
            <div class="card-header bg-white border-bottom">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link active" id="overview-tab" data-toggle="tab" href="#overview" role="tab" aria-controls="overview" aria-selected="true">
                            <i class="bi bi-speedometer2"></i> Vue d'ensemble
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="detail-tab" data-toggle="tab" href="#detail" role="tab" aria-controls="detail" aria-selected="false">
                            <i class="bi bi-calendar3"></i> Détail par jour
                            <?php if ($stats['days_infeasible'] > 0 || $stats['days_error'] > 0): ?>
                                <span class="badge badge-danger badge-count ml-1">
                                    <?= $stats['days_infeasible'] + $stats['days_error'] ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="diagnostics-tab" data-toggle="tab" href="#diagnostics" role="tab" aria-controls="diagnostics" aria-selected="false">
                            <i class="bi bi-bug"></i> Diagnostics
                            <?php if ($stats['total_warnings'] > 0 || $stats['total_excluded_agents'] > 0): ?>
                                <span class="badge badge-warning badge-count ml-1">
                                    <?= $stats['total_warnings'] + $stats['total_excluded_agents'] ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="performance-tab" data-toggle="tab" href="#performance" role="tab" aria-controls="performance" aria-selected="false">
                            <i class="bi bi-graph-up-arrow"></i> Performance
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="myTabContent">
                    <!-- Onglet Vue d'ensemble -->
                    <div class="tab-pane fade show active" id="overview" role="tabpanel">
                        <!-- Timeline visuelle -->
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h5 class="mb-0"><i class="bi bi-timeline"></i> Timeline des statuts</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap mb-3" style="min-height: 40px; gap: 0.25rem;">
                                    <?php foreach ($statusTimeline as $item): ?>
                                        <?php
                                        $color = 'secondary';
                                        $title = 'En attente';
                                        if ($item['status'] === 'ok') {
                                            $color = 'success';
                                            $title = 'OK';
                                        } elseif ($item['status'] === 'infeasible') {
                                            $color = 'warning';
                                            $title = 'Infaisable';
                                        } elseif ($item['status'] === 'error') {
                                            $color = 'danger';
                                            $title = 'Erreur';
                                        } elseif ($item['status'] === 'running') {
                                            $color = 'info';
                                            $title = 'En cours';
                                        }
                                        ?>
                                        <span class="timeline-bar bg-<?= $color ?>" 
                                              style="flex: 1; min-width: 8px;"
                                              data-toggle="tooltip" 
                                              data-placement="top"
                                              title="<?= h($item['date']) ?> : <?= h($title) ?>">
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                                <div class="d-flex justify-content-between text-muted small">
                                    <span><?= h($job->start_date) ?></span>
                                    <span><?= h($job->end_date) ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Statistiques consolidées -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="bi bi-pie-chart"></i> Répartition des statuts</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex flex-column" style="gap: 0.5rem;">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span><i class="bi bi-circle-fill text-success"></i> OK</span>
                                                <strong><?= $stats['days_ok'] ?> jours</strong>
                                            </div>
                                            <?php if ($stats['days_infeasible'] > 0): ?>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span><i class="bi bi-circle-fill text-warning"></i> Infaisable</span>
                                                    <strong><?= $stats['days_infeasible'] ?> jours</strong>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($stats['days_error'] > 0): ?>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span><i class="bi bi-circle-fill text-danger"></i> Erreur</span>
                                                    <strong><?= $stats['days_error'] ?> jours</strong>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($stats['days_queued'] > 0): ?>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span><i class="bi bi-circle-fill text-secondary"></i> En attente</span>
                                                    <strong><?= $stats['days_queued'] ?> jours</strong>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($stats['days_running'] > 0): ?>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span><i class="bi bi-circle-fill text-info"></i> En cours</span>
                                                    <strong><?= $stats['days_running'] ?> jours</strong>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Top problèmes</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($topWarnings) && empty($topExcludedAgents)): ?>
                                            <p class="text-muted mb-0">Aucun problème détecté</p>
                                        <?php else: ?>
                                            <ul class="list-unstyled mb-0">
                                                <?php foreach (array_slice($topWarnings, 0, 3) as $warning): ?>
                                                    <li class="mb-2">
                                                        <i class="bi bi-exclamation-circle text-warning"></i>
                                                        <strong><?= $warning['count'] ?>x</strong>
                                                        <small class="text-muted"><?= h(mb_substr($warning['message'], 0, 50)) ?><?= mb_strlen($warning['message']) > 50 ? '...' : '' ?></small>
                                                    </li>
                                                <?php endforeach; ?>
                                                <?php foreach (array_slice($topExcludedAgents, 0, 3) as $agent): ?>
                                                    <li class="mb-2">
                                                        <i class="bi bi-person-x text-danger"></i>
                                                        <strong><?= $agent['count'] ?>x</strong>
                                                        <small class="text-muted"><?= h($agent['name']) ?> exclu</small>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions rapides -->
                        <div class="card border-success">
                            <div class="card-header bg-success text-white">
                                <i class="bi bi-cloud-upload"></i> Actions rapides
                            </div>
                            <div class="card-body">
                                <div class="row" style="margin-left: -0.5rem; margin-right: -0.5rem;">
                                    <div class="col-md-6">
                                        <?= $this->Form->create(null, ['url' => ['action' => 'publish', (int)$job->id]]) ?>
                                        <div class="row align-items-end">
                                            <div class="col-6">
                                                <label class="form-label small">Début</label>
                                                <?php
                                                $startVal = '';
                                                if ($job->start_date instanceof \DateTimeInterface) {
                                                    $startVal = $job->start_date->format('Y-m-d');
                                                } else {
                                                    $raw = (string)$job->start_date;
                                                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
                                                        $startVal = $raw;
                                                    } elseif (strpos($raw, '/') !== false) {
                                                        $dt = \DateTime::createFromFormat('d/m/Y', $raw);
                                                        if ($dt) { $startVal = $dt->format('Y-m-d'); }
                                                    }
                                                }
                                                ?>
                                                <?= $this->Form->control('publish_start', [
                                                    'label' => false,
                                                    'type' => 'date',
                                                    'class' => 'form-control form-control-sm',
                                                    'value' => $startVal,
                                                ]) ?>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label small">Fin</label>
                                                <?php
                                                $endVal = '';
                                                if ($job->end_date instanceof \DateTimeInterface) {
                                                    $endVal = $job->end_date->format('Y-m-d');
                                                } else {
                                                    $raw = (string)$job->end_date;
                                                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
                                                        $endVal = $raw;
                                                    } elseif (strpos($raw, '/') !== false) {
                                                        $dt = \DateTime::createFromFormat('d/m/Y', $raw);
                                                        if ($dt) { $endVal = $dt->format('Y-m-d'); }
                                                    }
                                                }
                                                ?>
                                                <?= $this->Form->control('publish_end', [
                                                    'label' => false,
                                                    'type' => 'date',
                                                    'class' => 'form-control form-control-sm',
                                                    'value' => $endVal,
                                                ]) ?>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <?= $this->Form->button('<i class="bi bi-check2-circle"></i> Publier le brouillon', [
                                                'class' => 'btn btn-success btn-sm w-100',
                                                'escapeTitle' => false,
                                            ]) ?>
                                        </div>
                                        <small class="text-muted d-block mt-1">
                                            Les jours en échec seront exclus automatiquement.
                                        </small>
                                        <?= $this->Form->end() ?>
                                    </div>
                                    <div class="col-md-6">
                                        <?= $this->Form->postLink(
                                            '<i class="bi bi-trash"></i> Supprimer le brouillon',
                                            ['action' => 'clearDraft', (int)$job->id],
                                            [
                                                'class' => 'btn btn-outline-danger btn-sm w-100',
                                                'confirm' => 'Supprimer le brouillon ? Le planning publié ne sera pas modifié.',
                                                'escape' => false,
                                            ]
                                        ) ?>
                                        <small class="text-muted d-block mt-2">
                                            Supprime uniquement le brouillon, sans affecter le planning publié.
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Onglet Détail par jour -->
                    <div class="tab-pane fade" id="detail" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Statut</th>
                                        <th class="text-end">Durée</th>
                                        <th class="text-end">Segments</th>
                                        <th>Message</th>
                                        <th class="text-end">Diagnostics</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $passDefs = ['pass1' => 'P1', 'pass1_5' => 'P1.5', 'pass2' => 'P2']; ?>
                                    <?php foreach ($daysData as $dayData): ?>
                                        <?php
                                        $d = $dayData['day'];
                                        $st = (string)$d->status;
                                        $badge = 'secondary';
                                        $icon = 'clock';
                                        if ($st === 'ok') {
                                            $badge = 'success';
                                            $icon = 'check-circle';
                                        } elseif ($st === 'infeasible') {
                                            $badge = 'warning';
                                            $icon = 'exclamation-triangle';
                                        } elseif ($st === 'error') {
                                            $badge = 'danger';
                                            $icon = 'x-circle';
                                        } elseif ($st === 'running') {
                                            $badge = 'info';
                                            $icon = 'arrow-repeat';
                                        }

                                        $collapseId = 'diag_day_' . (int)$job->id . '_' . preg_replace('/[^0-9]/', '', (string)$d->date);
                                        $hasDiagnostics = $dayData['excluded_count'] > 0 || $dayData['warnings_count'] > 0;

                                        // Pastilles de statut par passe (P1 / P1.5 / P2)
                                        $passBadges = [];
                                        $passesReport = $dayData['report']['passes'] ?? [];
                                        $pass2Explanation = (isset($passesReport['pass2']['explanation']) && is_array($passesReport['pass2']['explanation']))
                                            ? $passesReport['pass2']['explanation']
                                            : null;
                                        $showPass2Explanation = is_array($pass2Explanation) && !empty($pass2Explanation['attempted']);
                                        foreach ($passDefs as $passKey => $passShort) {
                                            $passInfo = $passesReport[$passKey] ?? [];
                                            $passAttempted = (bool)($passInfo['attempted'] ?? false);
                                            $passStatus = $passInfo['status'] ?? null;
                                            $passError = $passInfo['error'] ?? null;
                                            $passLabel = $passInfo['label'] ?? $passShort;

                                            if (!$passAttempted) {
                                                $passColor = 'secondary';
                                                $passTooltip = $passKey === 'pass1_5'
                                                    ? 'Passe 1.5 : Non requise (calculée en début de semaine)'
                                                    : $passLabel . ' : non exécutée';
                                            } elseif (in_array($passStatus, ['FEASIBLE', 'OPTIMAL', 'success'], true)) {
                                                $passColor = 'success';
                                                $passTooltip = $passLabel . ' : OK';
                                            } elseif ($passStatus !== null && (str_contains((string)$passStatus, 'INFEASIBLE') || str_contains((string)$passStatus, 'INVALID'))) {
                                                $passColor = 'warning';
                                                $passTooltip = $passLabel . ' : ' . $passStatus . ($passError ? ' — ' . $passError : '');
                                            } elseif ($passStatus !== null && (str_contains((string)$passStatus, 'HTTP_') || $passStatus === 'EXCEPTION' || $passStatus === 'ERROR')) {
                                                $passColor = 'danger';
                                                $passTooltip = $passLabel . ' : erreur (' . $passStatus . ')' . ($passError ? ' — ' . $passError : '');
                                            } else {
                                                $passColor = 'secondary';
                                                $passTooltip = $passLabel . ' : ' . ($passStatus ?? 'statut inconnu');
                                            }

                                            $passBadges[] = [
                                                'short' => $passShort,
                                                'color' => $passColor,
                                                'tooltip' => $passTooltip,
                                            ];
                                        }
                                        ?>
                                        <tr>
                                            <td><strong><?= h((string)$d->date) ?></strong></td>
                                            <td>
                                                <span class="badge badge-<?= $badge ?>">
                                                    <i class="bi bi-<?= $icon ?>"></i> <?= h($st) ?>
                                                </span>
                                                <?php foreach ($passBadges as $pb): ?>
                                                    <span class="badge badge-<?= $pb['color'] ?> pass-badge"
                                                          data-toggle="tooltip"
                                                          data-placement="top"
                                                          title="<?= h($pb['tooltip']) ?>">
                                                        <?= h($pb['short']) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($d->duration_ms !== null): ?>
                                                    <?= number_format(((int)$d->duration_ms) / 1000, 2) ?> s
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($dayData['schedule_count'] > 0): ?>
                                                    <span class="badge badge-success"><?= number_format($dayData['schedule_count']) ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">0</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-muted small"><?= h((string)($d->error_message ?? '')) ?></td>
                                            <td class="text-end">
                                                <?php if ($hasDiagnostics): ?>
                                                    <button
                                                        class="btn btn-sm btn-outline-secondary"
                                                        type="button"
                                                        data-toggle="collapse"
                                                        data-target="#<?= h($collapseId) ?>"
                                                        aria-expanded="false"
                                                    >
                                                        <i class="bi bi-info-circle"></i>
                                                        <?php if ($dayData['excluded_count'] > 0): ?>
                                                            <span class="badge badge-danger"><?= $dayData['excluded_count'] ?></span>
                                                        <?php endif; ?>
                                                        <?php if ($dayData['warnings_count'] > 0): ?>
                                                            <span class="badge badge-warning"><?= $dayData['warnings_count'] ?></span>
                                                        <?php endif; ?>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <?= $this->Html->link(
                                                    '<i class="bi bi-eye"></i>',
                                                    ['action' => 'draft', (int)$job->id, '?' => ['date_start' => $d->date->format('d/m/Y'), 'date_end' => $d->date->format('d/m/Y')]],
                                                    ['class' => 'btn btn-sm btn-outline-primary', 'escape' => false, 'title' => 'Voir le brouillon']
                                                ) ?>
                                            </td>
                                        </tr>
                                        <?php if ($showPass2Explanation): ?>
                                            <tr>
                                                <td colspan="7" class="p-0 border-0">
                                                    <div class="alert alert-warning mb-2 mx-3 mt-2 py-2">
                                                        <strong><i class="bi bi-search"></i> Diagnostic d'infaisabilité</strong>
                                                        <?php
                                                        $explStatus = (string)($pass2Explanation['status'] ?? '');
                                                        ?>
                                                        <?php if ($explStatus === 'error' || $explStatus === 'timeout'): ?>
                                                            <p class="mb-0 mt-1">Explication indisponible</p>
                                                        <?php elseif ($explStatus === 'ok'): ?>
                                                            <?php
                                                            $messagesFr = $pass2Explanation['messages_fr'] ?? [];
                                                            $assumptionLabels = $pass2Explanation['assumption_labels'] ?? [];
                                                            if (!is_array($messagesFr)) {
                                                                $messagesFr = [];
                                                            }
                                                            if (!is_array($assumptionLabels)) {
                                                                $assumptionLabels = [];
                                                            }
                                                            ?>
                                                            <?php if (!empty($messagesFr)): ?>
                                                                <ul class="mb-1 mt-1">
                                                                    <?php foreach ($messagesFr as $msgFr): ?>
                                                                        <li><?= h((string)$msgFr) ?></li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            <?php else: ?>
                                                                <p class="mb-1 mt-1 text-muted">Aucun noyau d'assumptions identifié.</p>
                                                            <?php endif; ?>
                                                            <?php if (!empty($assumptionLabels)): ?>
                                                                <p class="mb-0 text-muted">
                                                                    <small><?= h(implode(', ', array_map('strval', $assumptionLabels))) ?></small>
                                                                </p>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <p class="mb-0 mt-1">Explication indisponible</p>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php if ($hasDiagnostics): ?>
                                            <tr>
                                                <td colspan="7" class="p-0">
                                                    <div class="collapse" id="<?= h($collapseId) ?>">
                                                        <div class="p-3 bg-light">
                                                            <div class="row" style="margin-left: -0.5rem; margin-right: -0.5rem;">
                                                                <?php if ($dayData['warnings_count'] > 0): ?>
                                                                    <div class="col-md-6">
                                                                        <div class="card border-warning">
                                                                            <div class="card-header bg-warning text-dark py-2">
                                                                                <strong><i class="bi bi-exclamation-triangle"></i> Warnings (<?= $dayData['warnings_count'] ?>)</strong>
                                                                            </div>
                                                                            <div class="card-body py-2" style="max-height: 200px; overflow-y: auto;">
                                                                                <ul class="mb-0 small">
                                                                                    <?php foreach (($dayData['diagnostics']['warnings'] ?? []) as $w): ?>
                                                                                        <?php
                                                                                        $msg = is_array($w) ? ($w['message'] ?? json_encode($w)) : (string)$w;
                                                                                        ?>
                                                                                        <li><?= h((string)$msg) ?></li>
                                                                                    <?php endforeach; ?>
                                                                                </ul>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <?php if ($dayData['excluded_count'] > 0): ?>
                                                                    <div class="col-md-6">
                                                                        <div class="card border-secondary">
                                                                            <div class="card-header bg-light py-2">
                                                                                <strong><i class="bi bi-person-x"></i> Agents exclus (<?= $dayData['excluded_count'] ?>)</strong>
                                                                            </div>
                                                                            <div class="card-body py-2" style="max-height: 200px; overflow-y: auto;">
                                                                                <ul class="mb-0 small">
                                                                                    <?php foreach (($dayData['diagnostics']['excluded_agents'] ?? []) as $ex): ?>
                                                                                        <?php
                                                                                        $line = '';
                                                                                        if (is_array($ex)) {
                                                                                            $name = (string)($ex['name'] ?? ('#' . (string)($ex['id'] ?? '')));
                                                                                            $reason = (string)($ex['reason'] ?? '');
                                                                                            $site = (string)($ex['site'] ?? '');
                                                                                            $line = trim($name . ($site !== '' ? ' (' . $site . ')' : '') . ' — ' . $reason);
                                                                                        } else {
                                                                                            $line = (string)$ex;
                                                                                        }
                                                                                        ?>
                                                                                        <li><?= h($line) ?></li>
                                                                                    <?php endforeach; ?>
                                                                                </ul>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Onglet Diagnostics -->
                    <div class="tab-pane fade" id="diagnostics" role="tabpanel">
                        <?php
                        $hasExcludedAgents = !empty($allExcludedAgents);
                        $hasWarnings = !empty($allWarnings);
                        $sectionsCount = ($hasExcludedAgents ? 1 : 0) + ($hasWarnings ? 1 : 0);
                        $useCollapse = $sectionsCount > 1; // Accordéons seulement s'il y a 2 sections
                        ?>
                        
                        <!-- Agents exclus -->
                        <?php if ($hasExcludedAgents): ?>
                            <div class="card mb-4">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center<?= $useCollapse ? ' cursor-pointer' : '' ?>"
                                     <?php if ($useCollapse): ?>
                                     data-toggle="collapse"
                                     data-target="#excluded-agents-collapse"
                                     aria-expanded="false"
                                     <?php endif; ?>>
                                    <h5 class="mb-0">
                                        <i class="bi bi-person-fill-dash"></i>
                                        Agents exclus (<?= count($allExcludedAgents) ?> agents, <?= $stats['total_excluded_agents'] ?> occurrences)
                                    </h5>
                                    <?php if ($useCollapse): ?>
                                        <i class="bi bi-chevron-down"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="<?= $useCollapse ? 'collapse' : '' ?>" id="excluded-agents-collapse">
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Agent</th>
                                                        <th>Site</th>
                                                        <th>Raison</th>
                                                        <th class="text-end">Occurrences</th>
                                                        <th>Dates concernées</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($topExcludedAgents as $agent): ?>
                                                        <tr>
                                                            <td><strong>#<?= $agent['id'] ?></strong> <?= h($agent['name']) ?></td>
                                                            <td><?= h($agent['site']) ?></td>
                                                            <td>
                                                                <span class="badge badge-danger"><?= h($agent['reason']) ?></span>
                                                            </td>
                                                            <td class="text-end">
                                                                <span class="badge badge-secondary"><?= $agent['count'] ?>x</span>
                                                            </td>
                                                            <td>
                                                                <small class="text-muted">
                                                                    <?= implode(', ', array_slice($agent['dates'], 0, 3)) ?>
                                                                    <?php if (count($agent['dates']) > 3): ?>
                                                                        <span class="text-muted">(+<?= count($agent['dates']) - 3 ?> autres)</span>
                                                                    <?php endif; ?>
                                                                </small>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Warnings -->
                        <?php if ($hasWarnings): ?>
                            <div class="card mb-4">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center<?= $useCollapse ? ' cursor-pointer' : '' ?>"
                                     <?php if ($useCollapse): ?>
                                     data-toggle="collapse"
                                     data-target="#warnings-collapse"
                                     aria-expanded="false"
                                     <?php endif; ?>>
                                    <h5 class="mb-0">
                                        <i class="bi bi-exclamation-triangle"></i>
                                        Warnings (<?= count($allWarnings) ?> types, <?= $stats['total_warnings'] ?> occurrences)
                                    </h5>
                                    <?php if ($useCollapse): ?>
                                        <i class="bi bi-chevron-down"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="<?= $useCollapse ? 'collapse' : '' ?>" id="warnings-collapse">
                                    <div class="card-body">
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
                                                            <td class="text-end">
                                                                <span class="badge badge-warning"><?= $warning['count'] ?>x</span>
                                                            </td>
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
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($allExcludedAgents) && empty($allWarnings)): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle"></i> Aucun problème détecté dans les diagnostics.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Onglet Performance -->
                    <div class="tab-pane fade" id="performance" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="bi bi-graph-up"></i> Évolution de la durée</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($durationData)): ?>
                                            <div class="duration-chart-container" style="min-height: 200px;">
                                                <?php
                                                $maxDuration = max(array_column($durationData, 'duration'));
                                                $maxDuration = $maxDuration > 0 ? $maxDuration : 1; // Éviter division par zéro
                                                ?>
                                                <div class="d-flex flex-column" style="gap: 0.5rem;">
                                                    <?php foreach ($durationData as $item): ?>
                                                        <?php
                                                        $percentage = ($item['duration'] / $maxDuration) * 100;
                                                        // Utiliser les fonctions natives CakePHP pour formater la date
                                                        $dateObj = $item['date'] ?? null;
                                                        if ($dateObj instanceof \Cake\I18n\FrozenDate || $dateObj instanceof \Cake\I18n\FrozenTime) {
                                                            $dateFormatted = $dateObj->i18nFormat('dd/MM/yyyy');
                                                            $dateFull = $dateObj->i18nFormat('dd/MM/yyyy');
                                                        } elseif ($dateObj instanceof \DateTimeInterface) {
                                                            $dateFormatted = $dateObj->format('d/m/Y');
                                                            $dateFull = $dateFormatted;
                                                        } elseif (is_string($dateObj)) {
                                                            // Fallback : parser manuellement si c'est une string
                                                            try {
                                                                $tempDate = new \Cake\I18n\FrozenDate($dateObj);
                                                                $dateFormatted = $tempDate->i18nFormat('dd/MM/yyyy');
                                                                $dateFull = $dateFormatted;
                                                            } catch (\Exception $e) {
                                                                // Si échec, utiliser regex
                                                                if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateObj, $matches)) {
                                                                    $dateFormatted = $matches[3] . '/' . $matches[2] . '/' . $matches[1];
                                                                    $dateFull = $dateFormatted;
                                                                } else {
                                                                    $dateFormatted = $dateObj;
                                                                    $dateFull = $dateFormatted;
                                                                }
                                                            }
                                                        } else {
                                                            $dateFormatted = '?';
                                                            $dateFull = '?';
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
                                                                         aria-valuenow="<?= $percentage ?>" 
                                                                         aria-valuemin="0" 
                                                                         aria-valuemax="100"
                                                                         data-toggle="tooltip"
                                                                         data-placement="top"
                                                                         title="<?= h($dateFull) ?> : <?= number_format($item['duration'], 2) ?>s">
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
                                                        <i class="bi bi-info-circle"></i> 
                                                        Durée maximale : <?= number_format($maxDuration, 2) ?>s
                                                    </small>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted mb-0">
                                                <i class="bi bi-info-circle"></i> 
                                                Aucune donnée de durée disponible. Les durées sont enregistrées uniquement pour les jours traités.
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="bi bi-bar-chart"></i> Statistiques de performance</h6>
                                    </div>
                                    <div class="card-body">
                                        <dl class="row mb-0">
                                            <dt class="col-sm-6">Durée totale</dt>
                                            <dd class="col-sm-6"><?= number_format($stats['total_duration_ms'] / 1000, 2) ?>s</dd>
                                            
                                            <dt class="col-sm-6">Durée moyenne</dt>
                                            <dd class="col-sm-6"><?= $stats['avg_duration_ms'] > 0 ? number_format($stats['avg_duration_ms'] / 1000, 2) : '—' ?>s</dd>
                                            
                                            <dt class="col-sm-6">Durée min</dt>
                                            <dd class="col-sm-6">
                                                <?php
                                                $durations = array_column($durationData, 'duration');
                                                echo !empty($durations) ? number_format(min($durations), 2) . 's' : '—';
                                                ?>
                                            </dd>
                                            
                                            <dt class="col-sm-6">Durée max</dt>
                                            <dd class="col-sm-6">
                                                <?php
                                                echo !empty($durations) ? number_format(max($durations), 2) . 's' : '—';
                                                ?>
                                            </dd>
                                            
                                            <dt class="col-sm-6">Segments par jour OK</dt>
                                            <dd class="col-sm-6">
                                                <?= $stats['days_ok'] > 0 ? number_format($stats['total_segments'] / $stats['days_ok'], 0) : '—' ?>
                                            </dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $this->Html->scriptStart(['block' => true]); ?>
// Initialiser les tooltips Bootstrap 4
$(function () {
    $('[data-toggle="tooltip"]').tooltip();
});

// Les tooltips sont déjà initialisés plus haut
<?php $this->Html->scriptEnd(); ?>
