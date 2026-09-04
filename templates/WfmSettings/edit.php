<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\WfmSetting $wfmSetting
 */
?>
<?php $this->assign('title', 'Éditer Profil : ' . h($wfmSetting->name)); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>
<?php $this->Html->script('wfm-settings', ['block' => true]); ?>

<div class="crud-app wfm-settings form crud-app-wide content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-pencil"></i>
            Éditer <?= h($wfmSetting->name) ?>
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-x-circle me-1"></i> Annuler',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
        <?= $this->Form->create($wfmSetting, [
            'data-slot-minutes' => (int)$slotMinutes,
        ]) ?>
        
        <?php // --- Nom du profil --- ?>
        <section class="crud-section">
            <h2 class="crud-section-title"><i class="bi bi-tag"></i> Nom du profil</h2>
                <label class="form-label"><i class="bi bi-pencil-square"></i> Nom</label>
                <?= $this->Form->control('name', [
                    'label' => false,
                    'class' => 'form-control'
                ]) ?>
        </section>

        <?php // --- Qualité de Service --- ?>
        <section class="crud-section">
            <h2 class="crud-section-title"><i class="bi bi-graph-up"></i> Qualité de Service (QS)</h2>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-percent"></i> Objectif QS (%)</label>
                        <?= $this->Form->control('service_level_percent', [
                            'label' => false,
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-clock"></i> Délai QS (secondes)</label>
                        <?= $this->Form->control('service_level_seconds', [
                            'label' => false,
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                </div>
        </section>

        <?php // --- Plage horaire de production --- ?>
        <section class="crud-section">
            <h2 class="crud-section-title"><i class="bi bi-clock-history"></i> Plage horaire de production</h2>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">
                            <i class="bi bi-sunrise"></i> Début de journée
                        </label>
                        <?= $this->Form->control('day_start_time', [
                            'label' => false,
                            'type' => 'time',
                            'class' => 'form-control',
                        ]) ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">
                            <i class="bi bi-arrow-left-right"></i> Heure de bascule Matin/Après-midi (Pivot)
                        </label>
                        <?= $this->Form->control('half_day_pivot', [
                            'label' => false,
                            'type' => 'time',
                            'class' => 'form-control',
                        ]) ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">
                            <i class="bi bi-sunset"></i> Fin de journée
                        </label>
                        <?= $this->Form->control('day_end_time', [
                            'label' => false,
                            'type' => 'time',
                            'class' => 'form-control',
                        ]) ?>
                    </div>
                </div>
                <small class="text-muted">
                    <i class="bi bi-info-circle"></i>
                    Ces heures définissent la plage utilisée pour les prévisions et la génération des plannings.
                    L'heure pivot marque la transition Matin/Après-midi pour l'import Excel (sans trou de pause déjeuner).
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

                <hr class="my-4">

                <div class="row">
                    <div class="col-12">
                        <label class="form-label">
                            <i class="bi bi-calendar-week"></i> Jours travaillés (pour le calcul des plages)
                        </label>
                        <?= $this->Form->control('worked_days_json', [
                            'type' => 'select',
                            'multiple' => 'checkbox',
                            'options' => [
                                1 => 'Lundi',
                                2 => 'Mardi',
                                3 => 'Mercredi',
                                4 => 'Jeudi',
                                5 => 'Vendredi',
                                6 => 'Samedi',
                                7 => 'Dimanche',
                            ],
                            'label' => false,
                            'class' => 'form-check-input',
                        ]) ?>
                        <small class="text-muted d-block mt-2">
                            <i class="bi bi-info-circle"></i>
                            Sélectionnez les jours où l'activité est planifiée. Ces jours seront utilisés pour le calcul des plages de prévision.
                        </small>
                    </div>
                </div>
        </section>

        <?php // --- Règles Générales --- ?>
        <section class="crud-section">
            <h2 class="crud-section-title"><i class="bi bi-sliders"></i> Règles Générales</h2>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><i class="bi bi-percent"></i> Shrinkage planifié (%)</label>
                        <?= $this->Form->control('shrinkage_percent', [
                            'label' => false,
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><i class="bi bi-hourglass-split"></i> Durée min bloc travail (min)</label>
                        <?= $this->Form->control('min_block_minutes', [
                            'label' => false,
                            'type' => 'number',
                            'step' => $slotMinutes,
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><i class="bi bi-hourglass"></i> Durée max bloc travail (min)</label>
                        <?= $this->Form->control('max_block_minutes', [
                            'label' => false,
                            'type' => 'number',
                            'step' => $slotMinutes,
                            'class' => 'form-control'
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
                        ]) ?>
                        <small class="text-muted d-block">
                            <i class="bi bi-info-circle"></i>
                            Si activé, le solveur interdira d'affecter les offres marquées incompatibles sur les créneaux télétravail.
                        </small>
                    </div>
                </div>
        </section>

        <?php // --- Configuration des Pauses --- ?>
        <section class="crud-section">
            <h2 class="crud-section-title"><i class="bi bi-cup-hot"></i> Configuration des Pauses</h2>
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
                        'class' => 'form-control'
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
                                'class' => 'form-control form-control-sm'
                            ]) ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label small">Fenêtre - Début</label>
                            <?= $this->Form->control('am_pause_start_time', [
                                'label' => false,
                                'class' => 'form-control form-control-sm'
                            ]) ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label small">Fenêtre - Fin</label>
                            <?= $this->Form->control('am_pause_end_time', [
                                'label' => false,
                                'class' => 'form-control form-control-sm'
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
                                'class' => 'form-control form-control-sm'
                            ]) ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label small">Fenêtre - Début</label>
                            <?= $this->Form->control('lunch_start_time', [
                                'label' => false,
                                'class' => 'form-control form-control-sm'
                            ]) ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label small">Fenêtre - Fin</label>
                            <?= $this->Form->control('lunch_end_time', [
                                'label' => false,
                                'class' => 'form-control form-control-sm'
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
                                'class' => 'form-control form-control-sm'
                            ]) ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label small">Fenêtre - Début</label>
                            <?= $this->Form->control('pm_pause_start_time', [
                                'label' => false,
                                'class' => 'form-control form-control-sm'
                            ]) ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label small">Fenêtre - Fin</label>
                            <?= $this->Form->control('pm_pause_end_time', [
                                'label' => false,
                                'class' => 'form-control form-control-sm'
                            ]) ?>
                        </div>
                    </div>
                </div>
        </section>

        <?php // --- Paramètres Prophet par défaut (système) --- ?>
        <section class="crud-section">
            <h2 class="crud-section-title"><i class="bi bi-stars"></i> Paramètres Prophet par défaut (profil système WFM)</h2>
                <p class="small text-muted">
                    Ces paramètres servent de base pour initialiser les profils Prophet de chaque offre
                    (`prophet_default_settings_json`). Ils sont également utilisés comme valeur de repli
                    si un profil d’offre est incomplet.
                </p>

                <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary"><i class="bi bi-gear"></i> Configuration du modèle</h6>
                            <div class="mb-3 bg-light p-3 border rounded">
                                <label class="font-weight-bold">Mode de Saisonnalité</label>
                                <?= $this->Form->control('prophet_defaults.seasonality_mode', [
                                    'type' => 'select',
                                    'options' => [
                                        'additive' => 'Additif (y = trend + seasonality)',
                                        'multiplicative' => 'Multiplicatif (y = trend × seasonality)'
                                    ],
                                    'value' => $prophetDefaults['seasonality_mode'] ?? 'multiplicative',
                                    'label' => false,
                                    'class' => 'form-control'
                                ]) ?>
                            </div>

                            <h6 class="text-primary mt-4"><i class="bi bi-calendar-event"></i> Saisonnalités</h6>
                            <div class="mb-3 bg-light p-3 border rounded">
                                <div class="form-check mb-2">
                                    <?= $this->Form->checkbox('prophet_defaults.yearly_seasonality', [
                                        'checked' => $prophetDefaults['yearly_seasonality'] ?? true,
                                        'class' => 'form-check-input',
                                        'id' => 'prophet_defaults_yearly_seasonality'
                                    ]) ?>
                                    <label class="form-check-label" for="prophet_defaults_yearly_seasonality">
                                        <strong>Saisonnalité Annuelle</strong>
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <?= $this->Form->checkbox('prophet_defaults.weekly_seasonality', [
                                        'checked' => $prophetDefaults['weekly_seasonality'] ?? true,
                                        'class' => 'form-check-input',
                                        'id' => 'prophet_defaults_weekly_seasonality'
                                    ]) ?>
                                    <label class="form-check-label" for="prophet_defaults_weekly_seasonality">
                                        <strong>Saisonnalité Hebdomadaire</strong>
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <?= $this->Form->checkbox('prophet_defaults.monthly_seasonality', [
                                        'checked' => $prophetDefaults['monthly_seasonality'] ?? true,
                                        'class' => 'form-check-input',
                                        'id' => 'prophet_defaults_monthly_seasonality'
                                    ]) ?>
                                    <label class="form-check-label" for="prophet_defaults_monthly_seasonality">
                                        <strong>Saisonnalité Mensuelle</strong>
                                    </label>
                                    <div class="mt-2">
                                        <label class="small font-weight-bold">Complexité du pattern mensuel (Fourier Order)</label>
                                        <?= $this->Form->control('prophet_defaults.monthly_fourier_order', [
                                            'type' => 'number',
                                            'min' => 1,
                                            'max' => 15,
                                            'value' => $prophetDefaults['monthly_fourier_order'] ?? 5,
                                            'label' => false,
                                            'class' => 'form-control form-control-sm',
                                            'style' => 'max-width: 100px;'
                                        ]) ?>
                                        <small class="form-text text-muted">
                                            1-3: Simple | <strong>5: Recommandé</strong> | 8-10: Complexe | 12-15: Maximum
                                        </small>
                                    </div>
                                </div>
                                <div class="form-check mb-2">
                                    <?= $this->Form->checkbox('prophet_defaults.daily_seasonality', [
                                        'checked' => $prophetDefaults['daily_seasonality'] ?? true,
                                        'class' => 'form-check-input',
                                        'id' => 'prophet_defaults_daily_seasonality'
                                    ]) ?>
                                    <label class="form-check-label" for="prophet_defaults_daily_seasonality">
                                        <strong>Saisonnalité Journalière</strong>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h6 class="text-primary"><i class="bi bi-sliders"></i> Sensibilité & Stabilité</h6>
                            <div class="mb-3 bg-light p-3 border rounded">
                                <label class="font-weight-bold">Sensibilité aux Changements (changepoint_prior_scale)</label>
                                <?= $this->Form->control('prophet_defaults.changepoint_prior_scale', [
                                    'type' => 'number',
                                    'step' => 'any',
                                    'min' => 0.001,
                                    'max' => 0.5,
                                    'value' => $prophetDefaults['changepoint_prior_scale'] ?? 0.1,
                                    'label' => false,
                                    'class' => 'form-control form-control-sm',
                                    'style' => 'max-width: 120px;'
                                ]) ?>
                                <small class="form-text text-muted">
                                    0.001-0.01: très stable • 0.1: standard • 0.2-0.5: très réactif.
                                </small>
                            </div>

                            <div class="mb-3 bg-light p-3 border rounded">
                                <label class="font-weight-bold">Force de la Saisonnalité (seasonality_prior_scale)</label>
                                <?= $this->Form->control('prophet_defaults.seasonality_prior_scale', [
                                    'type' => 'number',
                                    'step' => 'any',
                                    'min' => 0.01,
                                    'max' => 100,
                                    'value' => $prophetDefaults['seasonality_prior_scale'] ?? 10.0,
                                    'label' => false,
                                    'class' => 'form-control form-control-sm',
                                    'style' => 'max-width: 120px;'
                                ]) ?>
                                <small class="form-text text-muted">
                                    0.1-1: faible • 10: standard • 20-50: très marqué.
                                </small>
                            </div>

                            <div class="mb-3 bg-light p-3 border rounded">
                                <label class="font-weight-bold">Nombre de Points de Changement (n_changepoints)</label>
                                <?= $this->Form->control('prophet_defaults.n_changepoints', [
                                    'type' => 'number',
                                    'min' => 0,
                                    'max' => 100,
                                    'value' => $prophetDefaults['n_changepoints'] ?? 25,
                                    'label' => false,
                                    'class' => 'form-control form-control-sm',
                                    'style' => 'max-width: 120px;'
                                ]) ?>
                                <small class="form-text text-muted">
                                    0: tendance constante • 5-15: changements importants • 25+: très sensible.
                                </small>
                            </div>

                            <div class="mb-3 bg-light p-3 border rounded">
                                <div class="form-check">
                                    <?= $this->Form->checkbox('prophet_defaults.use_french_holidays', [
                                        'checked' => $prophetDefaults['use_french_holidays'] ?? true,
                                        'class' => 'form-check-input',
                                        'id' => 'prophet_defaults_use_french_holidays'
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

                    <h6 class="text-primary mt-3 mb-2"><i class="bi bi-calendar-range"></i> Plage de données historiques par défaut (système)</h6>
                    <div class="mb-3 bg-light p-3 border rounded">
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
        </section>

        <?php // --- Paramètres Optuna (moteur de tuning) --- ?>
        <?php $optunaSettings = $optunaSettings ?? \App\Service\ProphetOptunaConfig::DEFAULTS; ?>
        <section class="crud-section">
            <h2 class="crud-section-title"><i class="bi bi-cpu"></i> Tuning Optuna (moteur global)</h2>
                <p class="small text-muted">
                    Configuration du worker de tuning Prophet. Les offres activées peuvent être
                    tunées manuellement ou via le <strong>ticker cron</strong>
                    (<code>bin/cake prophet_tuning_scheduler_ticker</code>).
                    Fuseau : <strong>Europe/Paris</strong>.
                </p>
                <div class="alert alert-info py-2 small mb-3">
                    <?= \App\Service\ProphetOptunaConfig::fixedRulesHelpHtml() ?>
                    Les bornes ci-dessous concernent uniquement les 4 paramètres tunables
                    (priors, n_changepoints, fourier mensuel).
                </div>
                <?php
                $optunaCronEstimate = $optunaCronEstimate ?? null;
                $cronWeekdays = \App\Service\ProphetOptunaConfig::normalizeWeekdays($optunaSettings['cron_weekdays'] ?? [7]);
                ?>
                <?php if ($optunaCronEstimate): ?>
                    <div class="alert <?= !empty($optunaCronEstimate['overflow_risk']) ? 'alert-warning' : 'alert-secondary' ?> py-2 small mb-3"
                         id="optuna-cron-estimate"
                         data-enabled-offers="<?= (int)$optunaCronEstimate['enabled_offers'] ?>"
                         data-sec-per-trial="<?= h((string)$optunaCronEstimate['seconds_per_trial']) ?>"
                         data-workday-start="<?= (int)$optunaCronEstimate['workday_start_hour'] ?>">
                        <strong>Estimation d’une vague cron :</strong>
                        <span data-est-summary>
                            <?= (int)$optunaCronEstimate['enabled_offers'] ?> offre(s) ×
                            <?= (int)$optunaCronEstimate['n_trials'] ?> trials ≈
                            <strong><?= h($optunaCronEstimate['total_human']) ?></strong>
                            (fin estimée ~ <?= h($optunaCronEstimate['estimated_end']) ?> Europe/Paris)
                        </span>
                        <br>
                        <span class="text-muted">
                            Base temps/trial :
                            <?php if (($optunaCronEstimate['seconds_per_trial_source'] ?? '') === 'history'): ?>
                                historique (<?= (int)$optunaCronEstimate['sample_count'] ?> jobs) —
                            <?php else: ?>
                                heuristique (pas encore assez d’historique) —
                            <?php endif; ?>
                            ~<?= h((string)$optunaCronEstimate['seconds_per_trial']) ?> s / trial.
                            Ordre de grandeur uniquement.
                        </span>
                        <span data-est-overflow style="<?= !empty($optunaCronEstimate['overflow_risk']) ? '' : 'display:none' ?>">
                            <br><strong>Attention :</strong> fin estimée susceptible de chevaucher une journée ouvrée
                            (à partir de <?= (int)$optunaCronEstimate['workday_start_hour'] ?>h).
                        </span>
                    </div>
                <?php endif; ?>
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary"><i class="bi bi-sliders"></i> Backtest &amp; budget</h6>
                        <div class="mb-3 bg-light p-3 border rounded">
                            <label class="font-weight-bold">Horizon de test (jours)</label>
                            <?= $this->Form->control('optuna_settings.test_horizon_days', [
                                'type' => 'number',
                                'min' => 7,
                                'max' => 60,
                                'value' => $optunaSettings['test_horizon_days'] ?? 14,
                                'label' => false,
                                'class' => 'form-control form-control-sm',
                                'style' => 'max-width: 120px;',
                            ]) ?>
                            <small class="form-text text-muted">7–60. Défaut 14. Cutoffs walk-forward : 3 (fixe V1).</small>
                        </div>
                        <div class="mb-3 bg-light p-3 border rounded">
                            <label class="font-weight-bold">Nombre d’essais Optuna (n_trials)</label>
                            <?= $this->Form->control('optuna_settings.n_trials', [
                                'type' => 'number',
                                'min' => 10,
                                'max' => 200,
                                'value' => $optunaSettings['n_trials'] ?? 50,
                                'label' => false,
                                'class' => 'form-control form-control-sm',
                                'style' => 'max-width: 120px;',
                                'id' => 'optuna_settings_n_trials',
                            ]) ?>
                        </div>
                        <div class="mb-3 bg-light p-3 border rounded">
                            <label class="font-weight-bold">Historique minimum (jours)</label>
                            <?= $this->Form->control('optuna_settings.min_history_days', [
                                'type' => 'number',
                                'min' => 30,
                                'max' => 3650,
                                'value' => $optunaSettings['min_history_days'] ?? 90,
                                'label' => false,
                                'class' => 'form-control form-control-sm',
                                'style' => 'max-width: 120px;',
                            ]) ?>
                        </div>
                        <div class="mb-3 bg-light p-3 border rounded">
                            <div class="form-check mb-2">
                                <?= $this->Form->checkbox('optuna_settings.cron_enabled', [
                                    'checked' => !empty($optunaSettings['cron_enabled']),
                                    'class' => 'form-check-input',
                                    'id' => 'optuna_settings_cron_enabled',
                                ]) ?>
                                <label class="form-check-label" for="optuna_settings_cron_enabled">
                                    <strong>Activer le cron de tuning</strong>
                                </label>
                            </div>
                            <label class="small font-weight-bold d-block">Jours autorisés</label>
                            <div class="mb-2">
                                <?php foreach (\App\Service\ProphetOptunaConfig::WEEKDAY_LABELS as $dow => $label): ?>
                                    <div class="form-check form-check-inline">
                                        <input type="checkbox"
                                               class="form-check-input"
                                               name="optuna_settings[cron_weekdays][]"
                                               id="optuna_wd_<?= (int)$dow ?>"
                                               value="<?= (int)$dow ?>"
                                               <?= in_array((int)$dow, $cronWeekdays, true) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="optuna_wd_<?= (int)$dow ?>"><?= h($label) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="d-flex flex-wrap align-items-end mb-2">
                                <div class="me-3 mb-2">
                                    <label class="small font-weight-bold">Heure (Paris)</label>
                                    <?= $this->Form->control('optuna_settings.cron_hour', [
                                        'type' => 'number', 'min' => 0, 'max' => 23,
                                        'value' => $optunaSettings['cron_hour'] ?? 2,
                                        'label' => false, 'class' => 'form-control form-control-sm',
                                        'style' => 'max-width: 80px;', 'id' => 'optuna_settings_cron_hour',
                                    ]) ?>
                                </div>
                                <div class="me-3 mb-2">
                                    <label class="small font-weight-bold">Minute</label>
                                    <?= $this->Form->control('optuna_settings.cron_minute', [
                                        'type' => 'number', 'min' => 0, 'max' => 59,
                                        'value' => $optunaSettings['cron_minute'] ?? 0,
                                        'label' => false, 'class' => 'form-control form-control-sm',
                                        'style' => 'max-width: 80px;', 'id' => 'optuna_settings_cron_minute',
                                    ]) ?>
                                </div>
                                <div class="me-3 mb-2">
                                    <label class="small font-weight-bold">Périodicité / offre (j)</label>
                                    <?= $this->Form->control('optuna_settings.cron_period_days', [
                                        'type' => 'number', 'min' => 1, 'max' => 90,
                                        'value' => $optunaSettings['cron_period_days'] ?? 7,
                                        'label' => false, 'class' => 'form-control form-control-sm',
                                        'style' => 'max-width: 80px;',
                                    ]) ?>
                                </div>
                                <div class="mb-2">
                                    <label class="small font-weight-bold">Alerte ouvrée dès (h)</label>
                                    <?= $this->Form->control('optuna_settings.cron_workday_start_hour', [
                                        'type' => 'number', 'min' => 0, 'max' => 23,
                                        'value' => $optunaSettings['cron_workday_start_hour'] ?? 8,
                                        'label' => false, 'class' => 'form-control form-control-sm',
                                        'style' => 'max-width: 80px;', 'id' => 'optuna_settings_cron_workday_start_hour',
                                    ]) ?>
                                </div>
                            </div>
                            <small class="form-text text-muted">
                                Ex. Dimanche 02:00, périodicité 7. Le ticker doit tourner en permanence.
                            </small>
                        </div>
                        <div class="mb-3 bg-light p-3 border rounded">
                            <div class="form-check mb-2">
                                <?= $this->Form->checkbox('optuna_settings.auto_apply', [
                                    'checked' => !empty($optunaSettings['auto_apply']),
                                    'class' => 'form-check-input',
                                    'id' => 'optuna_settings_auto_apply',
                                ]) ?>
                                <label class="form-check-label" for="optuna_settings_auto_apply">
                                    <strong>Auto-écriture du profil si amélioration</strong>
                                </label>
                            </div>
                            <label class="small font-weight-bold">Seuil d’amélioration WAPE minimale (%)</label>
                            <?= $this->Form->control('optuna_settings.auto_apply_min_mae_improvement_pct', [
                                'type' => 'number',
                                'step' => 'any',
                                'min' => 0,
                                'max' => 100,
                                'value' => $optunaSettings['auto_apply_min_mae_improvement_pct'] ?? 5,
                                'label' => false,
                                'class' => 'form-control form-control-sm',
                                'style' => 'max-width: 120px;',
                            ]) ?>
                            <small class="form-text text-muted">
                                Désactivé par défaut. Le % s’applique au WAPE 15 min (walk-forward 3×14 j),
                                pas à la MAE. Clé JSON inchangée. Recalibrer après rétro-analyse ;
                                ne pas recopier 5 % MAE tel quel.
                            </small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary"><i class="bi bi-bounding-box"></i> Bornes de recherche</h6>
                        <div class="mb-3 bg-light p-3 border rounded">
                            <label class="font-weight-bold">changepoint_prior_scale (min / max)</label>
                            <div class="d-flex align-items-center">
                                <?= $this->Form->control('optuna_settings.changepoint_prior_scale_min', [
                                    'type' => 'number', 'step' => 'any', 'min' => 0.001, 'max' => 0.5,
                                    'value' => $optunaSettings['changepoint_prior_scale_min'] ?? 0.001,
                                    'label' => false, 'class' => 'form-control form-control-sm me-2', 'style' => 'max-width: 110px;',
                                ]) ?>
                                <span class="mx-1">→</span>
                                <?= $this->Form->control('optuna_settings.changepoint_prior_scale_max', [
                                    'type' => 'number', 'step' => 'any', 'min' => 0.001, 'max' => 0.5,
                                    'value' => $optunaSettings['changepoint_prior_scale_max'] ?? 0.5,
                                    'label' => false, 'class' => 'form-control form-control-sm ms-2', 'style' => 'max-width: 110px;',
                                ]) ?>
                            </div>
                        </div>
                        <div class="mb-3 bg-light p-3 border rounded">
                            <label class="font-weight-bold">seasonality_prior_scale (min / max)</label>
                            <div class="d-flex align-items-center">
                                <?= $this->Form->control('optuna_settings.seasonality_prior_scale_min', [
                                    'type' => 'number', 'step' => 'any', 'min' => 0.01, 'max' => 100,
                                    'value' => $optunaSettings['seasonality_prior_scale_min'] ?? 0.01,
                                    'label' => false, 'class' => 'form-control form-control-sm me-2', 'style' => 'max-width: 110px;',
                                ]) ?>
                                <span class="mx-1">→</span>
                                <?= $this->Form->control('optuna_settings.seasonality_prior_scale_max', [
                                    'type' => 'number', 'step' => 'any', 'min' => 0.01, 'max' => 100,
                                    'value' => $optunaSettings['seasonality_prior_scale_max'] ?? 100,
                                    'label' => false, 'class' => 'form-control form-control-sm ms-2', 'style' => 'max-width: 110px;',
                                ]) ?>
                            </div>
                        </div>
                        <div class="mb-3 bg-light p-3 border rounded">
                            <label class="font-weight-bold">n_changepoints (min / max)</label>
                            <div class="d-flex align-items-center">
                                <?= $this->Form->control('optuna_settings.n_changepoints_min', [
                                    'type' => 'number', 'min' => 1, 'max' => 100,
                                    'value' => $optunaSettings['n_changepoints_min'] ?? 10,
                                    'label' => false, 'class' => 'form-control form-control-sm me-2', 'style' => 'max-width: 110px;',
                                ]) ?>
                                <span class="mx-1">→</span>
                                <?= $this->Form->control('optuna_settings.n_changepoints_max', [
                                    'type' => 'number', 'min' => 1, 'max' => 100,
                                    'value' => $optunaSettings['n_changepoints_max'] ?? 50,
                                    'label' => false, 'class' => 'form-control form-control-sm ms-2', 'style' => 'max-width: 110px;',
                                ]) ?>
                            </div>
                        </div>
                        <div class="mb-3 bg-light p-3 border rounded">
                            <label class="font-weight-bold">monthly_fourier_order (min / max)</label>
                            <div class="d-flex align-items-center">
                                <?= $this->Form->control('optuna_settings.monthly_fourier_order_min', [
                                    'type' => 'number', 'min' => 1, 'max' => 15,
                                    'value' => $optunaSettings['monthly_fourier_order_min'] ?? 3,
                                    'label' => false, 'class' => 'form-control form-control-sm me-2', 'style' => 'max-width: 110px;',
                                ]) ?>
                                <span class="mx-1">→</span>
                                <?= $this->Form->control('optuna_settings.monthly_fourier_order_max', [
                                    'type' => 'number', 'min' => 1, 'max' => 15,
                                    'value' => $optunaSettings['monthly_fourier_order_max'] ?? 10,
                                    'label' => false, 'class' => 'form-control form-control-sm ms-2', 'style' => 'max-width: 110px;',
                                ]) ?>
                            </div>
                        </div>
                    </div>
                </div>
        </section>

        <?php // --- Temps de recherche des Solveurs --- ?>
        <?php
            $solver = $wfmSetting->solver_settings_json;
            $solverDefaults = ['global' => 300, 'pass1' => 60, 'pass1_5' => 30, 'pass2' => 195];
        ?>
        <section class="crud-section" id="solver-timeout-section">
            <h2 class="crud-section-title"><i class="bi bi-cpu"></i> Temps de recherche des Solveurs (Timeouts en secondes)</h2>
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
                            'value' => $solver['global'] ?? $solverDefaults['global'],
                            'data-default' => $solverDefaults['global'],
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
                            'value' => $solver['pass1'] ?? $solverDefaults['pass1'],
                            'data-default' => $solverDefaults['pass1'],
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
                            'value' => $solver['pass1_5'] ?? $solverDefaults['pass1_5'],
                            'data-default' => $solverDefaults['pass1_5'],
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
                            'value' => $solver['pass2'] ?? $solverDefaults['pass2'],
                            'data-default' => $solverDefaults['pass2'],
                        ]) ?>
                        <small class="text-muted">Solveur Passe 2 — solve-coverage</small>
                    </div>
                </div>
                <small class="text-muted d-block mt-2">
                    <i class="bi bi-calculator"></i>
                    Budget vérifié en temps réel : <strong>P1 + P1.5 + P2 ≤ Global - 15s</strong>.
                    Le bouton d'enregistrement se désactive automatiquement si la somme dépasse le budget.
                </small>
        </section>

        <div class="crud-actions-bar">
            <?= $this->Form->button('<i class="bi bi-save me-2"></i> Enregistrer', [
                'class' => 'btn btn-primary',
                'escapeTitle' => false,
                'id' => 'solver-submit-btn',
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
document.addEventListener('DOMContentLoaded', function () {
    var box = document.getElementById('optuna-cron-estimate');
    var trialsInput = document.getElementById('optuna_settings_n_trials');
    var hourInput = document.getElementById('optuna_settings_cron_hour');
    var minuteInput = document.getElementById('optuna_settings_cron_minute');
    var workdayInput = document.getElementById('optuna_settings_cron_workday_start_hour');
    if (!box || !trialsInput) {
        return;
    }

    function pad(n) {
        return (n < 10 ? '0' : '') + n;
    }

    function formatHuman(totalSec) {
        if (totalSec < 60) {
            return totalSec + ' s';
        }
        var m = Math.floor(totalSec / 60);
        if (m < 60) {
            return m + ' min';
        }
        var h = Math.floor(m / 60);
        var rm = m % 60;
        return h + ' h' + (rm ? ' ' + rm + ' min' : '');
    }

    function refreshEstimate() {
        var enabled = parseInt(box.getAttribute('data-enabled-offers') || '0', 10);
        var secPer = parseFloat(box.getAttribute('data-sec-per-trial') || '180');
        var trials = parseInt(trialsInput.value || '50', 10);
        var hour = parseInt((hourInput && hourInput.value) || '2', 10);
        var minute = parseInt((minuteInput && minuteInput.value) || '0', 10);
        var workday = parseInt((workdayInput && workdayInput.value) || box.getAttribute('data-workday-start') || '8', 10);
        if (isNaN(trials) || trials < 1) {
            trials = 1;
        }
        var totalSec = Math.round(enabled * trials * secPer);
        var start = new Date();
        start.setHours(hour, minute, 0, 0);
        if (start.getTime() < Date.now()) {
            start.setDate(start.getDate() + 1);
        }
        var end = new Date(start.getTime() + totalSec * 1000);
        var endDow = end.getDay(); // 0=Dim … 6=Sam
        var endHour = end.getHours();
        var isWeekday = endDow >= 1 && endDow <= 5;
        var overflow = isWeekday && endHour >= workday;

        var summary = box.querySelector('[data-est-summary]');
        if (summary) {
            summary.innerHTML =
                enabled + ' offre(s) × ' + trials + ' trials ≈ <strong>' + formatHuman(totalSec) + '</strong>' +
                ' (fin estimée ~ ' +
                end.getFullYear() + '-' + pad(end.getMonth() + 1) + '-' + pad(end.getDate()) +
                ' ' + pad(end.getHours()) + ':' + pad(end.getMinutes()) +
                ' Europe/Paris)';
        }
        var ov = box.querySelector('[data-est-overflow]');
        if (ov) {
            ov.style.display = overflow ? '' : 'none';
        }
        box.classList.toggle('alert-warning', overflow);
        box.classList.toggle('alert-secondary', !overflow);
    }

    ['change', 'input'].forEach(function (ev) {
        trialsInput.addEventListener(ev, refreshEstimate);
        if (hourInput) {
            hourInput.addEventListener(ev, refreshEstimate);
        }
        if (minuteInput) {
            minuteInput.addEventListener(ev, refreshEstimate);
        }
        if (workdayInput) {
            workdayInput.addEventListener(ev, refreshEstimate);
        }
    });
});
</script>

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

    // Validation initiale au chargement
    validateTimeouts();
});
</script>
