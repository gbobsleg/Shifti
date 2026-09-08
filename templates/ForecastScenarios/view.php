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
            <?php
            $dayStart = $snapshot['day_start_time'] ?? ($current->day_start_time ?? null);
            $dayEnd = $snapshot['day_end_time'] ?? ($current->day_end_time ?? null);
            $qsPercent = $snapshot['service_level_percent'] ?? ($current->service_level_percent ?? null);
            $qsSeconds = $snapshot['service_level_seconds'] ?? ($current->service_level_seconds ?? 20);
            $shrinkValue = $snapshot['shrinkage_percent'] ?? ($current->shrinkage_percent ?? null);
            ?>
            <h3 class="crud-subsection-title">Paramètres WFM figés</h3>
            <dl class="crud-fields">
                <div>
                    <dt>Début de journée</dt>
                    <dd><?= h($dayStart ?? '—') ?></dd>
                </div>
                <div>
                    <dt>Fin de journée</dt>
                    <dd><?= h($dayEnd ?? '—') ?></dd>
                </div>
                <div>
                    <dt>Taux de service</dt>
                    <dd><?= h($qsPercent ?? '—') ?> % des appels</dd>
                </div>
                <div>
                    <dt>Délai maximum</dt>
                    <dd><?= h($qsSeconds ?? '—') ?> s de réponse</dd>
                </div>
                <div>
                    <dt>Shrinkage</dt>
                    <dd><?= h($shrinkValue ?? '—') ?>% <span class="text-muted">· pauses, formation, absences</span></dd>
                </div>
            </dl>
        </section>

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
                    <?php
                    $vizMin = $scenario->start_date ? $scenario->start_date->format('Y-m-d') : '';
                    $vizMax = $scenario->end_date ? $scenario->end_date->format('Y-m-d') : '';
                    ?>
                    <div class="col-md-2">
                        <label class="form-label small text-muted">Début</label>
                        <input id="vizDateStart" type="date" class="form-control form-control-sm"
                               min="<?= h($vizMin) ?>" max="<?= h($vizMax) ?>" value="<?= h($vizMin) ?>" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted">Fin</label>
                        <input id="vizDateEnd" type="date" class="form-control form-control-sm"
                               min="<?= h($vizMin) ?>" max="<?= h($vizMax) ?>" value="<?= h($vizMax) ?>" />
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
                <p class="small text-muted mb-2 d-none" id="chartLegendHint">
                    Cliquez sur le nom d’une série dans la légende pour l’afficher ou la masquer.
                </p>
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

        <?php
        // --- Section Métriques Prophet par Offre ---
        if ($scenario->status === 'completed'):
            $allMetricsData = null;
            if (!empty($scenario->prophet_metrics_json)) {
                $allMetricsData = json_decode($scenario->prophet_metrics_json, true);
            }

            if ($allMetricsData && !empty($allMetricsData['per_offer'])):
                $metricsSummaries = [];
                foreach ($allMetricsData['per_offer'] as $offerMetric) {
                    $offerId = $offerMetric['offer_id'];
                    $offerName = 'Offre #' . $offerId;
                    foreach ($scenario->forecast_scenarios_offers as $link) {
                        if ($link->offer_id == $offerId) {
                            $offerName = $link->offer->name ?? $offerName;
                            break;
                        }
                    }
                    $mapeValue = $offerMetric['metrics']['mape'] ?? null;
                    $metricsSummaries[] = $offerName . ($mapeValue !== null && $mapeValue !== '' ? ' ' . $mapeValue . ' %' : '');
                }
        ?>
        <details class="crud-section crud-details">
            <summary class="crud-section-title">
                Métriques Prophet par Offre
                <span class="crud-details-meta"><?= h(implode(' · ', $metricsSummaries)) ?></span>
            </summary>
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
        </details>
        <?php
            endif;
        endif;
        ?>

        <?php
        // --- Section Paramètres Prophet (pour les offres en Prophet uniquement) ---
        $prophetOfferCount = 0;
        foreach ($scenario->forecast_scenarios_offers as $link) {
            if (($link->forecast_method ?? 'historical') === 'prophet') {
                $prophetOfferCount++;
            }
        }
        if ($prophetOfferCount > 0):
        ?>
        <details class="crud-section crud-details">
            <summary class="crud-section-title">
                Configuration Prophet (snapshot par offre)
                <span class="crud-details-meta"><?= (int)$prophetOfferCount ?> offre<?= $prophetOfferCount > 1 ? 's' : '' ?></span>
            </summary>
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
                <article class="border rounded p-3 mb-3">
                    <h3 class="crud-section-title mb-0"><?= h($offerName) ?></h3>
                    <?php if (empty($offerSnapshot)): ?>
                        <p class="text-muted mb-0 mt-3">
                            Aucun snapshot Prophet n'est encore disponible pour cette offre.
                            Lance un calcul pour matérialiser les paramètres effectifs.
                        </p>
                    <?php else: ?>
                        <?php
                        $seasonalityMode = $offerSnapshot['seasonality_mode'] ?? 'multiplicative';
                        $seasonalityModeLabel = $seasonalityMode === 'additive'
                            ? 'Additif (y = tendance + saisonnalité)'
                            : 'Multiplicatif (y = tendance × saisonnalité)';
                        $holidays = array_key_exists('use_french_holidays', $offerSnapshot)
                            ? (bool)$offerSnapshot['use_french_holidays']
                            : true;
                        $flags = [
                            'yearly_seasonality' => 'Saisonnalité annuelle',
                            'weekly_seasonality' => 'Saisonnalité hebdomadaire',
                            'daily_seasonality' => 'Saisonnalité journalière',
                            'monthly_seasonality' => 'Saisonnalité mensuelle',
                        ];
                        ?>
                        <dl class="crud-fields mt-3">
                            <div>
                                <dt>Méthode</dt>
                                <dd>Prophet</dd>
                            </div>
                            <div>
                                <dt>Plage de données historiques</dt>
                                <dd>
                                    <?php if ($hasHistory): ?>
                                        <span data-bs-toggle="tooltip" title="history_start_date"><?= h($historyStart ?: 'Début auto') ?></span>
                                        →
                                        <span data-bs-toggle="tooltip" title="history_end_date"><?= h($historyEnd ?: 'Fin auto') ?></span>
                                    <?php else: ?>
                                        Tout l'historique disponible
                                    <?php endif; ?>
                                </dd>
                            </div>
                            <div>
                                <dt data-bs-toggle="tooltip" title="seasonality_mode">Mode de saisonnalité</dt>
                                <dd><?= h($seasonalityModeLabel) ?></dd>
                            </div>
                            <div>
                                <dt data-bs-toggle="tooltip" title="use_french_holidays">Jours fériés</dt>
                                <dd><?= $holidays ? 'Français activés' : 'Désactivés' ?></dd>
                            </div>
                            <?php foreach ($flags as $key => $label):
                                $enabled = array_key_exists($key, $offerSnapshot) ? (bool)$offerSnapshot[$key] : true;
                            ?>
                            <div>
                                <dt data-bs-toggle="tooltip" title="<?= h($key) ?>"><?= h($label) ?></dt>
                                <dd><?= $enabled ? 'Activée' : 'Désactivée' ?></dd>
                            </div>
                            <?php endforeach; ?>
                            <div>
                                <dt data-bs-toggle="tooltip" title="monthly_fourier_order">Finesse du cycle mensuel</dt>
                                <dd><?= h($offerSnapshot['monthly_fourier_order'] ?? 5) ?></dd>
                            </div>
                            <div>
                                <dt data-bs-toggle="tooltip" title="changepoint_prior_scale">Sensibilité aux ruptures de tendance</dt>
                                <dd><?= h($offerSnapshot['changepoint_prior_scale'] ?? 0.1) ?></dd>
                            </div>
                            <div>
                                <dt data-bs-toggle="tooltip" title="seasonality_prior_scale">Force de la saisonnalité</dt>
                                <dd><?= h($offerSnapshot['seasonality_prior_scale'] ?? 10.0) ?></dd>
                            </div>
                            <div>
                                <dt data-bs-toggle="tooltip" title="n_changepoints">Nombre de ruptures de tendance</dt>
                                <dd><?= h($offerSnapshot['n_changepoints'] ?? 25) ?></dd>
                            </div>
                        </dl>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </details>
        <?php endif; ?>

        <?= $this->element('apex_series_chart'); ?>

        <?php
        $js = <<<JS
        function parseYmd(value) {
            const match = /^(\\d{4})-(\\d{2})-(\\d{2})$/.exec(value || '');
            if (!match) {
                return null;
            }
            return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
        }

        function formatYmd(date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return y + '-' + m + '-' + d;
        }

        function formatDmy(date) {
            const d = String(date.getDate()).padStart(2, '0');
            const m = String(date.getMonth() + 1).padStart(2, '0');
            return d + '/' + m + '/' + date.getFullYear();
        }

        function sameDay(a, b) {
            return a.getFullYear() === b.getFullYear()
                && a.getMonth() === b.getMonth()
                && a.getDate() === b.getDate();
        }

        function setChartLegendHintVisible(visible) {
            const hint = document.getElementById('chartLegendHint');
            if (!hint) {
                return;
            }
            if (visible) {
                hint.classList.remove('d-none');
            } else {
                hint.classList.add('d-none');
            }
        }

        function getVizRange() {
            const startEl = document.getElementById('vizDateStart');
            const endEl = document.getElementById('vizDateEnd');
            if (!startEl || !endEl) {
                return null;
            }
            const start = parseYmd(startEl.value);
            const end = parseYmd(endEl.value);
            if (!start || !end) {
                return null;
            }
            return { start: start, end: end };
        }

        function updateGranularityHint() {
            const range = getVizRange();
            const hintElement = document.getElementById('granularityHint');
            const granEl = document.getElementById('granularitySelect');
            if (!range || !hintElement || !granEl) {
                return;
            }

            const daysDiff = Math.round((range.end - range.start) / 86400000) + 1;
            const current = granEl.value;
            let recommended = '15min';
            let hint = '';

            if (daysDiff <= 7) {
                recommended = '15min';
                hint = '15 min recommandé';
            } else if (daysDiff <= 30) {
                recommended = 'hour';
                hint = 'Heure recommandée';
            } else {
                recommended = 'day';
                hint = 'Jour recommandé';
            }

            hintElement.textContent = hint;
            if (current !== recommended) {
                hintElement.style.color = '#ff9800';
                hintElement.style.fontWeight = 'bold';
            } else {
                hintElement.style.color = '#28a745';
                hintElement.style.fontWeight = 'normal';
            }
        }

        const SERIES_VOLUME = 'Prévision (volume)';
        const SERIES_NEED = 'Besoin';
        const SERIES_DMT = 'DMT';

        function toFiniteNumber(v) {
            if (v === null || typeof v === 'undefined' || v === '') {
                return null;
            }
            const n = Number(v);
            return Number.isFinite(n) ? n : null;
        }

        function isPositiveFinite(n) {
            return typeof n === 'number' && Number.isFinite(n) && n > 0;
        }

        function dmtMinutesFromSeconds(vol, dmtSeconds) {
            if (!isPositiveFinite(vol) || typeof dmtSeconds !== 'number' || !Number.isFinite(dmtSeconds)) {
                return null;
            }
            return dmtSeconds / 60;
        }

        function aggregateScenarioData(categories, forecastData, needData, dmtSecondsData, granularity) {
            if (granularity === '15min') {
                const dmtData = [];
                for (let i = 0; i < forecastData.length; i++) {
                    dmtData.push(dmtMinutesFromSeconds(forecastData[i], dmtSecondsData[i]));
                }
                return { categories, forecastData, needData, dmtData };
            }

            const buckets = {};

            for (let i = 0; i < categories.length; i++) {
                if (categories[i] === null || forecastData[i] === null) continue;

                const parts = categories[i].split(' ');
                const datePart = parts[0];
                const timePart = parts[1];

                let key;
                if (granularity === 'day') {
                    key = datePart;
                } else if (granularity === 'hour') {
                    const hour = timePart.split(':')[0];
                    key = datePart + ' ' + hour + ':00';
                }

                if (!buckets[key]) {
                    buckets[key] = {
                        forecastSum: 0,
                        needSum: 0,
                        count: 0,
                        dmtWeightedSum: 0,
                        dmtVolSum: 0
                    };
                }

                const vol = forecastData[i];
                buckets[key].forecastSum += (typeof vol === 'number' && Number.isFinite(vol)) ? vol : 0;
                buckets[key].needSum += needData[i] || 0;
                buckets[key].count++;

                const dmtS = dmtSecondsData[i];
                if (isPositiveFinite(vol) && typeof dmtS === 'number' && Number.isFinite(dmtS)) {
                    buckets[key].dmtWeightedSum += dmtS * vol;
                    buckets[key].dmtVolSum += vol;
                }
            }

            const aggCategories = [];
            const aggForecastData = [];
            const aggNeedData = [];
            const aggDmtData = [];

            for (const key in buckets) {
                aggCategories.push(key);
                aggForecastData.push(buckets[key].forecastSum);
                aggNeedData.push(Math.round(buckets[key].needSum / buckets[key].count));
                if (buckets[key].dmtVolSum > 0) {
                    aggDmtData.push((buckets[key].dmtWeightedSum / buckets[key].dmtVolSum) / 60);
                } else {
                    aggDmtData.push(null);
                }
            }

            return {
                categories: aggCategories,
                forecastData: aggForecastData,
                needData: aggNeedData,
                dmtData: aggDmtData
            };
        }

        document.addEventListener('DOMContentLoaded', function() {
            const startEl = document.getElementById('vizDateStart');
            const endEl = document.getElementById('vizDateEnd');
            const granEl = document.getElementById('granularitySelect');
            const loadBtn = document.getElementById('loadBtn');

            if (startEl && endEl) {
                startEl.addEventListener('change', function() {
                    if (startEl.value && endEl.value && startEl.value > endEl.value) {
                        endEl.value = startEl.value;
                    }
                    updateGranularityHint();
                });
                endEl.addEventListener('change', function() {
                    if (startEl.value && endEl.value && endEl.value < startEl.value) {
                        startEl.value = endEl.value;
                    }
                    updateGranularityHint();
                });
                updateGranularityHint();
            }

            if (granEl) {
                granEl.addEventListener('change', updateGranularityHint);
            }

            if (!loadBtn) {
                return;
            }

            loadBtn.addEventListener('click', async function() {
                const offerId = document.getElementById('offerSelect').value;
                const granularity = document.getElementById('granularitySelect').value;
                const range = getVizRange();

                if (!range) {
                    alert('Veuillez sélectionner une plage de dates');
                    return;
                }

                setChartLegendHintVisible(true);
                document.getElementById('chartContainer').innerHTML = '<div class="text-center p-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Chargement...</span></div><p class="mt-2">Chargement prévision + besoin...</p></div>';

                try {
                    const allCategories = [];
                    const forecastData = [];
                    const needData = [];
                    const dmtSecondsData = [];

                    for (let cursor = new Date(range.start); cursor <= range.end; cursor.setDate(cursor.getDate() + 1)) {
                        const day = new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate());
                        const dateStr = formatYmd(day);
                        const dayLabel = formatDmy(day);

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

                            Object.keys(dataForecast).forEach(function(timeKey) {
                                let cleanTime = timeKey;
                                if (timeKey.length > 5) {
                                    cleanTime = timeKey.substring(0, 5);
                                }

                                allCategories.push(dayLabel + ' ' + cleanTime);

                                let valueForecast;
                                let dmtSeconds = null;
                                if (typeof dataForecast[timeKey] === 'object' && dataForecast[timeKey] !== null) {
                                    valueForecast = toFiniteNumber(dataForecast[timeKey].volume);
                                    dmtSeconds = toFiniteNumber(dataForecast[timeKey].dmt);
                                } else {
                                    valueForecast = toFiniteNumber(dataForecast[timeKey]);
                                }
                                forecastData.push(valueForecast === null ? 0 : valueForecast);
                                dmtSecondsData.push(dmtSeconds);

                                const valueNeed = toFiniteNumber(
                                    typeof dataNeed[timeKey] === 'object' && dataNeed[timeKey] !== null
                                        ? dataNeed[timeKey].volume
                                        : dataNeed[timeKey]
                                );
                                needData.push(valueNeed === null ? 0 : valueNeed);
                            });

                            if (!sameDay(day, range.end)) {
                                allCategories.push(dayLabel + ' 18:00');
                                forecastData.push(null);
                                needData.push(null);
                                dmtSecondsData.push(null);

                                allCategories.push(dayLabel + ' 21:00');
                                forecastData.push(null);
                                needData.push(null);
                                dmtSecondsData.push(null);

                                allCategories.push(dayLabel + ' 23:59');
                                forecastData.push(null);
                                needData.push(null);
                                dmtSecondsData.push(null);
                            }
                        }
                    }

                    if (allCategories.length === 0) {
                        setChartLegendHintVisible(false);
                        document.getElementById('chartContainer').innerHTML = '<div class="alert alert-info">Aucune donnée pour cette sélection. Lance le calcul du scénario.</div>';
                        return;
                    }

                    const aggregated = aggregateScenarioData(allCategories, forecastData, needData, dmtSecondsData, granularity);

                    window.renderApexArea('chartContainer', aggregated.categories, [
                        { name: SERIES_VOLUME, type: 'area', data: aggregated.forecastData },
                        { name: SERIES_NEED, type: 'line', data: aggregated.needData },
                        { name: SERIES_DMT, type: 'line', data: aggregated.dmtData }
                    ], {
                        chart: { type: 'line' },
                        colors: ['#007bff', '#28a745', '#fd7e14'],
                        stroke: { width: [2, 2, 2], curve: 'smooth' },
                        fill: {
                            type: ['gradient', 'solid', 'solid'],
                            opacity: [0.3, 1, 1]
                        },
                        grid: {
                            padding: { right: 100 }
                        },
                        yaxis: [
                            {
                                seriesName: SERIES_VOLUME,
                                min: 0,
                                tickAmount: 5,
                                forceNiceScale: true,
                                title: { text: 'Appels' }
                            },
                            {
                                seriesName: SERIES_NEED,
                                opposite: true,
                                min: 0,
                                tickAmount: 5,
                                forceNiceScale: true,
                                title: { text: 'Agents' }
                            },
                            {
                                seriesName: SERIES_DMT,
                                opposite: true,
                                min: 0,
                                tickAmount: 5,
                                offsetX: 70,
                                labels: { offsetX: 15 },
                                title: { text: 'DMT (min)' }
                            }
                        ],
                        customFormats: {
                            [SERIES_VOLUME]: 'int',
                            [SERIES_NEED]: 'int',
                            [SERIES_DMT]: 'time',
                            'default': 'int'
                        }
                    });
                } catch (e) {
                    setChartLegendHintVisible(false);
                    document.getElementById('chartContainer').innerHTML = '<div class="alert alert-danger">Erreur lors du chargement des données: ' + e.message + '</div>';
                }
            });
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

