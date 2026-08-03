<?php
/**
 * @deprecated Redirection depuis le controller vers view?tab=qualite&section=equity.
 * Conservé pour référence ; n'est plus servi.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\PlanningGenerationJob $job
 * @var array<int, string> $okDates
 * @var int $minutesPerDay
 * @var int $theoreticalMinutesTotal
 * @var array<int, int> $offersUsedIds
 * @var array<int, \App\Model\Entity\Offer> $offersById
 * @var list<array{key: string, label: string, offer_ids: list<int>}> $equityGroupsColumns
 * @var array<int, array<string, mixed>> $rows
 * @var array<int, string> $sitesList
 * @var array<int, string> $usersList
 * @var int $filterSiteId
 * @var int $filterUserId
 * @var string $filterEquityGroup
 */
?>
<?php $this->assign('title', 'Rapport équité job #' . (int)$job->id); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php
function fmtMinutes(int $m): string {
    $h = intdiv($m, 60);
    $min = $m % 60;
    return sprintf('%dh%02d', $h, $min);
}
function fmtPct(?float $pct): string {
    if ($pct === null) {
        return '—';
    }
    return number_format($pct, 1, ',', ' ') . ' %';
}
?>

<?php
$this->append('css', '<style>
.equity-report-table { font-size: 0.85rem; }
.equity-report-table small { font-size: 0.8em; }
.equity-report-table .pct-dispo { font-weight: 600; }
.equity-report-table .pct-theo-hours { font-size: 0.85em; color: var(--bs-secondary-color); }
.equity-report-table th.equity-col { max-width: 10rem; }
</style>');
?>

<div class="row">
    <div class="col-12">
        <div class="card shadow mb-3">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h3 class="mb-0"><i class="bi bi-graph-up text-primary"></i> Rapport équité (job #<?= (int)$job->id ?>)</h3>
                <div class="d-flex gap-2">
                    <?= $this->Html->link('Rapport job', ['action' => 'report', (int)$job->id], ['class' => 'btn btn-sm btn-outline-secondary']) ?>
                    <?= $this->Html->link('Brouillon', ['action' => 'draft', (int)$job->id], ['class' => 'btn btn-sm btn-outline-primary']) ?>
                </div>
            </div>
            <div class="card-body">
                <div><strong>Jours pris en compte :</strong> <?= count($okDates) ?> (jours OK uniquement)</div>
                <div><strong>Temps théorique / jour :</strong> <?= fmtMinutes($minutesPerDay) ?></div>
                <div><strong>Temps théorique total :</strong> <?= fmtMinutes($theoreticalMinutesTotal) ?></div>
                <small class="text-muted d-block mt-2">
                    Les % sont calculés sur le brouillon (table <code>planning_range_drafts</code>) sur les jours OK.
                    “% disponible” utilise le temps théorique moins les absences (table <code>ranges</code>).
                    Les activités fixes couplées (même <code>equity_group_id</code>) sont regroupées dans une même colonne pour l’équité.
                    Les pauses et repas (<code>offer_type=pause</code>/<code>lunch</code>) sont affichés à part et exclus des %.
                </small>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-table"></i> Répartition par agent (par groupe d’équité)
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <?php
                    $equityGroupOptions = ['' => 'Toutes'];
                    foreach ($equityGroupsColumns as $col) {
                        $equityGroupOptions[$col['key']] = $col['label'];
                    }
                    ?>
                    <?= $this->Form->create(null, ['type' => 'get', 'url' => ['action' => 'equityReport', (int)$job->id]]) ?>
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
                </div>

                <div class="mb-2 d-flex justify-content-end">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="equity-copy-csv" title="Copie le tableau dans le presse-papier au format CSV">
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
                                <td class="text-end"><?= fmtMinutes((int)($r['absence_minutes'] ?? 0)) ?></td>
                                <td class="text-end"><?= fmtMinutes((int)($r['remote_minutes'] ?? 0)) ?></td>
                                <td class="text-end"><?= fmtMinutes((int)($r['pause_minutes'] ?? 0)) ?></td>
                                <td class="text-end"><?= fmtMinutes((int)($r['lunch_minutes'] ?? 0)) ?></td>
                                <td class="text-end"><?= fmtMinutes($workTotal) ?></td>
                                <?php
                                $pctDispoTooltip = '% disponible (temps travaillé / temps dispo.) : ' . (string)fmtPct($pctAvail);
                                $pctDispoTooltip .= ' | % théorique (temps travaillé / temps théorique) : ' . (string)fmtPct($pctTheo);
                                $pctDispoCsv = '% disponible : ' . (string)fmtPct($pctAvail) . ' ; % théorique : ' . (string)fmtPct($pctTheo);
                                ?>
                                <td class="text-end" title="<?= h($pctDispoTooltip) ?>" data-csv-content="<?= h($pctDispoCsv) ?>"><span class="pct-dispo"><?= fmtPct($pctAvail) ?></span></td>
                                <?php foreach ($equityGroupsColumns as $col): ?>
                                    <?php
                                    $m = (int)($groupMinutes[$col['key']] ?? 0);
                                    $pctTheoOff = $theo > 0 && $m > 0 ? ($m * 100.0 / $theo) : null;
                                    $pctAvailOff = $avail > 0 && $m > 0 ? ($m * 100.0 / $avail) : null;
                                    $tooltip = $m > 0 ? ('% disponible : ' . fmtPct($pctAvailOff) . ' | Durée : ' . fmtMinutes($m) . ' | % théorique : ' . fmtPct($pctTheoOff)) : '';
                                    $csvContent = $m > 0 ? ('% disponible : ' . fmtPct($pctAvailOff) . ' ; Durée : ' . fmtMinutes($m) . ' ; % théorique : ' . fmtPct($pctTheoOff)) : '—';
                                    ?>
                                    <td class="text-end" <?= $m > 0 ? ' title="' . h($tooltip) . '" data-csv-content="' . h($csvContent) . '"' : '' ?>>
                                        <?php if ($m > 0): ?>
                                            <span class="pct-dispo"><?= fmtPct($pctAvailOff) ?></span>
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

                <div class="alert alert-info mb-0">
                    Lecture: une répartition “équitable” implique des <strong>% disponible</strong> proches entre agents pour une même activité ou groupe d’activités couplées (sur la période).
                </div>
            </div>
        </div>
    </div>
</div>

<?php $this->start('script'); ?>
<script>
(function() {
    var btn = document.getElementById('equity-copy-csv');
    if (!btn) return;
    btn.addEventListener('click', function() {
        var table = document.getElementById('equity-report-table');
        if (!table) return;
        function escapeCsv(str) {
            if (str == null) return '';
            str = String(str).trim();
            if (str.indexOf('"') >= 0) str = str.replace(/"/g, '""');
            if (str.indexOf(',') >= 0 || str.indexOf('\n') >= 0 || str.indexOf('"') >= 0) return '"' + str + '"';
            return str;
        }
        var rows = [];
        var thead = table.querySelector('thead tr');
        if (thead) {
            var headerCells = thead.querySelectorAll('th');
            rows.push(Array.from(headerCells).map(function(th) { return escapeCsv(th.textContent); }).join(','));
        }
        table.querySelectorAll('tbody tr').forEach(function(tr) {
            var cells = tr.querySelectorAll('td');
            rows.push(Array.from(cells).map(function(td) {
                var content = td.getAttribute('data-csv-content');
                return escapeCsv(content != null ? content : td.textContent);
            }).join(','));
        });
        var csv = rows.join('\r\n');
        navigator.clipboard.writeText(csv).then(function() {
            var oldHtml = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check2"></i> Copié !';
            btn.classList.add('btn-success');
            btn.classList.remove('btn-outline-secondary');
            setTimeout(function() {
                btn.innerHTML = oldHtml;
                btn.classList.remove('btn-success');
                btn.classList.add('btn-outline-secondary');
            }, 2000);
        }).catch(function() {
            alert('Impossible de copier dans le presse-papier.');
        });
    });
})();
</script>
<?php $this->end(); ?>

