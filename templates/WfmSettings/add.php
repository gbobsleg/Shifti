<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\WfmSetting $wfmSetting
 */
?>
<?php $this->assign('title', 'Nouveau Profil de Paramètres WFM'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>
<?php $this->Html->script('wfm-settings', ['block' => true]); ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-plus-circle text-success"></i>
            Nouveau Profil de Paramètres WFM
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
        <?= $this->Form->create($wfmSetting, [
            'data-slot-minutes' => (int)$slotMinutes,
        ]) ?>
        
        <?php // --- Nom du profil --- ?>
        <div class="card border-primary mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-tag"></i> Nom du profil
            </div>
            <div class="card-body">
                <label class="form-label"><i class="bi bi-pencil-square"></i> Nom</label>
                <?= $this->Form->control('name', [
                    'label' => false,
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Profil Standard, Profil Weekend...'
                ]) ?>
            </div>
        </div>

        <?php // --- Qualité de Service --- ?>
        <div class="card border-success mb-4">
            <div class="card-header bg-success text-white">
                <i class="bi bi-graph-up"></i> Qualité de Service (QS)
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-percent"></i> Objectif QS (%)</label>
                        <?= $this->Form->control('service_level_percent', [
                            'label' => false,
                            'class' => 'form-control',
                            'default' => 80
                        ]) ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-clock"></i> Délai QS (secondes)</label>
                        <?= $this->Form->control('service_level_seconds', [
                            'label' => false,
                            'class' => 'form-control',
                            'default' => 20
                        ]) ?>
                    </div>
                </div>
            </div>
        </div>

        <?php // --- Plage horaire de production --- ?>
        <div class="card border-info mb-4">
            <div class="card-header bg-info text-white">
                <i class="bi bi-clock-history"></i> Plage horaire de production
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">
                            <i class="bi bi-sunrise"></i> Début de journée
                        </label>
                        <?= $this->Form->control('day_start_time', [
                            'label' => false,
                            'type' => 'time',
                            'class' => 'form-control',
                            'default' => '09:00',
                        ]) ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">
                            <i class="bi bi-sunset"></i> Fin de journée
                        </label>
                        <?= $this->Form->control('day_end_time', [
                            'label' => false,
                            'type' => 'time',
                            'class' => 'form-control',
                            'default' => '17:00',
                        ]) ?>
                    </div>
                </div>
                <small class="text-muted">
                    <i class="bi bi-info-circle"></i>
                    Ces heures définissent la plage utilisée pour les prévisions et la génération des plannings.
                    Elles sont indépendantes des paramètres d'affichage (grid_start_hour / grid_end_hour).
                </small>
                <div class="row mt-3">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">
                            <i class="bi bi-grid-3x3"></i> Pas de grille (slot)
                        </label>
                        <input type="text"
                               class="form-control"
                               value="<?= (int)$slotMinutes ?> min"
                               disabled
                               readonly>
                        <small class="text-muted d-block">
                            <i class="bi bi-info-circle"></i>
                            fixe pour l'instant — non modifiable (prévisions, grille, solveurs)
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <?php // --- Règles Générales --- ?>
        <div class="card border-warning mb-4">
            <div class="card-header bg-warning text-dark">
                <i class="bi bi-sliders"></i> Règles Générales
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><i class="bi bi-percent"></i> Shrinkage planifié (%)</label>
                        <?= $this->Form->control('shrinkage_percent', [
                            'label' => false,
                            'class' => 'form-control',
                            'default' => 10
                        ]) ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><i class="bi bi-hourglass-split"></i> Durée min bloc travail (min)</label>
                        <?= $this->Form->control('min_block_minutes', [
                            'label' => false,
                            'type' => 'number',
                            'step' => $slotMinutes,
                            'class' => 'form-control',
                            'default' => 60
                        ]) ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><i class="bi bi-hourglass"></i> Durée max bloc travail (min)</label>
                        <?= $this->Form->control('max_block_minutes', [
                            'label' => false,
                            'type' => 'number',
                            'step' => $slotMinutes,
                            'class' => 'form-control',
                            'default' => 360
                        ]) ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-lock"></i> Journée stricte (pas de fin anticipée)</label>
                        <?= $this->Form->control('strict_work_hours', [
                            'type' => 'select',
                            'options' => ['1' => 'Oui (journée stricte)', '0' => 'Non (fin anticipée autorisée)'],
                            'empty' => 'Par défaut (strict)',
                            'label' => false,
                            'class' => 'form-control'
                        ]) ?>
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i> Laisser vide = strict (pas de fin anticipée).
                        </small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">
                            <i class="bi bi-exclamation-octagon"></i> Interdire les blocs isolés autour de midi
                        </label>
                        <?= $this->Form->control('forbid_midday_singletons', [
                            'type' => 'checkbox',
                            'label' => false,
                            'class' => 'form-check-input',
                            'hiddenField' => false,
                            'default' => 0,
                        ]) ?>
                        <small class="text-muted d-block">
                            <i class="bi bi-info-circle"></i>
                            Si activé, le solveur n'autorisera aucun bloc de travail isolé dans la fenêtre repas :
                            chaque créneau de travail doit avoir un voisin immédiat avant/après (y compris hors 12h–14h).
                            Cela peut augmenter la pénurie si la couverture est déjà tendue.
                        </small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">
                            <i class="bi bi-house-x"></i> Appliquer les incompatibilités télétravail
                        </label>
                        <?= $this->Form->control('enforce_remote_work_incompatibilities', [
                            'type' => 'checkbox',
                            'label' => false,
                            'class' => 'form-check-input',
                            'default' => 0,
                        ]) ?>
                        <small class="text-muted d-block">
                            <i class="bi bi-info-circle"></i>
                            Si activé, le solveur interdira d'affecter les offres marquées incompatibles sur les créneaux télétravail.
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <?php // --- Configuration des Pauses --- ?>
        <div class="card border-info mb-4">
            <div class="card-header bg-info text-white">
                <i class="bi bi-cup-hot"></i> Configuration des Pauses
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label"><i class="bi bi-cup"></i> Offre pour les Pauses (AM/PM)</label>
                        <?= $this->Form->control('pause_offer_id', [
                            'label' => false,
                            'options' => $offers,
                            'empty' => '— Sélectionner une offre —',
                            'class' => 'form-control'
                        ]) ?>
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i>
                            Offre utilisée pour planifier les pauses AM et PM.
                        </small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="bi bi-egg-fried"></i> Offre pour le Repas</label>
                        <?= $this->Form->control('lunch_offer_id', [
                            'label' => false,
                            'options' => $offers,
                            'empty' => '— Sélectionner une offre —',
                            'class' => 'form-control'
                        ]) ?>
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i>
                            Offre utilisée pour planifier la pause déjeuner.
                        </small>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label"><i class="bi bi-toggle-on"></i> Planifier les pauses AM/PM</label>
                    <?= $this->Form->control('enable_am_pm_breaks', [
                        'type' => 'select',
                        'options' => ['1' => 'Oui (planifier AM/PM)', '0' => 'Non (désactiver AM/PM)'],
                        'empty' => false,
                        'label' => false,
                        'class' => 'form-control',
                        'default' => 1
                    ]) ?>
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i>
                        Si désactivé, aucune pause AM/PM ne sera posée par le solveur. Le déjeuner reste inchangé.
                    </small>
                </div>

                <div class="p-3 bg-light rounded mb-3">
                    <h6 class="mb-3"><i class="bi bi-sunrise"></i> Pause Matin (AM)</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label small">Durée (min)</label>
                            <?= $this->Form->control('am_pause_duration_minutes', [
                                'label' => false,
                                'type' => 'number',
                                'step' => $slotMinutes,
                                'class' => 'form-control form-control-sm',
                                'default' => 15
                            ]) ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label small">Fenêtre - Début</label>
                            <?= $this->Form->control('am_pause_start_time', [
                                'label' => false,
                                'class' => 'form-control form-control-sm',
                                'default' => '09:00'
                            ]) ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label small">Fenêtre - Fin</label>
                            <?= $this->Form->control('am_pause_end_time', [
                                'label' => false,
                                'class' => 'form-control form-control-sm',
                                'default' => '11:00'
                            ]) ?>
                        </div>
                    </div>
                </div>

                <div class="p-3 bg-light rounded mb-3">
                    <h6 class="mb-3"><i class="bi bi-brightness-high"></i> Pause Déjeuner</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label small">Durée (min)</label>
                            <?= $this->Form->control('lunch_duration_minutes', [
                                'label' => false,
                                'type' => 'number',
                                'step' => $slotMinutes,
                                'class' => 'form-control form-control-sm',
                                'default' => 60
                            ]) ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label small">Fenêtre - Début</label>
                            <?= $this->Form->control('lunch_start_time', [
                                'label' => false,
                                'class' => 'form-control form-control-sm',
                                'default' => '11:30'
                            ]) ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label small">Fenêtre - Fin</label>
                            <?= $this->Form->control('lunch_end_time', [
                                'label' => false,
                                'class' => 'form-control form-control-sm',
                                'default' => '14:00'
                            ]) ?>
                        </div>
                    </div>
                </div>

                <div class="p-3 bg-light rounded">
                    <h6 class="mb-3"><i class="bi bi-sunset"></i> Pause Après-Midi (PM)</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label small">Durée (min)</label>
                            <?= $this->Form->control('pm_pause_duration_minutes', [
                                'label' => false,
                                'type' => 'number',
                                'step' => $slotMinutes,
                                'class' => 'form-control form-control-sm',
                                'default' => 15
                            ]) ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label small">Fenêtre - Début</label>
                            <?= $this->Form->control('pm_pause_start_time', [
                                'label' => false,
                                'class' => 'form-control form-control-sm',
                                'default' => '14:00'
                            ]) ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label small">Fenêtre - Fin</label>
                            <?= $this->Form->control('pm_pause_end_time', [
                                'label' => false,
                                'class' => 'form-control form-control-sm',
                                'default' => '16:00'
                            ]) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php // --- Paramètres Prophet par défaut (système) --- ?>
        <div class="card mb-3 border-info">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="bi bi-stars"></i> Paramètres Prophet par défaut (profil système WFM)
                </h5>
            </div>
            <div class="card-body">
                <p class="small text-muted">
                    Ces paramètres servent de base pour initialiser les profils Prophet de chaque offre
                    (`prophet_default_settings_json`). Ils sont également utilisés comme valeur de repli
                    si un profil d’offre est incomplet.
                </p>

                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary"><i class="bi bi-gear"></i> Configuration du modèle</h6>
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

                        <h6 class="text-primary mt-4"><i class="bi bi-calendar-event"></i> Saisonnalités</h6>
                        <div class="form-group bg-light p-3 border rounded">
            <div class=\"form-check mb-2\">
                <?= $this->Form->checkbox('prophet_defaults.yearly_seasonality', [
                    'checked' => $prophetDefaults['yearly_seasonality'] ?? true,
                    'class' => 'form-check-input',
                    'id' => 'prophet_defaults_yearly_seasonality'
                ]) ?>
                <label class=\"form-check-label\" for=\"prophet_defaults_yearly_seasonality\">
                    <strong>Saisonnalité annuelle</strong>
                </label>
            </div>
            <div class=\"form-check mb-2\">
                <?= $this->Form->checkbox('prophet_defaults.weekly_seasonality', [
                    'checked' => $prophetDefaults['weekly_seasonality'] ?? true,
                    'class' => 'form-check-input',
                    'id' => 'prophet_defaults_weekly_seasonality'
                ]) ?>
                <label class=\"form-check-label\" for=\"prophet_defaults_weekly_seasonality\">
                    <strong>Saisonnalité hebdomadaire</strong>
                </label>
            </div>
            <div class=\"form-check mb-2\">
                <?= $this->Form->checkbox('prophet_defaults.monthly_seasonality', [
                    'checked' => $prophetDefaults['monthly_seasonality'] ?? true,
                    'class' => 'form-check-input',
                    'id' => 'prophet_defaults_monthly_seasonality'
                ]) ?>
                <label class=\"form-check-label\" for=\"prophet_defaults_monthly_seasonality\">
                    <strong>Saisonnalité mensuelle</strong>
                </label>
                <div class=\"mt-2\">
                    <label class=\"small font-weight-bold\">Complexité du pattern mensuel (Fourier Order)</label>
                    <?= $this->Form->control('prophet_defaults.monthly_fourier_order', [
                        'type' => 'number',
                        'min' => 1,
                        'max' => 15,
                        'value' => $prophetDefaults['monthly_fourier_order'] ?? 5,
                        'label' => false,
                        'class' => 'form-control form-control-sm',
                        'style' => 'max-width: 100px;',
                    ]) ?>
                    <small class=\"form-text text-muted\">
                        1-3: Simple | <strong>5: Recommandé</strong> | 8-10: Complexe | 12-15: Maximum
                    </small>
                </div>
            </div>
            <div class=\"form-check mb-2\">
                <?= $this->Form->checkbox('prophet_defaults.daily_seasonality', [
                    'checked' => $prophetDefaults['daily_seasonality'] ?? true,
                    'class' => 'form-check-input',
                    'id' => 'prophet_defaults_daily_seasonality'
                ]) ?>
                <label class=\"form-check-label\" for=\"prophet_defaults_daily_seasonality\">
                    <strong>Saisonnalité journalière</strong>
                </label>
            </div>
        </div>
                    </div>

                    <div class="col-md-6">
                        <h6 class="text-primary"><i class="bi bi-sliders"></i> Sensibilité & stabilité</h6>
                        <div class="form-group bg-light p-3 border rounded">
                            <label class="font-weight-bold">Sensibilité aux changements (changepoint_prior_scale)</label>
                            <?= $this->Form->control('prophet_defaults.changepoint_prior_scale', [
                                'type' => 'number',
                                'step' => 'any',
                                'min' => 0.001,
                                'max' => 0.5,
                                'value' => $prophetDefaults['changepoint_prior_scale'] ?? 0.1,
                                'label' => false,
                                'class' => 'form-control form-control-sm',
                                'style' => 'max-width: 120px;',
                            ]) ?>
                            <small class="form-text text-muted">
                                0.001-0.01: très stable • 0.1: standard • 0.2-0.5: très réactif.
                            </small>
                        </div>

                        <div class="form-group bg-light p-3 border rounded">
                            <label class="font-weight-bold">Force de la saisonnalité (seasonality_prior_scale)</label>
                            <?= $this->Form->control('prophet_defaults.seasonality_prior_scale', [
                                'type' => 'number',
                                'step' => 'any',
                                'min' => 0.01,
                                'max' => 100,
                                'value' => $prophetDefaults['seasonality_prior_scale'] ?? 10.0,
                                'label' => false,
                                'class' => 'form-control form-control-sm',
                                'style' => 'max-width: 120px;',
                            ]) ?>
                            <small class="form-text text-muted">
                                0.1-1: faible • 10: standard • 20-50: très marqué.
                            </small>
                        </div>

                        <div class="form-group bg-light p-3 border rounded mb-3">
                            <label class="font-weight-bold">Nombre de points de changement (n_changepoints)</label>
                            <?= $this->Form->control('prophet_defaults.n_changepoints', [
                                'type' => 'number',
                                'min' => 0,
                                'max' => 100,
                                'value' => $prophetDefaults['n_changepoints'] ?? 25,
                                'label' => false,
                                'class' => 'form-control form-control-sm',
                                'style' => 'max-width: 120px;',
                            ]) ?>
                            <small class="form-text text-muted">
                                0: tendance constante • 5-15: changements importants • 25+: très sensible.
                            </small>
                        </div>

                        <div class="form-group bg-light p-3 border rounded">
                            <div class="form-check">
                                <?= $this->Form->checkbox('prophet_defaults.use_french_holidays', [
                                    'checked' => $prophetDefaults['use_french_holidays'] ?? true,
                                    'class' => 'form-check-input',
                                    'id' => 'prophet_defaults_use_french_holidays',
                                ]) ?>
                                <label class="form-check-label" for="prophet_defaults_use_french_holidays">
                                    <strong>Prendre en compte les jours fériés français</strong>
                                </label>
                            </div>
                            <small class="form-text text-muted">
                                Les jours fériés sont automatiquement mis à 0 dans les prévisions.
                            </small>
                        </div>
                    </div>
                </div>

                <h6 class="text-primary mt-3 mb-2"><i class="bi bi-calendar-range"></i> Plage de données historiques par défaut (système)</h6>
                <div class="form-group bg-light p-3 border rounded">
                    <p class="small text-muted mb-2">
                        Cette plage globale est utilisée comme base pour toutes les offres, puis surchargée
                        par la plage définie au niveau de chaque offre si elle existe.
                        Elle s’applique <strong>quelle que soit la méthode</strong> (Moyenne historique ou Prophet).
                    </p>
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
                        Laissez les dates vides pour utiliser tout l'historique disponible par défaut.
                        Chaque offre peut ensuite restreindre sa propre fenêtre si nécessaire.
                    </small>
                </div>
            </div>
        </div>

        <?php // --- Temps de recherche des Solveurs --- ?>
        <?php $solverDefaults = ['global' => 300, 'pass1' => 60, 'pass1_5' => 30, 'pass2' => 195]; ?>
        <div class="card border-danger mb-4" id="solver-timeout-section">
            <div class="card-header bg-danger text-white">
                <i class="bi bi-cpu"></i> Temps de recherche des Solveurs (Timeouts en secondes)
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">
                    <i class="bi bi-info-circle"></i>
                    Ces limites définissent le temps maximum alloué à chaque solveur CP-SAT.
                    Le <strong>Global</strong> représente le timeout réseau total côté PHP (appel HTTP vers le solveur Python).
                    La somme des passes (P1 + P1.5 + P2) ne doit pas dépasser <strong>Global - 15s</strong> (marge réseau/PHP).
                </p>
                <div id="solver-timeout-error" class="alert alert-danger py-2 mb-3" style="display:none;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span id="solver-timeout-error-msg"></span>
                </div>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">
                            <i class="bi bi-globe2"></i> Limite Globale infrastructure
                        </label>
                        <?= $this->Form->control('solver_settings_json.global', [
                            'type' => 'number',
                            'min' => 15,
                            'step' => 1,
                            'required' => true,
                            'label' => false,
                            'class' => 'form-control solver-timeout-field',
                            'id' => 'solver-global',
                            'value' => $solverDefaults['global'],
                        ]) ?>
                        <small class="text-muted">Timeout HTTP côté PHP (doit couvrir toutes les passes + marge)</small>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">
                            <i class="bi bi-1-circle-fill"></i> Passe 1 : Activités fixes
                        </label>
                        <?= $this->Form->control('solver_settings_json.pass1', [
                            'type' => 'number',
                            'min' => 1,
                            'step' => 1,
                            'required' => true,
                            'label' => false,
                            'class' => 'form-control solver-timeout-field',
                            'id' => 'solver-pass1',
                            'value' => $solverDefaults['pass1'],
                        ]) ?>
                        <small class="text-muted">Solveur Passe 1 — solve-fixed-activities</small>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">
                            <i class="bi bi-arrow-repeat"></i> Passe 1.5 : Rotation
                        </label>
                        <?= $this->Form->control('solver_settings_json.pass1_5', [
                            'type' => 'number',
                            'min' => 1,
                            'step' => 1,
                            'required' => true,
                            'label' => false,
                            'class' => 'form-control solver-timeout-field',
                            'id' => 'solver-pass1_5',
                            'value' => $solverDefaults['pass1_5'],
                        ]) ?>
                        <small class="text-muted">Solveur Passe 1.5 — solve-rotation</small>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">
                            <i class="bi bi-2-circle-fill"></i> Passe 2 : Couverture
                        </label>
                        <?= $this->Form->control('solver_settings_json.pass2', [
                            'type' => 'number',
                            'min' => 1,
                            'step' => 1,
                            'required' => true,
                            'label' => false,
                            'class' => 'form-control solver-timeout-field',
                            'id' => 'solver-pass2',
                            'value' => $solverDefaults['pass2'],
                        ]) ?>
                        <small class="text-muted">Solveur Passe 2 — solve-coverage</small>
                    </div>
                </div>
                <small class="text-muted d-block mt-2">
                    <i class="bi bi-calculator"></i>
                    Budget vérifié en temps réel : <strong>P1 + P1.5 + P2 ≤ Global - 15s</strong>.
                    Le bouton de création se désactive automatiquement si la somme dépasse le budget.
                </small>
            </div>
        </div>

        <?php // --- Boutons d'action --- ?>
        <div class="mt-3">
            <?= $this->Form->button('<i class="bi bi-save mr-2"></i> Créer', [
                'class' => 'btn btn-success mr-3',
                'escapeTitle' => false,
                'id' => 'solver-submit-btn',
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
document.addEventListener('DOMContentLoaded', function () {
    var fields = [
        document.getElementById('solver-global'),
        document.getElementById('solver-pass1'),
        document.getElementById('solver-pass1_5'),
        document.getElementById('solver-pass2'),
    ];
    if (!fields[0]) {
        return;
    }
    var submitBtn = document.getElementById('solver-submit-btn');
    var errorDiv = document.getElementById('solver-timeout-error');
    var errorMsg = document.getElementById('solver-timeout-error-msg');
    var section = document.getElementById('solver-timeout-section');

    function validateTimeouts() {
        var g = parseInt(fields[0].value, 10);
        var p1 = parseInt(fields[1].value, 10);
        var p15 = parseInt(fields[2].value, 10);
        var p2 = parseInt(fields[3].value, 10);

        if (isNaN(g) || isNaN(p1) || isNaN(p15) || isNaN(p2)) {
            if (errorDiv) { errorDiv.style.display = 'none'; }
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('disabled');
                submitBtn.title = 'Veuillez remplir tous les champs.';
            }
            return;
        }

        var sum = p1 + p15 + p2;
        var limit = g - 15;

        if (sum > limit) {
            if (errorDiv) {
                errorDiv.style.display = '';
                errorMsg.textContent =
                    'Erreur : La somme des passes (' + sum + 's) dépasse le budget global (' +
                    g + 's) moins la marge réseau de 15s. Limite autorisée : ' + limit + 's.';
            }
            if (section) {
                section.classList.add('border-danger');
                section.classList.remove('border-success');
            }
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('disabled');
                submitBtn.title = 'La somme des passes dépasse le budget global.';
            }
        } else {
            if (errorDiv) {
                errorDiv.style.display = 'none';
            }
            if (section) {
                section.classList.remove('border-danger');
                section.classList.add('border-success');
            }
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('disabled');
                submitBtn.title = '';
            }
        }
    }

    fields.forEach(function (f) {
        if (f) {
            f.addEventListener('input', validateTimeouts);
            f.addEventListener('change', validateTimeouts);
        }
    });

    validateTimeouts();
});
</script>
