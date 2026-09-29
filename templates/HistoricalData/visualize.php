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
 * @var bool $compare
 */
?>
<?php $this->assign('title', 'Réel et prévision'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php $this->Html->css('historical-visualize', ['block' => true, 'timestamp' => 'force']); ?>
<?php $this->Html->script('historical-visualize', ['block' => true, 'timestamp' => 'force']); ?>
<?= $this->element('apex_series_chart'); ?>

<div class="crud-app historical-visualize content">
    <div class="crud-header">
        <h1>Réel et prévision</h1>
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
        <?= $this->Form->hidden('period_unit', [
            'value' => $this->request->getQuery('period_unit', ''),
            'id' => 'period-unit',
        ]) ?>
        <?= $this->Form->hidden('period_offset', [
            'value' => $this->request->getQuery('period_offset', ''),
            'id' => 'period-offset',
        ]) ?>
        <div class="mb-3">
            <label class="form-label mb-1">Offres</label>
            <small class="text-muted d-block mb-2">Maximum 3 · uniquement les offres utilisables en prévision</small>
            <div class="hv-offers">
                <?php if ($offers->isEmpty()): ?>
                    <p class="text-muted mb-0">Aucune offre utilisable en prévision.</p>
                <?php else: ?>
                    <?php foreach ($offers as $offer): ?>
                        <div class="form-check">
                            <?= $this->Form->checkbox('offers[]', [
                                'value' => $offer->id,
                                'checked' => in_array((string)$offer->id, array_map('strval', (array)$selectedOffers), true),
                                'id' => 'offer-' . $offer->id,
                                'class' => 'form-check-input offer-checkbox',
                            ]) ?>
                            <label class="form-check-label" for="offer-<?= $offer->id ?>">
                                <?= h($offer->name) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="form-check mt-3">
                <input
                    type="checkbox"
                    name="compare"
                    value="1"
                    id="compare-forecast"
                    class="form-check-input"
                    <?= !empty($compare) ? 'checked' : '' ?>
                >
                <label class="form-check-label" for="compare-forecast">Comparer à la prévision publiée</label>
                <small id="compare-hint" class="text-muted hv-compare-hint<?= !empty($compare) ? ' d-none' : '' ?>">Sélectionnez une seule offre pour comparer à la prévision publiée.</small>
            </div>
        </div>
        <div class="row">
            <div class="col-md-5 mb-3">
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
                <small class="text-muted">Maximum une année civile</small>
                <div class="mt-3">
                    <label class="small">Raccourcis</label>
                    <div class="hv-presets">
                        <div class="hv-preset-row" data-unit="week" data-offset="0">
                            <button type="button" class="btn btn-sm btn-outline-secondary preset-step" data-step="-1" aria-label="Période précédente">−</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary preset-current">Cette semaine</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary preset-step" data-step="1" aria-label="Période suivante" disabled>+</button>
                        </div>
                        <div class="hv-preset-row" data-unit="month" data-offset="0">
                            <button type="button" class="btn btn-sm btn-outline-secondary preset-step" data-step="-1" aria-label="Période précédente">−</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary preset-current">Ce mois</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary preset-step" data-step="1" aria-label="Période suivante" disabled>+</button>
                        </div>
                        <div class="hv-preset-row" data-unit="quarter" data-offset="0">
                            <button type="button" class="btn btn-sm btn-outline-secondary preset-step" data-step="-1" aria-label="Période précédente">−</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary preset-current">Ce trimestre</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary preset-step" data-step="1" aria-label="Période suivante" disabled>+</button>
                        </div>
                        <div class="hv-preset-row" data-unit="year" data-offset="0">
                            <button type="button" class="btn btn-sm btn-outline-secondary preset-step" data-step="-1" aria-label="Période précédente">−</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary preset-current">Cette année</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary preset-step" data-step="1" aria-label="Période suivante" disabled>+</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-3">
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
                    <span id="granularity-hint"></span>
                </small>
            </div>

            <div class="col-md-3 mb-3 d-flex align-items-end flex-column justify-content-end gap-2">
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
        <?php
        $formatDmt = static function ($seconds): string {
            if ($seconds === null || $seconds === '') {
                return '—';
            }
            $seconds = max(0, (int)$seconds);

            return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
        };
        $formatSigned = static function (int $value): string {
            $text = number_format($value, 0, ',', ' ');

            return $value > 0 ? '+' . $text : $text;
        };
        $formatSignedPct = static function ($value): string {
            if ($value === null) {
                return '—';
            }
            $text = number_format((float)$value, 2, ',', ' ');

            return ((float)$value > 0 ? '+' : '') . $text . ' %';
        };
        $formatPct = static function ($value): string {
            if ($value === null) {
                return '—';
            }

            return number_format((float)$value, 2, ',', ' ') . ' %';
        };
        ?>
        <section class="crud-section statistics-section">
            <h2 class="crud-section-title">Statistiques</h2>
            <div class="hv-stats-layout<?= count($statistics) > 1 ? ' has-share' : '' ?>">
                <div class="hv-stats-list">
                    <?php if (empty($statistics)): ?>
                        <p class="text-muted mb-0">Aucune statistique pour les offres et la période sélectionnées.</p>
                    <?php endif; ?>
                    <?php foreach ($statistics as $offerName => $stats): ?>
                        <?php $cmp = $stats['compare'] ?? null; ?>
                        <h3 class="crud-subsection-title"><?= h($offerName) ?></h3>
                        <?php if (is_array($cmp) && empty($cmp['has_forecast'])): ?>
                            <p class="text-muted">Aucune prévision publiée sur cette période.</p>
                        <?php endif; ?>
                        <?php if (is_array($cmp) && !empty($cmp['has_forecast'])): ?>
                            <dl class="crud-fields mb-2">
                                <div>
                                    <dt>Volume réel</dt>
                                    <dd><?= number_format((int)$cmp['volume_real'], 0, ',', ' ') ?></dd>
                                </div>
                                <div>
                                    <dt>Volume prévu</dt>
                                    <dd><?= number_format((int)$cmp['volume_forecast'], 0, ',', ' ') ?></dd>
                                </div>
                                <div>
                                    <dt>Écart</dt>
                                    <dd><?= h($formatSigned((int)$cmp['gap'])) ?> appels (<?= h($formatSignedPct($cmp['gap_percent'])) ?>)</dd>
                                </div>
                                <div>
                                    <dt>WAPE à cette granularité</dt>
                                    <dd><?= h($formatPct($cmp['wape'])) ?></dd>
                                </div>
                                <div>
                                    <dt>DMT réelle</dt>
                                    <dd><?= h($formatDmt($cmp['dmt_weighted'])) ?></dd>
                                </div>
                            </dl>
                            <?php $missingDays = (int)($cmp['missing_days'] ?? 0); ?>
                            <?php if ($missingDays === 1): ?>
                                <p class="crud-header-meta">1 jour sans prévision, exclu des totaux</p>
                            <?php elseif ($missingDays > 1): ?>
                                <p class="crud-header-meta"><?= number_format($missingDays, 0, ',', ' ') ?> jours sans prévision, exclus des totaux</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <dl class="crud-fields mb-2">
                                <div>
                                    <dt>Volume total</dt>
                                    <dd><?= number_format((int)$stats['volume_total'], 0, ',', ' ') ?></dd>
                                </div>
                                <div>
                                    <dt>DMT réelle</dt>
                                    <dd><?= h($formatDmt($stats['dmt_avg'])) ?></dd>
                                </div>
                            </dl>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php if (count($statistics) > 1): ?>
                    <div class="hv-stats-share">
                        <h3 class="crud-subsection-title mt-0">Répartition du volume</h3>
                        <div id="volume-share-chart"></div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="crud-section chart-section">
            <h2 class="crud-section-title"><?= count($statistics) > 1 ? 'Volume' : 'Volume et DMT' ?></h2>
            <?php if (count($statistics) > 1): ?>
                <p class="text-muted">DMT affichée pour une seule offre.</p>
            <?php endif; ?>
            <div id="volume-chart"></div>
        </section>

        <script>
            window.historicalChartData = <?= json_encode($chartData) ?>;
            window.historicalStatistics = <?= json_encode($statistics) ?>;
        </script>
    <?php elseif (!empty($selectedOffers)): ?>
        <div class="alert alert-warning" role="alert">
            Aucune statistique pour les offres et la période sélectionnées.
        </div>
    <?php else: ?>
        <div class="alert alert-info" role="alert">
            Sélectionnez au moins une offre et cliquez sur <strong>Afficher</strong> pour visualiser les données.
        </div>
    <?php endif; ?>
</div>
