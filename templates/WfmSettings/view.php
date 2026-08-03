<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\WfmSetting $wfmSetting
 */
?>
<?php $this->assign('title', 'Détail : ' . h($wfmSetting->name)); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php
$isStrict = $wfmSetting->strict_work_hours === null || $wfmSetting->strict_work_hours;
$badgeClass = $isStrict ? 'badge-danger' : 'badge-success';
$icon = $isStrict ? 'bi-lock' : 'bi-unlock';
$label = $isStrict ? 'Strict' : 'Flexible';

// Drapeaux pour les pauses
$breaksEnabled = $wfmSetting->enable_am_pm_breaks === null || $wfmSetting->enable_am_pm_breaks;
$forbidSingletons = (bool)($wfmSetting->forbid_midday_singletons ?? false);

// Paramètres Prophet par défaut (décodés une fois pour la vue)
$prophetDefaults = [
    'history_start_date' => null,
    'history_end_date' => null,
    'seasonality_mode' => 'multiplicative',
    'yearly_seasonality' => true,
    'weekly_seasonality' => true,
    'monthly_seasonality' => true,
    'monthly_fourier_order' => 5,
    'daily_seasonality' => true,
    'changepoint_prior_scale' => 0.1,
    'seasonality_prior_scale' => 10.0,
    'growth' => 'linear',
    'n_changepoints' => 25,
    'changepoint_range' => 0.8,
    'use_french_holidays' => true,
];

$rawProphet = $wfmSetting->prophet_defaults_json ?? null;
if (is_string($rawProphet) && $rawProphet !== '') {
    $decoded = json_decode($rawProphet, true);
    if (is_array($decoded)) {
        $prophetDefaults = array_merge($prophetDefaults, $decoded);
    }
} elseif (is_array($rawProphet)) {
    $prophetDefaults = array_merge($prophetDefaults, $rawProphet);
}
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-gear text-primary"></i>
            <?= h($wfmSetting->name) ?>
            <span class="badge <?= $badgeClass ?> ml-2">
                <i class="bi <?= $icon ?>"></i> <?= $label ?>
            </span>
        </h3>
        <div class="btn-toolbar">
            <?= $this->Html->link(
                '<i class="bi bi-pencil mr-1"></i> Modifier',
                ['action' => 'edit', $wfmSetting->id],
                ['class' => 'btn btn-primary mr-2', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash mr-1"></i> Supprimer',
                ['action' => 'delete', $wfmSetting->id],
                ['confirm' => 'Voulez-vous vraiment supprimer ce profil ?', 'class' => 'btn btn-danger mr-2', 'escape' => false]
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
            <?php // --- Qualité de Service --- ?>
            <div class="col-md-6 mb-4">
                <div class="card border-success">
                    <div class="card-header bg-success text-white">
                        <i class="bi bi-graph-up"></i> Qualité de Service
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="text-muted small mb-1"><i class="bi bi-percent"></i> Objectif QS</label>
                            <div>
                                <span class="badge badge-success" style="font-size: 1.2rem;">
                                    <?= $this->Number->format($wfmSetting->service_level_percent) ?>%
                                </span>
                            </div>
                        </div>
                        <div>
                            <label class="text-muted small mb-1"><i class="bi bi-clock"></i> Délai QS</label>
                            <div>
                                <span class="badge badge-info" style="font-size: 1.2rem;">
                                    <?= $this->Number->format($wfmSetting->service_level_seconds) ?>s
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php // --- Plage horaire de production --- ?>
            <div class="col-md-6 mb-4">
                <div class="card border-info">
                    <div class="card-header bg-info text-white">
                        <i class="bi bi-clock-history"></i> Plage horaire de production
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="text-muted small mb-1">
                                    <i class="bi bi-sunrise"></i> Début de journée
                                </label>
                                <div>
                                    <span class="badge badge-primary" style="font-size: 1.1rem;">
                                        <?= h($wfmSetting->day_start_time ?? 'N/A') ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="text-muted small mb-1">
                                    <i class="bi bi-arrow-left-right"></i> Pivot Matin/Après-midi
                                </label>
                                <div>
                                    <span class="badge badge-warning" style="font-size: 1.1rem;">
                                        <?= h($wfmSetting->half_day_pivot ?? '13:00:00') ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="text-muted small mb-1">
                                    <i class="bi bi-sunset"></i> Fin de journée
                                </label>
                                <div>
                                    <span class="badge badge-primary" style="font-size: 1.1rem;">
                                        <?= h($wfmSetting->day_end_time ?? 'N/A') ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-4">
                                <label class="text-muted small mb-1">
                                    <i class="bi bi-grid-3x3"></i> Pas de grille (slot)
                                </label>
                                <div>
                                    <span class="badge badge-secondary" style="font-size: 1.1rem;">
                                        <?= (int)$slotMinutes ?> min
                                    </span>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    fixe pour l'instant — non modifiable (prévisions, grille, solveurs)
                                </small>
                            </div>
                        </div>
                        <div>
                            <label class="text-muted small mb-1">
                                <i class="bi bi-calendar-week"></i> Jours travaillés
                            </label>
                            <div>
                                <?php
                                $dayNames = [
                                    1 => 'Lundi',
                                    2 => 'Mardi',
                                    3 => 'Mercredi',
                                    4 => 'Jeudi',
                                    5 => 'Vendredi',
                                    6 => 'Samedi',
                                    7 => 'Dimanche',
                                ];
                                $workedDays = $wfmSetting->worked_days_json ?? [];
                                if (is_string($workedDays)) {
                                    $workedDays = json_decode($workedDays, true) ?? [];
                                }
                                if (!empty($workedDays) && is_array($workedDays)) {
                                    $workedDayNames = array_map(fn($d) => $dayNames[(int)$d] ?? $d, $workedDays);
                                    echo '<span class="badge badge-info" style="font-size: 1rem;">' . h(implode(', ', $workedDayNames)) . '</span>';
                                } else {
                                    echo '<span class="text-muted">Non configuré</span>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <?php // --- Règles Générales --- ?>
            <div class="col-md-6 mb-4">
                <div class="card border-warning">
                    <div class="card-header bg-warning text-dark">
                        <i class="bi bi-sliders"></i> Règles Générales
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="text-muted small mb-1"><i class="bi bi-percent"></i> Shrinkage planifié</label>
                            <div>
                                <span class="badge badge-warning" style="font-size: 1.2rem;">
                                    <?= $this->Number->format($wfmSetting->shrinkage_percent) ?>%
                                </span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small mb-1"><i class="bi bi-hourglass-split"></i> Durée min bloc travail</label>
                            <div><?= $this->Number->format($wfmSetting->min_block_minutes) ?> minutes</div>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small mb-1"><i class="bi bi-hourglass"></i> Durée max bloc travail</label>
                            <div><?= $this->Number->format($wfmSetting->max_block_minutes) ?> minutes</div>
                        </div>

                        <div class="mb-2">
                            <label class="text-muted small mb-1">
                                <i class="bi bi-lock"></i> Journée stricte (fin anticipée)
                            </label>
                            <div>
                                <span class="badge <?= $isStrict ? 'badge-danger' : 'badge-success' ?>">
                                    <i class="bi <?= $isStrict ? 'bi-lock' : 'bi-unlock' ?>"></i>
                                    <?= $isStrict ? 'Strict (pas de fin anticipée)' : 'Flexible (fin anticipée autorisée)' ?>
                                </span>
                            </div>
                        </div>

                        <div>
                            <label class="text-muted small mb-1">
                                <i class="bi bi-exclamation-octagon"></i> Interdire les blocs isolés 12h–14h
                            </label>
                            <div>
                                <span class="badge <?= $forbidSingletons ? 'badge-warning' : 'badge-secondary' ?>">
                                    <i class="bi <?= $forbidSingletons ? 'bi-shield-exclamation' : 'bi-slash-circle' ?>"></i>
                                    <?= $forbidSingletons ? 'Activé' : 'Désactivé' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php // --- Pauses --- ?>
            <div class="card border-info mb-4">
            <div class="card-header bg-info text-white">
                <i class="bi bi-cup-hot"></i> Configuration des Pauses
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="text-muted small mb-1"><i class="bi bi-toggle-on"></i> Planifier les pauses AM/PM</label>
                    <div>
                        <span class="badge <?= $breaksEnabled ? 'badge-success' : 'badge-secondary' ?>">
                            <i class="bi <?= $breaksEnabled ? 'bi-check-circle' : 'bi-x-circle' ?>"></i>
                            <?= $breaksEnabled ? 'Oui' : 'Non' ?>
                        </span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="text-muted small mb-1"><i class="bi bi-cup-fill"></i> Offre utilisée pour les pauses AM/PM</label>
                    <div>
                        <?php
                        $pauseOfferName = $wfmSetting->pause_offer->name ?? null;
                        ?>
                        <?php if ($pauseOfferName): ?>
                            <span class="badge badge-primary">
                                <i class="bi bi-tag"></i> <?= h($pauseOfferName) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">Aucune offre de pauses définie</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="text-muted small mb-1"><i class="bi bi-egg-fried"></i> Offre utilisée pour le repas</label>
                    <div>
                        <?php
                        $lunchOfferName = $wfmSetting->lunch_offer->name ?? null;
                        ?>
                        <?php if ($lunchOfferName): ?>
                            <span class="badge badge-primary">
                                <i class="bi bi-tag"></i> <?= h($lunchOfferName) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">Aucune offre de repas définie</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-sunrise"></i> Pause Matin (AM)</label>
                        <div>
                            <strong><?= $this->Number->format($wfmSetting->am_pause_duration_minutes) ?> min</strong>
                            <br>
                            <small class="text-muted">
                                Entre <?= h($wfmSetting->am_pause_start_time) ?> et <?= h($wfmSetting->am_pause_end_time) ?>
                            </small>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-brightness-high"></i> Pause Déjeuner</label>
                        <div>
                            <strong><?= $this->Number->format($wfmSetting->lunch_duration_minutes) ?> min</strong>
                            <br>
                            <small class="text-muted">
                                Entre <?= h($wfmSetting->lunch_start_time) ?> et <?= h($wfmSetting->lunch_end_time) ?>
                            </small>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-sunset"></i> Pause Après-Midi (PM)</label>
                        <div>
                            <strong><?= $this->Number->format($wfmSetting->pm_pause_duration_minutes) ?> min</strong>
                            <br>
                            <small class="text-muted">
                                Entre <?= h($wfmSetting->pm_pause_start_time) ?> et <?= h($wfmSetting->pm_pause_end_time) ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php // --- Paramètres Prophet par défaut (système) --- ?>
        <div class="card border-info mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="bi bi-stars"></i> Paramètres Prophet par défaut (profil système)
                </h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Ces paramètres servent de base à tous les profils Prophet par offre.  
                    Ils sont utilisés lorsqu'un profil d'offre est incomplet ou non défini.
                </p>
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary">Modèle & changements</h6>
                        <ul class="list-unstyled small mb-2">
                            <li><strong>Mode:</strong> <?= h($prophetDefaults['seasonality_mode']) ?></li>
                            <li><strong>n_changepoints:</strong> <?= h($prophetDefaults['n_changepoints']) ?></li>
                            <li><strong>changepoint_prior_scale:</strong> <?= h($prophetDefaults['changepoint_prior_scale']) ?></li>
                            <li><strong>seasonality_prior_scale:</strong> <?= h($prophetDefaults['seasonality_prior_scale']) ?></li>
                            <li><strong>monthly_fourier_order:</strong> <?= h($prophetDefaults['monthly_fourier_order']) ?></li>
                        </ul>
                        <h6 class="text-primary">Plage historique (si définie)</h6>
                        <p class="small mb-0">
                            <?php if ($prophetDefaults['history_start_date'] || $prophetDefaults['history_end_date']): ?>
                                <?= h($prophetDefaults['history_start_date'] ?? 'début auto') ?> →
                                <?= h($prophetDefaults['history_end_date'] ?? 'fin auto') ?>
                            <?php else: ?>
                                <span class="text-muted">Historique complet</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary">Saisonnalités & jours fériés</h6>
                        <ul class="list-unstyled small mb-0">
                            <?php
                            $flags = [
                                'yearly_seasonality' => 'Annuelle',
                                'weekly_seasonality' => 'Hebdomadaire',
                                'monthly_seasonality' => 'Mensuelle',
                                'daily_seasonality' => 'Journalière',
                            ];
                            foreach ($flags as $key => $labelFlag):
                                $enabled = (bool)$prophetDefaults[$key];
                            ?>
                                <li>
                                    <strong><?= h($labelFlag) ?>:</strong>
                                    <span class="badge badge-<?= $enabled ? 'success' : 'light' ?>">
                                        <?= $enabled ? 'Activée' : 'Désactivée' ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                            <?php $hol = (bool)$prophetDefaults['use_french_holidays']; ?>
                            <li class="mt-1">
                                <strong>Jours fériés FR:</strong>
                                <span class="badge badge-<?= $hol ? 'success' : 'light' ?>">
                                    <?= $hol ? 'Pris en compte' : 'Ignorés' ?>
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <?php
        $optunaSettings = $optunaSettings ?? \App\Service\ProphetOptunaConfig::DEFAULTS;
        ?>
        <div class="card border-warning mb-4">
            <div class="card-header bg-warning">
                <h5 class="mb-0"><i class="bi bi-cpu"></i> Tuning Optuna (moteur global)</h5>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-2">
                    <?= \App\Service\ProphetOptunaConfig::fixedRulesHelpHtml() ?>
                    Bornes = uniquement les 4 params tunables. Fuseau cron : Europe/Paris.
                </p>
                <?php if (!empty($optunaCronEstimate)): ?>
                    <p class="small mb-2">
                        Estimation vague :
                        <?= (int)$optunaCronEstimate['enabled_offers'] ?> offre(s) ×
                        <?= (int)$optunaCronEstimate['n_trials'] ?> trials ≈
                        <strong><?= h($optunaCronEstimate['total_human']) ?></strong>
                        <?php if (!empty($optunaCronEstimate['overflow_risk'])): ?>
                            <span class="badge badge-warning">risque débordement journée</span>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
                <div class="row small">
                    <div class="col-md-6">
                        <ul class="list-unstyled mb-0">
                            <li><strong>Horizon test:</strong> <?= (int)$optunaSettings['test_horizon_days'] ?> j</li>
                            <li><strong>Trials:</strong> <?= (int)$optunaSettings['n_trials'] ?></li>
                            <li><strong>Cutoffs:</strong> <?= (int)$optunaSettings['n_cutoffs'] ?> (fixe V1)</li>
                            <li><strong>Historique min:</strong> <?= (int)$optunaSettings['min_history_days'] ?> j</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <ul class="list-unstyled mb-0">
                            <li><strong>Cron:</strong> <?= !empty($optunaSettings['cron_enabled']) ? 'ON' : 'OFF' ?>
                                —
                                <?php
                                $wd = \App\Service\ProphetOptunaConfig::normalizeWeekdays($optunaSettings['cron_weekdays'] ?? [7]);
                                $labels = [];
                                foreach ($wd as $d) {
                                    $labels[] = \App\Service\ProphetOptunaConfig::WEEKDAY_LABELS[$d] ?? (string)$d;
                                }
                                echo h(implode(', ', $labels));
                                ?>
                                à <?= sprintf('%02d:%02d', (int)$optunaSettings['cron_hour'], (int)$optunaSettings['cron_minute']) ?>
                                (périodicité <?= (int)$optunaSettings['cron_period_days'] ?> j/offre)
                            </li>
                            <li><strong>Auto-apply:</strong> <?= !empty($optunaSettings['auto_apply']) ? 'ON' : 'OFF' ?>
                                (seuil <?= h((string)$optunaSettings['auto_apply_min_mae_improvement_pct']) ?> %)</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <?php // --- Boutons d'action --- ?>
        <div class="mt-3">
            <?= $this->Html->link(
                '<i class="bi bi-pencil mr-2"></i> Modifier',
                ['action' => 'edit', $wfmSetting->id],
                ['class' => 'btn btn-primary mr-3', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list-ul mr-2"></i> Retour à la liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash mr-2"></i> Supprimer',
                ['action' => 'delete', $wfmSetting->id],
                [
                    'confirm' => 'Voulez-vous vraiment supprimer "' . h($wfmSetting->name) . '" ?',
                    'class' => 'btn btn-outline-danger float-right',
                    'escape' => false
                ]
            ) ?>
        </div>
    </div>
</div>
