<?php
/** @var \App\View\AppView $this */
/** @var \App\Model\Entity\FixedActivityRule $rule */
?>
<?php $this->assign('title', 'Détail activité fixe'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>



<?php
// Préparation des données
$modeLabels = [
    'per_site' => 'Par site',
    'pooled' => 'Mutualisé',
    'global' => 'Global (tous sites)'
];
$modeLabel = $modeLabels[$rule->site_mode ?? 'per_site'] ?? $rule->site_mode;

$modeBadgeClass = 'badge-info';
$modeIcon = 'bi-building';
if ($rule->site_mode === 'pooled') {
    $modeBadgeClass = 'badge-warning';
    $modeIcon = 'bi-diagram-3';
} elseif ($rule->site_mode === 'global') {
    $modeBadgeClass = 'badge-dark';
    $modeIcon = 'bi-globe';
}

$sitesDisplay = implode(', ', array_map(fn($s) => $s->name, (array)$rule->sites));
if (empty($sitesDisplay) && $rule->site_mode === 'global') {
    $sitesDisplay = 'Tous les sites';
}

$dow = [];
if (!empty($rule->days_of_week)) {
    $decoded = is_string($rule->days_of_week) ? json_decode($rule->days_of_week, true) : (array)$rule->days_of_week;
    $labels = [1=>'Lundi',2=>'Mardi',3=>'Mercredi',4=>'Jeudi',5=>'Vendredi',6=>'Samedi',7=>'Dimanche'];
    foreach ((array)$decoded as $v) { if (isset($labels[(int)$v])) { $dow[] = $labels[(int)$v]; } }
}
$daysDisplay = !empty($dow) ? implode(', ', $dow) : 'Tous les jours';

$lunchAttachLabels = [
    'none' => 'Aucune préférence particulière',
    'before' => 'De préférence juste AVANT cette activité',
    'after' => 'De préférence juste APRÈS cette activité',
];
$lunchAttach = $lunchAttachLabels[$rule->lunch_attach_mode ?? 'none'] ?? 'Aucune préférence particulière';

$durationDisplay = '';
if ($rule->start_time && $rule->end_time) {
    $start = new \DateTime($rule->start_time);
    $end = new \DateTime($rule->end_time);
    $diff = $start->diff($end);
    $durationDisplay = $diff->format('%Hh%I');
}
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-calendar-check text-primary"></i>
            Règle #<?= h($rule->id) ?> - <?= h($rule->offer->name ?? 'N/A') ?>
        </h3>
        <div class="btn-toolbar">
            <?= $this->Html->link(
                '<i class="bi bi-pencil mr-1"></i> Modifier',
                ['action' => 'edit', $rule->id],
                ['class' => 'btn btn-primary mr-2', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash mr-1"></i> Supprimer',
                ['action' => 'delete', $rule->id],
                ['confirm' => 'Voulez-vous vraiment supprimer cette règle ?', 'class' => 'btn btn-danger mr-2', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list mr-1"></i> Liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div class="card-body">
        <ul class="nav nav-tabs mb-4" id="rule-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="tab-scope-tab" data-toggle="tab" href="#tab-scope" role="tab" aria-controls="tab-scope" aria-selected="true">
                    <i class="bi bi-info-circle"></i> Portée &amp; activité
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-schedule-tab" data-toggle="tab" href="#tab-schedule" role="tab" aria-controls="tab-schedule" aria-selected="false">
                    <i class="bi bi-clock"></i> Horaires &amp; fréquence
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-equity-tab" data-toggle="tab" href="#tab-equity" role="tab" aria-controls="tab-equity" aria-selected="false">
                    <i class="bi bi-people"></i> Couverture &amp; équité
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-planning-tab" data-toggle="tab" href="#tab-planning" role="tab" aria-controls="tab-planning" aria-selected="false">
                    <i class="bi bi-calendar2-range"></i> Planification &amp; repas
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-incompat-tab" data-toggle="tab" href="#tab-incompat" role="tab" aria-controls="tab-incompat" aria-selected="false">
                    <i class="bi bi-x-octagon"></i> Incompatibilités
                </a>
            </li>
        </ul>

        <div class="tab-content">
            <?php // --- Onglet 1 : Portée & activité --- ?>
            <div class="tab-pane fade show active" id="tab-scope" role="tabpanel" aria-labelledby="tab-scope-tab">
                <div class="row mb-3">
                    <div class="col-md-3 text-muted small"><i class="bi bi-tag"></i> Offre</div>
                    <div class="col-md-9"><strong><?= h($rule->offer->name ?? 'N/A') ?></strong></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 text-muted small"><i class="bi bi-diagram-3"></i> Mode</div>
                    <div class="col-md-9">
                        <span class="badge <?= $modeBadgeClass ?>">
                            <i class="bi <?= $modeIcon ?>"></i> <?= h($modeLabel) ?>
                        </span>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 text-muted small"><i class="bi bi-building"></i> Sites concernés</div>
                    <div class="col-md-9"><?= h($sitesDisplay) ?></div>
                </div>
                <div class="row">
                    <div class="col-md-3 text-muted small"><i class="bi bi-power"></i> Statut</div>
                    <div class="col-md-9">
                        <?php if ($rule->active): ?>
                            <span class="badge badge-success"><i class="bi bi-check-circle"></i> Actif</span>
                        <?php else: ?>
                            <span class="badge badge-secondary"><i class="bi bi-x-circle"></i> Inactif</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php // --- Onglet 2 : Horaires & fréquence --- ?>
            <div class="tab-pane fade" id="tab-schedule" role="tabpanel" aria-labelledby="tab-schedule-tab">
                <div class="row mb-3">
                    <div class="col-md-3 text-muted small"><i class="bi bi-clock-history"></i> Heure de début</div>
                    <div class="col-md-9"><strong><?= h(substr($rule->start_time ?? '', 0, 5)) ?></strong></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 text-muted small"><i class="bi bi-clock-fill"></i> Heure de fin</div>
                    <div class="col-md-9"><strong><?= h(substr($rule->end_time ?? '', 0, 5)) ?></strong></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 text-muted small"><i class="bi bi-hourglass-split"></i> Durée</div>
                    <div class="col-md-9"><strong><?= h($durationDisplay) ?></strong></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 text-muted small"><i class="bi bi-hash"></i> Quantité</div>
                    <div class="col-md-9">
                        <span class="badge badge-primary" style="font-size: 1.2rem;"><?= h($rule->quantity) ?></span>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 text-muted small"><i class="bi bi-calendar-week"></i> Jours de la semaine</div>
                    <div class="col-md-9"><?= h($daysDisplay) ?></div>
                </div>
            </div>

            <?php // --- Onglet 3 : Couverture & équité --- ?>
            <div class="tab-pane fade" id="tab-equity" role="tabpanel" aria-labelledby="tab-equity-tab">
                <div class="row mb-3">
                    <div class="col-md-3 text-muted small"><i class="bi bi-exclamation-triangle"></i> Couverture</div>
                    <div class="col-md-9">
                        <?php if ($rule->allow_shortfall): ?>
                            <span class="badge badge-secondary"><i class="bi bi-check-circle"></i> Couverture optionnelle</span>
                        <?php else: ?>
                            <span class="badge badge-danger"><i class="bi bi-x-circle"></i> Couverture obligatoire</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 text-muted small"><i class="bi bi-people"></i> Équité (période)</div>
                    <div class="col-md-9">
                        <?php
                        $offerDefault = !empty($rule->offer->equity_enabled);
                        if ($rule->equity_enabled === true) {
                            echo '<span class="badge badge-success"><i class="bi bi-check-circle"></i> Activée</span>';
                        } elseif ($rule->equity_enabled === false) {
                            echo '<span class="badge badge-light text-muted"><i class="bi bi-x-circle"></i> Désactivée</span>';
                        } else {
                            echo '<span class="badge badge-info"><i class="bi bi-arrow-repeat"></i> Hérite</span>';
                            echo ' <small class="text-muted">(Offre: ' . ($offerDefault ? 'activée' : 'désactivée') . ')</small>';
                        }
                        ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 text-muted small"><i class="bi bi-diagram-2"></i> Groupe d'équité</div>
                    <div class="col-md-9"><?= h($rule->equity_group_id ?: '—') ?></div>
                </div>
                <div class="row">
                    <div class="col-md-3 text-muted small"><i class="bi bi-sort-numeric-down"></i> Ordre de résolution</div>
                    <div class="col-md-9">
                        <span class="badge badge-light text-dark"><?= h((int)($rule->sort_order ?? 0)) ?></span>
                    </div>
                </div>
            </div>

            <?php // --- Onglet 4 : Planification & repas --- ?>
            <div class="tab-pane fade" id="tab-planning" role="tabpanel" aria-labelledby="tab-planning-tab">
                <div class="row mb-3">
                    <div class="col-md-3 text-muted small"><i class="bi bi-scissors"></i> Scindable (relais)</div>
                    <div class="col-md-9">
                        <?php if ($rule->is_splittable): ?>
                            <span class="badge badge-info"><i class="bi bi-check-circle"></i> Oui</span>
                        <?php else: ?>
                            <span class="badge badge-light text-muted"><i class="bi bi-x-circle"></i> Non</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 text-muted small"><i class="bi bi-layout-three-columns"></i> Blocs intra-journée</div>
                    <div class="col-md-9">
                        <?php $blocks = $rule->fixed_activity_blocks ?? []; ?>
                        <?php if (!empty($blocks)): ?>
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($blocks as $b): ?>
                                    <li>
                                        <span class="badge badge-light border">
                                            <?= h(substr($b->start_time ?? '', 0, 5)) ?> – <?= h(substr($b->end_time ?? '', 0, 5)) ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <span class="text-muted">Aucun bloc</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 text-muted small"><i class="bi bi-cup-hot"></i> Repas à recouvrir</div>
                    <div class="col-md-9">
                        <?php if ($rule->lunch_overlap_allowed): ?>
                            <span class="badge badge-success"><i class="bi bi-check-circle"></i> Autorisé</span>
                        <?php else: ?>
                            <span class="badge badge-light text-muted"><i class="bi bi-x-circle"></i> Interdit</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 text-muted small"><i class="bi bi-arrow-left-right"></i> Position du repas</div>
                    <div class="col-md-9"><?= h($lunchAttach) ?></div>
                </div>
            </div>

            <?php // --- Onglet 5 : Incompatibilités --- ?>
            <div class="tab-pane fade" id="tab-incompat" role="tabpanel" aria-labelledby="tab-incompat-tab">
                <div class="row">
                    <div class="col-md-3 text-muted small"><i class="bi bi-slash-circle"></i> Offres incompatibles</div>
                    <div class="col-md-9">
                        <?php $incOffers = $rule->incompatible_offers ?? []; ?>
                        <?php if (!empty($incOffers)): ?>
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($incOffers as $o): ?>
                                    <li>
                                        <span class="badge badge-danger"><i class="bi bi-x-octagon"></i> <?= h($o->name) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <span class="text-muted">Aucune incompatibilité</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

