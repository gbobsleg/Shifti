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

<div class="crud-app offers form content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-pencil"></i>
            Modifier l'Offre
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-x-circle me-1"></i> Annuler',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
        <?= $this->Form->create($offer) ?>
        
        <section class="crud-section">
            <h2 class="crud-section-title">Informations de l'offre</h2>
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
                            <br><small class="text-muted">L'offre sera incluse dans les calculs de prévision</small>
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
                            Pré-sélectionnée pour le manager à la création d’un scénario de prévision
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
                            <br><small class="text-muted">Répartir équitablement cette offre sur la période, y compris les offres utilisées en prévision et les règles fixes en héritage</small>
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
        </section>

        <section class="crud-section" id="prophet-tuning-section">
            <h2 class="crud-section-title">Optimisation automatique des prévisions</h2>
            <p class="small text-muted mb-3">
                Le lancement, le suivi et l’application d’un brouillon se font depuis la fiche de l’offre.
            </p>
            <?= \App\Service\ProphetOptunaConfig::fixedRulesHelpHtml() ?>
            <div class="form-check mb-0">
                <?= $this->Form->checkbox('prophet_tuning_enabled', [
                    'checked' => !empty($offer->prophet_tuning_enabled),
                    'class' => 'form-check-input',
                    'id' => 'prophet_tuning_enabled',
                ]) ?>
                <label class="form-check-label" for="prophet_tuning_enabled">
                    <strong>Activer l’optimisation automatique pour cette offre</strong>
                    <span class="text-muted small">(planification automatique)</span>
                </label>
            </div>
        </section>

        <section class="crud-section" id="prophet-settings-section">
            <h2 class="crud-section-title">Paramètres Prophet</h2>
                <p class="small text-muted">
                    Ces paramètres seront utilisés comme base automatique pour tous les scénarios Prophet
                    qui incluent cette offre. Le manager pourra toujours les ajuster scénario par scénario.
                </p>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="font-weight-bold" data-bs-toggle="tooltip" title="seasonality_mode">Mode de saisonnalité</label>
                            <?= $this->Form->control('prophet_defaults.seasonality_mode', [
                                'type' => 'select',
                                'options' => [
                                    'additive' => 'Additif (y = tendance + saisonnalité)',
                                    'multiplicative' => 'Multiplicatif (y = tendance × saisonnalité)',
                                ],
                                'value' => $prophetDefaults['seasonality_mode'] ?? 'multiplicative',
                                'label' => false,
                                'class' => 'form-control',
                            ]) ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="font-weight-bold" data-bs-toggle="tooltip" title="use_french_holidays">Jours fériés</label>
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

                <h3 class="crud-subsection-title">Saisonnalités</h3>
                <div class="mb-3">
                    <div class="form-check mb-2">
                        <?= $this->Form->checkbox('prophet_defaults.yearly_seasonality', [
                            'checked' => !empty($prophetDefaults['yearly_seasonality']),
                            'class' => 'form-check-input',
                            'id' => 'prophet_defaults_yearly_seasonality',
                        ]) ?>
                        <label class="form-check-label" for="prophet_defaults_yearly_seasonality" data-bs-toggle="tooltip" title="yearly_seasonality">
                            Saisonnalité annuelle
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <?= $this->Form->checkbox('prophet_defaults.weekly_seasonality', [
                            'checked' => !empty($prophetDefaults['weekly_seasonality']),
                            'class' => 'form-check-input',
                            'id' => 'prophet_defaults_weekly_seasonality',
                        ]) ?>
                        <label class="form-check-label" for="prophet_defaults_weekly_seasonality" data-bs-toggle="tooltip" title="weekly_seasonality">
                            Saisonnalité hebdomadaire
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <?= $this->Form->checkbox('prophet_defaults.daily_seasonality', [
                            'checked' => !empty($prophetDefaults['daily_seasonality']),
                            'class' => 'form-check-input',
                            'id' => 'prophet_defaults_daily_seasonality',
                        ]) ?>
                        <label class="form-check-label" for="prophet_defaults_daily_seasonality" data-bs-toggle="tooltip" title="daily_seasonality">
                            Saisonnalité journalière
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <?= $this->Form->checkbox('prophet_defaults.monthly_seasonality', [
                            'checked' => !empty($prophetDefaults['monthly_seasonality']),
                            'class' => 'form-check-input',
                            'id' => 'prophet_defaults_monthly_seasonality',
                        ]) ?>
                        <label class="form-check-label" for="prophet_defaults_monthly_seasonality" data-bs-toggle="tooltip" title="monthly_seasonality">
                            Saisonnalité mensuelle
                        </label>
                    </div>
                    <div class="mt-2">
                        <label class="small font-weight-bold" data-bs-toggle="tooltip" title="monthly_fourier_order">Finesse du cycle mensuel</label>
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

                <h3 class="crud-subsection-title">Sensibilité et saisonnalité</h3>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="font-weight-bold" data-bs-toggle="tooltip" title="changepoint_prior_scale">Sensibilité aux ruptures de tendance</label>
                            <?= $this->Form->control('prophet_defaults.changepoint_prior_scale', [
                                'type' => 'number',
                                'step' => 'any',
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
                        <div class="mb-3">
                            <label class="font-weight-bold" data-bs-toggle="tooltip" title="seasonality_prior_scale">Force de la saisonnalité</label>
                            <?= $this->Form->control('prophet_defaults.seasonality_prior_scale', [
                                'type' => 'number',
                                'step' => 'any',
                                'min' => 0.01,
                                'max' => 100,
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

                <h3 class="crud-subsection-title">Ruptures de tendance</h3>
                <div class="mb-3">
                    <label class="font-weight-bold" data-bs-toggle="tooltip" title="n_changepoints">Nombre de ruptures de tendance</label>
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

                <h3 class="crud-subsection-title">Plage de données historiques</h3>
                <div class="mb-3">
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
                                <label class="small font-weight-bold" data-bs-toggle="tooltip" title="history_start_date">Date de début</label>
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
                                <label class="small font-weight-bold" data-bs-toggle="tooltip" title="history_end_date">Date de fin</label>
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
        </section>


        <div class="crud-actions-bar">
            <?= $this->Form->button('<i class="bi bi-save me-2"></i> Sauvegarder', [
                'class' => 'btn btn-primary',
                'escapeTitle' => false
            ]) ?>
            <?= $this->Html->link(
                '<i class="bi bi-x-circle me-2"></i> Annuler',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
        
        <?= $this->Form->end() ?>
</div>

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
