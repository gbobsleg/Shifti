<?php
/** @var \App\Model\Entity\ForecastScenario $scenario */
?>
<?php $this->assign('title', 'Scénario #' . h($scenario->id)); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>
<?php
$statusLabels = [
    'draft' => 'Brouillon',
    'queued' => 'En file d\'attente',
    'running' => 'En cours',
    'completed' => 'Terminé',
    'failed' => 'Échec',
];
?>

<?php $this->Html->css('daterangepicker', ['block' => true]); ?>
<?php $this->Html->script('moment.min', ['block' => true]); ?>
<?php $this->Html->script('daterangepicker', ['block' => true]); ?>



<div class="crud-app forecast-scenarios view crud-app-wide content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-diagram-3"></i>
            Scénario #<?= h($scenario->id) ?> — <?= h($scenario->name) ?>
        </h1>
        <?php
        $canLaunch = in_array((string)$scenario->status, ['draft', 'failed', 'completed'], true);
        $isInProgress = in_array((string)$scenario->status, ['queued', 'running'], true);
        ?>
        <div class="crud-header-actions">
            <?php if ($canLaunch): ?>
                <?= $this->Html->link(
                    '<i class="bi bi-play-circle-fill me-1"></i> Lancer',
                    ['action' => 'run', $scenario->id],
                    ['class' => 'btn btn-primary', 'escape' => false, 'id' => 'runScenarioLink']
                ) ?>
            <?php endif; ?>
            <?php if ($scenario->status === 'completed'): ?>
                <?php
                $isPublished = !empty($scenario->forecast_scenario_publications);
                ?>
                <?php if ($isPublished): ?>
                    <?= $this->Html->link(
                        '<i class="bi bi-broadcast-pin me-1"></i> Dépublier',
                        ['action' => 'unpublish', $scenario->id],
                        ['class' => 'btn btn-outline-secondary', 'escape' => false, 'confirm' => 'Dépublier ce scénario ? Les données ne seront plus utilisées pour la planification.']
                    ) ?>
                <?php else: ?>
                    <?= $this->Html->link(
                        '<i class="bi bi-broadcast me-1"></i> Publier',
                        ['action' => 'publish', $scenario->id],
                        ['class' => 'btn btn-outline-secondary', 'escape' => false]
                    ) ?>
                <?php endif; ?>
            <?php endif; ?>
            <?= $this->Html->link(
                '<i class="bi bi-pencil me-1"></i> Modifier',
                ['action' => 'edit', $scenario->id],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash me-1"></i> Supprimer',
                ['action' => 'delete', $scenario->id],
                ['confirm' => 'Voulez-vous vraiment supprimer ce scénario ?', 'class' => 'btn btn-outline-danger', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list me-1"></i> Liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div id="scenarioViewContent">
        <?php
        $offersDone = (int)($scenario->progress_offers_done ?? 0);
        $offersTotal = (int)($scenario->progress_offers_total ?? 0);
        $daysDone = (int)($scenario->progress_days_done ?? 0);
        $daysTotal = (int)($scenario->progress_days_total ?? 0);
        $pctDays = $daysTotal > 0 ? (int)round(($daysDone / $daysTotal) * 100) : 0;
        ?>
        <div id="scenarioProgressBanner"
             class="alert alert-warning mb-4<?= $isInProgress ? '' : ' d-none' ?>"
             data-status-url="<?= h($this->Url->build(['action' => 'status', $scenario->id, '_ext' => 'json'])) ?>"
             data-initial-status="<?= h((string)$scenario->status) ?>">
            <div class="d-flex align-items-start">
                <div class="spinner-border text-warning me-3 mt-1" role="status" style="width: 2rem; height: 2rem;">
                    <span class="visually-hidden">Calcul...</span>
                </div>
                <div class="flex-grow-1">
                    <h5 class="alert-heading mb-2">
                        <i class="bi bi-gear-fill"></i>
                        <span id="progressStatusLabel"><?= $scenario->status === 'queued' ? 'En file d\'attente…' : 'Calcul en cours…' ?></span>
                    </h5>
                    <p class="mb-2">
                        <strong>Offre en cours :</strong>
                        <span id="progressOfferName"><?= h($scenario->progress_offer_name ?: '—') ?></span>
                    </p>
                    <p class="mb-2 small text-muted mb-1">
                        Offres :
                        <span id="progressOffersDone"><?= $offersDone ?></span>
                        /
                        <span id="progressOffersTotal"><?= $offersTotal ?></span>
                        &nbsp;·&nbsp;
                        Jours :
                        <span id="progressDaysDone"><?= $daysDone ?></span>
                        /
                        <span id="progressDaysTotal"><?= $daysTotal ?></span>
                    </p>
                    <div class="progress mb-1" style="height: 18px;">
                        <div id="progressBarDays"
                             class="progress-bar progress-bar-striped progress-bar-animated bg-warning"
                             role="progressbar"
                             style="width: <?= $pctDays ?>%;"
                             aria-valuenow="<?= $pctDays ?>"
                             aria-valuemin="0"
                             aria-valuemax="100">
                            <?= $pctDays ?>%
                        </div>
                    </div>
                    <div id="progressError" class="text-danger small mt-2<?= empty($scenario->error_message) ? ' d-none' : '' ?>">
                        <?= h((string)($scenario->error_message ?? '')) ?>
                    </div>
                </div>
            </div>
        </div>

        <section class="crud-section">
            <h2 class="crud-section-title">Informations</h2>
            <?php
            $badgeClass = 'bg-secondary';
            $badgeIcon = 'bi-file-earmark';
            if ($scenario->status === 'queued') {
                $badgeClass = 'bg-warning';
                $badgeIcon = 'bi-hourglass-split';
            } elseif ($scenario->status === 'running') {
                $badgeClass = 'bg-warning';
                $badgeIcon = 'bi-arrow-repeat';
            } elseif ($scenario->status === 'completed') {
                $badgeClass = 'bg-success';
                $badgeIcon = 'bi-check-circle';
            } elseif ($scenario->status === 'failed') {
                $badgeClass = 'bg-danger';
                $badgeIcon = 'bi-exclamation-triangle';
            }
            $duration = null;
            if ($scenario->start_date && $scenario->end_date) {
                $start = new \DateTime($scenario->start_date->format('Y-m-d'));
                $end = new \DateTime($scenario->end_date->format('Y-m-d'));
                $duration = $start->diff($end)->days + 1;
            }
            ?>
            <dl class="crud-fields">
                <div>
                    <dt>Période</dt>
                    <dd>
                        <?= h($scenario->start_date) ?> → <?= h($scenario->end_date) ?>
                        <?php if ($duration !== null): ?>
                            <span class="text-muted">(<?= $duration ?> jour<?= $duration > 1 ? 's' : '' ?>)</span>
                        <?php endif; ?>
                    </dd>
                </div>
                <div id="scenarioStatusCard">
                    <dt>Statut</dt>
                    <dd>
                        <span class="badge <?= $badgeClass ?>" id="scenarioStatusBadge">
                            <i class="bi <?= $badgeIcon ?>"></i> <span id="scenarioStatusText"><?= h($statusLabels[$scenario->status] ?? (string)$scenario->status) ?></span>
                        </span>
                    </dd>
                </div>
                <div>
                    <dt>Publication</dt>
                    <dd>
                        <?php if (!empty($scenario->forecast_scenario_publications)): ?>
                            Publié (<?= count($scenario->forecast_scenario_publications) ?> jour(s))
                        <?php else: ?>
                            Non publié
                        <?php endif; ?>
                    </dd>
                </div>
            </dl>
        </section>

        <?php
        // --- Section Métriques Prophet par Offre ---
        if ($scenario->status === 'completed'):
            $allMetricsData = null;
            if (!empty($scenario->prophet_metrics_json)) {
                $allMetricsData = json_decode($scenario->prophet_metrics_json, true);
            }

            if ($allMetricsData && !empty($allMetricsData['per_offer'])):
        ?>
        <section class="crud-section">
            <h2 class="crud-section-title">Métriques Prophet par Offre</h2>
            <?php foreach ($allMetricsData['per_offer'] as $offerMetric):
                $offerId = $offerMetric['offer_id'];
                $metrics = $offerMetric['metrics'];

                $offerName = 'Offre #' . $offerId;
                foreach ($scenario->forecast_scenarios_offers as $link) {
                    if ($link->offer_id == $offerId) {
                        $offerName = $link->offer->name ?? $offerName;
                        break;
                    }
                }

                $mape = $metrics['mape'];
                $mapeClass = $mape < 20 ? 'text-success' : ($mape < 30 ? 'text-warning' : 'text-danger');
                if ($mape < 20) {
                    $mapeLabel = 'Excellente précision';
                } elseif ($mape < 30) {
                    $mapeLabel = 'Bonne précision';
                } elseif ($mape < 100) {
                    $mapeLabel = 'Précision à améliorer';
                } else {
                    $mapeLabel = 'Précision très faible — revoir les paramètres';
                }
            ?>
            <h3 class="crud-subsection-title"><?= h($offerName) ?></h3>
            <dl class="crud-fields">
                <div>
                    <dt>
                        <span data-bs-toggle="tooltip" data-placement="top"
                              title="Erreur moyenne en pourcentage. Plus c'est bas, meilleures sont les prévisions. < 20% = Excellent, < 30% = Bon, > 30% = À améliorer">
                            MAPE <i class="bi bi-question-circle text-info"></i>
                        </span>
                    </dt>
                    <dd>
                        <span class="<?= $mapeClass ?>"><?= h($mape) ?>%</span>
                        <span class="text-muted"> — <?= h($mapeLabel) ?></span>
                    </dd>
                </div>
                <div>
                    <dt>
                        <span data-bs-toggle="tooltip" data-placement="top"
                              title="Erreur Absolue Moyenne. Nombre moyen d'appels d'écart entre prévisions et réalité (par intervalle de 15 min)">
                            MAE <i class="bi bi-question-circle text-info"></i>
                        </span>
                    </dt>
                    <dd><?= h($metrics['mae']) ?></dd>
                </div>
                <div>
                    <dt>
                        <span data-bs-toggle="tooltip" data-placement="top"
                              title="Erreur Quadratique Moyenne. Similaire au MAE mais pénalise davantage les grosses erreurs. Plus sensible aux pics d'erreur.">
                            RMSE <i class="bi bi-question-circle text-info"></i>
                        </span>
                    </dt>
                    <dd><?= h($metrics['rmse']) ?></dd>
                </div>
            </dl>
            <?php endforeach; ?>
        </section>
        <?php
            endif;
        endif;
        ?>

        <?php // --- Section Offres / paramètres appliqués par offre (vue synthétique) --- ?>
        <section class="crud-section">
            <h2 class="crud-section-title">Offres concernées &amp; méthode de prévision</h2>
            <p class="small text-muted mb-3">
                Pour ce scénario, le choix de méthode (moyenne historique / Prophet)
                et les paramètres Prophet sont <strong>figés</strong> (à la création ou à l’ajout d’une offre).
                Une modification ultérieure de l’offre source via son administration
                <strong>ne mettra pas à jour</strong> ce scénario existant.
                Pour appliquer de nouveaux défauts, créez un nouveau scénario.
            </p>
            <div class="table-responsive">
                <table class="table table-hover table-sm crud-table">
                    <thead>
                        <tr>
                            <th>Offre</th>
                            <th>Méthode</th>
                            <th>Plage historique (si Prophet)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($scenario->forecast_scenarios_offers as $link):
                            $offerName = $link->offer->name ?? ('Offre #' . $link->offer_id);

                            $offerSnapshot = [];
                            if (!empty($link->prophet_settings_json)) {
                                if (is_string($link->prophet_settings_json)) {
                                    $offerSnapshot = json_decode($link->prophet_settings_json, true) ?: [];
                                } elseif (is_array($link->prophet_settings_json)) {
                                    $offerSnapshot = $link->prophet_settings_json;
                                }
                            }

                            $historyStart = $offerSnapshot['history_start_date'] ?? null;
                            $historyEnd = $offerSnapshot['history_end_date'] ?? null;
                            $hasHistory = !empty($historyStart) || !empty($historyEnd);
                        ?>
                        <tr>
                            <td><?= h($offerName) ?></td>
                            <td>
                                <?php if (($link->forecast_method ?? 'historical') === 'prophet'): ?>
                                    Prophet
                                <?php else: ?>
                                    Historique
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (($link->forecast_method ?? 'historical') === 'prophet'): ?>
                                    <?php if ($hasHistory): ?>
                                        <?= h($historyStart ?: 'Début auto') ?> → <?= h($historyEnd ?: 'Fin auto') ?>
                                    <?php else: ?>
                                        <span class="text-muted">Historique complet (défaut système / offre)</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php // --- Section Paramètres WFM --- ?>
        <section class="crud-section">
            <h2 class="crud-section-title">Configuration WFM (snapshot)</h2>
            <?php
            $dayStart = $snapshot['day_start_time'] ?? ($current->day_start_time ?? null);
            $dayEnd = $snapshot['day_end_time'] ?? ($current->day_end_time ?? null);
            $qsPercent = $snapshot['service_level_percent'] ?? ($current->service_level_percent ?? null);
            $qsSeconds = $snapshot['service_level_seconds'] ?? ($current->service_level_seconds ?? 20);
            $shrinkValue = $snapshot['shrinkage_percent'] ?? ($current->shrinkage_percent ?? null);
            ?>

            <h3 class="crud-subsection-title">Plage horaire de production</h3>
            <dl class="crud-fields">
                <div>
                    <dt>Début de journée</dt>
                    <dd><?= h($dayStart ?? '—') ?></dd>
                </div>
                <div>
                    <dt>Fin de journée</dt>
                    <dd><?= h($dayEnd ?? '—') ?></dd>
                </div>
            </dl>

            <h3 class="crud-subsection-title">Objectifs de qualité de service</h3>
            <dl class="crud-fields">
                <div>
                    <dt>Taux de service</dt>
                    <dd><?= h($qsPercent ?? '—') ?> % des appels</dd>
                </div>
                <div>
                    <dt>Délai maximum</dt>
                    <dd><?= h($qsSeconds ?? '—') ?> s de réponse</dd>
                </div>
            </dl>
            <p class="small text-muted">
                Objectif QS : répondre à <strong><?= h($qsPercent ?? '—') ?>%</strong> des appels
                en moins de <strong><?= h($qsSeconds ?? '—') ?>s</strong>.
            </p>

            <h3 class="crud-subsection-title">Paramètres ressources humaines</h3>
            <dl class="crud-fields">
                <div>
                    <dt>Shrinkage (temps improductif)</dt>
                    <dd><?= h($shrinkValue ?? '—') ?>%</dd>
                </div>
            </dl>
            <p class="small text-muted mb-0">Formation, pauses, réunions, absences…</p>
        </section>

        <?php
        // --- Section Paramètres Prophet (pour les offres en Prophet uniquement) ---
        $hasProphetOffer = false;
        foreach ($scenario->forecast_scenarios_offers as $link) {
            if (($link->forecast_method ?? 'historical') === 'prophet') {
                $hasProphetOffer = true;
                break;
            }
        }
        if ($hasProphetOffer):
        ?>
        <section class="crud-section">
            <h2 class="crud-section-title">Configuration Prophet (snapshot par offre)</h2>
            <p class="small text-muted mb-3">
                Paramètres Prophet figés (voir la section « Offres concernées &amp; méthode de prévision » ci-dessus).
            </p>
            <?php if (empty($scenario->forecast_scenarios_offers)): ?>
                <p class="text-muted mb-0">
                    Aucune offre n'est associée à ce scénario.
                </p>
            <?php else: ?>
                <?php foreach ($scenario->forecast_scenarios_offers as $link):
                    if (($link->forecast_method ?? 'historical') !== 'prophet') {
                        continue;
                    }
                    $offerName = $link->offer->name ?? ('Offre #' . $link->offer_id);

                    $offerSnapshot = [];
                    if (!empty($link->prophet_settings_json)) {
                        if (is_string($link->prophet_settings_json)) {
                            $offerSnapshot = json_decode($link->prophet_settings_json, true) ?: [];
                        } elseif (is_array($link->prophet_settings_json)) {
                            $offerSnapshot = $link->prophet_settings_json;
                        }
                    }

                    $historyStart = $offerSnapshot['history_start_date'] ?? null;
                    $historyEnd = $offerSnapshot['history_end_date'] ?? null;
                    $hasHistory = !empty($historyStart) || !empty($historyEnd);
                ?>
                <h3 class="crud-subsection-title"><?= h($offerName) ?></h3>
                <?php if (empty($offerSnapshot)): ?>
                    <p class="text-muted">
                        Aucun snapshot Prophet n'est encore disponible pour cette offre.
                        Lance un calcul pour matérialiser les paramètres effectifs.
                    </p>
                <?php else: ?>
                    <dl class="crud-fields">
                        <div>
                            <dt>Méthode</dt>
                            <dd>Prophet</dd>
                        </div>
                        <div>
                            <dt>Plage historique</dt>
                            <dd>
                                <?php if ($hasHistory): ?>
                                    <?= h($historyStart ?: 'Début auto') ?> → <?= h($historyEnd ?: 'Fin auto') ?>
                                <?php else: ?>
                                    Historique complet (défauts)
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div>
                            <dt>Mode</dt>
                            <dd><?= h($offerSnapshot['seasonality_mode'] ?? 'multiplicative') ?></dd>
                        </div>
                        <div>
                            <dt>n_changepoints</dt>
                            <dd><?= h($offerSnapshot['n_changepoints'] ?? 25) ?></dd>
                        </div>
                        <div>
                            <dt>changepoint_prior_scale</dt>
                            <dd><?= h($offerSnapshot['changepoint_prior_scale'] ?? 0.1) ?></dd>
                        </div>
                        <div>
                            <dt>seasonality_prior_scale</dt>
                            <dd><?= h($offerSnapshot['seasonality_prior_scale'] ?? 10.0) ?></dd>
                        </div>
                        <div>
                            <dt>monthly_fourier_order</dt>
                            <dd><?= h($offerSnapshot['monthly_fourier_order'] ?? 5) ?></dd>
                        </div>
                        <?php
                        $flags = [
                            'yearly_seasonality' => 'Saisonnalité annuelle',
                            'weekly_seasonality' => 'Saisonnalité hebdomadaire',
                            'monthly_seasonality' => 'Saisonnalité mensuelle',
                            'daily_seasonality' => 'Saisonnalité journalière',
                        ];
                        foreach ($flags as $key => $label):
                            $enabled = array_key_exists($key, $offerSnapshot) ? (bool)$offerSnapshot[$key] : true;
                        ?>
                        <div>
                            <dt><?= h($label) ?></dt>
                            <dd><?= $enabled ? 'Activée' : 'Désactivée' ?></dd>
                        </div>
                        <?php endforeach; ?>
                        <?php
                        $holidays = array_key_exists('use_french_holidays', $offerSnapshot) ? (bool)$offerSnapshot['use_french_holidays'] : true;
                        ?>
                        <div>
                            <dt>Jours fériés FR</dt>
                            <dd><?= $holidays ? 'Pris en compte' : 'Ignorés' ?></dd>
                        </div>
                    </dl>
                <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php // --- Section Visualisation --- ?>
        <section class="crud-section">
            <h2 class="crud-section-title">Visualisation sur une période</h2>
            <?php if ($scenario->status === 'completed'): ?>
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small text-muted">Offre</label>
                        <select id="offerSelect" class="form-control form-control-sm">
                            <?php foreach ($scenario->forecast_scenarios_offers as $link): ?>
                                <option value="<?= h($link->offer_id) ?>"><?= h($link->offer->name ?? ('Offer #' . $link->offer_id)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">Plage de dates</label>
                        <input id="dateRangeInput" type="text" class="form-control form-control-sm"
                               placeholder="Sélectionner une période..." readonly
                               data-start-date="<?= $scenario->start_date ? $scenario->start_date->format('Y-m-d') : '' ?>"
                               data-end-date="<?= $scenario->end_date ? $scenario->end_date->format('Y-m-d') : '' ?>" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted">Granularité</label>
                        <select id="granularitySelect" class="form-control form-control-sm">
                            <option value="15min">15 minutes</option>
                            <option value="hour">Heure</option>
                            <option value="day">Jour</option>
                        </select>
                        <small id="granularityHint" class="text-muted" style="font-size: 0.7rem;"></small>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button id="loadBtn" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-bar-chart"></i> Charger
                        </button>
                    </div>
                </div>
                <div id="chartContainer"></div>
            <?php else: ?>
                <p class="text-muted mb-0">
                    La visualisation n'est disponible que pour les scénarios avec le statut <strong>Terminé</strong>.
                    <?php if ($scenario->status === 'draft'): ?>
                        Lance un calcul pour voir les données.
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </section>

        <?= $this->element('apex_series_chart'); ?>

        <?php
        $js = <<<JS
        // Configuration du daterangepicker
        $(document).ready(function() {
            const input = $('#dateRangeInput');
            const startDateStr = input.data('start-date'); // Format YYYY-MM-DD
            const endDateStr = input.data('end-date');     // Format YYYY-MM-DD
            
            if ($.fn.daterangepicker && startDateStr && endDateStr) {
                const startMoment = moment(startDateStr, 'YYYY-MM-DD');
                const endMoment = moment(endDateStr, 'YYYY-MM-DD');
                
                input.daterangepicker({
                    startDate: startMoment,
                    endDate: endMoment,
                    minDate: startMoment,
                    maxDate: endMoment,
                    locale: {
                        format: 'DD/MM/YYYY',
                        separator: ' - ',
                        applyLabel: 'Appliquer',
                        cancelLabel: 'Annuler',
                        daysOfWeek: ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'],
                        monthNames: ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 
                                     'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'],
                        firstDay: 1
                    },
                    opens: 'left'
                });
                
                // Initialiser avec la période du scénario
                input.val(startMoment.format('DD/MM/YYYY') + ' - ' + endMoment.format('DD/MM/YYYY'));
                
                // Mettre à jour le hint de granularité
                updateGranularityHint();
            }
            
            // Écouter les changements de dates et de granularité
            $('#dateRangeInput').on('apply.daterangepicker', function() {
                updateGranularityHint();
            });
            
            $('#granularitySelect').on('change', function() {
                updateGranularityHint();
            });
        });
        
        // Fonction pour suggérer la granularité selon la plage
        function updateGranularityHint() {
            const dateRangePicker = $('#dateRangeInput').data('daterangepicker');
            if (!dateRangePicker) return;
            
            const start = dateRangePicker.startDate;
            const end = dateRangePicker.endDate;
            const daysDiff = end.diff(start, 'days') + 1;
            const current = $('#granularitySelect').val();
            
            let recommended = '15min';
            let hint = '';
            
            if (daysDiff <= 7) {
                recommended = '15min';
                hint = '✓ 15 min recommandé';
            } else if (daysDiff <= 30) {
                recommended = 'hour';
                hint = '⚠ Heure recommandée';
            } else {
                recommended = 'day';
                hint = '⚠ Jour recommandé';
            }
            
            const hintElement = $('#granularityHint');
            hintElement.text(hint);
            
            if (current !== recommended) {
                hintElement.css('color', '#ff9800').css('font-weight', 'bold');
            } else {
                hintElement.css('color', '#28a745').css('font-weight', 'normal');
            }
        }

        // Fonction d'agrégation des données
        function aggregateScenarioData(categories, forecastData, needData, granularity) {
            if (granularity === '15min') {
                return { categories, forecastData, needData }; // Pas d'agrégation
            }
            
            const buckets = {};
            
            for (let i = 0; i < categories.length; i++) {
                if (categories[i] === null || forecastData[i] === null) continue;
                
                const parts = categories[i].split(' '); // Format: "DD/MM/YYYY HH:mm"
                const datePart = parts[0]; // "DD/MM/YYYY"
                const timePart = parts[1]; // "HH:mm"
                
                let key;
                if (granularity === 'day') {
                    key = datePart; // Grouper par jour
                } else if (granularity === 'hour') {
                    const hour = timePart.split(':')[0]; // Extraire l'heure
                    key = datePart + ' ' + hour + ':00'; // Grouper par heure
                }
                
                if (!buckets[key]) {
                    buckets[key] = {
                        forecastSum: 0,
                        needSum: 0,
                        count: 0
                    };
                }
                
                buckets[key].forecastSum += forecastData[i] || 0;
                buckets[key].needSum += needData[i] || 0;
                buckets[key].count++;
            }
            
            const aggCategories = [];
            const aggForecastData = [];
            const aggNeedData = [];
            
            for (const key in buckets) {
                aggCategories.push(key);
                aggForecastData.push(buckets[key].forecastSum); // SOMME des volumes (total appels)
                aggNeedData.push(Math.round(buckets[key].needSum / buckets[key].count)); // MOYENNE des besoins (agents moyen)
            }
            
            return {
                categories: aggCategories,
                forecastData: aggForecastData,
                needData: aggNeedData
            };
        }

        document.getElementById('loadBtn').addEventListener('click', async () => {
            const offerId = document.getElementById('offerSelect').value;
            const dateRangeInput = document.getElementById('dateRangeInput');
            const granularity = document.getElementById('granularitySelect').value;
            
            // Extraire les dates de la plage
            const dateRangePicker = $(dateRangeInput).data('daterangepicker');
            if (!dateRangePicker) {
                alert('Veuillez sélectionner une plage de dates');
                return;
            }
            
            const startDate = dateRangePicker.startDate;
            const endDate = dateRangePicker.endDate;
            
            console.log('Chargement:', {offerId, start: startDate.format('YYYY-MM-DD'), end: endDate.format('YYYY-MM-DD'), granularity});
            
            // Afficher un indicateur de chargement
            document.getElementById('chartContainer').innerHTML = '<div class="text-center p-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Chargement...</span></div><p class="mt-2">Chargement Forecast + Need...</p></div>';
            
            try {
                const allCategories = [];
                const forecastData = [];
                const needData = [];
                
                // Charger les données pour chaque jour
                let dayIndex = 0;
                for (let d = moment(startDate); d.isSameOrBefore(endDate); d.add(1, 'days')) {
                    const dateStr = d.format('YYYY-MM-DD');
                    
                    // Charger les deux types en parallèle pour aller plus vite
                    const [resForecast, resNeed] = await Promise.all([
                        fetch('{$this->Url->build(['action' => 'series', $scenario->id, '_ext' => 'json'])}?offer_id=' + encodeURIComponent(offerId) + '&date=' + encodeURIComponent(dateStr) + '&type=forecast', 
                              { headers: { 'Accept': 'application/json' } }),
                        fetch('{$this->Url->build(['action' => 'series', $scenario->id, '_ext' => 'json'])}?offer_id=' + encodeURIComponent(offerId) + '&date=' + encodeURIComponent(dateStr) + '&type=need', 
                              { headers: { 'Accept': 'application/json' } })
                    ]);
                    
                    const [jsonForecast, jsonNeed] = await Promise.all([
                        resForecast.json(),
                        resNeed.json()
                    ]);
                    
                    if (jsonForecast.success && jsonForecast.series && jsonForecast.series.data) {
                        const dataForecast = jsonForecast.series.data;
                        const dataNeed = (jsonNeed.success && jsonNeed.series && jsonNeed.series.data) ? jsonNeed.series.data : {};
                        
                        Object.keys(dataForecast).forEach(timeKey => {
                            // Nettoyer l'heure : enlever les secondes si présentes
                            let cleanTime = timeKey;
                            if (timeKey.length > 5) { // Format HH:mm:ss
                                cleanTime = timeKey.substring(0, 5); // Garder juste HH:mm
                            }
                            
                            const category = d.format('DD/MM/YYYY') + ' ' + cleanTime;
                            allCategories.push(category);
                            
                            const valueForecast = typeof dataForecast[timeKey] === 'object' ? dataForecast[timeKey].volume : dataForecast[timeKey];
                            forecastData.push(valueForecast || 0);
                            
                            const valueNeed = typeof dataNeed[timeKey] === 'object' ? dataNeed[timeKey].volume : dataNeed[timeKey];
                            needData.push(valueNeed || 0);
                        });
                        
                        // Ajouter plusieurs points null à la fin de chaque jour (sauf le dernier) pour créer une séparation visuelle marquée
                        if (!d.isSame(endDate, 'day')) {
                            // Ajouter 3 points null pour un gap plus visible
                            allCategories.push(d.format('DD/MM/YYYY') + ' 18:00');
                            forecastData.push(null);
                            needData.push(null);
                            
                            allCategories.push(d.format('DD/MM/YYYY') + ' 21:00');
                            forecastData.push(null);
                            needData.push(null);
                            
                            allCategories.push(d.format('DD/MM/YYYY') + ' 23:59');
                            forecastData.push(null);
                            needData.push(null);
                        }
                    }
                    
                    dayIndex++;
                }
                
                console.log('Total données brutes:', allCategories.length, 'points');
                
                if (allCategories.length === 0) {
                    document.getElementById('chartContainer').innerHTML = '<div class="alert alert-info">Aucune donnée pour cette sélection. Lance le calcul du scénario.</div>';
                    return;
                }
                
                // Agréger les données selon la granularité
                const aggregated = aggregateScenarioData(allCategories, forecastData, needData, granularity);
                
                console.log('Données après agrégation (' + granularity + '):', aggregated.categories.length, 'points');
                
                // Afficher les deux courbes en aires
                window.renderApexArea('chartContainer', aggregated.categories, [
                    { name: 'Forecast (Volume)', data: aggregated.forecastData },
                    { name: 'Need (Besoin)', data: aggregated.needData }
                ], {
                    colors: ['#007bff', '#28a745']
                });
            } catch (e) {
                console.error('Erreur:', e);
                document.getElementById('chartContainer').innerHTML = '<div class="alert alert-danger">Erreur lors du chargement des données: ' + e.message + '</div>';
            }
        });
        JS;
        echo $this->Html->scriptBlock($js, ['block' => true]);
        ?>
    </div>
</div>

<?php $this->Html->scriptStart(['block' => true]); ?>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.initTooltips === 'function') {
        window.initTooltips();
    }

    var banner = document.getElementById('scenarioProgressBanner');
    if (!banner) {
        return;
    }

    var statusUrl = banner.getAttribute('data-status-url');
    var pollMs = 2500;
    var pollTimer = null;
    var pollingStopped = false;
    var finalStatuses = { completed: true, failed: true, draft: true };

    function pct(done, total) {
        if (!total || total <= 0) {
            return 0;
        }
        return Math.min(100, Math.round((done / total) * 100));
    }

    function setText(id, value) {
        var el = document.getElementById(id);
        if (el) {
            el.textContent = value;
        }
    }

    function applyStatus(scenario) {
        var status = String(scenario.status || '');
        var inProgress = status === 'queued' || status === 'running';

        if (inProgress) {
            banner.classList.remove('d-none');
        } else {
            banner.classList.add('d-none');
        }

        setText('progressStatusLabel', status === 'queued' ? 'En file d\'attente…' : 'Calcul en cours…');
        setText('progressOfferName', scenario.progress_offer_name || '—');
        setText('progressOffersDone', String(scenario.progress_offers_done || 0));
        setText('progressOffersTotal', String(scenario.progress_offers_total || 0));
        setText('progressDaysDone', String(scenario.progress_days_done || 0));
        setText('progressDaysTotal', String(scenario.progress_days_total || 0));

        var daysPct = pct(scenario.progress_days_done || 0, scenario.progress_days_total || 0);
        var bar = document.getElementById('progressBarDays');
        if (bar) {
            bar.style.width = daysPct + '%';
            bar.setAttribute('aria-valuenow', String(daysPct));
            bar.textContent = daysPct + '%';
        }

        var err = document.getElementById('progressError');
        if (err) {
            if (scenario.error_message) {
                err.textContent = scenario.error_message;
                err.classList.remove('d-none');
            } else {
                err.textContent = '';
                err.classList.add('d-none');
            }
        }

        var badge = document.getElementById('scenarioStatusBadge');
        var statusText = document.getElementById('scenarioStatusText');
        var statusLabels = {
            draft: 'Brouillon',
            queued: 'En file d\'attente',
            running: 'En cours',
            completed: 'Terminé',
            failed: 'Échec'
        };
        if (statusText) {
            statusText.textContent = statusLabels[status] || status;
        }
        if (badge) {
            badge.className = 'badge ' + (
                status === 'completed' ? 'bg-success' :
                status === 'failed' ? 'bg-danger' :
                (status === 'running' || status === 'queued') ? 'bg-warning' : 'bg-secondary'
            );
        }

        return inProgress;
    }

    function scheduleNextPoll() {
        if (pollingStopped) {
            return;
        }
        pollTimer = setTimeout(pollOnce, pollMs);
    }

    function pollOnce() {
        // Chaînage : le prochain tick n'est planifié qu'après résolution (anti-empilement)
        fetch(statusUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function(res) { return res.json(); })
            .then(function(json) {
                if (!json || !json.success || !json.scenario) {
                    scheduleNextPoll();
                    return;
                }
                var stillRunning = applyStatus(json.scenario);
                if (!stillRunning) {
                    pollingStopped = true;
                    if (pollTimer) {
                        clearTimeout(pollTimer);
                        pollTimer = null;
                    }
                    if (finalStatuses[json.scenario.status]) {
                        window.location.reload();
                    }
                    return;
                }
                scheduleNextPoll();
            })
            .catch(function() {
                scheduleNextPoll();
            });
    }

    var initial = banner.getAttribute('data-initial-status');
    if (initial === 'queued' || initial === 'running') {
        pollOnce();
    }
});
<?php $this->Html->scriptEnd(); ?>

