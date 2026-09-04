<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Offer $offer
 * @var array $prophetDefaults
 * @var bool $hasOfferProphetProfile
 * @var string|null $historyMinDate
 * @var string|null $historyMaxDate
 */

$boolText = function (bool $value, string $yesLabel = 'Oui', string $noLabel = 'Non'): string {
    return $value ? $yesLabel : $noLabel;
};

$formatDateFr = function (?string $date): ?string {
    if (empty($date)) {
        return null;
    }

    return (new \DateTime($date))->format('d/m/Y');
};
?>
<?php $this->assign('title', 'Détails Offre : ' . h($offer->name)); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app offers view content">
    <div class="crud-header">
        <h1>
            <span class="crud-swatch" style="background-color: <?= h($offer->color) ?>"></span>
            <?= h($offer->name) ?>
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-pencil me-1"></i> Modifier',
                ['action' => 'edit', $offer->id],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list me-1"></i> Liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash me-1"></i> Supprimer',
                ['action' => 'delete', $offer->id],
                ['confirm' => 'Voulez-vous vraiment supprimer "' . h($offer->name) . '" ?', 'class' => 'btn btn-outline-danger', 'escape' => false]
            ) ?>
        </div>
    </div>
        <?php
        $asMixed = $offer->offer_group_as_mixed ?? null;
        $asMember = $offer->offer_group_member ?? null;
        $memberGroup = $asMember->offer_group ?? null;
        ?>
        <?php if ($asMixed): ?>
            <div class="crud-notice">
                <div>
                    Cette offre est configurée comme <strong>Mixte</strong> du groupe
                    <strong><?= h($asMixed->name) ?></strong>.
                </div>
                <?= $this->Html->link(
                    'Voir le groupe',
                    ['controller' => 'OfferGroups', 'action' => 'view', $asMixed->id],
                    ['class' => 'btn btn-sm btn-outline-secondary']
                ) ?>
            </div>
        <?php elseif ($memberGroup): ?>
            <div class="crud-notice">
                <div>
                    Cette offre est configurée comme <strong>Membre</strong> du groupe
                    <strong><?= h($memberGroup->name) ?></strong>.
                </div>
                <?= $this->Html->link(
                    'Voir le groupe',
                    ['controller' => 'OfferGroups', 'action' => 'view', $memberGroup->id],
                    ['class' => 'btn btn-sm btn-outline-secondary']
                ) ?>
            </div>
        <?php endif; ?>

        <section class="crud-section">
            <h2 class="crud-section-title">Informations de l'offre</h2>
            <?php
            $typeLabels = [
                'normal' => 'Normale',
                'absence' => 'Absence',
                'meeting' => 'Réunion, Formation, Mandat',
                'remote_work' => 'Télétravail',
                'pause' => 'Pause',
                'lunch' => 'Repas',
            ];
            $validity = 'Non définie';
            if ($offer->start_date || $offer->end_date) {
                $validity = ($offer->start_date ? $offer->start_date->i18nFormat('dd/MM/yyyy') : '...')
                    . ' → '
                    . ($offer->end_date ? $offer->end_date->i18nFormat('dd/MM/yyyy') : '...');
            }
            $forecastMethod = ($offer->default_forecast_method ?? 'historical') === 'prophet'
                ? 'Prophet'
                : 'Moyenne historique';
            ?>
            <dl class="crud-fields">
                <div>
                    <dt>Nom</dt>
                    <dd><?= h($offer->name) ?></dd>
                </div>
                <div>
                    <dt>Couleur</dt>
                    <dd>
                        <span class="crud-color">
                            <span class="crud-swatch" style="background-color: <?= h($offer->color) ?>"></span>
                            <span class="crud-color-hex"><?= h($offer->color) ?></span>
                        </span>
                    </dd>
                </div>
                <div>
                    <dt>Type</dt>
                    <dd><?= h($typeLabels[$offer->offer_type] ?? $offer->offer_type) ?></dd>
                </div>
                <div>
                    <dt>Ordre d'affichage</dt>
                    <dd><?= $this->Number->format($offer->display_order) ?></dd>
                </div>
                <div>
                    <dt>Affiché dans le planning</dt>
                    <dd><?= h($boolText((bool)$offer->is_displayed_in_grid)) ?></dd>
                </div>
                <div>
                    <dt>Utilisable en prévision</dt>
                    <dd><?= h($boolText((bool)$offer->is_forecastable)) ?></dd>
                </div>
                <?php if (!empty($offer->is_forecastable)): ?>
                <div>
                    <dt>Méthode de prévision par défaut</dt>
                    <dd><?= h($forecastMethod) ?></dd>
                </div>
                <?php endif; ?>
                <div>
                    <dt>Équité (sur la période)</dt>
                    <dd><?= h($boolText(!empty($offer->equity_enabled), 'Activée', 'Désactivée')) ?></dd>
                </div>
                <div>
                    <dt>Compatible télétravail</dt>
                    <dd><?= h($boolText(!empty($offer->is_remote_work_compatible))) ?></dd>
                </div>
                <div>
                    <dt>Validité</dt>
                    <dd><?= h($validity) ?></dd>
                </div>
            </dl>
        </section>

        <?php if (!empty($offer->is_forecastable)): ?>
            <?php
            $seasonalityMode = $prophetDefaults['seasonality_mode'] ?? 'multiplicative';
            $seasonalityModeLabel = $seasonalityMode === 'additive'
                ? 'Additif (y = tendance + saisonnalité)'
                : 'Multiplicatif (y = tendance × saisonnalité)';
            $historyStartFr = $formatDateFr($prophetDefaults['history_start_date'] ?? null);
            $historyEndFr = $formatDateFr($prophetDefaults['history_end_date'] ?? null);
            $historyMinFr = $formatDateFr($historyMinDate);
            $historyMaxFr = $formatDateFr($historyMaxDate);
            ?>
        <?php if (!empty($prophetTuning)): ?>
            <?php
            $csrfToken = (string)$this->request->getAttribute('csrfToken');
            $jobStatus = $prophetTuning['job']['status'] ?? 'none';
            $trialsDone = (int)($prophetTuning['job']['progress_trials_done'] ?? 0);
            $trialsTotal = (int)($prophetTuning['job']['progress_trials_total'] ?? 0);
            $progressPct = $trialsTotal > 0 ? min(100, (int)round($trialsDone / $trialsTotal * 100)) : 0;
            $draftScores = $prophetTuning['draft_scores'] ?? null;
            $baselineScores = $draftScores['baseline'] ?? ($prophetTuning['job']['baseline_scores'] ?? null);
            $proposedScores = $draftScores['proposed'] ?? ($prophetTuning['job']['best_scores'] ?? null);
            $seasonalityAdapt = $draftScores['seasonality_adaptation'] ?? null;
            $fmtScore = function ($s) {
                if (!$s) {
                    return '—';
                }
                $wape = isset($s['wape_volume']) ? number_format((float)$s['wape_volume'], 2, '.', '') : '—';
                $mae = isset($s['mae_volume']) ? number_format((float)$s['mae_volume'], 2, '.', '') : '—';
                $mape = isset($s['mape_volume']) ? number_format((float)$s['mape_volume'], 2, '.', '') : '—';

                return "WAPE {$wape}% · MAE {$mae} · MAPE {$mape}%";
            };
            $fmtSeasonalityAdapt = function ($a) {
                if (!is_array($a) || empty($a['notes']) || !is_array($a['notes'])) {
                    return '';
                }

                return implode(' · ', array_map('strval', $a['notes']));
            };
            $isJobActive = in_array($jobStatus, ['queued', 'running'], true);
            $jobStatusLabels = [
                'none' => 'Aucun',
                'queued' => 'En file',
                'running' => 'En cours',
                'completed' => 'Terminé',
                'failed' => 'Échec',
                'cancelled' => 'Annulé',
            ];
            $jobStatusLabel = $jobStatusLabels[$jobStatus] ?? $jobStatus;
            ?>
            <section class="crud-section">
                <h2 class="crud-section-title">
                    Optimisation automatique des prévisions
                </h2>
                <div
                     id="prophet-tuning-root"
                     data-csrf-token="<?= h($csrfToken) ?>"
                     data-url-status="<?= h($prophetTuning['urls']['status']) ?>"
                     data-url-start="<?= h($prophetTuning['urls']['start']) ?>"
                     data-url-cancel="<?= h($prophetTuning['urls']['cancel']) ?>"
                     data-url-apply="<?= h($prophetTuning['urls']['apply']) ?>"
                     data-url-reject="<?= h($prophetTuning['urls']['reject']) ?>"
                     data-url-rollback="<?= h($prophetTuning['urls']['rollback']) ?>">

                    <p class="small text-muted mb-3">
                        <span data-bs-toggle="tooltip" title="prophet_tuning_enabled">Optimisation automatique</span> :
                        <?= h($boolText(!empty($offer->prophet_tuning_enabled), 'activée', 'désactivée')) ?>
                    </p>
                    <?= \App\Service\ProphetOptunaConfig::fixedRulesHelpHtml() ?>

                    <div class="row mb-3 g-3">
                        <div class="col-auto pe-4">
                            <div class="small text-muted">État de la tâche</div>
                            <div class="font-weight-bold" data-pt-status><?= h($jobStatusLabel) ?></div>
                        </div>
                        <div class="col">
                            <div class="small text-muted">Référence (actuel)</div>
                            <div class="text-nowrap" data-pt-baseline><?= h($fmtScore($baselineScores)) ?></div>
                        </div>
                        <div class="col">
                            <div class="small text-muted">Proposé</div>
                            <div class="text-nowrap" data-pt-proposed><?= h($fmtScore($proposedScores)) ?></div>
                            <div class="small text-muted" data-pt-improvement>
                                <?php
                                $impPct = is_array($draftScores)
                                    ? ($draftScores['wape_improvement_pct'] ?? $draftScores['mae_improvement_pct'] ?? null)
                                    : null;
                                ?>
                                <?php if ($impPct !== null): ?>
                                    <?= h(number_format((float)$impPct, 1)) ?> % WAPE
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php $seasonalityAdaptText = $fmtSeasonalityAdapt($seasonalityAdapt); ?>
                    <div class="alert alert-secondary py-2 small mb-3"
                         data-pt-seasonality-adapt
                         style="<?= $seasonalityAdaptText !== '' ? '' : 'display:none' ?>">
                        <strong>Saisonnalités adaptées à l’historique :</strong>
                        <span data-pt-seasonality-adapt-text><?= h($seasonalityAdaptText) ?></span>
                    </div>

                    <div class="mb-3" data-pt-progress-wrap style="<?= in_array($jobStatus, ['queued', 'running', 'completed', 'failed', 'cancelled'], true) ? '' : 'display:none' ?>">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Progression</span>
                            <span data-pt-progress-label><?= (int)$trialsDone ?> / <?= (int)$trialsTotal ?> essais</span>
                        </div>
                        <div class="progress" style="height: 18px;">
                            <div class="progress-bar progress-bar-striped <?= $jobStatus === 'running' ? 'progress-bar-animated' : '' ?>"
                                 role="progressbar"
                                 data-pt-progress-bar
                                 style="width: <?= (int)$progressPct ?>%;"
                                 aria-valuenow="<?= (int)$progressPct ?>"
                                 aria-valuemin="0"
                                 aria-valuemax="100"></div>
                        </div>
                    </div>

                    <div class="alert alert-danger py-2 small" data-pt-error style="<?= !empty($prophetTuning['job']['error_message']) ? '' : 'display:none' ?>">
                        <?= h($prophetTuning['job']['error_message'] ?? '') ?>
                    </div>

                    <div class="mb-2">
                        <button type="button" class="btn btn-warning btn-sm me-2" data-pt-start
                                <?= $isJobActive ? 'disabled' : '' ?>>
                            <i class="bi bi-play-fill"></i> Lancer une optimisation
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm me-2" data-pt-cancel
                                style="<?= $isJobActive ? '' : 'display:none' ?>">
                            <i class="bi bi-x-circle"></i> Annuler
                        </button>
                        <span class="small" data-pt-message></span>
                    </div>

                    <div class="mb-2" data-pt-draft-actions style="<?= !empty($prophetTuning['has_draft']) ? '' : 'display:none' ?>">
                        <button type="button" class="btn btn-success btn-sm me-2" data-pt-apply>
                            <i class="bi bi-check2"></i> Appliquer le brouillon
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-pt-reject>
                            <i class="bi bi-x"></i> Rejeter
                        </button>
                    </div>

                    <div data-pt-rollback-wrap style="<?= !empty($prophetTuning['has_previous']) ? '' : 'display:none' ?>">
                        <button type="button" class="btn btn-outline-danger btn-sm" data-pt-rollback>
                            <i class="bi bi-arrow-counterclockwise"></i> Restaurer le profil précédent
                        </button>
                    </div>
                </div>
            </section>
            <?php $this->Html->script('prophet-tuning', ['block' => true, 'timestamp' => true]); ?>
        <?php endif; ?>

            <details class="crud-section crud-details">
                <summary class="crud-section-title">
                    Paramètres Prophet
                    <span class="crud-details-meta">
                        <?php if ($hasOfferProphetProfile): ?>
                            Profil spécifique à l'offre
                        <?php else: ?>
                            Défauts système (WFM)
                        <?php endif; ?>
                    </span>
                </summary>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                                <label class="text-muted small mb-1" data-bs-toggle="tooltip" title="seasonality_mode">Mode de saisonnalité</label>
                                <div><strong><?= h($seasonalityModeLabel) ?></strong></div>
                        </div>
                        <div class="col-md-6 mb-3">
                                <label class="text-muted small mb-1" data-bs-toggle="tooltip" title="use_french_holidays">Jours fériés</label>
                                <div><?= h($boolText(!empty($prophetDefaults['use_french_holidays']), 'Français activés', 'Désactivés')) ?></div>
                        </div>
                    </div>

                    <h3 class="crud-subsection-title">Saisonnalités</h3>
                    <div class="mb-3">
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <span class="text-muted small d-inline-block" data-bs-toggle="tooltip" title="yearly_seasonality">Saisonnalité annuelle</span>
                                <?= h($boolText(!empty($prophetDefaults['yearly_seasonality']), 'Activée', 'Désactivée')) ?>
                            </div>
                            <div class="col-md-6 mb-2">
                                <span class="text-muted small d-inline-block" data-bs-toggle="tooltip" title="weekly_seasonality">Saisonnalité hebdomadaire</span>
                                <?= h($boolText(!empty($prophetDefaults['weekly_seasonality']), 'Activée', 'Désactivée')) ?>
                            </div>
                            <div class="col-md-6 mb-2">
                                <span class="text-muted small d-inline-block" data-bs-toggle="tooltip" title="daily_seasonality">Saisonnalité journalière</span>
                                <?= h($boolText(!empty($prophetDefaults['daily_seasonality']), 'Activée', 'Désactivée')) ?>
                            </div>
                            <div class="col-md-6 mb-2">
                                <span class="text-muted small d-inline-block" data-bs-toggle="tooltip" title="monthly_seasonality">Saisonnalité mensuelle</span>
                                <?= h($boolText(!empty($prophetDefaults['monthly_seasonality']), 'Activée', 'Désactivée')) ?>
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted small d-inline-block" data-bs-toggle="tooltip" title="monthly_fourier_order">Finesse du cycle mensuel</span>
                                <strong><?= h((string)($prophetDefaults['monthly_fourier_order'] ?? 5)) ?></strong>
                            </div>
                        </div>
                    </div>

                    <h3 class="crud-subsection-title">Sensibilité et saisonnalité</h3>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                                <label class="text-muted small mb-1" data-bs-toggle="tooltip" title="changepoint_prior_scale">Sensibilité aux ruptures de tendance</label>
                                <div><strong><?= h((string)($prophetDefaults['changepoint_prior_scale'] ?? 0.1)) ?></strong></div>
                        </div>
                        <div class="col-md-6 mb-3">
                                <label class="text-muted small mb-1" data-bs-toggle="tooltip" title="seasonality_prior_scale">Force de la saisonnalité</label>
                                <div><strong><?= h((string)($prophetDefaults['seasonality_prior_scale'] ?? 10.0)) ?></strong></div>
                        </div>
                    </div>

                    <h3 class="crud-subsection-title">Ruptures de tendance</h3>
                    <div class="mb-3">
                        <label class="text-muted small mb-1" data-bs-toggle="tooltip" title="n_changepoints">Nombre de ruptures de tendance</label>
                        <div><strong><?= h((string)($prophetDefaults['n_changepoints'] ?? 25)) ?></strong></div>
                    </div>

                    <h3 class="crud-subsection-title">Plage de données historiques</h3>
                    <div class="mb-3">
                        <p class="small text-muted mb-2">
                            Fenêtre par défaut pour les prévisions, quelle que soit la méthode (Moyenne historique ou Prophet).
                        </p>
                        <div class="mb-2">
                            <?php if ($historyStartFr || $historyEndFr): ?>
                                <span data-bs-toggle="tooltip" title="history_start_date"><?= h($historyStartFr ?? '...') ?></span>
                                <i class="bi bi-arrow-right"></i>
                                <span data-bs-toggle="tooltip" title="history_end_date"><?= h($historyEndFr ?? '...') ?></span>
                            <?php else: ?>
                                <strong>Tout l'historique disponible</strong>
                            <?php endif; ?>
                        </div>
                        <?php if ($historyMinFr && $historyMaxFr): ?>
                            <small class="text-muted d-block">
                                Données historiques disponibles du <strong><?= h($historyMinFr) ?></strong>
                                au <strong><?= h($historyMaxFr) ?></strong>.
                            </small>
                        <?php else: ?>
                            <small class="text-muted d-block">
                                Aucune donnée historique trouvée pour cette offre.
                            </small>
                        <?php endif; ?>
                    </div>
            </details>
        <?php endif; ?>
</div>
