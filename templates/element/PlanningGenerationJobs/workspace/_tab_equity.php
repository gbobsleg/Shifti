<?php
/**
 * Onglet Équité : répartition équitable des activités entre agents.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\PlanningGenerationJob $job
 */
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
$offersById = $offersById ?? [];

if (!function_exists('fmtMinutesWs')) {
    function fmtMinutesWs(int $m): string
    {
        $h = intdiv($m, 60);
        $min = $m % 60;
        return sprintf('%dh%02d', $h, $min);
    }
}

$equityHeatClass = function (float $gapMin, float $targetMin): string {
    if ($targetMin <= 0) {
        return 'text-muted';
    }
    $ratio = abs($gapMin) / $targetMin;
    if ($ratio <= 0.10) {
        return 'text-success';
    }
    if ($gapMin < 0) {
        // Déficit (en dessous de la cible)
        return $ratio <= 0.25 ? 'text-warning' : 'text-danger';
    }
    // Excédent (au-dessus de la cible)
    return $ratio <= 0.25 ? 'text-info' : 'text-primary';
};
?>

<?php if (!$equityAvailable): ?>
    <div class="alert alert-warning mb-0">
        <i class="bi bi-exclamation-triangle"></i>
        Rapport d'équité indisponible (aucun jour OK, aucun brouillon, ou filtres trop restrictifs).
    </div>
<?php else: ?>
    <div class="mb-3 small text-muted">
        <span class="mr-3"><strong>Jours pris en compte :</strong> <?= count($okDates) ?> (jours OK uniquement)</span>
        <span class="mr-3"><strong>Temps théorique / jour :</strong> <?= fmtMinutesWs((int)$minutesPerDay) ?></span>
        <span><strong>Temps théorique total :</strong> <?= fmtMinutesWs((int)$theoreticalMinutesTotal) ?></span>
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
    <?= $this->Form->hidden('tab', ['value' => 'equite']) ?>
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

    <?php
    $equityColDefs = [
        'id' => 'Id',
        'agent' => 'Agent',
        'contrat' => 'Contrat',
        'absences' => 'Absences',
        'planifie_net' => 'Planifié net',
        'teletravail' => 'Télétravail',
        'pause' => 'Pause',
        'repas' => 'Repas',
    ];
    foreach ($equityGroupsColumns as $col) {
        $equityColDefs['equity:' . $col['key']] = $col['label'];
    }
    $equityColsHiddenByDefault = ['teletravail', 'pause', 'repas'];
    ?>
    <div class="mb-2 d-flex justify-content-end align-items-center">
        <div class="dropdown mr-2">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button"
                    id="equity-cols-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                    title="Afficher / masquer les colonnes">
                <i class="bi bi-layout-three-columns"></i> Colonnes
            </button>
            <div class="dropdown-menu dropdown-menu-right p-2" id="equity-cols-menu"
                 aria-labelledby="equity-cols-toggle" style="min-width: 260px; max-height: 60vh; overflow-y: auto;">
                <?php foreach ($equityColDefs as $colKey => $colLabel): ?>
                    <?php $colChecked = !in_array($colKey, $equityColsHiddenByDefault, true); ?>
                    <div class="form-check">
                        <input class="form-check-input equity-col-toggle" type="checkbox"
                               id="equity-col-<?= h($colKey) ?>"
                               data-col="<?= h($colKey) ?>"
                               <?= $colChecked ? 'checked' : '' ?>>
                        <label class="form-check-label" for="equity-col-<?= h($colKey) ?>"><?= h($colLabel) ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="equity-copy-csv" title="Copie le tableau au format CSV">
            <i class="bi bi-clipboard"></i> Copier en CSV
        </button>
    </div>
    <div class="table-responsive equity-table-responsive">
        <table class="table table-sm table-striped table-hover align-middle equity-report-table" id="equity-report-table">
            <thead>
                <tr>
                    <th data-col="id" data-sort="number">Id</th>
                    <th data-col="agent" data-sort="text">Agent</th>
                    <th class="text-end" data-col="contrat" data-sort="number">Contrat</th>
                    <th class="text-end" data-col="absences" data-sort="number">Absences</th>
                    <th class="text-end" data-col="planifie_net" data-sort="number">Planifié net</th>
                    <th class="text-end" data-col="teletravail" data-sort="number">Télétravail</th>
                    <th class="text-end" data-col="pause" data-sort="number">Pause</th>
                    <th class="text-end" data-col="repas" data-sort="number">Repas</th>
                    <?php foreach ($equityGroupsColumns as $col): ?>
                        <th class="text-end equity-col" data-col="equity:<?= h($col['key']) ?>" data-sort="number" title="<?= h($col['label']) ?>"><?= h($col['label']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $contractMin = (int)($r['contract_minutes'] ?? 0);
                    $workTotal = (int)$r['work_minutes_total'];
                    $absenceMin = (int)($r['absence_minutes'] ?? 0);
                    $remoteMin = (int)($r['remote_minutes'] ?? 0);
                    $pauseMin = (int)($r['pause_minutes'] ?? 0);
                    $lunchMin = (int)($r['lunch_minutes'] ?? 0);
                    $groupMinutes = (array)($r['group_minutes'] ?? []);
                    ?>
                    <tr>
                        <td data-sort-value="<?= (int)$r['user_id'] ?>"><?= (int)$r['user_id'] ?></td>
                        <td><strong><?= h((string)$r['name']) ?></strong></td>
                        <td class="text-end" data-sort-value="<?= $contractMin ?>"><?= fmtMinutesWs($contractMin) ?></td>
                        <td class="text-end" data-sort-value="<?= $absenceMin ?>"><?= fmtMinutesWs($absenceMin) ?></td>
                        <td class="text-end" data-sort-value="<?= $workTotal ?>"><?= fmtMinutesWs($workTotal) ?></td>
                        <td class="text-end" data-sort-value="<?= $remoteMin ?>"><?= fmtMinutesWs($remoteMin) ?></td>
                        <td class="text-end" data-sort-value="<?= $pauseMin ?>"><?= fmtMinutesWs($pauseMin) ?></td>
                        <td class="text-end" data-sort-value="<?= $lunchMin ?>"><?= fmtMinutesWs($lunchMin) ?></td>
                        <?php foreach ($equityGroupsColumns as $col): ?>
                            <?php
                            $m = (int)($groupMinutes[$col['key']] ?? 0);
                            $target = (int)($r['target_minutes_by_group'][$col['key']] ?? 0);
                            $gap = (int)($r['gap_minutes_by_group'][$col['key']] ?? 0);
                            $heatClass = $equityHeatClass((float)$gap, (float)$target);
                            $sign = $gap > 0 ? '+' : ($gap < 0 ? '-' : '');
                            $gapLabel = $sign . fmtMinutesWs(abs($gap));

                            // Info-bulle : cible / réalisé / écart / atteinte + détail par offre
                            $tooltipParts = [];
                            if ($target > 0) {
                                $pctAtteinte = $m * 100.0 / $target;
                                $tooltipParts[] = 'Cible : ' . fmtMinutesWs($target);
                                $tooltipParts[] = 'Réalisé : ' . fmtMinutesWs($m);
                                $tooltipParts[] = 'Écart : ' . $gapLabel;
                                $tooltipParts[] = 'Atteinte : ' . number_format($pctAtteinte, 0, ',', ' ') . ' %';
                            } else {
                                $tooltipParts[] = 'Réalisé : ' . fmtMinutesWs($m);
                                $tooltipParts[] = 'Aucune cible définie';
                            }

                            if (count($col['offer_ids']) > 1) {
                                $offersMin = (array)($r['offers_minutes'] ?? []);
                                $diversityParts = [];
                                foreach ($col['offer_ids'] as $oid) {
                                    $om = (int)($offersMin[$oid] ?? 0);
                                    $oname = (string)($offersById[$oid]->name ?? ('#' . $oid));
                                    $diversityParts[] = $oname . ' : ' . fmtMinutesWs($om);
                                }
                                if (!empty($diversityParts)) {
                                    $tooltipParts[] = '';
                                    $tooltipParts[] = 'Détail par offre :';
                                    foreach ($diversityParts as $dp) {
                                        $tooltipParts[] = '• ' . $dp;
                                    }
                                }
                            }

                            $tooltipHtml = implode('<br>', array_map('h', $tooltipParts));
                            $csvContent = $target > 0 ? $gapLabel : ($m > 0 ? fmtMinutesWs($m) : '—');
                            ?>
                            <td class="text-end" data-toggle="tooltip" data-html="true"
                                title="<?= $tooltipHtml ?>"
                                data-csv-content="<?= h($csvContent) ?>"
                                data-sort-value="<?= $m ?>">
                                <?php if ($m > 0 || $target > 0): ?>
                                    <span class="pct-dispo <?= $heatClass ?>"><?= fmtMinutesWs($m) ?></span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="alert alert-info mb-0 mt-3">
        <div class="mb-1">Lecture des colonnes de groupe : la valeur affichée est le <strong>temps réalisé</strong>, comparé à la <strong>cible</strong> d'équité (visible en info-bulle au survol). Les deux valeurs sont exprimées en <strong>créneaux complets (pauses incluses)</strong>. L'écart est signé (<code>+</code> = au-dessus de la cible, <code>-</code> = en dessous). Les offres de <strong>rotation quota</strong> (ex. AE 2×3 h) prennent la cible proratisée × durée de shift — la même que l’onglet Qualité. Les lignes de <strong>couverture</strong> (ex. livechat) n’ont pas de cible individuelle : c’est un need par plage. La colonne <strong>Contrat</strong> est la somme des disponibilités de l'agent sur la période (brut, pauses incluses). La colonne <strong>Planifié net</strong> est le temps de production effectif (pauses et repas déduits).</div>
        <div class="mb-0">
            <i class="bi bi-circle-fill text-success"></i> proche de la cible (≤ 10 % d'écart)
            &nbsp;&nbsp;
            <i class="bi bi-circle-fill text-warning"></i> déficit modéré (10-25 %)
            &nbsp;&nbsp;
            <i class="bi bi-circle-fill text-danger"></i> déficit important (&gt; 25 %)
            &nbsp;&nbsp;
            <i class="bi bi-circle-fill text-info"></i> excédent modéré (10-25 %)
            &nbsp;&nbsp;
            <i class="bi bi-circle-fill text-primary"></i> excédent important (&gt; 25 %)
            &nbsp;&nbsp;
            <span class="text-muted">Cliquez sur un en-tête pour trier. Survolez une cellule pour voir la cible, le détail et, pour les groupes couplés, la répartition par offre. Utilisez le menu <strong>Colonnes</strong> pour afficher ou masquer des colonnes.</span>
        </div>
    </div>
<?php endif; ?>
