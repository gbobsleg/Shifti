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
        <div class="row">
            <?php // --- Carte Informations générales --- ?>
            <div class="col-md-6 mb-4">
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-info-circle"></i> Informations générales
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="text-muted small mb-1"><i class="bi bi-tag"></i> Offre</label>
                            <div><strong><?= h($rule->offer->name ?? 'N/A') ?></strong></div>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small mb-1"><i class="bi bi-diagram-3"></i> Mode</label>
                            <div>
                                <span class="badge <?= $modeBadgeClass ?>">
                                    <i class="bi <?= $modeIcon ?>"></i> <?= h($modeLabel) ?>
                                </span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small mb-1"><i class="bi bi-building"></i> Sites concernés</label>
                            <div><?= h($sitesDisplay) ?></div>
                        </div>
                        <div>
                            <label class="text-muted small mb-1"><i class="bi bi-calendar-week"></i> Jours de la semaine</label>
                            <div><?= h($daysDisplay) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <?php // --- Carte Horaires et quantité --- ?>
            <div class="col-md-6 mb-4">
                <div class="card border-info">
                    <div class="card-header bg-info text-white">
                        <i class="bi bi-clock"></i> Horaires et quantité
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="text-muted small mb-1"><i class="bi bi-clock-history"></i> Heure de début</label>
                            <div><strong><?= h(substr($rule->start_time ?? '', 0, 5)) ?></strong></div>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small mb-1"><i class="bi bi-clock-fill"></i> Heure de fin</label>
                            <div><strong><?= h(substr($rule->end_time ?? '', 0, 5)) ?></strong></div>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small mb-1"><i class="bi bi-hash"></i> Quantité</label>
                            <div>
                                <span class="badge badge-primary" style="font-size: 1.2rem;">
                                    <?= h($rule->quantity) ?>
                                </span>
                            </div>
                        </div>
                        <div>
                            <label class="text-muted small mb-1"><i class="bi bi-hourglass-split"></i> Durée</label>
                            <div>
                                <?php
                                if ($rule->start_time && $rule->end_time) {
                                    $start = new \DateTime($rule->start_time);
                                    $end = new \DateTime($rule->end_time);
                                    $diff = $start->diff($end);
                                    echo h($diff->format('%Hh%I'));
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php // --- Carte Options --- ?>
        <div class="row">
            <div class="col-md-12">
                <div class="card border-secondary">
                    <div class="card-header bg-secondary text-white">
                        <i class="bi bi-sliders"></i> Options
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="text-muted small mb-1"><i class="bi bi-exclamation-triangle"></i> Prioritaire</label>
                                    <div>
                                        <?php if ($rule->priority): ?>
                                            <span class="badge badge-danger"><i class="bi bi-check-circle"></i> Oui</span>
                                        <?php else: ?>
                                            <span class="badge badge-light text-muted"><i class="bi bi-x-circle"></i> Non</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="text-muted small mb-1"><i class="bi bi-power"></i> Statut</label>
                                    <div>
                                        <?php if ($rule->active): ?>
                                            <span class="badge badge-success"><i class="bi bi-check-circle"></i> Actif</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary"><i class="bi bi-x-circle"></i> Inactif</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="text-muted small mb-1"><i class="bi bi-scissors"></i> Scindable (relais)</label>
                                    <div>
                                        <?php if ($rule->is_splittable): ?>
                                            <span class="badge badge-info"><i class="bi bi-check-circle"></i> Oui</span>
                                        <?php else: ?>
                                            <span class="badge badge-light text-muted"><i class="bi bi-x-circle"></i> Non</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="text-muted small mb-1"><i class="bi bi-people"></i> Équité (période)</label>
                                    <div>
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
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php // --- Boutons d'action --- ?>
        <div class="mt-4 d-flex gap-2">
            <?= $this->Html->link(
                '<i class="bi bi-pencil mr-2"></i> Modifier',
                ['action' => 'edit', $rule->id],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list-ul mr-2"></i> Retour à la liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash mr-2"></i> Supprimer',
                ['action' => 'delete', $rule->id],
                [
                    'confirm' => 'Supprimer cette règle ?',
                    'class' => 'btn btn-outline-danger ml-auto',
                    'escape' => false
                ]
            ) ?>
        </div>
    </div>
</div>

