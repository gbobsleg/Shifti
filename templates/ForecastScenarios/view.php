<?php
/** @var \App\Model\Entity\ForecastScenario $scenario */
?>
<?php $this->assign('title', 'Scénario #' . h($scenario->id)); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php $this->Html->css('daterangepicker', ['block' => true]); ?>
<?php $this->Html->script('moment.min', ['block' => true]); ?>
<?php $this->Html->script('daterangepicker', ['block' => true]); ?>



<div class="forecast-scenarios view content card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="mb-0">
            <i class="bi bi-diagram-3 text-primary"></i>
            Scénario #<?= h($scenario->id) ?> — <?= h($scenario->name) ?>
        </h3>
        <?php
        $canLaunch = in_array((string)$scenario->status, ['draft', 'failed', 'completed'], true);
        $isInProgress = in_array((string)$scenario->status, ['queued', 'running'], true);
        ?>
        <div class="btn-toolbar">
            <?php if ($canLaunch): ?>
                <?= $this->Html->link(
                    '<i class="bi bi-play-circle-fill mr-1"></i> Lancer',
                    ['action' => 'run', $scenario->id],
                    ['class' => 'btn btn-success mr-2', 'escape' => false, 'id' => 'runScenarioLink']
                ) ?>
            <?php endif; ?>
            <?php if ($scenario->status === 'completed'): ?>
                <?php
                $isPublished = !empty($scenario->forecast_scenario_publications);
                ?>
                <?php if ($isPublished): ?>
                    <?= $this->Html->link(
                        '<i class="bi bi-broadcast-pin mr-1"></i> Dépublier',
                        ['action' => 'unpublish', $scenario->id],
                        ['class' => 'btn btn-warning mr-2', 'escape' => false, 'confirm' => 'Dépublier ce scénario ? Les données ne seront plus utilisées pour la planification.']
                    ) ?>
                <?php else: ?>
                    <?= $this->Html->link(
                        '<i class="bi bi-broadcast mr-1"></i> Publier',
                        ['action' => 'publish', $scenario->id],
                        ['class' => 'btn btn-info mr-2', 'escape' => false]
                    ) ?>
                <?php endif; ?>
            <?php endif; ?>
            <?= $this->Html->link(
                '<i class="bi bi-pencil mr-1"></i> Modifier',
                ['action' => 'edit', $scenario->id],
                ['class' => 'btn btn-primary mr-2', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash mr-1"></i> Supprimer',
                ['action' => 'delete', $scenario->id],
                ['confirm' => 'Voulez-vous vraiment supprimer ce scénario ?', 'class' => 'btn btn-danger mr-2', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list mr-1"></i> Liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div class="card-body" id="scenarioViewContent">
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
                <div class="spinner-border text-warning mr-3 mt-1" role="status" style="width: 2rem; height: 2rem;">
                    <span class="sr-only">Calcul...</span>
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

        <?php // --- Section Informations principales --- ?>
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body">
                        <h6 class="text-muted mb-2"><i class="bi bi-calendar-range"></i> Période</h6>
                        <p class="mb-1"><strong><?= h($scenario->start_date) ?></strong></p>
                        <p class="mb-1"><strong><?= h($scenario->end_date) ?></strong></p>
                        <?php if ($scenario->start_date && $scenario->end_date): 
                            $start = new \DateTime($scenario->start_date->format('Y-m-d'));
                            $end = new \DateTime($scenario->end_date->format('Y-m-d'));
                            $duration = $start->diff($end)->days + 1;
                        ?>
                            <small class="text-muted"><i class="bi bi-clock"></i> <?= $duration ?> jour<?= $duration > 1 ? 's' : '' ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <?php
                $statusBorder = 'secondary';
                if ($scenario->status === 'completed') {
                    $statusBorder = 'success';
                } elseif (in_array((string)$scenario->status, ['running', 'queued'], true)) {
                    $statusBorder = 'warning';
                } elseif ($scenario->status === 'failed') {
                    $statusBorder = 'danger';
                }
                ?>
                <div class="card border-<?= $statusBorder ?>" id="scenarioStatusCard">
                    <div class="card-body">
                        <h6 class="text-muted mb-3"><i class="bi bi-tag"></i> Statut</h6>
                        <?php
                        $badgeClass = 'badge-secondary';
                        $badgeIcon = 'bi-file-earmark';
                        if ($scenario->status === 'queued') {
                            $badgeClass = 'badge-warning';
                            $badgeIcon = 'bi-hourglass-split';
                        } elseif ($scenario->status === 'running') {
                            $badgeClass = 'badge-warning';
                            $badgeIcon = 'bi-arrow-repeat';
                        } elseif ($scenario->status === 'completed') {
                            $badgeClass = 'badge-success';
                            $badgeIcon = 'bi-check-circle';
                        } elseif ($scenario->status === 'failed') {
                            $badgeClass = 'badge-danger';
                            $badgeIcon = 'bi-exclamation-triangle';
                        }
                        ?>
                        <h4 class="mb-0">
                            <span class="badge <?= $badgeClass ?>" id="scenarioStatusBadge">
                                <i class="bi <?= $badgeIcon ?>"></i> <span id="scenarioStatusText"><?= h(ucfirst((string)$scenario->status)) ?></span>
                            </span>
                        </h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body">
                        <h6 class="text-muted mb-3"><i class="bi bi-broadcast"></i> Publication</h6>
                        <?php if (!empty($scenario->forecast_scenario_publications)): ?>
                            <h4 class="mb-0">
                                <span class="badge badge-info">
                                    <i class="bi bi-broadcast"></i> Publié
                                </span>
                            </h4>
                            <small class="text-muted"><?= count($scenario->forecast_scenario_publications) ?> jour(s) publié(s)</small>
                        <?php else: ?>
                            <h4 class="mb-0">
                                <span class="badge badge-light">
                                    <i class="bi bi-broadcast-pin"></i> Non publié
                                </span>
                            </h4>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php 
        // --- Section Métriques Prophet par Offre --- 
        if ($scenario->status === 'completed'): 
            $allMetricsData = null;
            if (!empty($scenario->prophet_metrics_json)) {
                $allMetricsData = json_decode($scenario->prophet_metrics_json, true);
            }
            
            if ($allMetricsData && !empty($allMetricsData['per_offer'])): 
        ?>
        <div class="card mb-4 border-primary">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-graph-up-arrow"></i> Métriques Prophet par Offre</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($allMetricsData['per_offer'] as $offerMetric): 
                        $offerId = $offerMetric['offer_id'];
                        $metrics = $offerMetric['metrics'];
                        
                        // Trouver le nom de l'offre
                        $offerName = 'Offre #' . $offerId;
                        foreach ($scenario->forecast_scenarios_offers as $link) {
                            if ($link->offer_id == $offerId) {
                                $offerName = $link->offer->name ?? $offerName;
                                break;
                            }
                        }
                    ?>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 border-<?= $metrics['mape'] < 20 ? 'success' : ($metrics['mape'] < 30 ? 'warning' : 'danger') ?>">
                            <div class="card-body">
                                <h6 class="font-weight-bold mb-3">
                                    <i class="bi bi-tag"></i> <?= h($offerName) ?>
                                </h6>
                                <div class="mb-2">
                                    <strong>
                                        <span data-toggle="tooltip" data-placement="top" 
                                              title="Erreur moyenne en pourcentage. Plus c'est bas, meilleures sont les prévisions. < 20% = Excellent, < 30% = Bon, > 30% = À améliorer">
                                            MAPE: <i class="bi bi-question-circle text-info"></i>
                                        </span>
                                    </strong> 
                                    <span class="badge badge-<?= $metrics['mape'] < 20 ? 'success' : ($metrics['mape'] < 30 ? 'warning' : 'danger') ?>">
                                        <?= h($metrics['mape']) ?>%
                                    </span>
                                </div>
                                <div class="mb-2">
                                    <strong>
                                        <span data-toggle="tooltip" data-placement="top" 
                                              title="Erreur Absolue Moyenne. Nombre moyen d'appels d'écart entre prévisions et réalité (par intervalle de 15 min)">
                                            MAE: <i class="bi bi-question-circle text-info"></i>
                                        </span>
                                    </strong> 
                                    <span class="text-muted"><?= h($metrics['mae']) ?></span>
                                </div>
                                <div class="mb-3">
                                    <strong>
                                        <span data-toggle="tooltip" data-placement="top" 
                                              title="Erreur Quadratique Moyenne. Similaire au MAE mais pénalise davantage les grosses erreurs. Plus sensible aux pics d'erreur.">
                                            RMSE: <i class="bi bi-question-circle text-info"></i>
                                        </span>
                                    </strong> 
                                    <span class="text-muted"><?= h($metrics['rmse']) ?></span>
                                </div>
                                <small class="text-muted">
                                    <i class="bi bi-info-circle"></i> 
                                    <?php if ($metrics['mape'] < 20): ?>
                                        <span class="text-success">Excellente précision</span>
                                    <?php elseif ($metrics['mape'] < 30): ?>
                                        <span class="text-warning">Bonne précision</span>
                                    <?php elseif ($metrics['mape'] < 100): ?>
                                        <span class="text-danger">Précision à améliorer</span>
                                    <?php else: ?>
                                        <span class="text-danger font-weight-bold">⚠️ Précision très faible - Revoir paramètres</span>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php 
            endif;
        endif; 
        ?>

        <?php // --- Section Offres / paramètres appliqués par offre (vue synthétique) --- ?>
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-tags"></i> Offres concernées & méthode de prévision</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-3">
                    <small>
                        <i class="bi bi-info-circle"></i>
                        <strong>Règle produit — méthode &amp; paramètres Prophet :</strong>
                        pour ce scénario, le choix de méthode
                        (<strong>(Moyenne historique / Prophet)</strong>
                        et les paramètres Prophet sont
                        <strong>figés</strong> (à la création ou à l’ajout d’une offre).
                        Une modification ultérieure de l’offre source via son administration
                        <strong>ne mettra pas à jour</strong> ce scénario existant.
                        Pour appliquer de nouveaux défauts, créez un nouveau scénario.
                    </small>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
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
                                
                                // Snapshot complet Prophet par offre/scénario (peut être null ou incomplet)
                                $snapshot = [];
                                if (!empty($link->prophet_settings_json)) {
                                    if (is_string($link->prophet_settings_json)) {
                                        $snapshot = json_decode($link->prophet_settings_json, true) ?: [];
                                    } elseif (is_array($link->prophet_settings_json)) {
                                        $snapshot = $link->prophet_settings_json;
                                    }
                                }

                                $historyStart = $snapshot['history_start_date'] ?? null;
                                $historyEnd = $snapshot['history_end_date'] ?? null;
                                $hasHistory = !empty($historyStart) || !empty($historyEnd);
                            ?>
                            <tr>
                                <td>
                                    <span class="badge badge-primary">
                                        <i class="bi bi-tag"></i> <?= h($offerName) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (($link->forecast_method ?? 'historical') === 'prophet'): ?>
                                        <span class="badge badge-info">
                                            <i class="bi bi-stars"></i> Prophet
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">
                                            <i class="bi bi-clock-history"></i> Historique
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (($link->forecast_method ?? 'historical') === 'prophet'): ?>
                                        <?php if ($hasHistory): ?>
                                            <span class="badge badge-warning">
                                                <?= h($historyStart ?: 'Début auto') ?> → <?= h($historyEnd ?: 'Fin auto') ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Historique complet (défaut système / offre)</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">N/A (méthode historique)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php // --- Section Paramètres WFM --- ?>
        <div class="card mb-4 border-primary">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-people"></i> Configuration WFM (snapshot)</h5>
            </div>
            <div class="card-body">
                
                <?php // === Plage Horaire === ?>
                <div class="mb-4">
                    <h6 class="text-primary border-bottom pb-2 mb-3">
                        <i class="bi bi-clock-history"></i> Plage Horaire de Production
                    </h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-primary mb-3">
                                <div class="card-body py-3 text-center">
                                    <i class="bi bi-sunrise text-primary" style="font-size: 2.5rem;"></i>
                                    <div class="mt-2">
                                        <small class="text-muted d-block">Début de journée</small>
                                        <?php 
                                        $dayStart = $snapshot['day_start_time'] 
                                            ?? ($current->day_start_time ?? null);
                                        ?>
                                        <strong class="h4"><?= h($dayStart ?? 'N/A') ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-primary mb-3">
                                <div class="card-body py-3 text-center">
                                    <i class="bi bi-sunset text-primary" style="font-size: 2.5rem;"></i>
                                    <div class="mt-2">
                                        <small class="text-muted d-block">Fin de journée</small>
                                        <?php 
                                        $dayEnd = $snapshot['day_end_time'] 
                                            ?? ($current->day_end_time ?? null);
                                        ?>
                                        <strong class="h4"><?= h($dayEnd ?? 'N/A') ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php // === Qualité de Service === ?>
                <div class="mb-4">
                    <h6 class="text-success border-bottom pb-2 mb-3">
                        <i class="bi bi-award"></i> Objectifs de Qualité de Service
                    </h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-success mb-3">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="mr-3">
                                            <i class="bi bi-speedometer text-success" style="font-size: 3rem;"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <small class="text-muted d-block">Taux de Service</small>
                                            <?php 
                                            $qsPercent = $snapshot['service_level_percent'] 
                                                ?? ($current->service_level_percent ?? null);
                                            ?>
                                            <div class="d-flex align-items-baseline">
                                                <strong class="h2 mb-0"><?= h($qsPercent) ?></strong>
                                                <span class="h4 text-success ml-1">%</span>
                                            </div>
                                            <small class="text-muted">des appels</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-success mb-3">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="mr-3">
                                            <i class="bi bi-stopwatch text-success" style="font-size: 3rem;"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <small class="text-muted d-block">Délai Maximum</small>
                                            <?php 
                                            $qsSeconds = $snapshot['service_level_seconds'] 
                                                ?? ($current->service_level_seconds ?? 20);
                                            ?>
                                            <div class="d-flex align-items-baseline">
                                                <strong class="h2 mb-0"><?= h($qsSeconds) ?></strong>
                                                <span class="h4 text-success ml-1">s</span>
                                            </div>
                                            <small class="text-muted">de réponse</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-success mb-0">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-info-circle mr-2"></i>
                            <div>
                                <strong>Objectif QS :</strong> 
                                <?php 
                                $qsPct = $snapshot['service_level_percent'] 
                                    ?? ($current->service_level_percent ?? null);
                                $qsSec = $snapshot['service_level_seconds'] 
                                    ?? ($current->service_level_seconds ?? null);
                                ?>
                                Répondre à <strong><?= h($qsPct ?? 'N/A') ?>%</strong> des appels 
                                en moins de <strong><?= h($qsSec ?? 'N/A') ?>s</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <?php // === Paramètres RH === ?>
                <div>
                    <h6 class="text-warning border-bottom pb-2 mb-3">
                        <i class="bi bi-people-fill"></i> Paramètres Ressources Humaines
                    </h6>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card border-warning">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-person-dash text-warning" style="font-size: 2.5rem;" class="mr-3"></i>
                                            <div class="ml-3">
                                                <small class="text-muted d-block">Shrinkage (Temps improductif)</small>
                                                <?php 
                                                $shrinkValue = $snapshot['shrinkage_percent'] 
                                                    ?? ($current->shrinkage_percent ?? null);
                                                ?>
                                                <div class="d-flex align-items-baseline">
                                                    <strong class="h3 mb-0"><?= h($shrinkValue ?? 'N/A') ?></strong>
                                                    <span class="h4 text-warning ml-1">%</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="progress" style="width: 150px; height: 25px;">
                                                <div class="progress-bar bg-warning" role="progressbar" 
                                                     style="width: <?= h($shrinkValue) ?>%;" 
                                                     aria-valuenow="<?= h($shrinkValue) ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                    <?= h($shrinkValue) ?>%
                                                </div>
                                            </div>
                                            <small class="text-muted d-block mt-1">
                                                Formation, pauses, réunions, absences...
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

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
        <div class="card mb-4 border-info">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-stars"></i> Configuration Prophet (snapshot par offre)</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-light border mb-3 py-2">
                    <small class="text-muted mb-0">
                        <i class="bi bi-lock"></i>
                        Paramètres Prophet figés (voir règle dans la section « Offres concernées & méthode de prévision » ci-dessus)
                    </small>
                </div>
                <?php if (empty($scenario->forecast_scenarios_offers)): ?>
                    <p class="text-muted mb-0">
                        Aucune offre n'est associée à ce scénario.
                    </p>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($scenario->forecast_scenarios_offers as $link): 
                            if (($link->forecast_method ?? 'historical') !== 'prophet') {
                                continue;
                            }
                            $offerName = $link->offer->name ?? ('Offre #' . $link->offer_id);

                            // Snapshot Prophet complet pour cette offre
                            $snapshot = [];
                            if (!empty($link->prophet_settings_json)) {
                                if (is_string($link->prophet_settings_json)) {
                                    $snapshot = json_decode($link->prophet_settings_json, true) ?: [];
                                } elseif (is_array($link->prophet_settings_json)) {
                                    $snapshot = $link->prophet_settings_json;
                                }
                            }

                            $historyStart = $snapshot['history_start_date'] ?? null;
                            $historyEnd = $snapshot['history_end_date'] ?? null;
                            $hasHistory = !empty($historyStart) || !empty($historyEnd);
                        ?>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <strong><i class="bi bi-tag"></i> <?= h($offerName) ?></strong>
                                </div>
                                <div class="card-body">
                                    <div class="mb-2">
                                        <span class="badge badge-info">
                                            <i class="bi bi-stars"></i> Prophet
                                        </span>
                                        <?php if ($hasHistory): ?>
                                            <span class="badge badge-warning ml-1">
                                                <?= h($historyStart ?: 'Début auto') ?> → <?= h($historyEnd ?: 'Fin auto') ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-light ml-1">
                                                Historique complet (défauts)
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (empty($snapshot)): ?>
                                        <p class="text-muted mb-0">
                                            Aucun snapshot Prophet n'est encore disponible pour cette offre.
                                            Lance un calcul pour matérialiser les paramètres effectifs.
                                        </p>
                                    <?php else: ?>
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <h6 class="text-muted">Modèle & changements</h6>
                                                <ul class="list-unstyled small mb-2">
                                                    <li>
                                                        <strong>Mode:</strong>
                                                        <?= h($snapshot['seasonality_mode'] ?? 'multiplicative') ?>
                                                    </li>
                                                    <li>
                                                        <strong>n_changepoints:</strong>
                                                        <?= h($snapshot['n_changepoints'] ?? 25) ?>
                                                    </li>
                                                    <li>
                                                        <strong>changepoint_prior_scale:</strong>
                                                        <?= h($snapshot['changepoint_prior_scale'] ?? 0.1) ?>
                                                    </li>
                                                    <li>
                                                        <strong>seasonality_prior_scale:</strong>
                                                        <?= h($snapshot['seasonality_prior_scale'] ?? 10.0) ?>
                                                    </li>
                                                    <li>
                                                        <strong>monthly_fourier_order:</strong>
                                                        <?= h($snapshot['monthly_fourier_order'] ?? 5) ?>
                                                    </li>
                                                </ul>
                                            </div>
                                            <div class="col-sm-6">
                                                <h6 class="text-muted">Saisonnalités & jours fériés</h6>
                                                <ul class="list-unstyled small mb-0">
                                                    <?php
                                                    $flags = [
                                                        'yearly_seasonality' => 'Annuelle',
                                                        'weekly_seasonality' => 'Hebdomadaire',
                                                        'monthly_seasonality' => 'Mensuelle',
                                                        'daily_seasonality' => 'Journalière',
                                                    ];
                                                    foreach ($flags as $key => $label):
                                                        $enabled = array_key_exists($key, $snapshot) ? (bool)$snapshot[$key] : true;
                                                    ?>
                                                        <li>
                                                            <strong><?= h($label) ?>:</strong>
                                                            <span class="badge badge-<?= $enabled ? 'success' : 'light' ?>">
                                                                <?= $enabled ? 'Activée' : 'Désactivée' ?>
                                                            </span>
                                                        </li>
                                                    <?php endforeach; ?>

                                                    <?php 
                                                    $holidays = array_key_exists('use_french_holidays', $snapshot) ? (bool)$snapshot['use_french_holidays'] : true;
                                                    ?>
                                                    <li class="mt-1">
                                                        <strong>Jours fériés FR:</strong>
                                                        <span class="badge badge-<?= $holidays ? 'success' : 'light' ?>">
                                                            <?= $holidays ? 'Pris en compte' : 'Ignorés' ?>
                                                        </span>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php // --- Section Visualisation --- ?>
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-graph-up"></i> Visualisation sur une période</h5>
            </div>
            <div class="card-body">
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
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> 
                        La visualisation n'est disponible que pour les scénarios avec le statut <strong>Completed</strong>.
                        <?php if ($scenario->status === 'draft'): ?>
                            Lance un calcul pour voir les données.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

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
            document.getElementById('chartContainer').innerHTML = '<div class="text-center p-4"><div class="spinner-border text-primary" role="status"><span class="sr-only">Chargement...</span></div><p class="mt-2">Chargement Forecast + Need...</p></div>';
            
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
    if (typeof $ !== 'undefined' && $('[data-toggle="tooltip"]').tooltip) {
        $('[data-toggle="tooltip"]').tooltip();
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
        if (statusText) {
            statusText.textContent = status ? status.charAt(0).toUpperCase() + status.slice(1) : '';
        }
        if (badge) {
            badge.className = 'badge ' + (
                status === 'completed' ? 'badge-success' :
                status === 'failed' ? 'badge-danger' :
                (status === 'running' || status === 'queued') ? 'badge-warning' : 'badge-secondary'
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

