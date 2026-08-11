<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Offer $offer
 * @var array $prophetDefaults
 * @var bool $hasOfferProphetProfile
 * @var string|null $historyMinDate
 * @var string|null $historyMaxDate
 */

$boolBadge = function (bool $value, string $yesLabel = 'Oui', string $noLabel = 'Non'): string {
    if ($value) {
        return '<span class="badge badge-success"><i class="bi bi-check-circle"></i> ' . h($yesLabel) . '</span>';
    }

    return '<span class="badge badge-secondary"><i class="bi bi-x-circle"></i> ' . h($noLabel) . '</span>';
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

<div class="offers view content card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-basket text-primary"></i>
            <?= h($offer->name) ?>
            <span style="display: inline-block; width: 30px; height: 30px; background-color: <?= h($offer->color) ?>; border: 2px solid #ddd; border-radius: 5px; vertical-align: middle; margin-left: 15px;"></span>
        </h3>
        <div class="btn-toolbar">
            <?= $this->Html->link(
                '<i class="bi bi-pencil mr-1"></i> Modifier',
                ['action' => 'edit', $offer->id],
                ['class' => 'btn btn-primary mr-2', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash mr-1"></i> Supprimer',
                ['action' => 'delete', $offer->id],
                ['confirm' => 'Voulez-vous vraiment supprimer "' . h($offer->name) . '" ?', 'class' => 'btn btn-danger mr-2', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list mr-1"></i> Liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div class="card-body">
        <?php
        $asMixed = $offer->offer_group_as_mixed ?? null;
        $asMember = $offer->offer_group_member ?? null;
        $memberGroup = $asMember->offer_group ?? null;
        ?>
        <?php if ($asMixed): ?>
            <div class="alert alert-info d-flex justify-content-between align-items-center mb-4">
                <div>
                    <i class="bi bi-diagram-3"></i>
                    Cette offre est configurée comme <strong>Mixte</strong> du groupe
                    <strong><?= h($asMixed->name) ?></strong>.
                </div>
                <?= $this->Html->link(
                    'Voir le groupe',
                    ['controller' => 'OfferGroups', 'action' => 'view', $asMixed->id],
                    ['class' => 'btn btn-sm btn-outline-info']
                ) ?>
            </div>
        <?php elseif ($memberGroup): ?>
            <div class="alert alert-secondary d-flex justify-content-between align-items-center mb-4">
                <div>
                    <i class="bi bi-people"></i>
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

        <?php // --- Informations de l'offre --- ?>
        <div class="card border-primary mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-info-circle"></i> Informations de l'offre
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-tag"></i> Nom</label>
                        <div><strong><?= h($offer->name) ?></strong></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-sort-numeric-up"></i> Ordre d'affichage</label>
                        <div>
                            <span class="badge badge-primary" style="font-size: 1rem;">
                                <?= $this->Number->format($offer->display_order) ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-tag"></i> Type d'offre</label>
                        <div>
                            <?php
                            $typeLabels = [
                                'normal' => '<span class="badge badge-primary">Normale</span>',
                                'absence' => '<span class="badge badge-secondary">Absence</span>',
                                'meeting' => '<span class="badge badge-dark">Réunion, Formation, Mandat</span>',
                                'remote_work' => '<span class="badge badge-info">Télétravail</span>',
                                'pause' => '<span class="badge badge-warning">Pause</span>',
                                'lunch' => '<span class="badge badge-success">Repas</span>',
                            ];
                            echo $typeLabels[$offer->offer_type] ?? '<span class="badge badge-light">Inconnu</span>';
                            ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-grid"></i> Affichage dans le planning</label>
                        <div>
                            <?php if ($offer->is_displayed_in_grid): ?>
                                <span class="badge badge-success"><i class="bi bi-eye"></i> Oui</span>
                            <?php else: ?>
                                <span class="badge badge-secondary"><i class="bi bi-eye-slash"></i> Non</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-graph-up"></i> Utilisable en prévision</label>
                        <div>
                            <?php if ($offer->is_forecastable): ?>
                                <span class="badge badge-primary"><i class="bi bi-graph-up"></i> Oui</span>
                            <?php else: ?>
                                <span class="badge badge-secondary"><i class="bi bi-graph-down"></i> Non</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($offer->is_forecastable)): ?>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-diagram-3"></i> Méthode de prévision par défaut</label>
                        <div>
                            <?php if (($offer->default_forecast_method ?? 'historical') === 'prophet'): ?>
                                <span class="badge badge-info"><i class="bi bi-stars"></i> Prophet</span>
                            <?php else: ?>
                                <span class="badge badge-secondary"><i class="bi bi-clock-history"></i> Moyenne historique</span>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted d-block">Pré-sélectionnée à la création d’un scénario de forecast.</small>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-people"></i> Équité (sur la période)</label>
                        <div>
                            <?php if (!empty($offer->equity_enabled)): ?>
                                <span class="badge badge-success"><i class="bi bi-check-circle"></i> Activée</span>
                            <?php else: ?>
                                <span class="badge badge-secondary"><i class="bi bi-x-circle"></i> Désactivée</span>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted d-block">Utilisé comme défaut pour les règles fixes en “héritage”, et (plus tard) pour l’équité des forecastables.</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-house-check"></i> Compatible télétravail</label>
                        <div>
                            <?php if (!empty($offer->is_remote_work_compatible)): ?>
                                <span class="badge badge-success"><i class="bi bi-check-circle"></i> Oui</span>
                            <?php else: ?>
                                <span class="badge badge-secondary"><i class="bi bi-x-circle"></i> Non</span>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted d-block">Si non, l'offre est interdite sur les créneaux de télétravail quand l'option WFM est activée.</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-palette"></i> Couleur</label>
                        <div>
                            <span style="display: inline-block; width: 40px; height: 40px; background-color: <?= h($offer->color) ?>; border: 2px solid #ddd; border-radius: 5px; vertical-align: middle;"></span>
                            <span class="ml-2" style="font-size: 1rem;"><code><?= h($offer->color) ?></code></span>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-calendar-range"></i> Période de validité</label>
                        <div>
                            <?php if ($offer->start_date || $offer->end_date): ?>
                                <?= h($offer->start_date ? $offer->start_date->i18nFormat('dd/MM/yyyy') : '...') ?>
                                <i class="bi bi-arrow-right"></i>
                                <?= h($offer->end_date ? $offer->end_date->i18nFormat('dd/MM/yyyy') : '...') ?>
                            <?php else: ?>
                                <span class="text-muted">Non définie</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($offer->is_forecastable)): ?>
            <?php
            $seasonalityMode = $prophetDefaults['seasonality_mode'] ?? 'multiplicative';
            $seasonalityModeLabel = $seasonalityMode === 'additive'
                ? 'Additif (y = trend + seasonality)'
                : 'Multiplicatif (y = trend × seasonality)';
            $historyStartFr = $formatDateFr($prophetDefaults['history_start_date'] ?? null);
            $historyEndFr = $formatDateFr($prophetDefaults['history_end_date'] ?? null);
            $historyMinFr = $formatDateFr($historyMinDate);
            $historyMaxFr = $formatDateFr($historyMaxDate);
            ?>
            <div class="card border-info mb-4">
                <div class="card-header bg-info text-white">
                    <i class="bi bi-graph-up-arrow"></i> Paramètres Prophet par défaut (profil administrateur)
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">
                        Base automatique pour les scénarios Prophet incluant cette offre.
                        <?php if ($hasOfferProphetProfile): ?>
                            <span class="badge badge-info">Profil spécifique à l'offre</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Défauts système (WFM)</span>
                        <?php endif; ?>
                    </p>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="bg-light p-3 border rounded h-100">
                                <label class="text-muted small mb-1">Mode de saisonnalité</label>
                                <div><strong><?= h($seasonalityModeLabel) ?></strong></div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="bg-light p-3 border rounded h-100">
                                <label class="text-muted small mb-1">Jours fériés</label>
                                <div><?php echo $boolBadge(!empty($prophetDefaults['use_french_holidays']), 'Français activés', 'Désactivés'); ?></div>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary mt-2 mb-2"><i class="bi bi-calendar-event"></i> Saisonnalités</h6>
                    <div class="bg-light p-3 border rounded mb-3">
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <span class="text-muted small d-block">Annuelle</span>
                                <?php echo $boolBadge(!empty($prophetDefaults['yearly_seasonality']), 'Activée', 'Désactivée'); ?>
                            </div>
                            <div class="col-md-6 mb-2">
                                <span class="text-muted small d-block">Hebdomadaire</span>
                                <?php echo $boolBadge(!empty($prophetDefaults['weekly_seasonality']), 'Activée', 'Désactivée'); ?>
                            </div>
                            <div class="col-md-6 mb-2">
                                <span class="text-muted small d-block">Journalière</span>
                                <?php echo $boolBadge(!empty($prophetDefaults['daily_seasonality']), 'Activée', 'Désactivée'); ?>
                            </div>
                            <div class="col-md-6 mb-2">
                                <span class="text-muted small d-block">Mensuelle</span>
                                <?php echo $boolBadge(!empty($prophetDefaults['monthly_seasonality']), 'Activée', 'Désactivée'); ?>
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted small d-block">Complexité mensuelle (Fourier order)</span>
                                <strong><?= h((string)($prophetDefaults['monthly_fourier_order'] ?? 5)) ?></strong>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary mt-3 mb-2"><i class="bi bi-sliders"></i> Sensibilité & saisonnalité</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="bg-light p-3 border rounded h-100">
                                <label class="text-muted small mb-1">Sensibilité aux changements (changepoint_prior_scale)</label>
                                <div><strong><?= h((string)($prophetDefaults['changepoint_prior_scale'] ?? 0.1)) ?></strong></div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="bg-light p-3 border rounded h-100">
                                <label class="text-muted small mb-1">Force de la saisonnalité (seasonality_prior_scale)</label>
                                <div><strong><?= h((string)($prophetDefaults['seasonality_prior_scale'] ?? 10.0)) ?></strong></div>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary mt-2 mb-2"><i class="bi bi-graph-up"></i> Changepoints (tendance)</h6>
                    <div class="bg-light p-3 border rounded mb-3">
                        <label class="text-muted small mb-1">Nombre de changepoints (n_changepoints)</label>
                        <div><strong><?= h((string)($prophetDefaults['n_changepoints'] ?? 25)) ?></strong></div>
                    </div>

                    <h6 class="text-primary mt-3 mb-2"><i class="bi bi-calendar-range"></i> Plage de données historiques par défaut</h6>
                    <div class="bg-light p-3 border rounded">
                        <p class="small text-muted mb-2">
                            Fenêtre par défaut pour les prévisions, quelle que soit la méthode (Moyenne historique ou Prophet).
                        </p>
                        <div class="mb-2">
                            <?php if ($historyStartFr || $historyEndFr): ?>
                                <?= h($historyStartFr ?? '...') ?>
                                <i class="bi bi-arrow-right"></i>
                                <?= h($historyEndFr ?? '...') ?>
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
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($offer->is_forecastable) && !empty($prophetTuning)): ?>
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
                $mae = isset($s['mae_volume']) ? number_format((float)$s['mae_volume'], 2, '.', '') : '—';
                $mape = isset($s['mape_volume']) ? number_format((float)$s['mape_volume'], 2, '.', '') : '—';

                return "MAE {$mae} · MAPE {$mape}%";
            };
            $fmtSeasonalityAdapt = function ($a) {
                if (!is_array($a) || empty($a['notes']) || !is_array($a['notes'])) {
                    return '';
                }

                return implode(' · ', array_map('strval', $a['notes']));
            };
            $isJobActive = in_array($jobStatus, ['queued', 'running'], true);
            ?>
            <div class="card border-warning mb-4">
                <div class="card-header bg-warning d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-cpu"></i> Tuning Optuna</span>
                    <?= $this->Html->link(
                        'Configurer / activer',
                        ['action' => 'edit', $offer->id],
                        ['class' => 'btn btn-sm btn-outline-dark']
                    ) ?>
                </div>
                <div class="card-body"
                     id="prophet-tuning-root"
                     data-csrf-token="<?= h($csrfToken) ?>"
                     data-url-status="<?= h($prophetTuning['urls']['status']) ?>"
                     data-url-start="<?= h($prophetTuning['urls']['start']) ?>"
                     data-url-cancel="<?= h($prophetTuning['urls']['cancel']) ?>"
                     data-url-apply="<?= h($prophetTuning['urls']['apply']) ?>"
                     data-url-reject="<?= h($prophetTuning['urls']['reject']) ?>"
                     data-url-rollback="<?= h($prophetTuning['urls']['rollback']) ?>">

                    <p class="small text-muted mb-3">
                        Suivi live du tuning. Activation cron et params Prophet :
                        <?= $this->Html->link('page Modifier', ['action' => 'edit', $offer->id]) ?>.
                        <br>
                        Cron tuning :
                        <?php if (!empty($offer->prophet_tuning_enabled)): ?>
                            <span class="badge badge-success">activé</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">désactivé</span>
                        <?php endif; ?>
                    </p>
                    <div class="alert alert-info py-2 small mb-3">
                        <?= \App\Service\ProphetOptunaConfig::fixedRulesHelpHtml() ?>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="small text-muted">Statut job</div>
                            <div class="font-weight-bold" data-pt-status><?= h($jobStatus) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted">Baseline (actuel)</div>
                            <div data-pt-baseline><?= h($fmtScore($baselineScores)) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted">Proposé</div>
                            <div data-pt-proposed><?= h($fmtScore($proposedScores)) ?></div>
                            <div class="small text-muted" data-pt-improvement>
                                <?php if (isset($draftScores['mae_improvement_pct'])): ?>
                                    <?= h(number_format((float)$draftScores['mae_improvement_pct'], 1)) ?> % MAE
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
                        <button type="button" class="btn btn-warning btn-sm mr-2" data-pt-start
                                <?= $isJobActive ? 'disabled' : '' ?>>
                            <i class="bi bi-play-fill"></i> Lancer un tuning
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm mr-2" data-pt-cancel
                                style="<?= $isJobActive ? '' : 'display:none' ?>">
                            <i class="bi bi-x-circle"></i> Annuler le job
                        </button>
                        <span class="small" data-pt-message></span>
                    </div>

                    <div class="mb-2" data-pt-draft-actions style="<?= !empty($prophetTuning['has_draft']) ? '' : 'display:none' ?>">
                        <button type="button" class="btn btn-success btn-sm mr-2" data-pt-apply>
                            <i class="bi bi-check2"></i> Appliquer le brouillon
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-pt-reject>
                            <i class="bi bi-x"></i> Rejeter
                        </button>
                    </div>

                    <div data-pt-rollback-wrap style="<?= !empty($prophetTuning['has_previous']) ? '' : 'display:none' ?>">
                        <button type="button" class="btn btn-outline-danger btn-sm" data-pt-rollback>
                            <i class="bi bi-arrow-counterclockwise"></i> Rollback profil précédent
                        </button>
                    </div>
                </div>
            </div>
            <?php $this->Html->script('prophet-tuning', ['block' => true]); ?>
        <?php endif; ?>

        <?php // --- Boutons d'action --- ?>
        <div class="mt-4">
            <?= $this->Html->link(
                '<i class="bi bi-pencil mr-2"></i> Modifier',
                ['action' => 'edit', $offer->id],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-arrow-left mr-2"></i> Retour à la liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary ml-2', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash mr-2"></i> Supprimer',
                ['action' => 'delete', $offer->id],
                [
                    'confirm' => 'Voulez-vous vraiment supprimer "' . h($offer->name) . '" ?',
                    'class' => 'btn btn-outline-danger ml-2',
                    'escape' => false
                ]
            ) ?>
        </div>
    </div>
</div>
