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
$strictLabel = $isStrict ? 'Strict (pas de fin anticipée)' : 'Flexible (fin anticipée autorisée)';

$breaksEnabled = $wfmSetting->enable_am_pm_breaks === null || $wfmSetting->enable_am_pm_breaks;
$forbidSingletons = (bool)($wfmSetting->forbid_midday_singletons ?? false);

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
$workedDaysLabel = 'Non configuré';
if (!empty($workedDays) && is_array($workedDays)) {
    $workedDaysLabel = implode(', ', array_map(fn($d) => $dayNames[(int)$d] ?? $d, $workedDays));
}

$pauseOfferName = $wfmSetting->pause_offer->name ?? null;
$lunchOfferName = $wfmSetting->lunch_offer->name ?? null;

$optunaSettings = $optunaSettings ?? \App\Service\ProphetOptunaConfig::DEFAULTS;

$solver = $wfmSetting->solver_settings_json;
$solverDefaults = ['global' => 300, 'pass1' => 60, 'pass1_5' => 30, 'pass2' => 195];
$g = $solver['global'] ?? $solverDefaults['global'];
$p1 = $solver['pass1'] ?? $solverDefaults['pass1'];
$p15 = $solver['pass1_5'] ?? $solverDefaults['pass1_5'];
$p2 = $solver['pass2'] ?? $solverDefaults['pass2'];
$sum = $p1 + $p15 + $p2;
$limit = (int)$g - 15;
$budgetOk = $sum <= $limit;
?>

<div class="crud-app wfm-settings view crud-app-wide content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-gear"></i>
            <?= h($wfmSetting->name) ?>
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-pencil me-1"></i> Modifier',
                ['action' => 'edit', $wfmSetting->id],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list me-1"></i> Liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash me-1"></i> Supprimer',
                ['action' => 'delete', $wfmSetting->id],
                ['confirm' => 'Voulez-vous vraiment supprimer ce profil ?', 'class' => 'btn btn-outline-danger', 'escape' => false]
            ) ?>
        </div>
    </div>

    <section class="crud-section">
        <h2 class="crud-section-title">Qualité de service</h2>
        <dl class="crud-fields">
            <div>
                <dt>Objectif QS</dt>
                <dd><?= $this->Number->format($wfmSetting->service_level_percent) ?>%</dd>
            </div>
            <div>
                <dt>Délai QS</dt>
                <dd><?= $this->Number->format($wfmSetting->service_level_seconds) ?>s</dd>
            </div>
        </dl>
    </section>

    <section class="crud-section">
        <h2 class="crud-section-title">Plage horaire de production</h2>
        <dl class="crud-fields">
            <div>
                <dt>Début de journée</dt>
                <dd><?= h($wfmSetting->day_start_time ?? '—') ?></dd>
            </div>
            <div>
                <dt>Pivot matin / après-midi</dt>
                <dd><?= h($wfmSetting->half_day_pivot ?? '13:00:00') ?></dd>
            </div>
            <div>
                <dt>Fin de journée</dt>
                <dd><?= h($wfmSetting->day_end_time ?? '—') ?></dd>
            </div>
            <div>
                <dt>Pas de grille (slot)</dt>
                <dd>
                    <?= (int)$slotMinutes ?> min
                    <div class="text-muted small">fixe pour l'instant — non modifiable (prévisions, grille, solveurs)</div>
                </dd>
            </div>
            <div>
                <dt>Jours travaillés</dt>
                <dd><?= h($workedDaysLabel) ?></dd>
            </div>
        </dl>
    </section>

    <section class="crud-section">
        <h2 class="crud-section-title">Règles générales</h2>
        <dl class="crud-fields">
            <div>
                <dt>Shrinkage planifié</dt>
                <dd><?= $this->Number->format($wfmSetting->shrinkage_percent) ?>%</dd>
            </div>
            <div>
                <dt>Durée min bloc travail</dt>
                <dd><?= $this->Number->format($wfmSetting->min_block_minutes) ?> minutes</dd>
            </div>
            <div>
                <dt>Durée max bloc travail</dt>
                <dd><?= $this->Number->format($wfmSetting->max_block_minutes) ?> minutes</dd>
            </div>
            <div>
                <dt>Journée stricte (fin anticipée)</dt>
                <dd><?= h($strictLabel) ?></dd>
            </div>
            <div>
                <dt>Interdire les blocs isolés 12h–14h</dt>
                <dd><?= $forbidSingletons ? 'Activé' : 'Désactivé' ?></dd>
            </div>
        </dl>
    </section>

    <section class="crud-section">
        <h2 class="crud-section-title">Configuration des pauses</h2>
        <dl class="crud-fields">
            <div>
                <dt>Planifier les pauses AM/PM</dt>
                <dd><?= $breaksEnabled ? 'Oui' : 'Non' ?></dd>
            </div>
            <div>
                <dt>Offre utilisée pour les pauses AM/PM</dt>
                <dd><?= $pauseOfferName ? h($pauseOfferName) : 'Aucune offre de pauses définie' ?></dd>
            </div>
            <div>
                <dt>Offre utilisée pour le repas</dt>
                <dd><?= $lunchOfferName ? h($lunchOfferName) : 'Aucune offre de repas définie' ?></dd>
            </div>
            <div>
                <dt>Pause matin (AM)</dt>
                <dd>
                    <?= $this->Number->format($wfmSetting->am_pause_duration_minutes) ?> min
                    <div class="text-muted small">Entre <?= h($wfmSetting->am_pause_start_time) ?> et <?= h($wfmSetting->am_pause_end_time) ?></div>
                </dd>
            </div>
            <div>
                <dt>Pause déjeuner</dt>
                <dd>
                    <?= $this->Number->format($wfmSetting->lunch_duration_minutes) ?> min
                    <div class="text-muted small">Entre <?= h($wfmSetting->lunch_start_time) ?> et <?= h($wfmSetting->lunch_end_time) ?></div>
                </dd>
            </div>
            <div>
                <dt>Pause après-midi (PM)</dt>
                <dd>
                    <?= $this->Number->format($wfmSetting->pm_pause_duration_minutes) ?> min
                    <div class="text-muted small">Entre <?= h($wfmSetting->pm_pause_start_time) ?> et <?= h($wfmSetting->pm_pause_end_time) ?></div>
                </dd>
            </div>
        </dl>
    </section>

    <section class="crud-section">
        <h2 class="crud-section-title">Paramètres Prophet par défaut (profil système)</h2>
        <p class="text-muted">
            Ces paramètres servent de base à tous les profils Prophet par offre.
            Ils sont utilisés lorsqu'un profil d'offre est incomplet ou non défini.
        </p>
        <h3 class="crud-subsection-title">Modèle et changements</h3>
        <dl class="crud-fields">
            <div>
                <dt>Mode</dt>
                <dd><?= h($prophetDefaults['seasonality_mode']) ?></dd>
            </div>
            <div>
                <dt>n_changepoints</dt>
                <dd><?= h($prophetDefaults['n_changepoints']) ?></dd>
            </div>
            <div>
                <dt>changepoint_prior_scale</dt>
                <dd><?= h($prophetDefaults['changepoint_prior_scale']) ?></dd>
            </div>
            <div>
                <dt>seasonality_prior_scale</dt>
                <dd><?= h($prophetDefaults['seasonality_prior_scale']) ?></dd>
            </div>
            <div>
                <dt>monthly_fourier_order</dt>
                <dd><?= h($prophetDefaults['monthly_fourier_order']) ?></dd>
            </div>
            <div>
                <dt>Plage historique</dt>
                <dd>
                    <?php if ($prophetDefaults['history_start_date'] || $prophetDefaults['history_end_date']): ?>
                        <?= h($prophetDefaults['history_start_date'] ?? 'début auto') ?>
                        →
                        <?= h($prophetDefaults['history_end_date'] ?? 'fin auto') ?>
                    <?php else: ?>
                        Historique complet
                    <?php endif; ?>
                </dd>
            </div>
        </dl>
        <h3 class="crud-subsection-title">Saisonnalités et jours fériés</h3>
        <dl class="crud-fields">
            <?php
            $flags = [
                'yearly_seasonality' => 'Annuelle',
                'weekly_seasonality' => 'Hebdomadaire',
                'monthly_seasonality' => 'Mensuelle',
                'daily_seasonality' => 'Journalière',
            ];
            foreach ($flags as $key => $labelFlag):
            ?>
                <div>
                    <dt><?= h($labelFlag) ?></dt>
                    <dd><?= !empty($prophetDefaults[$key]) ? 'Activée' : 'Désactivée' ?></dd>
                </div>
            <?php endforeach; ?>
            <div>
                <dt>Jours fériés FR</dt>
                <dd><?= !empty($prophetDefaults['use_french_holidays']) ? 'Pris en compte' : 'Ignorés' ?></dd>
            </div>
        </dl>
    </section>

    <section class="crud-section">
        <h2 class="crud-section-title">Tuning Optuna (moteur global)</h2>
        <p class="text-muted">
            <?= \App\Service\ProphetOptunaConfig::fixedRulesHelpHtml() ?>
            Bornes = uniquement les 4 params tunables. Fuseau cron : Europe/Paris.
        </p>
        <?php if (!empty($optunaCronEstimate)): ?>
            <p class="text-muted">
                Estimation vague :
                <?= (int)$optunaCronEstimate['enabled_offers'] ?> offre(s) ×
                <?= (int)$optunaCronEstimate['n_trials'] ?> trials ≈
                <strong><?= h($optunaCronEstimate['total_human']) ?></strong>
                <?php if (!empty($optunaCronEstimate['overflow_risk'])): ?>
                    — risque de débordement journée
                <?php endif; ?>
            </p>
        <?php endif; ?>
        <?php
        $wd = \App\Service\ProphetOptunaConfig::normalizeWeekdays($optunaSettings['cron_weekdays'] ?? [7]);
        $cronDayLabels = [];
        foreach ($wd as $d) {
            $cronDayLabels[] = \App\Service\ProphetOptunaConfig::WEEKDAY_LABELS[$d] ?? (string)$d;
        }
        ?>
        <dl class="crud-fields">
            <div>
                <dt>Horizon test</dt>
                <dd><?= (int)$optunaSettings['test_horizon_days'] ?> j</dd>
            </div>
            <div>
                <dt>Trials</dt>
                <dd><?= (int)$optunaSettings['n_trials'] ?></dd>
            </div>
            <div>
                <dt>Cutoffs</dt>
                <dd><?= (int)$optunaSettings['n_cutoffs'] ?> (fixe V1)</dd>
            </div>
            <div>
                <dt>Historique min</dt>
                <dd><?= (int)$optunaSettings['min_history_days'] ?> j</dd>
            </div>
            <div>
                <dt>Cron</dt>
                <dd>
                    <?= !empty($optunaSettings['cron_enabled']) ? 'Oui' : 'Non' ?>
                    —
                    <?= h(implode(', ', $cronDayLabels)) ?>
                    à <?= sprintf('%02d:%02d', (int)$optunaSettings['cron_hour'], (int)$optunaSettings['cron_minute']) ?>
                    (périodicité <?= (int)$optunaSettings['cron_period_days'] ?> j/offre)
                </dd>
            </div>
            <div>
                <dt>Auto-apply</dt>
                <dd>
                    <?= !empty($optunaSettings['auto_apply']) ? 'Oui' : 'Non' ?>
                    (seuil WAPE <?= h((string)$optunaSettings['auto_apply_min_mae_improvement_pct']) ?> %)
                </dd>
            </div>
        </dl>
    </section>

    <section class="crud-section">
        <h2 class="crud-section-title">Temps de recherche des solveurs (timeouts en secondes)</h2>
        <dl class="crud-fields">
            <div>
                <dt>Limite globale infrastructure</dt>
                <dd>
                    <?= h((string)$g) ?>s
                    <div class="text-muted small">Timeout HTTP côté PHP</div>
                </dd>
            </div>
            <div>
                <dt>Passe 1 : activités fixes</dt>
                <dd>
                    <?= h((string)$p1) ?>s
                    <div class="text-muted small">solve-fixed-activities</div>
                </dd>
            </div>
            <div>
                <dt>Passe 1.5 : rotation</dt>
                <dd>
                    <?= h((string)$p15) ?>s
                    <div class="text-muted small">solve-rotation</div>
                </dd>
            </div>
            <div>
                <dt>Passe 2 : couverture</dt>
                <dd>
                    <?= h((string)$p2) ?>s
                    <div class="text-muted small">solve-coverage</div>
                </dd>
            </div>
            <div>
                <dt>Budget</dt>
                <dd>
                    P1 + P1.5 + P2 = <?= (int)$sum ?>s ≤ globale − 15s = <?= (int)$limit ?>s
                    —
                    <?php if ($budgetOk): ?>
                        respecté
                    <?php else: ?>
                        dépassement de <?= (int)($sum - $limit) ?>s
                    <?php endif; ?>
                </dd>
            </div>
        </dl>
    </section>

    <div class="crud-actions-bar">
        <?= $this->Html->link(
            '<i class="bi bi-pencil me-2"></i> Modifier',
            ['action' => 'edit', $wfmSetting->id],
            ['class' => 'btn btn-primary', 'escape' => false]
        ) ?>
        <?= $this->Html->link(
            '<i class="bi bi-list-ul me-2"></i> Retour à la liste',
            ['action' => 'index'],
            ['class' => 'btn btn-outline-secondary', 'escape' => false]
        ) ?>
        <?= $this->Form->postLink(
            '<i class="bi bi-trash me-2"></i> Supprimer',
            ['action' => 'delete', $wfmSetting->id],
            [
                'confirm' => 'Voulez-vous vraiment supprimer "' . h($wfmSetting->name) . '" ?',
                'class' => 'btn btn-outline-danger',
                'escape' => false,
            ]
        ) ?>
    </div>
</div>
