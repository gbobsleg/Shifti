<?php
/** @var \App\View\AppView $this */
/** @var \App\Model\Entity\FixedActivityRule $rule */
?>
<?php $this->assign('title', 'Détail activité fixe'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php
$modeLabels = [
    'per_site' => 'Par site',
    'pooled' => 'Mutualisé',
    'global' => 'Global (tous sites)',
];
$modeLabel = $modeLabels[$rule->site_mode ?? 'per_site'] ?? $rule->site_mode;

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

$offerDefault = !empty($rule->offer->equity_enabled);
if ($rule->equity_enabled === true) {
    $equityLabel = 'Activée';
} elseif ($rule->equity_enabled === false) {
    $equityLabel = 'Désactivée';
} else {
    $equityLabel = 'Hérite (Offre: ' . ($offerDefault ? 'activée' : 'désactivée') . ')';
}
?>

<div class="crud-app fixed-activity-rules view content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-calendar-check"></i>
            <?= h($rule->offer->name ?? 'Règle #' . $rule->id) ?>
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-pencil me-1"></i> Modifier',
                ['action' => 'edit', $rule->id],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list me-1"></i> Liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash me-1"></i> Supprimer',
                ['action' => 'delete', $rule->id],
                [
                    'confirm' => 'Voulez-vous vraiment supprimer cette règle ?',
                    'class' => 'btn btn-outline-danger',
                    'escape' => false,
                ]
            ) ?>
        </div>
    </div>

    <section class="crud-section">
        <h2 class="crud-section-title">Portée &amp; activité</h2>
        <dl class="crud-fields">
            <div>
                <dt>Offre</dt>
                <dd><?= h($rule->offer->name ?? '—') ?></dd>
            </div>
            <div>
                <dt>Mode</dt>
                <dd><?= h($modeLabel) ?></dd>
            </div>
            <div>
                <dt>Sites concernés</dt>
                <dd><?= h($sitesDisplay) ?></dd>
            </div>
            <div>
                <dt>Statut</dt>
                <dd><?= $rule->active ? 'Actif' : 'Inactif' ?></dd>
            </div>
        </dl>
    </section>

    <section class="crud-section">
        <h2 class="crud-section-title">Horaires &amp; fréquence</h2>
        <dl class="crud-fields">
            <div>
                <dt>Heure de début</dt>
                <dd><?= h(substr($rule->start_time ?? '', 0, 5)) ?></dd>
            </div>
            <div>
                <dt>Heure de fin</dt>
                <dd><?= h(substr($rule->end_time ?? '', 0, 5)) ?></dd>
            </div>
            <div>
                <dt>Durée</dt>
                <dd><?= h($durationDisplay) ?></dd>
            </div>
            <div>
                <dt>Quantité</dt>
                <dd><?= h($rule->quantity) ?></dd>
            </div>
            <div>
                <dt>Jours de la semaine</dt>
                <dd><?= h($daysDisplay) ?></dd>
            </div>
        </dl>
    </section>

    <section class="crud-section">
        <h2 class="crud-section-title">Couverture &amp; équité</h2>
        <dl class="crud-fields">
            <div>
                <dt>Couverture</dt>
                <dd><?= $rule->allow_shortfall ? 'Couverture optionnelle' : 'Couverture obligatoire' ?></dd>
            </div>
            <div>
                <dt>Équité (période)</dt>
                <dd><?= h($equityLabel) ?></dd>
            </div>
            <div>
                <dt>Groupe d'équité</dt>
                <dd><?= h($rule->equity_group_id ?: '—') ?></dd>
            </div>
            <div>
                <dt>Ordre de résolution</dt>
                <dd><?= h((int)($rule->sort_order ?? 0)) ?></dd>
            </div>
        </dl>
    </section>

    <section class="crud-section">
        <h2 class="crud-section-title">Planification &amp; repas</h2>
        <dl class="crud-fields">
            <div>
                <dt>Scindable (relais)</dt>
                <dd><?= $rule->is_splittable ? 'Oui' : 'Non' ?></dd>
            </div>
            <div>
                <dt>Blocs intra-journée</dt>
                <dd>
                    <?php $blocks = $rule->fixed_activity_blocks ?? []; ?>
                    <?php if (!empty($blocks)): ?>
                        <?php foreach ($blocks as $b): ?>
                            <?= h(substr($b->start_time ?? '', 0, 5)) ?> – <?= h(substr($b->end_time ?? '', 0, 5)) ?><br>
                        <?php endforeach; ?>
                    <?php else: ?>
                        Aucun bloc
                    <?php endif; ?>
                </dd>
            </div>
            <div>
                <dt>Repas à recouvrir</dt>
                <dd><?= $rule->lunch_overlap_allowed ? 'Autorisé' : 'Interdit' ?></dd>
            </div>
            <div>
                <dt>Position du repas</dt>
                <dd><?= h($lunchAttach) ?></dd>
            </div>
        </dl>
    </section>

    <section class="crud-section">
        <h2 class="crud-section-title">Incompatibilités</h2>
        <dl class="crud-fields">
            <div>
                <dt>Offres incompatibles</dt>
                <dd>
                    <?php $incOffers = $rule->incompatible_offers ?? []; ?>
                    <?php if (!empty($incOffers)): ?>
                        <?= h(implode(', ', array_map(fn($o) => $o->name, (array)$incOffers))) ?>
                    <?php else: ?>
                        Aucune incompatibilité
                    <?php endif; ?>
                </dd>
            </div>
        </dl>
    </section>
</div>
