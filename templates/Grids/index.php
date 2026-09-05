<?php
/**
 * @var \App\View\AppView $this
 * @var bool|null $embedMode
 * @var \App\Model\Entity\PlanningGenerationJob|null $job
 */
use App\Service\Planning\GridQueryBudget;

$embedMode = !empty($embedMode);
$budgetThresholds = $budgetThresholds ?? (new GridQueryBudget())->thresholds();
$budgetResult = $budgetResult ?? [
    'allowed' => true,
    'message' => '',
    'code' => 'ok',
    'working_days' => 1,
    'view' => GridQueryBudget::VIEW_GANTT,
];
$zoom = $zoom ?? '15';
$gridView = $gridView ?? GridQueryBudget::VIEW_GANTT;
$showCharts = $showCharts ?? false;
$sortBy = $sortBy ?? 'site_name';
?>
<?php if (!$embedMode): ?>
<?php $this->extend('/layout/TwitterBootstrap/jumbotron'); ?>
<?php endif; ?>
<?php
$this->Html->css('grids/tokens', ['block' => true, 'timestamp' => 'force']);
$this->Html->css('grids/chrome', ['block' => true, 'timestamp' => 'force']);
$this->Html->css('grids/rail', ['block' => true, 'timestamp' => 'force']);
$this->Html->css('grids/gantt', ['block' => true, 'timestamp' => 'force']);
$this->Html->css('grids/month', ['block' => true, 'timestamp' => 'force']);
$this->Html->css('daterangepicker', ['block' => true]);
$identityObj = $this->request->getAttribute('identity');
$can = function (string $action, object $resource) use ($identityObj): bool {
    return $identityObj && method_exists($identityObj, 'can') && $identityObj->can($action, $resource);
};
$canSavePlanning = $can('add', new \App\Resource\GridsResource());
$canLoadSeries = $can('plannedSeries', new \App\Resource\GridsResource());
$canAlertsAdd = $can('add', new \App\Resource\AlertsResource());
$canAlertsDelete = $can('delete', new \App\Resource\AlertsResource());
$this->Html->scriptBlock('window.gridsBudget = ' . json_encode($budgetThresholds) . ';', ['block' => true]);
echo $this->Html->script('moment.min', ['block' => true]);
echo $this->Html->script('daterangepicker', ['block' => true]);
echo $this->Html->script('picker-grids', ['block' => true]);
echo $this->Html->script('grids-filters', ['block' => true]);
echo $this->Html->script('grids-nav', ['block' => true, 'timestamp' => 'force']);
echo $this->Html->script('grids-layout', ['block' => true, 'timestamp' => 'force']);
echo $this->Html->script('grids-bars', ['block' => true, 'timestamp' => 'force']);
if ($canSavePlanning) {
    echo $this->Html->script('dragselect', ['block' => true]);
    $this->Html->css('planning-day-history', ['block' => true]);
    echo $this->Html->script('planning-day-history', ['block' => true, 'timestamp' => true]);
}
echo $this->Html->script('grids', ['block' => true, 'timestamp' => 'force']);
if (!empty($showCharts)) {
    echo $this->element('apex_series_chart');
    echo $this->Html->script('grids-charts', ['block' => true, 'timestamp' => 'force']);
}

$saveUrl = $saveUrl ?? ['controller' => 'Grids', 'action' => 'add'];
$searchUrl = $searchUrl ?? ['controller' => 'Grids', 'action' => 'index'];
$plannedSeriesBaseUrl = $plannedSeriesBaseUrl ?? $this->Url->build(['controller' => 'Grids', 'action' => 'plannedSeries', '_ext' => 'json']);
$plannedSeriesExtraQuery = $plannedSeriesExtraQuery ?? '';

$offers_name = [];
$remoteWorkColor = '';
foreach ($offers_list as $offer) {
    $offers_name[$offer->id] = $offer->name;
    if (($offer->offer_type ?? '') === 'remote_work' && (string)($offer->color ?? '') !== '') {
        $remoteWorkColor = (string)$offer->color;
    }
}
$users_name = [];
foreach ($users_list as $user) {
    $users_name[$user->id] = $user->last_name . ' ' . $user->first_name;
}
asort($users_name);
$sites_name = [];
foreach ($sites_list as $sites) {
    $sites_name[$sites->id] = $sites->name;
}
asort($sites_name);

$alertsArray = (!$embedMode && isset($alerts_list)) ? $alerts_list->toArray() : [];
$totalAlerts = count($alertsArray);
$alertCounts = [1 => 0, 2 => 0, 3 => 0];
foreach ($alertsArray as $alert) {
    $prio = (int)$alert->priority;
    if (isset($alertCounts[$prio])) {
        $alertCounts[$prio]++;
    }
}

$appClass = 'grids-app';
if ($embedMode) {
    $appClass .= ' grids-app--embed';
}
if ($zoom === 'hour') {
    $appClass .= ' is-zoom-hour';
}
if ($remoteWorkColor === '') {
    $remoteOffer = \Cake\ORM\TableRegistry::getTableLocator()->get('Offers')
        ->find()
        ->select(['color'])
        ->where(['offer_type' => 'remote_work'])
        ->first();
    if ($remoteOffer && (string)($remoteOffer->color ?? '') !== '') {
        $remoteWorkColor = (string)$remoteOffer->color;
    }
}
if ($remoteWorkColor !== '' && !preg_match('/^#[0-9A-Fa-f]{3,8}$/', $remoteWorkColor)) {
    $remoteWorkColor = '';
}
?>
<div class="<?= h($appClass) ?>"<?php if ($remoteWorkColor !== ''): ?> style="--grids-remote-bg: <?= h($remoteWorkColor) ?>"<?php endif; ?> data-budget="<?= h(json_encode($budgetThresholds)) ?>">
<?php if (!empty($job) && !$embedMode): ?>
<div class="alert alert-info mb-2">
    <i class="bi bi-info-circle"></i>
    Brouillon job #<?= (int)$job->id ?> — non publié
</div>
<?php endif; ?>

<div class="grids-chrome">
    <?= $this->element('grids-search-form', [
        'users' => $users_name,
        'offers' => $offers_name,
        'sites' => $sites_name,
        'day_ranges' => [$day_ranges['begin']->format('d/m/Y'), $day_ranges['end']->format('d/m/Y')],
        'searchUrl' => $searchUrl,
        'embedMode' => $embedMode,
        'zoom' => $zoom,
        'sortBy' => $sortBy,
        'canAlertsAdd' => $canAlertsAdd,
    ]) ?>
    <?php if ($totalAlerts > 0): ?>
    <div class="grids-alerts">
        <div
            class="grids-chrome-row grids-alerts-toggle"
            data-bs-toggle="collapse"
            data-bs-target="#alertsCollapseContainer"
            aria-expanded="false"
            aria-controls="alertsCollapseContainer"
            role="button"
        >
            <span class="grids-alerts-meta">
                <i class="bi bi-chevron-right grids-alerts-chevron"></i>
                <i class="bi bi-bell"></i>
                <span>Alertes actives</span>
                <span class="grids-alerts-total"><?= (int)$totalAlerts ?></span>
                <?php if ($alertCounts[1] > 0): ?>
                    <span class="grids-alerts-pill is-p1" title="Priorité 1"><?= (int)$alertCounts[1] ?></span>
                <?php endif; ?>
                <?php if ($alertCounts[2] > 0): ?>
                    <span class="grids-alerts-pill is-p2" title="Priorité 2"><?= (int)$alertCounts[2] ?></span>
                <?php endif; ?>
                <?php if ($alertCounts[3] > 0): ?>
                    <span class="grids-alerts-pill is-p3" title="Priorité 3"><?= (int)$alertCounts[3] ?></span>
                <?php endif; ?>
            </span>
        </div>
        <div class="collapse" id="alertsCollapseContainer">
            <ul class="grids-alerts-list">
                <?php foreach ($alertsArray as $alert):
                    $prio = (int)$alert->priority;
                    $prioClass = $prio === 1 ? 'is-p1' : ($prio === 2 ? 'is-p2' : 'is-p3');
                    ?>
                    <li class="grids-alert <?= $prioClass ?>">
                        <span class="grids-alert-prio" title="Priorité <?= $prio ?>">P<?= $prio ?></span>
                        <span class="grids-alert-body">
                            <span class="grids-alert-dates">Du <?= $alert->date_start->i18nFormat('dd/MM') ?> au <?= $alert->date_end->i18nFormat('dd/MM') ?></span>
                            <span class="grids-alert-text"><?= h($alert->content) ?></span>
                        </span>
                        <?php if ($canAlertsDelete): ?>
                            <?= $this->Form->postLink(
                                '<i class="bi bi-x-lg"></i>',
                                ['controller' => 'Alerts', 'action' => 'delete', $alert->id],
                                [
                                    'confirm' => "Supprimer cette alerte ?\n\nPensez à sauvegarder le planning d'abord.",
                                    'class' => 'grids-alert-delete',
                                    'escape' => false,
                                    'title' => 'Supprimer',
                                ]
                            ) ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>
    <?php
    $usersCount = is_countable($users_ranges ?? null) ? count($users_ranges) : 0;
    $showLoadRows = !empty($showCharts) && !empty($canLoadSeries)
        && $gridView !== GridQueryBudget::VIEW_MONTH
        && $usersCount !== 1
        && !empty($offers_list);
    if (!empty($showCharts) && !empty($canLoadSeries) && $gridView !== GridQueryBudget::VIEW_MONTH) {
        $chartDay = $day_ranges['begin'];
        while ($chartDay->isWeekend() && $chartDay <= $day_ranges['end']) {
            $chartDay = $chartDay->addDays(1);
        }
        if (!$chartDay->isWeekend()) {
            echo $this->element('grids/_single_day_chart', [
                'dayDate' => $chartDay,
                'offers_list' => $offers_list,
                'publishedByDate' => $publishedByDate ?? [],
                'canLoadSeries' => $canLoadSeries,
                'showLoadRowsToggle' => $showLoadRows,
            ]);
        }
    }
    ?>
</div>

<?php if (empty($budgetResult['allowed'])): ?>
<div class="grids-budget-banner">
    <p><?= h((string)$budgetResult['message']) ?></p>
    <?php
    $q = $this->request->getQueryParams();
    $week = $q;
    $monday = \Cake\I18n\FrozenTime::now()->startOfWeek();
    $week['date_start'] = $monday->format('d/m/Y');
    $week['date_end'] = $monday->addDays(4)->format('d/m/Y');
    echo $this->Html->link('Cette semaine', ['action' => 'index', '?' => $week], ['class' => 'btn btn-sm btn-grids-primary me-2']);
    ?>
</div>
<?php endif; ?>

<div class="grids-body">
    <?php if (!empty($budgetResult['allowed']) && $gridView !== GridQueryBudget::VIEW_MONTH): ?>
        <?= $this->element('grids/_paint_rail', ['offers_list' => $offers_list]) ?>
    <?php endif; ?>
    <div class="grids-main">
        <?php echo $this->Form->create(null, ['url' => $saveUrl, 'id' => 'rangesForm']); ?>
        <input type="hidden" name="planning_data" id="planning-data-json" value="" />
        <?php echo $this->Form->hidden('day_ranges[begin]', ['value' => $day_ranges['begin']]); ?>
        <?php echo $this->Form->hidden('day_ranges[end]', ['value' => $day_ranges['end']]); ?>

        <?php if (!empty($budgetResult['allowed'])): ?>
            <?php
            $usersCount = $usersCount ?? (is_countable($users_ranges) ? count($users_ranges) : 0);
            if ($gridView === GridQueryBudget::VIEW_MONTH) {
                echo $this->element('grids/_grid_month', compact('users_ranges', 'day_ranges', 'offers_name'));
            } elseif ($usersCount === 1) {
                echo $this->element('grids/_grid_single_user', compact('users_ranges', 'day_ranges', 'offers_name', 'gridStartHour', 'gridEndHour'));
            } else {
                echo $this->element('grids/_grid_multi_user', compact(
                    'users_ranges',
                    'day_ranges',
                    'offers_name',
                    'offers_list',
                    'publishedByDate',
                    'canLoadSeries',
                    'gridStartHour',
                    'gridEndHour',
                    'showCharts'
                ));
            }
            ?>
        <?php endif; ?>

        <?php echo $this->Form->end(); ?>
    </div>
</div>

<?php if ($canSavePlanning && !empty($budgetResult['allowed']) && $gridView !== GridQueryBudget::VIEW_MONTH): ?>
    <button type="submit" form="rangesForm" class="btn grids-save-btn-floating" id="submitRanges" title="Enregistrer le planning">
        <span class="grids-save-btn-text">
            <i class="bi bi-floppy-fill me-2"></i><span>Enregistrer le planning</span>
        </span>
    </button>
    <div
        id="planningDayHistoryRoot"
        class="d-none"
        data-history-url="<?= h($this->Url->build(['controller' => 'Grids', 'action' => 'dayHistory', '_ext' => 'json'])) ?>"
        data-restore-url="<?= h($this->Url->build(['controller' => 'Grids', 'action' => 'restoreDayHistory', '_ext' => 'json'])) ?>"
        data-csrf-token="<?= h((string)$this->request->getAttribute('csrfToken')) ?>"
    ></div>
    <div id="planningDayHistoryMenu" class="pdh-context-menu" hidden>
        <button type="button" class="pdh-context-menu__item" id="planningDayHistoryMenuOpen">Historique du jour</button>
    </div>
    <div class="modal fade" id="planningDayHistoryModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Historique du jour</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <p class="pdh-modal-meta text-muted small mb-3" id="planningDayHistoryMeta"></p>
                    <div id="planningDayHistoryList" class="pdh-versions"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($canAlertsAdd): ?>
<div class="modal fade" id="alertAddModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-bell-fill text-warning me-2"></i>Ajouter une alerte</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <?= $this->element('alert-add-form', ['day_ranges' => $day_ranges]) ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($showCharts)): ?>
<div id="grids-charts-root" class="d-none"
     data-planned-base="<?= h($plannedSeriesBaseUrl) ?>"
     data-planned-extra="<?= h($plannedSeriesExtraQuery) ?>"
     data-need-base="<?= h($this->Url->build(['controller' => 'ForecastScenarios', 'action' => 'series'])) ?>">
</div>
<?php endif; ?>
</div>
