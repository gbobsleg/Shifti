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
    <div class="grids-chart-card" data-scenario-id="<?= isset($publishedByDate[$panelDayKey]) ? (int)$publishedByDate[$panelDayKey] : '' ?>">
        <div class="grids-chrome-row grids-chart-toggle">
            <span
                class="grids-chart-meta"
                data-bs-toggle="collapse"
                data-bs-target="#collapseChart<?= h($panelDayKey) ?>"
                aria-expanded="false"
                aria-controls="collapseChart<?= h($panelDayKey) ?>"
                role="button"
            >
                <i class="bi bi-chevron-right grids-chart-chevron"></i>
                <i class="bi bi-graph-up"></i>
                <span><?= $dayDate->i18nFormat('dd/MM/yyyy'); ?></span>
            </span>
            <label class="grids-chart-offer" for="offerSelect<?= h($panelDayKey) ?>">
                <i class="bi bi-briefcase"></i>
                <span>Offre</span>
            </label>
            <select id="offerSelect<?= h($panelDayKey) ?>" class="form-control form-control-sm">
                <?php foreach ($offers_list as $offer): ?>
                    <option value="<?= h($offer->id) ?>"><?= h($offer->name) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-grids-ghost btn-sm" id="compareBtn<?= h($panelDayKey) ?>">
                <i class="bi bi-arrow-clockwise"></i> Charger
            </button>
        </div>
        <div class="collapse" id="collapseChart<?= h($panelDayKey) ?>">
            <div class="grids-chart-container d-flex align-items-center justify-content-center" id="compareChart<?= h($panelDayKey) ?>">
                <div class="text-center">
                    <i class="bi bi-bar-chart"></i>
                    <p>Choisissez une offre pour afficher les courbes</p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
