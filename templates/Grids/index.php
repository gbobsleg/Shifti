<?php
/**
* @var \App\View\AppView $this
* @var \App\Model\Entity\User[]|\Cake\Collection\CollectionInterface $users
* @var bool|null $embedMode
* @var \App\Model\Entity\PlanningGenerationJob|null $job
*/
$embedMode = !empty($embedMode);
?>
<?php if (!$embedMode): ?>
<?php $this->extend('/layout/TwitterBootstrap/jumbotron'); ?>
<?php endif; ?>
<?php $this->Html->css('planning-grids', ['block' => true]); ?>
<?php $this->Html->css('daterangepicker', ['block' => true]); ?>
<?php
// Autorisations UI via l'identité décorée (évite la dépendance au helper Authorization)
$identityObj = $this->request->getAttribute('identity');
$can = function (string $action, object $resource) use ($identityObj): bool {
    return $identityObj && method_exists($identityObj, 'can') && $identityObj->can($action, $resource);
};
$canSavePlanning = $can('add', new \App\Resource\GridsResource());
$canLoadSeries = $can('plannedSeries', new \App\Resource\GridsResource());
$canAlertsAdd = $can('add', new \App\Resource\AlertsResource());
$canAlertsDelete = $can('delete', new \App\Resource\AlertsResource());
?>
<?php echo $this->Html->script('moment.min', ['block' => true]); ?>
<?php echo $this->Html->script('daterangepicker', ['block' => true]); ?>
<?php echo $this->Html->script('picker-grids', ['block' => true]); ?>
<?php if ($canSavePlanning): ?>
    <?php echo $this->Html->script('dragselect', ['block' => true]); ?>
    <?php $this->Html->css('planning-day-history', ['block' => true]); ?>
    <?php echo $this->Html->script('planning-day-history', ['block' => true, 'timestamp' => true]); ?>
<?php endif; ?>
<?php echo $this->Html->script('grids', ['block' => true]); ?>
<?= $this->element('apex_series_chart'); ?>

<?php
$saveUrl = $saveUrl ?? ['controller' => 'Grids', 'action' => 'add'];
$searchUrl = $searchUrl ?? ['controller' => 'Grids', 'action' => 'index'];
$plannedSeriesBaseUrl = $plannedSeriesBaseUrl ?? $this->Url->build(['controller' => 'Grids', 'action' => 'plannedSeries', '_ext' => 'json']);
$plannedSeriesExtraQuery = $plannedSeriesExtraQuery ?? '';

$offers_name = [];
foreach ($offers_list as $offer) {
    $offers_name[$offer->id]= $offer->name;
}
$users_name = [];
foreach ($users_list as $user) {
    $users_name[$user->id] = $user->last_name.' '.$user->first_name;
}
asort($users_name);
$sites_name = [];
foreach ($sites_list as $sites) {
    $sites_name[$sites->id] = $sites->name;
}
asort($sites_name);
?>
<?php // variables $can* déjà définies ci-dessus ?>
<?php if (!$embedMode): ?>
<div class="grids-page-shell">
<?php endif; ?>
<?php if (!empty($job) && !$embedMode): ?>
<div class="alert alert-info mb-2">
    <i class="bi bi-info-circle"></i>
    Brouillon job #<?= (int)$job->id ?> — non publié
</div>
<?php endif; ?>
<div class="card <?= $embedMode ? 'mb-2' : 'grids-search-bar-sticky' ?>">
    <div class="card-body py-2">
        <div class="row align-items-center justify-content-center">
            <?php echo $this->element('grids-search-form', [
                'users' => $users_name,
                'offers' => $offers_name,
                'sites' => $sites_name,
                // i18nFormat() utilise ICU: 'm' = minutes. On utilise format() (PHP) pour JJ/MM/AAAA.
                'day_ranges' => [$day_ranges['begin']->format('d/m/Y'), $day_ranges['end']->format('d/m/Y')],
                'searchUrl' => $searchUrl,
                'embedMode' => $embedMode,
            ]); ?>
        </div>
    </div>
</div>
<?php if (!$embedMode): ?>
<div class="grids-search-spacer"></div>
<?php endif; ?>

<?php if (!$embedMode): ?>
<?php
// Initialiser les compteurs pour les alertes
$counts = [1 => 0, 2 => 0, 3 => 0, 'other' => 0];
$totalAlerts = 0;

// Convertir la Query en tableau
$alertsArray = $alerts_list->toArray();
$totalAlerts = count($alertsArray);

// Compter les alertes par priorité
foreach($alertsArray as $alert) {
    if (isset($counts[$alert->priority])) {
        $counts[$alert->priority]++;
    } else {
        $counts['other']++;
    }
}
?>

<div class="grids-alerts-bar-sticky <?= $totalAlerts > 0 ? 'border-warning' : '' ?>">
    <div class="card shadow-sm">
        <div class="card-header bg-light cursor-pointer py-2" data-toggle="collapse" data-target="#alertsCollapseContainer" aria-expanded="false" aria-controls="alertsCollapseContainer">
            <div class="d-flex justify-content-center align-items-center">
                <h6 class="mb-0 mr-5">
                    <i class="bi bi-bell-fill <?= $totalAlerts > 0 ? 'text-warning' : 'text-muted' ?>"></i>
                    <?= $totalAlerts > 0 ? "Alertes actives ({$totalAlerts})" : "Aucune alerte en cours" ?>
                </h6>
                <span class="d-flex align-items-center mr-3">
                    <?php if ($counts[1] > 0): ?>
                        <span class="badge badge-danger badge-pill mr-1">
                            <i class="bi bi-exclamation-triangle-fill"></i> <?= $counts[1] ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($counts[2] > 0): ?>
                        <span class="badge badge-warning badge-pill mr-1">
                            <i class="bi bi-flag-fill"></i> <?= $counts[2] ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($counts[3] > 0): ?>
                        <span class="badge badge-primary badge-pill mr-2">
                            <i class="bi bi-info-circle-fill"></i> <?= $counts[3] ?>
                        </span>
                    <?php endif; ?>
                    <i class="bi bi-chevron-down"></i>
                </span>
            </div>
        </div>
    </div>
    <div class="collapse" id="alertsCollapseContainer">
        <div class="card">
            <div class="card-body bg-light pt-3">
                        <?php if ($totalAlerts > 0): ?>
                            <div class="row mb-3">
                                <?php foreach ($alertsArray as $alert) {
                                    $cardClass = '';
                                    $headerText = 'Priorité ' . $alert->priority;
                                    $icon = 'bi-info-circle-fill';

                                    if ($alert->priority === 1) {
                                        $cardClass = 'border-danger';
                                        $headerText = 'Urgent';
                                        $icon = 'bi-exclamation-triangle-fill';
                                    } elseif ($alert->priority === 2) {
                                        $cardClass = 'border-warning';
                                        $headerText = 'Important';
                                        $icon = 'bi-flag-fill';
                                    } elseif ($alert->priority === 3) {
                                        $cardClass = 'border-primary';
                                        $headerText = 'Information';
                                        $icon = 'bi-info-circle-fill';
                                    } else {
                                        $cardClass = 'border-secondary';
                                    }
                                    ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card h-100 <?= $cardClass; ?>">
                                            <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                                                <span class="badge badge-<?= $alert->priority === 1 ? 'danger' : ($alert->priority === 2 ? 'warning' : 'primary') ?>">
                                                    <i class="bi <?= $icon ?>"></i> <?= h($headerText) ?>
                                                </span>
                                                <?php if ($canAlertsDelete): ?>
                                                    <?= $this->Form->postLink(
                                                        '<i class="bi bi-x-lg"></i>',
                                                        ['controller' => 'Alerts', 'action' => 'delete', $alert->id],
                                                        [
                                                            'confirm' => "Supprimer cette alerte ?\n\nPensez à sauvegarder le planning d'abord.",
                                                            'class' => 'btn btn-sm btn-outline-danger p-0 px-2',
                                                            'escape' => false,
                                                            'title' => 'Supprimer'
                                                        ]
                                                    ); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="card-body">
                                                <p class="small mb-2">
                                                    <i class="bi bi-calendar-range text-muted"></i>
                                                    <strong>Du <?= $alert->date_start->i18nFormat('dd/MM') ?> au <?= $alert->date_end->i18nFormat('dd/MM') ?></strong>
                                                </p>
                                                <p class="card-text mb-0">
                                                    <?= h($alert->content) ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-2 mb-0">Aucune alerte en cours pour cette période</p>
                            </div>
                        <?php endif; ?>

                        <?php if ($canAlertsAdd): ?>
                            <div class="border-top pt-3 mt-3">
                                <?php echo $this->element('alert-add-form', [
                                    'day_ranges' => $day_ranges
                                ]); ?>
                            </div>
                        <?php endif; ?>
                </div><!-- .card-body -->
            </div><!-- .card -->
        </div><!-- #alertsCollapseContainer -->
</div><!-- .grids-alerts-bar-sticky -->
<?php endif; ?>

<?php if ($embedMode): ?>
<div class="grids-embed-body">
    <aside class="grids-embed-sidebar">
<?php endif; ?>

<div class="grids-sort-card card shadow-sm mb-2">
    <div class="card-header bg-light py-2">
        <h6 class="mb-0 text-center">
            <i class="bi bi-sort-alpha-down text-primary"></i> Tri
        </h6>
    </div>
    <div class="card-body p-2">
        <select id="sort-select" name="sort_by" class="form-control form-control-sm">
            <option value="site_name" <?= $this->request->getQuery('sort_by', 'site_name') === 'site_name' ? 'selected' : '' ?>>Site (A-Z)</option>
            <option value="last_name" <?= $this->request->getQuery('sort_by') === 'last_name' ? 'selected' : '' ?>>Nom (A-Z)</option>
            <option value="user_code" <?= $this->request->getQuery('sort_by') === 'user_code' ? 'selected' : '' ?>>Code Agent</option>
        </select>
    </div>
</div>

<div class="grids-site-toggle-card card shadow-sm mb-2">
    <div class="card-body p-2">
        <button type="button" id="toggle-site-column" class="btn btn-sm btn-outline-primary w-100" title="Afficher/Masquer la colonne Site">
            <i class="bi bi-building mr-1" id="toggle-site-icon"></i>
            <span id="toggle-site-text">Afficher Site</span>
        </button>
    </div>
</div>

<div class="offers-legend-card card shadow-sm">
    <div class="card-header bg-light py-2">
        <h6 class="mb-0 text-center">
            <i class="bi bi-palette-fill text-primary"></i> Légende
        </h6>
    </div>
    <div class="card-body p-2">
        <small class="text-muted d-block mb-2">
            <i class="bi bi-hand-index"></i> Cliquer pour sélectionner
        </small>
        <?php foreach ($offers_list as $offer) { ?>
            <div class="offer-legend-item offerColor" data-id="<?= $offer->id; ?>" data-color="<?= $offer->color; ?>">
                <div class="offer-color-box" style="background-color: <?= $offer->color; ?>"></div>
                <span class="offer-name"><?= h($offer->name) ?></span>
            </div>
        <?php } ?>
    </div>
</div>

<?php if ($embedMode): ?>
    </aside>
    <div class="grids-embed-main">
<?php endif; ?>

<div class="main-content-wrapper">
    <div class="row">
        <div class="grids col <?= $embedMode ? 'mt-0' : 'mt-4' ?>">

        <?php
        // Compter les utilisateurs pour afficher ou non les graphiques
        $usersCount = is_countable($users_ranges) ? count($users_ranges) : $users_ranges->count();
        ?>

        <?php echo $this->Form->create(null, [
            // Permet de réutiliser la même page pour un brouillon en changeant simplement l'URL.
            // Par défaut: Grids::add
            'url' => $saveUrl,
            'id' => 'rangesForm',
        ]); ?>

            <input type="hidden" name="planning_data" id="planning-data-json" value="" />

            <?php echo $this->Form->hidden('day_ranges[begin]', array('value' => $day_ranges['begin'])); ?>
            <?php echo $this->Form->hidden('day_ranges[end]', array('value' => $day_ranges['end'])); ?>

        <?php
        // Aiguillage de la vue en fonction du nombre de résultats (variable $usersCount déjà définie plus haut)
        if ($usersCount === 1) {
            // Affichage pour un seul agent : jours en lignes
            echo $this->element('grids/_grid_single_user', compact('users_ranges', 'day_ranges', 'offers_name', 'gridStartHour', 'gridEndHour'));
        } else {
            // Affichage classique pour plusieurs agents : une grille par jour
            // Passer aussi les variables pour les graphiques
            echo $this->element('grids/_grid_multi_user', compact('users_ranges', 'day_ranges', 'offers_name', 'offers_list', 'publishedByDate', 'canLoadSeries', 'gridStartHour', 'gridEndHour'));
        }
        ?>

        </div>
        <?php echo $this->Form->end(); ?>
    </div>
</div>

<?php if ($embedMode): ?>
    </div><!-- .grids-embed-main -->
</div><!-- .grids-embed-body -->
<?php endif; ?>

<?php if ($canSavePlanning): ?>
    <button type="submit" form="rangesForm" class="btn btn-warning grids-save-btn-floating" id="submitRanges" title="Enregistrer le planning">
        <span class="grids-save-btn-text">
            <i class="bi bi-floppy-fill mr-2"></i><span>Enregistrer le planning</span>
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
        <button type="button" class="pdh-context-menu__item" id="planningDayHistoryMenuOpen">
            Historique du jour
        </button>
    </div>

    <div class="modal fade" id="planningDayHistoryModal" tabindex="-1" role="dialog" aria-labelledby="planningDayHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="planningDayHistoryModalLabel">Historique du jour</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Fermer">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="pdh-modal-meta text-muted small mb-3" id="planningDayHistoryMeta"></p>
                    <div id="planningDayHistoryList" class="pdh-versions"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php if (!$embedMode): ?>
</div><!-- .grids-page-shell -->
<?php endif; ?>

<?php $this->Html->scriptStart(['block' => true]); ?>
document.addEventListener('DOMContentLoaded', () => {
    // Barre filtres fixed : spacer = hauteur réelle ; Tri calé sur la 1re barre prévision (évite décalage FF)
    const gridsSearchBar = document.querySelector('.grids-search-bar-sticky');
    const gridsSearchSpacer = document.querySelector('.grids-search-spacer');
    const syncGridsFixedSearchLayout = () => {
        if (!gridsSearchBar || !gridsSearchSpacer) {
            return;
        }
        const barRect = gridsSearchBar.getBoundingClientRect();
        if (barRect.height < 40) {
            return;
        }

        gridsSearchSpacer.style.height = `${Math.ceil(barRect.height)}px`;

        const sortCard = document.querySelector('.grids-page-shell .grids-sort-card');
        const siteCard = document.querySelector('.grids-page-shell .grids-site-toggle-card');
        const legendCard = document.querySelector('.grids-page-shell .offers-legend-card');
        const offersCard = document.querySelector('.grids-page-shell .offers');
        const forecastBar = document.querySelector(
            '.grids-page-shell .main-content-wrapper .grids .card[data-scenario-id]'
        );

        const minTop = Math.ceil(barRect.bottom) + 8;
        let stackTop = forecastBar
            ? Math.round(forecastBar.getBoundingClientRect().top)
            : minTop;
        // Si la page est scrollée, le top viewport de la barre peut remonter trop haut
        stackTop = Math.max(stackTop, minTop);

        const stackGap = 8;
        const footerEl = document.querySelector('.app-footer, footer.app-footer, body > footer');
        const footerReserve = footerEl
            ? Math.max(40, Math.ceil(footerEl.getBoundingClientRect().height) + 8)
            : 48;

        if (offersCard) {
            offersCard.style.top = `${stackTop}px`;
            offersCard.style.bottom = `${footerReserve}px`;
            offersCard.style.maxHeight = 'none';
        }
        if (sortCard) {
            sortCard.style.top = `${stackTop}px`;
            stackTop += Math.round(sortCard.getBoundingClientRect().height) + stackGap;
        }
        if (siteCard) {
            siteCard.style.top = `${stackTop}px`;
            stackTop += Math.round(siteCard.getBoundingClientRect().height) + stackGap;
        }
        if (legendCard) {
            // top + bottom : la carte s'arrête au-dessus du footer (plus de max-height → 100vh)
            legendCard.style.top = `${stackTop}px`;
            legendCard.style.bottom = `${footerReserve}px`;
            legendCard.style.maxHeight = 'none';
        }
    };
    const scheduleGridsFixedSearchLayout = () => {
        requestAnimationFrame(() => requestAnimationFrame(syncGridsFixedSearchLayout));
    };
    scheduleGridsFixedSearchLayout();
    window.addEventListener('resize', scheduleGridsFixedSearchLayout);
    if (typeof ResizeObserver !== 'undefined' && gridsSearchBar) {
        new ResizeObserver(scheduleGridsFixedSearchLayout).observe(gridsSearchBar);
    }

    // Gestion du toggle de la colonne Site
    const toggleSiteBtn = document.getElementById('toggle-site-column');
    const toggleSiteIcon = document.getElementById('toggle-site-icon');
    const toggleSiteText = document.getElementById('toggle-site-text');
    const mainWrapper = document.querySelector('.main-content-wrapper');
    
    if (toggleSiteBtn && mainWrapper) {
        // Préférence utilisateur (si déjà enregistrée)
        const savedPreference = localStorage.getItem('showSiteColumn');

        // Déterminer l'état initial :
        // - si une préférence existe, on la respecte
        // - sinon, on affiche la colonne Site par défaut
        let isVisible = true;
        if (savedPreference !== null) {
            isVisible = (savedPreference === 'true');
        }
        
        // Appliquer l'état initial
        function updateSiteColumnVisibility(visible) {
            if (visible) {
                mainWrapper.classList.remove('hide-site-column');
                mainWrapper.classList.add('show-site-column');
                toggleSiteText.textContent = 'Masquer Site';
                toggleSiteBtn.classList.remove('btn-outline-primary');
                toggleSiteBtn.classList.add('btn-primary');
            } else {
                mainWrapper.classList.remove('show-site-column');
                mainWrapper.classList.add('hide-site-column');
                toggleSiteText.textContent = 'Afficher Site';
                toggleSiteBtn.classList.remove('btn-primary');
                toggleSiteBtn.classList.add('btn-outline-primary');
            }
            localStorage.setItem('showSiteColumn', visible.toString());
        }
        
        // Appliquer l'état initial au chargement
        updateSiteColumnVisibility(isVisible);
        
        // Gérer le clic sur le bouton
        toggleSiteBtn.addEventListener('click', () => {
            const currentlyVisible = mainWrapper.classList.contains('show-site-column');
            updateSiteColumnVisibility(!currentlyVisible);
        });
    }
    
    // Animation du chevron pour les graphiques collapsables
    const chartCollapses = document.querySelectorAll('[id^="collapseChart"]');
    chartCollapses.forEach(collapse => {
        const header = document.querySelector(`[data-target="#${collapse.id}"]`);
        if (!header) return;
        const chevron = header.querySelector('.bi-chevron-right');
        if (!chevron) return;
        
        collapse.addEventListener('show.bs.collapse', () => {
            chevron.style.transform = 'rotate(90deg)';
        });
        collapse.addEventListener('hide.bs.collapse', () => {
            chevron.style.transform = 'rotate(0deg)';
        });
    });
    
    const compareBlocks = document.querySelectorAll('[id^="compareBtn"]');
    compareBlocks.forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();
            const dayKey = btn.id.replace('compareBtn','');
            const parentCard = btn.closest('.card[data-scenario-id]');
            const scenarioId = parentCard ? parentCard.getAttribute('data-scenario-id') : '';
            
            // Déplier automatiquement le collapse si fermé
            const collapseEl = document.getElementById('collapseChart' + dayKey);
            if (collapseEl && !collapseEl.classList.contains('show')) {
                $(collapseEl).collapse('show');
            }
            
            if (!scenarioId) {
                document.getElementById('compareChart' + dayKey).innerHTML = '<div class="alert alert-info">Aucun scénario publié pour ce jour.</div>';
                return;
            }
            const offerId = document.getElementById('offerSelect' + dayKey).value;
            const date = dayKey; // yyyy-mm-dd
            const plannedBase = '<?= h($plannedSeriesBaseUrl) ?>';
            const plannedExtra = '<?= $plannedSeriesExtraQuery ?>'; // Ne pas encoder HTML, c'est une partie d'URL
            const plannedUrl = plannedBase + `?offer_id=${encodeURIComponent(offerId)}&date=${encodeURIComponent(date)}` + plannedExtra;
            
            try {
                // Fonctions utilitaires
                function timeToMinutes(timeStr) {
                    const parts = timeStr.substring(0, 5).split(':').map(Number);
                    if (parts.length !== 2 || isNaN(parts[0]) || isNaN(parts[1])) {
                        console.warn('Invalid time format:', timeStr);
                        return 0;
                    }
                    return parts[0] * 60 + parts[1];
                }
                
                function compareTimes(time1, time2) {
                    return timeToMinutes(time1) - timeToMinutes(time2);
                }
                
                // 1. Récupérer les données planifiées et besoin
                console.log('[Chart] Fetching planned data from:', plannedUrl);
                const plannedRes = await fetch(plannedUrl, { headers: { 'Accept': 'application/json' } });
                
                if (!plannedRes.ok) {
                    throw new Error(`Planned data fetch failed: ${plannedRes.status} ${plannedRes.statusText}`);
                }
                
                const planned = await plannedRes.json();
                console.log('[Chart] Planned response (raw):', planned);
                console.log('[Chart] Planned response (parsed):', {
                    success: planned?.success,
                    hasSeries: !!planned?.series,
                    startTime: planned?.series?.startTime,
                    endTime: planned?.series?.endTime,
                    dataKeys: planned?.series?.data ? Object.keys(planned.series.data).length : 0,
                    sampleData: planned?.series?.data ? Object.entries(planned.series.data).slice(0, 3) : null
                });
                
                // Accepter même si success est false, tant qu'on a une structure series
                if (!planned || (!planned.series && planned.success !== false)) {
                    throw new Error('Invalid planned data response: missing series structure');
                }
                
                // Si success est false, on peut quand même avoir des données vides
                if (planned.success === false && !planned.series) {
                    console.warn('[Chart] Planned data returned success: false, using empty data');
                }
                
                let need = null;
                if (scenarioId) {
                    const needBase = '<?= $this->Url->build(['controller' => 'ForecastScenarios', 'action' => 'series']); ?>';
                    const needUrl = needBase + `/${encodeURIComponent(scenarioId)}.json?offer_id=${encodeURIComponent(offerId)}&date=${encodeURIComponent(date)}&type=need`;
                    console.log('[Chart] Fetching need data from:', needUrl);
                    const needRes = await fetch(needUrl, { headers: { 'Accept': 'application/json' } });
                    
                    if (!needRes.ok) {
                        console.warn('[Chart] Need data fetch failed:', needRes.status, needRes.statusText);
                    } else {
                        need = await needRes.json();
                        console.log('[Chart] Need response:', {
                            success: need?.success,
                            hasSeries: !!need?.series,
                            startTime: need?.series?.startTime,
                            endTime: need?.series?.endTime,
                            dataKeys: need?.series?.data ? Object.keys(need.series.data).length : 0,
                            sampleData: need?.series?.data ? Object.entries(need.series.data).slice(0, 3) : null
                        });
                    }
                } else {
                    console.warn('[Chart] No scenarioId provided, need data will be empty');
                }
                
                // 2. Extraire et normaliser les métadonnées (startTime, endTime)
                // Utiliser les valeurs par défaut si les données ne sont pas disponibles
                const plannedSeries = planned?.series || {};
                const needSeries = need?.series || {};
                
                const plannedStart = plannedSeries.startTime || '09:00:00';
                const plannedEnd = plannedSeries.endTime || '17:00:00';
                const needStart = needSeries.startTime || plannedStart;
                const needEnd = needSeries.endTime || plannedEnd;
                
                console.log('[Chart] Time ranges:', {
                    plannedStart,
                    plannedEnd,
                    needStart,
                    needEnd
                });
                
                // 3. Fonction pour générer tous les créneaux temporels (15 min)
                function generateTimeSlots(start, end) {
                    const slots = [];
                    const startParts = start.substring(0, 5).split(':').map(Number);
                    const endParts = end.substring(0, 5).split(':').map(Number);
                    
                    if (startParts.length !== 2 || endParts.length !== 2) {
                        console.error('[Chart] Invalid time format in generateTimeSlots:', { start, end });
                        return [];
                    }
                    
                    let h = startParts[0];
                    let m = startParts[1];
                    const endH = endParts[0];
                    const endM = endParts[1];
                    
                    while (h < endH || (h === endH && m <= endM)) {
                        slots.push(`${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`);
                        m += 15;
                        if (m >= 60) {
                            m = 0;
                            h++;
                        }
                    }
                    return slots;
                }
                
                // 4. Créer l'ensemble unifié de catégories (utiliser la plage la plus large avec comparaison numérique)
                const minStart = compareTimes(plannedStart, needStart) <= 0 ? plannedStart : needStart;
                const maxEnd = compareTimes(plannedEnd, needEnd) >= 0 ? plannedEnd : needEnd;
                const allSlots = generateTimeSlots(minStart, maxEnd);
                
                console.log('[Chart] Generated slots:', {
                    minStart,
                    maxEnd,
                    slotsCount: allSlots.length,
                    firstSlots: allSlots.slice(0, 5),
                    lastSlots: allSlots.slice(-5)
                });
                
                if (allSlots.length === 0) {
                    document.getElementById('compareChart' + dayKey).innerHTML = '<div class="alert alert-info">Aucune donnée à afficher.</div>';
                    return;
                }
                
                // 5. Fonction pour normaliser les données avec toutes les clés
                function normalizeData(data, allSlots, dataName) {
                    if (!data || typeof data !== 'object') {
                        console.warn(`[Chart] Invalid data for ${dataName}:`, data);
                        return {};
                    }
                    
                    const normalized = {};
                    let foundCount = 0;
                    
                    allSlots.forEach(slot => {
                        let value = 0;
                        // Chercher la valeur dans les données (gérer différents formats de clés)
                        for (const [key, val] of Object.entries(data)) {
                            const normalizedKey = key.substring(0, 5);
                            if (normalizedKey === slot) {
                                // Gérer le cas où val est un objet avec une propriété 'volume'
                                if (typeof val === 'object' && val !== null && !Array.isArray(val)) {
                                    value = val.volume || val.value || 0;
                                } else {
                                    value = val;
                                }
                                foundCount++;
                                break;
                            }
                        }
                        // Convertir en nombre et gérer les cas NaN/null/undefined
                        const numValue = Number(value);
                        normalized[slot] = isNaN(numValue) ? 0 : numValue;
                    });
                    
                    console.log(`[Chart] Normalized ${dataName}:`, {
                        totalSlots: allSlots.length,
                        foundKeys: foundCount,
                        sampleEntries: Object.entries(normalized).slice(0, 5),
                        totalValue: Object.values(normalized).reduce((a, b) => a + b, 0)
                    });
                    
                    return normalized;
                }
                
                const plannedData = plannedSeries.data || {};
                const needData = needSeries.data || {};
                
                // Vérifier si on a au moins quelques données
                const hasPlannedData = Object.keys(plannedData).length > 0;
                const hasNeedData = Object.keys(needData).length > 0;
                
                console.log('[Chart] Data availability:', {
                    hasPlannedData,
                    hasNeedData,
                    plannedDataKeys: Object.keys(plannedData).length,
                    needDataKeys: Object.keys(needData).length
                });
                
                if (!hasPlannedData && !hasNeedData) {
                    document.getElementById('compareChart' + dayKey).innerHTML = 
                        '<div class="alert alert-warning">' +
                        '<strong>Aucune donnée disponible</strong><br>' +
                        'Aucune donnée planifiée ou de besoin trouvée pour cette date et cette offre.' +
                        '</div>';
                    return;
                }
                
                const plannedMap = normalizeData(plannedData, allSlots, 'planned');
                const needMap = normalizeData(needData, allSlots, 'need');
                
                // 6. Calculer les zones (couvert, manque, surplus) avec validation
                const covered = [];
                const shortage = [];
                const surplus = [];
                
                let totalCovered = 0;
                let totalShortage = 0;
                let totalSurplus = 0;
                
                allSlots.forEach(slot => {
                    const needV = Number(needMap[slot]) || 0;
                    const planV = Number(plannedMap[slot]) || 0;
                    
                    const cov = Math.min(needV, planV);
                    const miss = Math.max(needV - planV, 0);
                    const extra = Math.max(planV - needV, 0);
                    
                    covered.push(cov);
                    shortage.push(miss);
                    surplus.push(extra);
                    
                    totalCovered += cov;
                    totalShortage += miss;
                    totalSurplus += extra;
                });
                
                console.log('[Chart] Calculated zones:', {
                    slotsCount: allSlots.length,
                    totalCovered,
                    totalShortage,
                    totalSurplus,
                    sampleCovered: covered.slice(0, 5),
                    sampleShortage: shortage.slice(0, 5),
                    sampleSurplus: surplus.slice(0, 5),
                    maxCovered: Math.max(...covered),
                    maxShortage: Math.max(...shortage),
                    maxSurplus: Math.max(...surplus)
                });

                // 8. Afficher le graphique
                window.renderApexStacked('compareChart' + dayKey, allSlots, [
                    { name: 'Couvert', data: covered },
                    { name: 'Manque', data: shortage },
                    { name: 'Surplus', data: surplus }
                ], { 
                    chart: { 
                        type: 'bar', 
                        height: 250, 
                        stacked: true, 
                        stackType: 'normal',
                        offsetY: -25,
                        toolbar: {
                            show: false
                        },
                        zoom: {
                            enabled: false
                        },
                        selection: {
                            enabled: false
                        }
                    },
                    grid: { 
                        padding: { top: 0, right: 0, bottom: -60, left: 0 } 
                    },
                    tooltip: {
                        enabled: false
                    },
                    states: {
                        hover: {
                            filter: {
                                type: 'none'
                            }
                        },
                        active: {
                            filter: {
                                type: 'none'
                            }
                        }
                    }
                });
            } catch (err) {
                console.error('[Chart] Error loading chart:', err);
                console.error('[Chart] Error stack:', err.stack);
                document.getElementById('compareChart' + dayKey).innerHTML = 
                    '<div class="alert alert-danger">' +
                    '<strong>Erreur lors du chargement:</strong><br>' +
                    err.message +
                    '<br><small>Voir la console pour plus de détails.</small>' +
                    '</div>';
            }
        });
    });
});
<?php $this->Html->scriptEnd(); ?>
