<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Collection\CollectionInterface $offers
 * @var array $selectedOffers
 * @var string $startDate
 * @var string $endDate
 * @var array|null $chartData
 * @var array|null $statistics
 * @var bool $hasData
 */
?>
<?php $this->assign('title', 'Visualisation des Données Historiques'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php $this->Html->css('historical-visualize', ['block' => true]); ?>
<?php $this->Html->script('moment.min', ['block' => true]); ?>
<?php $this->Html->script('daterangepicker', ['block' => true]); ?>
<?php $this->Html->script('historical-visualize', ['block' => true]); ?>
<?= $this->element('apex_series_chart'); ?>

<div class="historical-visualize content">
    <div class="card">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h3 class="mb-0">
                <i class="bi bi-graph-up text-primary"></i>
                Visualisation des Données Historiques
            </h3>
            <div>
                <?= $this->Html->link(
                    '<i class="bi bi-arrow-left mr-1"></i> Retour Administration',
                    ['controller' => 'Pages', 'action' => 'display', 'admin'],
                    ['class' => 'btn btn-secondary btn-sm', 'escape' => false]
                ) ?>
            </div>
        </div>
        
        <div class="card-body">
            <!-- Section Filtres -->
            <div class="filters-section card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-funnel"></i> Filtres
                    </h5>
                </div>
                <div class="card-body">
                    <?= $this->Form->create(null, ['type' => 'get', 'id' => 'filter-form']) ?>
                    <div class="row">
                        <!-- Sélection des offres -->
                        <div class="col-md-4 mb-3">
                            <label class="font-weight-bold">
                                <i class="bi bi-basket"></i> Offres
                            </label>
                            <small class="text-muted d-block mb-2">Maximum 3 offres</small>
                            <?php foreach ($offers as $offer): ?>
                                <div class="form-check">
                                    <?= $this->Form->checkbox('offers[]', [
                                        'value' => $offer->id,
                                        'checked' => in_array($offer->id, $selectedOffers),
                                        'id' => 'offer-' . $offer->id,
                                        'class' => 'form-check-input offer-checkbox'
                                    ]) ?>
                                    <label class="form-check-label" for="offer-<?= $offer->id ?>">
                                        <?= h($offer->name) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Plage de dates -->
                        <div class="col-md-4 mb-3">
                            <label class="font-weight-bold">
                                <i class="bi bi-calendar-range"></i> Période
                            </label>
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="start_date" class="small">Date de début</label>
                                    <?= $this->Form->control('start_date', [
                                        'type' => 'date',
                                        'value' => $startDate,
                                        'class' => 'form-control',
                                        'label' => false,
                                        'required' => true
                                    ]) ?>
                                </div>
                                <div class="col-md-6">
                                    <label for="end_date" class="small">Date de fin</label>
                                    <?= $this->Form->control('end_date', [
                                        'type' => 'date',
                                        'value' => $endDate,
                                        'class' => 'form-control',
                                        'label' => false,
                                        'required' => true
                                    ]) ?>
                                </div>
                            </div>
                            <small class="text-muted">Maximum 90 jours</small>
                            
                            <!-- Boutons présets -->
                            <div class="mt-3">
                                <label class="small font-weight-bold">Raccourcis :</label>
                                <div class="btn-group btn-group-sm d-block" role="group">
                                    <button type="button" class="btn btn-outline-secondary preset-btn" data-days="7">7 jours</button>
                                    <button type="button" class="btn btn-outline-secondary preset-btn" data-days="30">30 jours</button>
                                    <button type="button" class="btn btn-outline-secondary preset-btn" data-days="60">60 jours</button>
                                    <button type="button" class="btn btn-outline-secondary preset-btn" data-days="90">90 jours</button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Granularité -->
                        <div class="col-md-2 mb-3">
                            <label class="font-weight-bold">
                                <i class="bi bi-speedometer2"></i> Granularité
                            </label>
                            <small class="text-muted d-block mb-2">Niveau de détail</small>
                            <?= $this->Form->control('granularity', [
                                'type' => 'select',
                                'options' => [
                                    '15min' => '15 minutes',
                                    'hour' => 'Heure',
                                    'day' => 'Jour'
                                ],
                                'value' => $granularity ?? '15min',
                                'class' => 'form-control',
                                'label' => false,
                                'id' => 'granularity-select'
                            ]) ?>
                            <small class="text-muted mt-1 d-block">
                                <span id="granularity-hint">Recommandé pour cette plage</span>
                            </small>
                        </div>
                        
                        <!-- Boutons d'action -->
                        <div class="col-md-2 mb-3 d-flex align-items-end flex-column justify-content-center">
                            <button type="submit" class="btn btn-primary btn-block mb-2">
                                <i class="bi bi-search"></i> Afficher
                            </button>
                            <button type="button" id="export-csv-btn" class="btn btn-success btn-block" <?= !$hasData ? 'disabled' : '' ?>>
                                <i class="bi bi-file-earmark-excel"></i> Exporter CSV
                            </button>
                        </div>
                    </div>
                    <?= $this->Form->end() ?>
                </div>
            </div>
            
            <!-- Loading indicator -->
            <div id="loading-indicator" class="text-center py-5" style="display: none;">
                <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                    <span class="sr-only">Chargement...</span>
                </div>
                <p class="mt-3 text-muted">Chargement des données en cours...</p>
            </div>
            
            <?php if ($hasData): ?>
                <!-- Section Statistiques -->
                <div class="statistics-section mb-4">
                    <h4 class="mb-3">
                        <i class="bi bi-graph-up-arrow text-success"></i> Statistiques
                    </h4>
                    <div class="row">
                        <?php foreach ($statistics as $offerName => $stats): ?>
                            <div class="col-md-12 mb-3">
                                <div class="card border-info">
                                    <div class="card-header bg-info text-white">
                                        <strong><?= h($offerName) ?></strong>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-md-2">
                                                <div class="stat-card">
                                                    <i class="bi bi-telephone text-primary stat-icon"></i>
                                                    <h5 class="mb-1"><?= number_format($stats['volume_total']) ?></h5>
                                                    <small class="text-muted">Volume Total</small>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="stat-card">
                                                    <i class="bi bi-graph-up text-success stat-icon"></i>
                                                    <h5 class="mb-1"><?= number_format($stats['volume_avg'], 2) ?></h5>
                                                    <small class="text-muted">Moyenne/15min</small>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="stat-card">
                                                    <i class="bi bi-arrow-up-circle text-danger stat-icon"></i>
                                                    <h5 class="mb-1"><?= number_format($stats['volume_max']) ?></h5>
                                                    <small class="text-muted">Volume Max</small>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="stat-card">
                                                    <i class="bi bi-clock text-info stat-icon"></i>
                                                    <h5 class="mb-1"><?= gmdate("i:s", $stats['dmt_avg']) ?></h5>
                                                    <small class="text-muted">DMT Moyen</small>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="stat-card">
                                                    <i class="bi bi-arrow-down-circle text-success stat-icon"></i>
                                                    <h5 class="mb-1"><?= gmdate("i:s", $stats['dmt_min']) ?></h5>
                                                    <small class="text-muted">DMT Min</small>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="stat-card">
                                                    <i class="bi bi-arrow-up-circle text-warning stat-icon"></i>
                                                    <h5 class="mb-1"><?= gmdate("i:s", $stats['dmt_max']) ?></h5>
                                                    <small class="text-muted">DMT Max</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-2 text-center">
                                            <small class="badge badge-secondary">
                                                <?= number_format($stats['data_points']) ?> points de données
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Section Graphique Volume -->
                <div class="chart-section mb-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="bi bi-bar-chart-line"></i> Volume d'Appels
                            </h5>
                        </div>
                        <div class="card-body">
                            <div id="volume-chart" style="min-height: 450px;"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Section Graphique DMT -->
                <div class="chart-section mb-4">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="bi bi-clock-history"></i> Durée Moyenne de Traitement (DMT)
                            </h5>
                        </div>
                        <div class="card-body">
                            <div id="dmt-chart" style="min-height: 450px;"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Données pour JavaScript -->
                <script>
                    window.historicalChartData = <?= json_encode($chartData) ?>;
                </script>
            <?php elseif (!empty($selectedOffers)): ?>
                <div class="alert alert-warning" role="alert">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Aucune donnée trouvée</strong> pour les filtres sélectionnés.
                </div>
            <?php else: ?>
                <div class="alert alert-info" role="alert">
                    <i class="bi bi-info-circle"></i>
                    Sélectionnez au moins une offre et cliquez sur <strong>Afficher</strong> pour visualiser les données.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

