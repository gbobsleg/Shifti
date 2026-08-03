<?php
/**
 * Onglet Qualité : KPI, timeline, détail par jour, équité.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\PlanningGenerationJob $job
 * @var array $stats
 * @var float $successRate
 * @var int $healthScore
 * @var array $statusTimeline
 * @var array $daysData
 * @var string $workspaceSection
 */
$stats = $stats ?? [
    'total_days' => 0,
    'days_ok' => 0,
    'days_infeasible' => 0,
    'days_error' => 0,
    'days_queued' => 0,
    'days_running' => 0,
];
$successRate = $successRate ?? 0;
$healthScore = $healthScore ?? 0;
$statusTimeline = $statusTimeline ?? [];
$daysData = $daysData ?? [];
$equityAvailable = $equityAvailable ?? false;
$rows = $rows ?? [];
$okDates = $okDates ?? [];
$minutesPerDay = $minutesPerDay ?? 0;
$theoreticalMinutesTotal = $theoreticalMinutesTotal ?? 0;
$equityGroupsColumns = $equityGroupsColumns ?? [];
$sitesList = $sitesList ?? [];
$usersList = $usersList ?? [];
$filterSiteId = $filterSiteId ?? 0;
$filterUserId = $filterUserId ?? 0;
$filterEquityGroup = $filterEquityGroup ?? '';

if (!function_exists('fmtMinutesWs')) {
    function fmtMinutesWs(int $m): string
    {
        $h = intdiv($m, 60);
        $min = $m % 60;
        return sprintf('%dh%02d', $h, $min);
    }
}
if (!function_exists('fmtPctWs')) {
    function fmtPctWs(?float $pct): string
    {
        if ($pct === null) {
            return '—';
        }
        return number_format($pct, 1, ',', ' ') . ' %';
    }
}

$healthColor = 'success';
if ($healthScore < 50) {
    $healthColor = 'danger';
} elseif ($healthScore < 75) {
    $healthColor = 'warning';
}
?>

<!-- KPI -->
<div class="row mb-3">
    <div class="col-md-4">
        <div class="card shadow kpi-card border-left-success">
            <div class="card-body">
                <div class="kpi-value text-success"><?= number_format((int)$stats['days_ok']) ?></div>
                <div class="kpi-label">Jours OK</div>
                <small class="text-muted">
                    <?= ($stats['total_days'] ?? 0) > 0 ? number_format(($stats['days_ok'] / $stats['total_days']) * 100, 1) : 0 ?>% de réussite
                </small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow kpi-card border-left-info">
            <div class="card-body">
                <div class="kpi-value text-info"><?= number_format((float)$successRate, 1) ?>%</div>
                <div class="kpi-label">Taux de succès</div>
                <small class="text-muted"><?= (int)$stats['days_ok'] ?> / <?= (int)$stats['total_days'] ?> jours</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow kpi-card border-left-primary">
            <div class="card-body">
                <div class="kpi-value text-<?= $healthColor ?>"><?= (int)$healthScore ?><small style="font-size:1.2rem;">/100</small></div>
                <div class="kpi-label">Score de santé</div>
                <div class="progress mt-2" style="height: 6px;">
                    <div class="progress-bar bg-<?= $healthColor ?>" style="width: <?= (int)$healthScore ?>%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Timeline -->
<div class="card shadow mb-3">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="bi bi-timeline"></i> Timeline des statuts</h5>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap mb-2" style="min-height: 40px; gap: 0.25rem;">
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
                      title="<?= h($item['date']) ?> : <?= h($title) ?>"></span>
            <?php endforeach; ?>
        </div>
        <div class="d-flex justify-content-between text-muted small">
            <span><?= h((string)$job->start_date) ?></span>
            <span><?= h((string)$job->end_date) ?></span>
        </div>
        <div class="d-flex flex-wrap mt-3" style="gap: 1rem;">
            <span><i class="bi bi-circle-fill text-success"></i> OK : <?= (int)$stats['days_ok'] ?></span>
            <?php if (($stats['days_infeasible'] ?? 0) > 0): ?>
                <span><i class="bi bi-circle-fill text-warning"></i> Infaisable : <?= (int)$stats['days_infeasible'] ?></span>
            <?php endif; ?>
            <?php if (($stats['days_error'] ?? 0) > 0): ?>
                <span><i class="bi bi-circle-fill text-danger"></i> Erreur : <?= (int)$stats['days_error'] ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$complianceFixed = $complianceFixed ?? [];
$complianceRotation = $complianceRotation ?? [];
$complianceSummary = $complianceSummary ?? [
    'fixed_ok' => 0,
    'fixed_ko' => 0,
    'fixed_total' => 0,
    'rotation_ok' => 0,
    'rotation_ko' => 0,
    'rotation_total' => 0,
    'ko_total' => 0,
];
$complianceKo = (int)($complianceSummary['ko_total'] ?? 0);
$complianceHasData = ((int)$complianceSummary['fixed_total'] + (int)$complianceSummary['rotation_total']) > 0;
$complianceOpen = $complianceKo > 0;
$statusLabel = static function (string $status): array {
    return match ($status) {
        'manque' => ['Manque', 'danger'],
        'excedent' => ['Excédent', 'warning'],
        default => ['OK', 'success'],
    };
};
?>
<!-- Conformité pré-publication -->
<div class="card shadow mb-3" id="compliance-panel" data-compliance-ko="<?= $complianceKo ?>">
    <div class="card-header bg-light d-flex justify-content-between align-items-center cursor-pointer"
         data-toggle="collapse"
         data-target="#ws-quality-compliance"
         aria-expanded="<?= $complianceOpen ? 'true' : 'false' ?>"
         aria-controls="ws-quality-compliance"
         role="button">
        <h5 class="mb-0">
            <i class="bi bi-shield-check"></i> Conformité
            <?php if ($complianceKo > 0): ?>
                <span class="badge badge-danger badge-count ml-1"><?= $complianceKo ?></span>
            <?php elseif ($complianceHasData): ?>
                <span class="badge badge-success badge-count ml-1">OK</span>
            <?php endif; ?>
        </h5>
        <i class="bi bi-chevron-down"></i>
    </div>
    <div class="collapse<?= $complianceOpen ? ' show' : '' ?>" id="ws-quality-compliance">
        <div class="card-body">
            <?php if (!$complianceHasData): ?>
                <div class="alert alert-secondary mb-0">
                    <i class="bi bi-info-circle"></i>
                    Aucune règle à contrôler (pas de jour OK, ou aucune activité fixe / rotation applicable).
                </div>
            <?php else: ?>
                <div class="d-flex flex-wrap mb-3" style="gap: 0.75rem;">
                    <?php
                    $fixedKo = (int)$complianceSummary['fixed_ko'];
                    $fixedTotal = (int)$complianceSummary['fixed_total'];
                    $fixedOk = (int)$complianceSummary['fixed_ok'];
                    $rotKo = (int)$complianceSummary['rotation_ko'];
                    $rotTotal = (int)$complianceSummary['rotation_total'];
                    $rotOk = (int)$complianceSummary['rotation_ok'];
                    ?>
                    <span class="badge badge-<?= $fixedKo > 0 ? 'danger' : 'success' ?> p-2">
                        Fixes : <?= $fixedOk ?>/<?= $fixedTotal ?> OK
                    </span>
                    <span class="badge badge-<?= $rotKo > 0 ? 'danger' : 'success' ?> p-2">
                        Rotations : <?= $rotOk ?>/<?= $rotTotal ?> OK
                    </span>
                </div>
                <p class="text-muted small mb-3">
                    <strong>Couverture</strong> (fixes) = nombre d’agents présents sur la plage horaire demandée.
                    <strong>Plage</strong> (rotations) = bloc contigu sur l’offre, durée ≥ durée de shift.
                </p>

                <?php
                $fixedByDate = [];
                foreach ($complianceFixed as $row) {
                    $dk = (string)($row['date'] ?? '');
                    if ($dk === '') {
                        continue;
                    }
                    $fixedByDate[$dk][] = $row;
                }
                ksort($fixedByDate);
                ?>
                <!-- Fixes -->
                <div class="card mb-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center cursor-pointer"
                         data-toggle="collapse" data-target="#compliance-fixed-body" aria-expanded="false" role="button">
                        <h6 class="mb-0">
                            <i class="bi bi-geo-alt"></i> Activités fixes
                            <?php if ($fixedKo > 0): ?>
                                <span class="badge badge-danger ml-1"><?= $fixedKo ?> manque(s)</span>
                            <?php endif; ?>
                        </h6>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="collapse" id="compliance-fixed-body">
                        <div class="card-body">
                            <?php if ($fixedTotal === 0): ?>
                                <p class="text-muted mb-0">Aucune activité fixe à contrôler sur les jours OK.</p>
                            <?php else: ?>
                                <div class="compliance-fixed-filter-wrap">
                                <input type="radio" name="compliance-fixed-filter" id="cff-all" class="compliance-filter-radio" value="all" checked>
                                <input type="radio" name="compliance-fixed-filter" id="cff-ko" class="compliance-filter-radio" value="ko">
                                <div class="mb-3 compliance-fixed-filters">
                                    <label for="cff-all" class="btn btn-sm btn-outline-secondary mb-0">Tous</label>
                                    <label for="cff-ko" class="btn btn-sm btn-outline-danger mb-0">Manques / excédents</label>
                                </div>
                                <div id="compliance-fixed-groups" class="compliance-fixed-groups">
                                    <?php foreach ($fixedByDate as $dateKey => $dateRows): ?>
                                        <?php
                                        $dateKo = 0;
                                        foreach ($dateRows as $r) {
                                            if (($r['status'] ?? '') !== 'ok') {
                                                $dateKo++;
                                            }
                                        }
                                        $dateCollapseId = 'compliance-fixed-date-' . preg_replace('/\D/', '', $dateKey);
                                        try {
                                            $dateLabel = (new \DateTimeImmutable($dateKey))->format('d/m/Y');
                                        } catch (\Throwable $e) {
                                            $dateLabel = $dateKey;
                                        }
                                        ?>
                                        <div class="compliance-fixed-date-group mb-2 border rounded"
                                             data-date-ko="<?= (int)$dateKo ?>">
                                            <div class="d-flex justify-content-between align-items-center px-3 py-2 bg-light cursor-pointer"
                                                 data-toggle="collapse"
                                                 data-target="#<?= h($dateCollapseId) ?>"
                                                 aria-expanded="false"
                                                 role="button">
                                                <strong>
                                                    <i class="bi bi-calendar-event"></i>
                                                    <?= h($dateLabel) ?>
                                                    <span class="text-muted font-weight-normal ml-1">(<?= count($dateRows) ?>)</span>
                                                    <?php if ($dateKo > 0): ?>
                                                        <span class="badge badge-danger ml-1"><?= $dateKo ?></span>
                                                    <?php endif; ?>
                                                </strong>
                                                <i class="bi bi-chevron-down"></i>
                                            </div>
                                            <div class="collapse" id="<?= h($dateCollapseId) ?>">
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-hover align-middle mb-0 compliance-fixed-table">
                                                        <thead class="thead-light">
                                                            <tr>
                                                                <th>Site</th>
                                                                <th>Offre</th>
                                                                <th>Fenêtre</th>
                                                                <th class="text-end">Requis</th>
                                                                <th class="text-end">Réel</th>
                                                                <th>Statut</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($dateRows as $row): ?>
                                                                <?php [$label, $badge] = $statusLabel((string)$row['status']); ?>
                                                                <tr data-compliance-status="<?= h((string)$row['status']) ?>">
                                                                    <td><?= h((string)$row['site']) ?></td>
                                                                    <td><?= h((string)$row['offer']) ?></td>
                                                                    <td><?= h((string)$row['window_label']) ?></td>
                                                                    <td class="text-end"><?= (int)$row['required'] ?></td>
                                                                    <td class="text-end"><?= (int)$row['actual'] ?></td>
                                                                    <td><span class="badge badge-<?= $badge ?>"><?= h($label) ?></span></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                </div><!-- /.compliance-fixed-filter-wrap -->
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Rotations -->
                <div class="card mb-0">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center cursor-pointer"
                         data-toggle="collapse" data-target="#compliance-rotation-body" aria-expanded="false" role="button">
                        <h6 class="mb-0">
                            <i class="bi bi-arrow-repeat"></i> Rotations
                            <?php if ($rotKo > 0): ?>
                                <span class="badge badge-danger ml-1"><?= $rotKo ?> écart(s)</span>
                            <?php endif; ?>
                        </h6>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="collapse" id="compliance-rotation-body">
                        <div class="card-body">
                            <?php if ($rotTotal === 0): ?>
                                <p class="text-muted mb-0">Aucune rotation à contrôler pour les agents du brouillon.</p>
                            <?php else: ?>
                                <div class="compliance-rotation-filter-wrap">
                                <input type="radio" name="compliance-rotation-filter" id="crf-all" class="compliance-filter-radio" value="all" checked>
                                <input type="radio" name="compliance-rotation-filter" id="crf-ko" class="compliance-filter-radio" value="ko">
                                <div class="mb-2 compliance-rotation-filters">
                                    <label for="crf-all" class="btn btn-sm btn-outline-secondary mb-0">Tous</label>
                                    <label for="crf-ko" class="btn btn-sm btn-outline-danger mb-0">Manques / excédents</label>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle" id="compliance-rotation-table">
                                        <thead class="thead-light">
                                            <tr>
                                                <th style="width: 2rem;"></th>
                                                <th>Agent</th>
                                                <th>Règle</th>
                                                <th>Période</th>
                                                <th>Cible</th>
                                                <th class="text-end">Cible proratisée</th>
                                                <th class="text-end">Réel</th>
                                                <th>Statut</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($complianceRotation as $row): ?>
                                                <?php
                                                [$label, $badge] = $statusLabel((string)$row['status']);
                                                $collapseId = 'compliance-rot-' . (int)$row['user_id'] . '-' . substr(md5((string)$row['rule_id']), 0, 8);
                                                $targetLabel = (string)($row['target_label'] ?? ((int)($row['required'] ?? 0) . ' plages'));
                                                ?>
                                                <tr class="compliance-rotation-row" data-compliance-status="<?= h((string)$row['status']) ?>">
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-link p-0"
                                                                data-toggle="collapse" data-target="#<?= h($collapseId) ?>"
                                                                aria-expanded="false" title="Voir les plages">
                                                            <i class="bi bi-chevron-down"></i>
                                                        </button>
                                                    </td>
                                                    <td><strong>#<?= (int)$row['user_id'] ?></strong> <?= h((string)$row['name']) ?></td>
                                                    <td>
                                                        <?= h((string)$row['rule_name']) ?>
                                                        <?php if (!empty($row['offer'])): ?>
                                                            <small class="text-muted d-block"><?= h((string)$row['offer']) ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><small><?= h((string)$row['period_label']) ?></small></td>
                                                    <td><small><?= h($targetLabel) ?></small></td>
                                                    <td class="text-end"><?= (int)$row['required'] ?></td>
                                                    <td class="text-end"><?= (int)$row['actual'] ?></td>
                                                    <td><span class="badge badge-<?= $badge ?>"><?= h($label) ?></span></td>
                                                </tr>
                                                <tr class="compliance-rotation-detail" data-compliance-status="<?= h((string)$row['status']) ?>">
                                                    <td colspan="8" class="p-0 border-0">
                                                        <div class="collapse" id="<?= h($collapseId) ?>">
                                                            <div class="p-3 bg-light border-bottom">
                                                                <?php if (empty($row['plages'])): ?>
                                                                    <span class="text-muted">Aucune plage trouvée sur la période.</span>
                                                                <?php else: ?>
                                                                    <table class="table table-sm mb-0">
                                                                        <thead>
                                                                            <tr>
                                                                                <th>Date</th>
                                                                                <th>Début</th>
                                                                                <th>Fin</th>
                                                                                <th class="text-end">Durée</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            <?php foreach ($row['plages'] as $plage): ?>
                                                                                <tr>
                                                                                    <td><?= h((string)$plage['date']) ?></td>
                                                                                    <td><?= h((string)$plage['start']) ?></td>
                                                                                    <td><?= h((string)$plage['end']) ?></td>
                                                                                    <td class="text-end"><?= (int)$plage['duration_min'] ?> min</td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        </tbody>
                                                                    </table>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <p class="text-muted small mt-2 mb-0">
                                    Statut = réel vs <strong>cible proratisée</strong> (somme des cibles hebdomadaires après absences, comme la génération).
                                    <strong>Cible</strong> = règle ou override × nombre de semaines du job.
                                </p>
                                </div><!-- /.compliance-rotation-filter-wrap -->
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Détail par jour (replié par défaut) -->
<div class="card shadow mb-3">
    <div class="card-header bg-light d-flex justify-content-between align-items-center cursor-pointer"
         data-toggle="collapse"
         data-target="#ws-quality-days"
         aria-expanded="false"
         aria-controls="ws-quality-days"
         role="button">
        <h5 class="mb-0">
            <i class="bi bi-calendar3"></i> Détail par jour
            <?php if (($stats['days_infeasible'] ?? 0) > 0 || ($stats['days_error'] ?? 0) > 0): ?>
                <span class="badge badge-danger badge-count ml-1">
                    <?= (int)$stats['days_infeasible'] + (int)$stats['days_error'] ?>
                </span>
            <?php endif; ?>
        </h5>
        <i class="bi bi-chevron-down"></i>
    </div>
    <div class="collapse" id="ws-quality-days">
    <div class="card-body">
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
                        $hasDiagnostics = ($dayData['excluded_count'] ?? 0) > 0 || ($dayData['warnings_count'] ?? 0) > 0;

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

                        $dayDateStr = $d->date instanceof \DateTimeInterface
                            ? $d->date->format('d/m/Y')
                            : (string)$d->date;
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
                                <?php if (($dayData['schedule_count'] ?? 0) > 0): ?>
                                    <span class="badge badge-success"><?= number_format((int)$dayData['schedule_count']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?= h((string)($d->error_message ?? '')) ?></td>
                            <td class="text-end">
                                <?php if ($hasDiagnostics): ?>
                                    <button class="btn btn-sm btn-outline-secondary" type="button"
                                            data-toggle="collapse" data-target="#<?= h($collapseId) ?>">
                                        <i class="bi bi-info-circle"></i>
                                        <?php if (($dayData['excluded_count'] ?? 0) > 0): ?>
                                            <span class="badge badge-danger"><?= (int)$dayData['excluded_count'] ?></span>
                                        <?php endif; ?>
                                        <?php if (($dayData['warnings_count'] ?? 0) > 0): ?>
                                            <span class="badge badge-warning"><?= (int)$dayData['warnings_count'] ?></span>
                                        <?php endif; ?>
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?= $this->Html->link(
                                    '<i class="bi bi-eye"></i>',
                                    [
                                        'action' => 'view',
                                        (int)$job->id,
                                        '?' => [
                                            'tab' => 'planning',
                                            'date_start' => $dayDateStr,
                                            'date_end' => $dayDateStr,
                                        ],
                                    ],
                                    ['class' => 'btn btn-sm btn-outline-primary', 'escape' => false, 'title' => 'Voir le brouillon']
                                ) ?>
                            </td>
                        </tr>
                        <?php if ($showPass2Explanation): ?>
                            <tr>
                                <td colspan="7" class="p-0 border-0">
                                    <div class="alert alert-warning mb-2 mx-3 mt-2 py-2">
                                        <strong><i class="bi bi-search"></i> Diagnostic d'infaisabilité</strong>
                                        <?php $explStatus = (string)($pass2Explanation['status'] ?? ''); ?>
                                        <?php if ($explStatus === 'ok'): ?>
                                            <?php
                                            $messagesFr = is_array($pass2Explanation['messages_fr'] ?? null) ? $pass2Explanation['messages_fr'] : [];
                                            $assumptionLabels = is_array($pass2Explanation['assumption_labels'] ?? null) ? $pass2Explanation['assumption_labels'] : [];
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
                                            <div class="row">
                                                <?php if (($dayData['warnings_count'] ?? 0) > 0): ?>
                                                    <div class="col-md-6">
                                                        <div class="card border-warning mb-2">
                                                            <div class="card-header bg-warning text-dark py-2">
                                                                <strong>Warnings (<?= (int)$dayData['warnings_count'] ?>)</strong>
                                                            </div>
                                                            <div class="card-body py-2" style="max-height: 200px; overflow-y: auto;">
                                                                <ul class="mb-0 small">
                                                                    <?php foreach (($dayData['diagnostics']['warnings'] ?? []) as $w): ?>
                                                                        <?php $msg = is_array($w) ? ($w['message'] ?? json_encode($w)) : (string)$w; ?>
                                                                        <li><?= h((string)$msg) ?></li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (($dayData['excluded_count'] ?? 0) > 0): ?>
                                                    <div class="col-md-6">
                                                        <div class="card border-secondary mb-2">
                                                            <div class="card-header bg-light py-2">
                                                                <strong>Agents exclus (<?= (int)$dayData['excluded_count'] ?>)</strong>
                                                            </div>
                                                            <div class="card-body py-2" style="max-height: 200px; overflow-y: auto;">
                                                                <ul class="mb-0 small">
                                                                    <?php foreach (($dayData['diagnostics']['excluded_agents'] ?? []) as $ex): ?>
                                                                        <?php
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
                    <?php if (empty($daysData)): ?>
                        <tr>
                            <td colspan="7" class="text-muted text-center">Aucun jour à afficher.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    </div><!-- /#ws-quality-days -->
</div>

<?php
$equityOpen = (($workspaceSection ?? '') === 'equity');
?>
<!-- Section équité (repliée par défaut, ouverte si section=equity) -->
<div class="card shadow mb-3" id="equity">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center cursor-pointer"
         data-toggle="collapse"
         data-target="#ws-quality-equity"
         aria-expanded="<?= $equityOpen ? 'true' : 'false' ?>"
         aria-controls="ws-quality-equity"
         role="button">
        <h5 class="mb-0"><i class="bi bi-graph-up"></i> Équité</h5>
        <i class="bi bi-chevron-down"></i>
    </div>
    <div class="collapse<?= $equityOpen ? ' show' : '' ?>" id="ws-quality-equity">
    <div class="card-body">
        <?php if (!$equityAvailable): ?>
            <div class="alert alert-warning mb-0">
                <i class="bi bi-exclamation-triangle"></i>
                Rapport d'équité indisponible (aucun jour OK, aucun brouillon, ou filtres trop restrictifs).
            </div>
        <?php else: ?>
            <div class="mb-3">
                <div><strong>Jours pris en compte :</strong> <?= count($okDates) ?> (jours OK uniquement)</div>
                <div><strong>Temps théorique / jour :</strong> <?= fmtMinutesWs((int)$minutesPerDay) ?></div>
                <div><strong>Temps théorique total :</strong> <?= fmtMinutesWs((int)$theoreticalMinutesTotal) ?></div>
            </div>

            <?php
            $equityGroupOptions = ['' => 'Toutes'];
            foreach ($equityGroupsColumns as $col) {
                $equityGroupOptions[$col['key']] = $col['label'];
            }
            ?>
            <?= $this->Form->create(null, [
                'type' => 'get',
                'url' => ['action' => 'view', (int)$job->id],
                'class' => 'mb-3',
            ]) ?>
            <?= $this->Form->hidden('tab', ['value' => 'qualite']) ?>
            <?= $this->Form->hidden('section', ['value' => 'equity']) ?>
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Site</label>
                    <?= $this->Form->select('site_id', $sitesList, [
                        'empty' => 'Tous',
                        'class' => 'form-control',
                        'value' => $filterSiteId > 0 ? $filterSiteId : '',
                    ]) ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Agent</label>
                    <?= $this->Form->select('user_id', $usersList, [
                        'empty' => 'Tous',
                        'class' => 'form-control',
                        'value' => $filterUserId > 0 ? $filterUserId : '',
                    ]) ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Offre planifiée</label>
                    <?= $this->Form->select('equity_group', $equityGroupOptions, [
                        'class' => 'form-control',
                        'value' => $filterEquityGroup,
                    ]) ?>
                </div>
                <div class="col-md-3 d-flex">
                    <?= $this->Form->button('<i class="bi bi-funnel"></i> Filtrer', [
                        'class' => 'btn btn-primary w-100 align-self-end',
                        'escapeTitle' => false,
                    ]) ?>
                </div>
            </div>
            <?= $this->Form->end() ?>

            <div class="mb-2 d-flex justify-content-end">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="equity-copy-csv" title="Copie le tableau au format CSV">
                    <i class="bi bi-clipboard"></i> Copier en CSV
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover align-middle equity-report-table" id="equity-report-table">
                    <thead>
                        <tr>
                            <th>Id</th>
                            <th>Agent</th>
                            <th class="text-end">Absences</th>
                            <th class="text-end">Télétravail</th>
                            <th class="text-end">Pause</th>
                            <th class="text-end">Repas</th>
                            <th class="text-end">Work total</th>
                            <th class="text-end"><span class="pct-dispo">% disponible</span></th>
                            <?php foreach ($equityGroupsColumns as $col): ?>
                                <th class="text-end equity-col" title="<?= h($col['label']) ?>"><?= h($col['label']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $theo = (int)$r['theoretical_minutes'];
                            $avail = (int)$r['available_minutes'];
                            $workTotal = (int)$r['work_minutes_total'];
                            $pctTheo = $theo > 0 ? ($workTotal * 100.0 / $theo) : null;
                            $pctAvail = $avail > 0 ? ($workTotal * 100.0 / $avail) : null;
                            $groupMinutes = (array)($r['group_minutes'] ?? []);
                            ?>
                            <tr>
                                <td><?= (int)$r['user_id'] ?></td>
                                <td><strong><?= h((string)$r['name']) ?></strong></td>
                                <td class="text-end"><?= fmtMinutesWs((int)($r['absence_minutes'] ?? 0)) ?></td>
                                <td class="text-end"><?= fmtMinutesWs((int)($r['remote_minutes'] ?? 0)) ?></td>
                                <td class="text-end"><?= fmtMinutesWs((int)($r['pause_minutes'] ?? 0)) ?></td>
                                <td class="text-end"><?= fmtMinutesWs((int)($r['lunch_minutes'] ?? 0)) ?></td>
                                <td class="text-end"><?= fmtMinutesWs($workTotal) ?></td>
                                <?php
                                $pctDispoTooltip = '% disponible : ' . fmtPctWs($pctAvail) . ' | % théorique : ' . fmtPctWs($pctTheo);
                                $pctDispoCsv = '% disponible : ' . fmtPctWs($pctAvail) . ' ; % théorique : ' . fmtPctWs($pctTheo);
                                ?>
                                <td class="text-end" title="<?= h($pctDispoTooltip) ?>" data-csv-content="<?= h($pctDispoCsv) ?>">
                                    <span class="pct-dispo"><?= fmtPctWs($pctAvail) ?></span>
                                </td>
                                <?php foreach ($equityGroupsColumns as $col): ?>
                                    <?php
                                    $m = (int)($groupMinutes[$col['key']] ?? 0);
                                    $pctTheoOff = $theo > 0 && $m > 0 ? ($m * 100.0 / $theo) : null;
                                    $pctAvailOff = $avail > 0 && $m > 0 ? ($m * 100.0 / $avail) : null;
                                    $tooltip = $m > 0 ? ('% disponible : ' . fmtPctWs($pctAvailOff) . ' | Durée : ' . fmtMinutesWs($m) . ' | % théorique : ' . fmtPctWs($pctTheoOff)) : '';
                                    $csvContent = $m > 0 ? ('% disponible : ' . fmtPctWs($pctAvailOff) . ' ; Durée : ' . fmtMinutesWs($m) . ' ; % théorique : ' . fmtPctWs($pctTheoOff)) : '—';
                                    ?>
                                    <td class="text-end" <?= $m > 0 ? ' title="' . h($tooltip) . '" data-csv-content="' . h($csvContent) . '"' : '' ?>>
                                        <?= $m > 0 ? '<span class="pct-dispo">' . fmtPctWs($pctAvailOff) . '</span>' : '—' ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="alert alert-info mb-0 mt-3">
                Lecture : une répartition « équitable » implique des <strong>% disponible</strong> proches entre agents pour une même activité ou groupe d’activités couplées.
            </div>
        <?php endif; ?>
    </div>
    </div><!-- /#ws-quality-equity -->
</div>
