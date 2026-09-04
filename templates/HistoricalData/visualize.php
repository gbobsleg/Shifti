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

<div class="crud-app historical-visualize content">
    <div class="crud-header">
        <h1>Visualisation des données historiques</h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-arrow-left me-1"></i> Retour Administration',
                ['controller' => 'Pages', 'action' => 'display', 'admin'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>

    <section class="crud-section filters-section">
        <h2 class="crud-section-title">Filtres</h2>
        <?= $this->Form->create(null, ['type' => 'get', 'id' => 'filter-form']) ?>
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Offres</label>
                <small class="text-muted d-block mb-2">Maximum 3 offres</small>
                <?php foreach ($offers as $offer): ?>
                    <div class="form-check">
                        <?= $this->Form->checkbox('offers[]', [
                            'value' => $offer->id,
                            'checked' => in_array($offer->id, $selectedOffers),
                            'id' => 'offer-' . $offer->id,
                            'class' => 'form-check-input offer-checkbox',
                        ]) ?>
                        <label class="form-check-label" for="offer-<?= $offer->id ?>">
                            <?= h($offer->name) ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label">Période</label>
                <div class="row">
                    <div class="col-md-6">
                        <label for="start-date" class="small">Date de début</label>
                        <?= $this->Form->control('start_date', [
                            'type' => 'date',
                            'value' => $startDate,
                            'class' => 'form-control',
                            'label' => false,
                            'required' => true,
                            'id' => 'start-date',
                            'templates' => ['inputContainer' => '{{content}}'],
                        ]) ?>
                    </div>
                    <div class="col-md-6">
                        <label for="end-date" class="small">Date de fin</label>
                        <?= $this->Form->control('end_date', [
                            'type' => 'date',
                            'value' => $endDate,
                            'class' => 'form-control',
                            'label' => false,
                            'required' => true,
                            'id' => 'end-date',
                            'templates' => ['inputContainer' => '{{content}}'],
                        ]) ?>
                    </div>
                </div>
                <small class="text-muted">Maximum 90 jours</small>
                <div class="mt-3">
                    <label class="small">Raccourcis</label>
                    <div class="btn-group btn-group-sm d-block" role="group">
                        <button type="button" class="btn btn-outline-secondary preset-btn" data-days="7">7 jours</button>
                        <button type="button" class="btn btn-outline-secondary preset-btn" data-days="30">30 jours</button>
                        <button type="button" class="btn btn-outline-secondary preset-btn" data-days="60">60 jours</button>
                        <button type="button" class="btn btn-outline-secondary preset-btn" data-days="90">90 jours</button>
                    </div>
                </div>
            </div>

            <div class="col-md-2 mb-3">
                <label class="form-label" for="granularity-select">Granularité</label>
                <small class="text-muted d-block mb-2">Niveau de détail</small>
                <?= $this->Form->control('granularity', [
                    'type' => 'select',
                    'options' => [
                        '15min' => '15 minutes',
                        'hour' => 'Heure',
                        'day' => 'Jour',
                    ],
                    'value' => $granularity ?? '15min',
                    'class' => 'form-control',
                    'label' => false,
                    'id' => 'granularity-select',
                    'templates' => ['inputContainer' => '{{content}}'],
                ]) ?>
                <small class="text-muted mt-1 d-block">
                    <span id="granularity-hint">Recommandé pour cette plage</span>
                </small>
            </div>

            <div class="col-md-2 mb-3 d-flex align-items-end flex-column justify-content-center gap-2">
                <button type="submit" class="btn btn-primary w-100">Afficher</button>
                <button type="button" id="export-csv-btn" class="btn btn-outline-secondary w-100" <?= !$hasData ? 'disabled' : '' ?>>
                    Exporter CSV
                </button>
            </div>
        </div>
        <?= $this->Form->end() ?>
    </section>

    <div id="loading-indicator" class="text-center py-5" style="display: none;">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Chargement...</span>
        </div>
        <p class="mt-3 text-muted">Chargement des données en cours...</p>
    </div>

    <?php if ($hasData): ?>
        <section class="crud-section statistics-section">
            <h2 class="crud-section-title">Statistiques</h2>
            <?php foreach ($statistics as $offerName => $stats): ?>
                <h3 class="crud-subsection-title"><?= h($offerName) ?></h3>
                <dl class="crud-fields mb-2">
                    <div>
                        <dt>Volume total</dt>
                        <dd><?= number_format($stats['volume_total']) ?></dd>
                    </div>
                    <div>
                        <dt>Moyenne / 15 min</dt>
                        <dd><?= number_format($stats['volume_avg'], 2) ?></dd>
                    </div>
                    <div>
                        <dt>Volume max</dt>
                        <dd><?= number_format($stats['volume_max']) ?></dd>
                    </div>
                    <div>
                        <dt>DMT moyen</dt>
                        <dd><?= gmdate('i:s', $stats['dmt_avg']) ?></dd>
                    </div>
                    <div>
                        <dt>DMT min</dt>
                        <dd><?= gmdate('i:s', $stats['dmt_min']) ?></dd>
                    </div>
                    <div>
                        <dt>DMT max</dt>
                        <dd><?= gmdate('i:s', $stats['dmt_max']) ?></dd>
                    </div>
                </dl>
                <p class="crud-header-meta"><?= number_format($stats['data_points']) ?> points de données</p>
            <?php endforeach; ?>
        </section>

        <section class="crud-section chart-section">
            <h2 class="crud-section-title">Volume d'appels</h2>
            <div id="volume-chart" style="min-height: 450px;"></div>
        </section>

        <section class="crud-section chart-section">
            <h2 class="crud-section-title">Durée moyenne de traitement (DMT)</h2>
            <div id="dmt-chart" style="min-height: 450px;"></div>
        </section>

        <script>
            window.historicalChartData = <?= json_encode($chartData) ?>;
        </script>
    <?php elseif (!empty($selectedOffers)): ?>
        <div class="alert alert-warning" role="alert">
            Aucune donnée trouvée pour les filtres sélectionnés.
        </div>
    <?php else: ?>
        <div class="alert alert-info" role="alert">
            Sélectionnez au moins une offre et cliquez sur <strong>Afficher</strong> pour visualiser les données.
        </div>
    <?php endif; ?>
</div>
