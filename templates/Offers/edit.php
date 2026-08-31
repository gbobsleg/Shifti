<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Offer $offer
 */
?>
<?php $this->assign('title', 'Modifier Offre : ' . h($offer->name)); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>
<?php
$this->Html->css('pickr-classic.min', ['block' => true]);
$this->Html->css('offers-color-picker', ['block' => true]);
$this->Html->script('pickr.min', ['block' => true]);
$this->Html->script('offers-color-picker', ['block' => true]);
?>

<div class="offers form content card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-pencil text-primary"></i>
            Modifier l'Offre
        </h3>
        <div>
            <?= $this->Html->link(
                '<i class="bi bi-x-circle mr-1"></i> Annuler',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div class="card-body">
        <?= $this->Form->create($offer) ?>
        
        <div class="card border-primary mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-basket"></i> Informations de l'offre
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-tag"></i> Nom</label>
                    <?= $this->Form->control('name', [
                        'label' => false,
                        'class' => 'form-control',
                        'required' => true
                    ]) ?>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-palette"></i> Couleur</label>
                        <div class="d-flex align-items-center gap-2">
                            <div class="flex-grow-1">
                                <?= $this->Form->control('color', [
                                    'type' => 'text',
                                    'label' => false,
                                    'class' => 'form-control',
                                    'required' => true,
                                    'templates' => [
                                        'inputContainer' => '<div class="m-0 p-0" style="margin-bottom: 0 !important;">{{content}}</div>'
                                    ]
                                ]) ?>
                            </div>
                            <div class="offer-color-pickr-trigger" aria-label="Choisir une couleur"></div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-tag"></i> Type d'offre</label>
                        <?= $this->Form->control('offer_type', [
                            'type' => 'select',
                            'options' => [
                                'normal' => 'Normale (affectation standard)',
                                'absence' => 'Absence (congés, maladie)',
                                'meeting' => 'Réunion, Formation, Mandat',
                                'remote_work' => 'Télétravail',
                                'pause' => 'Pause',
                                'lunch' => 'Repas',
                            ],
                            'label' => false,
                            'class' => 'form-control',
                            'required' => true
                        ]) ?>
                        <small class="form-text text-muted">
                            Définit le comportement de l'offre dans le système
                        </small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-sort-numeric-up"></i> Ordre d'affichage</label>
                        <?= $this->Form->control('display_order', [
                            'type' => 'number',
                            'label' => false,
                            'class' => 'form-control',
                            'required' => true
                        ]) ?>
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i> Plus le nombre est bas, plus l'offre sera affichée en premier
                        </small>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="form-check">
                            <?= $this->Form->checkbox('is_displayed_in_grid', [
                                'class' => 'form-check-input',
                                'id' => 'is_displayed_in_grid'
                            ]) ?>
                            <label class="form-check-label" for="is_displayed_in_grid">
                                <i class="bi bi-grid"></i> Afficher dans le planning
                            </label>
                            <br><small class="text-muted">L'offre sera sélectionnable dans la grille de planning</small>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="form-check">
                            <?= $this->Form->checkbox('is_forecastable', [
                                'class' => 'form-check-input',
                                'id' => 'is_forecastable'
                            ]) ?>
                            <label class="form-check-label" for="is_forecastable">
                                <i class="bi bi-graph-up"></i> Utilisable en prévision
                            </label>
                            <br><small class="text-muted">L'offre sera incluse dans les calculs de forecast</small>
                        </div>
                    </div>
                </div>
                <div class="row" id="default-forecast-method-section">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="default_forecast_method">
                            <i class="bi bi-diagram-3"></i> Méthode de prévision par défaut
                        </label>
                        <?= $this->Form->control('default_forecast_method', [
                            'type' => 'select',
                            'options' => [
                                'historical' => 'Moyenne historique',
                                'prophet' => 'Prophet',
                            ],
                            'label' => false,
                            'class' => 'form-control',
                            'id' => 'default_forecast_method',
                        ]) ?>
                        <small class="text-muted">
                            Pré-sélectionnée pour le manager à la création d’un scénario de forecast
                        </small>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="form-check">
                            <?= $this->Form->checkbox('equity_enabled', [
                                'class' => 'form-check-input',
                                'id' => 'equity_enabled'
                            ]) ?>
                            <label class="form-check-label" for="equity_enabled">
                                <i class="bi bi-people"></i> Équité (sur la période)
                            </label>
                            <br><small class="text-muted">Répartir équitablement cette offre sur la période (forecastables + règles fixes en “héritage”)</small>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="form-check">
                            <?= $this->Form->checkbox('is_remote_work_compatible', [
                                'class' => 'form-check-input',
                                'id' => 'is_remote_work_compatible'
                            ]) ?>
                            <label class="form-check-label" for="is_remote_work_compatible">
                                <i class="bi bi-house-check"></i> Compatible télétravail
                            </label>
                            <br><small class="text-muted">Si décoché, l'offre est interdite sur les créneaux de télétravail quand l'option WFM est activée</small>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-calendar-event"></i> Date de début</label>
                        <?= $this->Form->control('start_date', [
                            'type' => 'date',
                            'label' => false,
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-calendar-x"></i> Date de fin</label>
                        <?= $this->Form->control('end_date', [
                            'type' => 'date',
                            'label' => false,
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-info mb-4" id="prophet-settings-section">
            <div class="card-header bg-info text-white">
                <i class="bi bi-graph-up-arrow"></i> Paramètres Prophet par défaut (profil administrateur)
            </div>
            <div class="card-body">
                <p class="small text-muted">
                    Ces paramètres seront utilisés comme base automatique pour tous les scénarios Prophet
                    qui incluent cette offre. Le manager pourra toujours les ajuster scénario par scénario.
                </p>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group bg-light p-3 border rounded">
                            <label class="font-weight-bold">Mode de saisonnalité</label>
                            <?= $this->Form->control('prophet_defaults.seasonality_mode', [
                                'type' => 'select',
                                'options' => [
                                    'additive' => 'Additif (y = trend + seasonality)',
                                    'multiplicative' => 'Multiplicatif (y = trend × seasonality)',
                                ],
                                'value' => $prophetDefaults['seasonality_mode'] ?? 'multiplicative',
                                'label' => false,
                                'class' => 'form-control',
                            ]) ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group bg-light p-3 border rounded">
                            <label class="font-weight-bold">Jours fériés</label>
                            <div class="form-check">
                                <?= $this->Form->checkbox('prophet_defaults.use_french_holidays', [
                                    'checked' => !empty($prophetDefaults['use_french_holidays']),
                                    'class' => 'form-check-input',
                                    'id' => 'prophet_defaults_use_french_holidays',
                                ]) ?>
                                <label class="form-check-label" for="prophet_defaults_use_french_holidays">
                                    Utiliser les jours fériés français (recommandé)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <h6 class="text-primary mt-3 mb-2"><i class="bi bi-calendar-event"></i> Saisonnalités</h6>
                <div class="form-group bg-light p-3 border rounded">
                    <div class="form-check mb-2">
                        <?= $this->Form->checkbox('prophet_defaults.yearly_seasonality', [
                            'checked' => !empty($prophetDefaults['yearly_seasonality']),
                            'class' => 'form-check-input',
                            'id' => 'prophet_defaults_yearly_seasonality',
                        ]) ?>
                        <label class="form-check-label" for="prophet_defaults_yearly_seasonality">
                            Saisonnalité annuelle
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <?= $this->Form->checkbox('prophet_defaults.weekly_seasonality', [
                            'checked' => !empty($prophetDefaults['weekly_seasonality']),
                            'class' => 'form-check-input',
                            'id' => 'prophet_defaults_weekly_seasonality',
                        ]) ?>
                        <label class="form-check-label" for="prophet_defaults_weekly_seasonality">
                            Saisonnalité hebdomadaire
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <?= $this->Form->checkbox('prophet_defaults.daily_seasonality', [
                            'checked' => !empty($prophetDefaults['daily_seasonality']),
                            'class' => 'form-check-input',
                            'id' => 'prophet_defaults_daily_seasonality',
                        ]) ?>
                        <label class="form-check-label" for="prophet_defaults_daily_seasonality">
                            Saisonnalité journalière
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <?= $this->Form->checkbox('prophet_defaults.monthly_seasonality', [
                            'checked' => !empty($prophetDefaults['monthly_seasonality']),
                            'class' => 'form-check-input',
                            'id' => 'prophet_defaults_monthly_seasonality',
                        ]) ?>
                        <label class="form-check-label" for="prophet_defaults_monthly_seasonality">
                            Saisonnalité mensuelle
                        </label>
                    </div>
                    <div class="mt-2">
                        <label class="small font-weight-bold">Complexité mensuelle (Fourier order)</label>
                        <?= $this->Form->control('prophet_defaults.monthly_fourier_order', [
                            'type' => 'number',
                            'min' => 1,
                            'max' => 15,
                            'value' => $prophetDefaults['monthly_fourier_order'] ?? 5,
                            'label' => false,
                            'class' => 'form-control form-control-sm',
                            'style' => 'max-width: 120px;',
                        ]) ?>
                    </div>
                </div>

                <h6 class="text-primary mt-3 mb-2"><i class="bi bi-sliders"></i> Sensibilité & saisonnalité</h6>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group bg-light p-3 border rounded">
                            <label class="font-weight-bold">Sensibilité aux changements (changepoint_prior_scale)</label>
                            <?= $this->Form->control('prophet_defaults.changepoint_prior_scale', [
                                'type' => 'number',
                                'step' => '0.001',
                                'min' => 0.001,
                                'max' => 0.5,
                                'value' => $prophetDefaults['changepoint_prior_scale'] ?? 0.1,
                                'label' => false,
                                'class' => 'form-control',
                                'style' => 'max-width: 150px;',
                            ]) ?>
                            <small class="text-muted d-block mt-1">
                                0.001–0.01 : très stable, 0.1 : standard, 0.2–0.5 : très réactif.
                            </small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group bg-light p-3 border rounded">
                            <label class="font-weight-bold">Force de la saisonnalité (seasonality_prior_scale)</label>
                            <?= $this->Form->control('prophet_defaults.seasonality_prior_scale', [
                                'type' => 'number',
                                'step' => '0.1',
                                'min' => 0,
                                'max' => 50,
                                'value' => $prophetDefaults['seasonality_prior_scale'] ?? 10.0,
                                'label' => false,
                                'class' => 'form-control',
                                'style' => 'max-width: 150px;',
                            ]) ?>
                            <small class="text-muted d-block mt-1">
                                0.1–1 : subtil, 10 : standard, 20–50 : très marqué.
                            </small>
                        </div>
                    </div>
                </div>

                <h6 class="text-primary mt-3 mb-2"><i class="bi bi-graph-up"></i> Changepoints (tendance)</h6>
                <div class="form-group bg-light p-3 border rounded mb-3">
                    <label class="font-weight-bold">Nombre de changepoints (n_changepoints)</label>
                    <?= $this->Form->control('prophet_defaults.n_changepoints', [
                        'type' => 'number',
                        'min' => 0,
                        'max' => 100,
                        'value' => $prophetDefaults['n_changepoints'] ?? 25,
                        'label' => false,
                        'class' => 'form-control',
                        'style' => 'max-width: 150px;',
                    ]) ?>
                    <small class="text-muted d-block mt-1">
                        0 : tendance constante, 5–15 : changements majeurs, 25 : valeur Prophet par défaut.
                    </small>
                </div>

                <h6 class="text-primary mt-3 mb-2"><i class="bi bi-calendar-range"></i> Plage de données historiques par défaut</h6>
                <div class="form-group bg-light p-3 border rounded">
                    <p class="small text-muted mb-2">
                        Cette plage est utilisée comme fenêtre par défaut pour les prévisions,
                        <strong>quelle que soit la méthode</strong> (Moyenne historique ou Prophet).
                    </p>

                    <?php if (empty($historyMinDate) || empty($historyMaxDate)): ?>
                        <small class="text-muted d-block mt-1">
                            Aucune donnée historique n'a été trouvée pour cette offre.
                            <strong>Toute l'historique disponible</strong> sera utilisé par défaut
                            lorsqu'il y aura des données. Vous pourrez alors borner la plage ici.
                        </small>
                    <?php else: ?>
                        <div class="row mt-2">
                            <div class="col-md-6 mb-2">
                                <label class="small font-weight-bold">Date de début</label>
                                <?php
                                $optsStart = [
                                    'type' => 'date',
                                    'label' => false,
                                    'class' => 'form-control form-control-sm',
                                    'value' => $prophetDefaults['history_start_date'] ?? null,
                                ];
                                if (!empty($historyMinDate)) {
                                    $optsStart['min'] = $historyMinDate;
                                }
                                if (!empty($historyMaxDate)) {
                                    $optsStart['max'] = $historyMaxDate;
                                }
                                echo $this->Form->control('prophet_defaults.history_start_date', $optsStart);
                                ?>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="small font-weight-bold">Date de fin</label>
                                <?php
                                $optsEnd = [
                                    'type' => 'date',
                                    'label' => false,
                                    'class' => 'form-control form-control-sm',
                                    'value' => $prophetDefaults['history_end_date'] ?? null,
                                ];
                                if (!empty($historyMinDate)) {
                                    $optsEnd['min'] = $historyMinDate;
                                }
                                if (!empty($historyMaxDate)) {
                                    $optsEnd['max'] = $historyMaxDate;
                                }
                                echo $this->Form->control('prophet_defaults.history_end_date', $optsEnd);
                                ?>
                            </div>
                        </div>
                        <small class="text-muted d-block mt-1">
                            Laissez les deux dates vides pour utiliser tout l'historique disponible.
                        </small>
                        <?php
                        $historyMinDateFr = (new \DateTime($historyMinDate))->format('d/m/Y');
                        $historyMaxDateFr = (new \DateTime($historyMaxDate))->format('d/m/Y');
                        ?>
                        <small class="text-muted d-block">
                            Données historiques disponibles du <strong><?= h($historyMinDateFr) ?></strong>
                            au <strong><?= h($historyMaxDateFr) ?></strong>.
                        </small>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php
        // --- Tuning Optuna ---
        $prophetTuning = $prophetTuning ?? [
            'enabled' => !empty($offer->prophet_tuning_enabled),
            'has_draft' => false,
            'has_previous' => false,
            'draft_scores' => null,
            'job' => null,
            'urls' => [
                'status' => $this->Url->build(['action' => 'tuneStatus', $offer->id]),
                'start' => $this->Url->build(['action' => 'tuneStart', $offer->id]),
                'cancel' => $this->Url->build(['action' => 'tuneCancel', $offer->id]),
                'apply' => $this->Url->build(['action' => 'tuneApply', $offer->id]),
                'reject' => $this->Url->build(['action' => 'tuneReject', $offer->id]),
                'rollback' => $this->Url->build(['action' => 'tuneRollback', $offer->id]),
            ],
        ];
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
        ?>
        <div class="card border-warning mb-4" id="prophet-tuning-section">
            <div class="card-header bg-warning">
                <i class="bi bi-cpu"></i> Tuning Optuna
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

                <p class="small text-muted">
                    Optimise automatiquement 4 paramètres Prophet via backtest walk-forward.
                    Le profil officiel n’est modifié qu’après <strong>Appliquer</strong>
                    (sauf auto-apply WFM).
                </p>
                <div class="alert alert-info py-2 small mb-3">
                    <?= \App\Service\ProphetOptunaConfig::fixedRulesHelpHtml() ?>
                </div>

                <div class="form-check mb-3">
                    <?= $this->Form->checkbox('prophet_tuning_enabled', [
                        'checked' => !empty($offer->prophet_tuning_enabled),
                        'class' => 'form-check-input',
                        'id' => 'prophet_tuning_enabled',
                    ]) ?>
                    <label class="form-check-label" for="prophet_tuning_enabled">
                        <strong>Activer le tuning Optuna pour cette offre</strong>
                        <span class="text-muted small">(cron + éligibilité)</span>
                    </label>
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

        <div class="mt-3">
            <?= $this->Form->button('<i class="bi bi-save mr-2"></i> Sauvegarder', [
                'class' => 'btn btn-success mr-3',
                'escapeTitle' => false
            ]) ?>
            <?= $this->Html->link(
                '<i class="bi bi-x-circle mr-2"></i> Annuler',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
        
        <?= $this->Form->end() ?>
    </div>
</div>

<?php
$this->Html->script('prophet-tuning', ['block' => true]);
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const forecastableCheckbox = document.getElementById('is_forecastable');
    const prophetSection = document.getElementById('prophet-settings-section');
    const defaultMethodSection = document.getElementById('default-forecast-method-section');
    const tuningSection = document.getElementById('prophet-tuning-section');

    function toggleForecastableSections() {
        const visible = forecastableCheckbox && forecastableCheckbox.checked;
        if (prophetSection) {
            prophetSection.style.display = visible ? 'block' : 'none';
        }
        if (defaultMethodSection) {
            defaultMethodSection.style.display = visible ? '' : 'none';
        }
        if (tuningSection) {
            tuningSection.style.display = visible ? 'block' : 'none';
        }
    }

    toggleForecastableSections();

    if (forecastableCheckbox) {
        forecastableCheckbox.addEventListener('change', toggleForecastableSections);
    }
});
</script>
