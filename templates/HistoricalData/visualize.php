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

<?php $this->Html->css('daterangepicker', ['block' => true]); ?>
<?php $this->Html->css('historical-visualize', ['block' => true, 'timestamp' => 'force']); ?>
<?php $this->Html->script('moment.min', ['block' => true]); ?>
<?php $this->Html->script('daterangepicker', ['block' => true]); ?>
<?php $this->Html->script('historical-visualize', ['block' => true, 'timestamp' => 'force']); ?>
<?= $this->element('apex_series_chart'); ?>

<?php
$selectedLookup = array_fill_keys(array_map('strval', (array)$selectedOffers), true);
$selectedNames = [];
foreach ($offers as $offer) {
    if (isset($selectedLookup[(string)$offer->id])) {
        $selectedNames[] = (string)$offer->name;
    }
}
if (count($selectedNames) === 1) {
    $offerButtonLabel = $selectedNames[0];
} elseif (count($selectedNames) > 1) {
    $offerButtonLabel = count($selectedNames) . ' offres';
} else {
    $offerButtonLabel = 'Aucune offre';
}
$startFr = $startDate;
$endFr = $endDate;
try {
    $startFr = (new DateTime($startDate))->format('d/m/Y');
    $endFr = (new DateTime($endDate))->format('d/m/Y');
} catch (Exception $e) {
}
?>
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

    <section class="crud-section hv-filters">
        <?= $this->Form->create(null, ['type' => 'get', 'id' => 'filter-form']) ?>
        <?= $this->Form->hidden('period_unit', [
            'value' => $this->request->getQuery('period_unit', ''),
            'id' => 'period-unit',
        ]) ?>
        <?= $this->Form->hidden('period_offset', [
            'value' => $this->request->getQuery('period_offset', ''),
            'id' => 'period-offset',
        ]) ?>
        <?= $this->Form->hidden('start_date', [
            'value' => $startDate,
            'id' => 'start-date',
        ]) ?>
        <?= $this->Form->hidden('end_date', [
            'value' => $endDate,
            'id' => 'end-date',
        ]) ?>
        <div class="hv-filter-rows">
        <div class="hv-filter-row">
            <div class="hv-filter-field">
                <label>Offres</label>
                <div class="dropdown">
                    <button
                        type="button"
                        class="btn btn-outline-secondary hv-offers-toggle"
                        id="offers-toggle"
                        data-bs-toggle="dropdown"
                        data-bs-auto-close="outside"
                        aria-haspopup="true"
                    ><?= h($offerButtonLabel) ?></button>
                    <div class="dropdown-menu hv-offers-menu" aria-labelledby="offers-toggle">
                        <p class="hv-offers-help">3 offres maximum. En comparaison, les volumes s’additionnent et la DMT est pondérée.</p>
                        <?php if ($offers->isEmpty()): ?>
                            <p class="text-muted mb-0">Aucune offre utilisable en prévision.</p>
                        <?php else: ?>
                            <?php foreach ($offers as $offer): ?>
                                <div class="form-check">
                                    <?= $this->Form->checkbox('offers[]', [
                                        'value' => $offer->id,
                                        'checked' => isset($selectedLookup[(string)$offer->id]),
                                        'hiddenField' => false,
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
                </div>
            </div>

            <div class="hv-filter-field">
                <label for="period-display">Période</label>
                <input
                    type="text"
                    id="period-display"
                    class="form-control hv-period-display"
                    value="<?= h($startFr . ' – ' . $endFr) ?>"
                    readonly
                    title="Maximum une année civile"
                >
            </div>

            <div class="hv-filter-field">
                <label for="granularity-select">Granularité</label>
                <?= $this->Form->control('granularity', [
                    'type' => 'select',
                    'options' => [
                        '15min' => '15 minutes',
                        'hour' => 'Heure',
                        'day' => 'Jour',
                    ],
                    'value' => $granularity ?? '15min',
                    'class' => 'form-control hv-granularity',
                    'label' => false,
                    'id' => 'granularity-select',
                    'templates' => ['inputContainer' => '{{content}}'],
                ]) ?>
            </div>

            <div class="form-check hv-compare">
                <input
                    type="checkbox"
                    name="compare"
                    value="1"
                    id="compare-forecast"
                    class="form-check-input"
                    <?= !empty($compare) ? 'checked' : '' ?>
                >
                <label class="form-check-label" for="compare-forecast">Comparer à la prévision</label>
            </div>
        </div>
        <div class="hv-filter-row hv-filter-row-bottom">
            <div class="hv-filter-field">
                <label>Raccourcis</label>
                <div class="hv-presets">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="preset-prev" aria-label="Période précédente">−</button>
                    <div class="hv-preset-row" data-unit="week" data-offset="0">
                        <button type="button" class="btn btn-sm btn-outline-secondary preset-current">Cette semaine</button>
                    </div>
                    <div class="hv-preset-row" data-unit="month" data-offset="0">
                        <button type="button" class="btn btn-sm btn-outline-secondary preset-current">Ce mois</button>
                    </div>
                    <div class="hv-preset-row" data-unit="quarter" data-offset="0">
                        <button type="button" class="btn btn-sm btn-outline-secondary preset-current">Ce trimestre</button>
                    </div>
                    <div class="hv-preset-row" data-unit="year" data-offset="0">
                        <button type="button" class="btn btn-sm btn-outline-secondary preset-current">Cette année</button>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="preset-next" aria-label="Période suivante" disabled>+</button>
                </div>
            </div>

            <div class="hv-filter-actions">
                <button type="submit" class="btn btn-primary">Afficher</button>
                <button type="button" id="export-csv-btn" class="btn btn-outline-secondary" <?= !$hasData ? 'disabled' : '' ?>>
                    Exporter CSV
                </button>
            </div>
        </div>
        </div>
        <small id="granularity-hint" class="text-muted hv-filter-note"></small>
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
