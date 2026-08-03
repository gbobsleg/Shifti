<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\I18n\FrozenDate $dayDate
 * @var array $offers_list
 * @var array $publishedByDate
 * @var bool $canLoadSeries
 */

$panelDayKey = $dayDate->i18nFormat('yyyy-MM-dd');
?>

<?php if ($canLoadSeries): ?>
    <div class="card shadow-sm mb-3" data-scenario-id="<?= isset($publishedByDate[$panelDayKey]) ? (int)$publishedByDate[$panelDayKey] : '' ?>">
        <div class="card-header bg-light py-2 cursor-pointer" data-toggle="collapse" data-target="#collapseChart<?= h($panelDayKey) ?>" aria-expanded="false" aria-controls="collapseChart<?= h($panelDayKey) ?>">
            <div class="row g-2 align-items-center">
                <div class="col-md-3 d-flex align-items-center">
                    <i class="bi bi-chevron-right mr-2" style="transition: transform 0.2s;"></i>
                    <h6 class="mb-0 text-primary small">
                        <i class="bi bi-graph-up"></i> <?= $dayDate->i18nFormat('dd/MM/yyyy'); ?>
                    </h6>
                </div>
                <div class="col-md-5">
                    <div class="d-flex align-items-center">
                        <label for="offerSelect<?= h($panelDayKey) ?>" class="form-label small text-muted mb-0 mr-2 d-flex align-items-center">
                            <i class="bi bi-basket mr-1"></i><span>Offre</span>
                        </label>
                        <select id="offerSelect<?= h($panelDayKey) ?>" class="form-control form-control-sm flex-grow-1" onclick="event.stopPropagation();">
                            <?php foreach ($offers_list as $offer): ?>
                                <option value="<?= h($offer->id) ?>"><?= h($offer->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <button type="button" class="btn btn-primary btn-sm w-100" id="compareBtn<?= h($panelDayKey) ?>">
                        <i class="bi bi-arrow-clockwise"></i> Charger
                    </button>
                </div>
            </div>
        </div>
        <div class="collapse" id="collapseChart<?= h($panelDayKey) ?>">
            <div class="card-body py-0">
                <div class="grids-chart-container d-flex align-items-center justify-content-center" id="compareChart<?= h($panelDayKey) ?>">
                    <div class="text-center">
                        <i class="bi bi-bar-chart" style="font-size: 4rem; color: #dee2e6;"></i>
                        <h5 class="mt-3 text-muted">Aucun graphique chargé</h5>
                        <p class="text-muted mb-0">Cliquez sur "Charger" pour afficher les données</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

