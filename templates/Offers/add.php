<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Offer $offer
 */
?>
<?php $this->assign('title', 'Ajouter une Offre'); ?>
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
            <i class="bi bi-plus-circle text-success"></i>
            Ajouter une Offre
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
        
        <div class="card border-success mb-4">
            <div class="card-header bg-success text-white">
                <i class="bi bi-basket"></i> Informations de l'offre
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-tag"></i> Nom</label>
                    <?= $this->Form->control('name', [
                        'label' => false,
                        'class' => 'form-control',
                        'required' => true,
                        'placeholder' => 'Ex: Support Technique, Service Client...'
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
                                    'value' => '#3498db',
                                    'templates' => [
                                        'inputContainer' => '<div class="m-0 p-0" style="margin-bottom: 0 !important;">{{content}}</div>'
                                    ]
                                ]) ?>
                            </div>
                            <div class="offer-color-pickr-trigger" aria-label="Choisir une couleur"></div>
                        </div>
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i> Utilisé pour la visualisation dans les plannings
                        </small>
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
                            'required' => true,
                            'value' => 'normal'
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
                            'required' => true,
                            'value' => 10
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
                                'id' => 'is_displayed_in_grid',
                                'checked' => true
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
                                'id' => 'is_forecastable',
                                'checked' => true
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
                            'default' => 'historical',
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
                                'id' => 'equity_enabled',
                                'checked' => false
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
                                'id' => 'is_remote_work_compatible',
                                'checked' => true
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
                        <label class="form-label"><i class="bi bi-calendar-event"></i> Date de début (optionnel)</label>
                        <?= $this->Form->control('start_date', [
                            'type' => 'date',
                            'label' => false,
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-calendar-x"></i> Date de fin (optionnel)</label>
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
                        <div class="form-group bg-light p-3 border rounded">
                            <label class="font-weight-bold">Force de la saisonnalité (seasonality_prior_scale)</label>
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
                        Cette plage sera utilisée comme fenêtre par défaut pour les prévisions,
                        <strong>quelle que soit la méthode</strong> (Moyenne historique ou Prophet).
                        Pour une offre nouvelle, <strong>tout l'historique disponible</strong> sera utilisé
                        automatiquement. Vous pourrez borner cette plage une fois que des données
                        historiques auront été injectées pour cette offre (via l'écran de modification).
                    </p>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <?= $this->Form->button('<i class="bi bi-save mr-2"></i> Créer l\'offre', [
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const forecastableCheckbox = document.getElementById('is_forecastable');
    const prophetSection = document.getElementById('prophet-settings-section');
    const defaultMethodSection = document.getElementById('default-forecast-method-section');

    function toggleForecastableSections() {
        const visible = forecastableCheckbox && forecastableCheckbox.checked;
        if (prophetSection) {
            prophetSection.style.display = visible ? 'block' : 'none';
        }
        if (defaultMethodSection) {
            defaultMethodSection.style.display = visible ? '' : 'none';
        }
    }

    toggleForecastableSections();

    if (forecastableCheckbox) {
        forecastableCheckbox.addEventListener('change', toggleForecastableSections);
    }
});
</script>
