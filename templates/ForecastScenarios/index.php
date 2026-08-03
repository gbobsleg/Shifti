<?php
/** @var \App\View\AppView $this */
/** @var \Cake\Datasource\ResultSetInterface|\App\Model\Entity\ForecastScenario[] $scenarios */
?>
<?php $this->assign('title', 'Scénarios'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php $this->Html->script('forecast-scenarios', ['block' => true]); ?>

<style>
/* Fonds subtils pour les lignes de scénarios selon leur statut */
.table-row-draft {
    background-color: rgba(108, 117, 125, 0.03);
}
.table-row-queued,
.table-row-running {
    background-color: rgba(255, 193, 7, 0.05);
}
.table-row-completed {
    background-color: rgba(40, 167, 69, 0.05);
}
.table-row-failed {
    background-color: rgba(220, 53, 69, 0.05);
}
.scenario-inline-progress {
    font-size: 0.75rem;
    margin-top: 0.35rem;
    max-width: 220px;
}
/* Effet hover plus marqué */
.forecast-scenarios .table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.08) !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.2s ease;
}
</style>

<div class="forecast-scenarios index content card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="mb-0">
            <i class="bi bi-diagram-3 text-primary"></i> Scénarios
        </h3>
        <div>
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle mr-1"></i> Nouveau Scénario',
                ['action' => 'add'],
                ['class' => 'btn btn-success', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div class="card-body" id="scenarioListContent">
        <?php // --- Cards de statistiques --- ?>
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-diagram-3 text-primary" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['total'] ?></h3>
                        <small class="text-muted">Total</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card border-success">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['completed'] ?></h3>
                        <small class="text-muted">Completed</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card border-warning">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-arrow-repeat text-warning" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= (int)$stats['running'] + (int)($stats['queued'] ?? 0) ?></h3>
                        <small class="text-muted">Queue / Running</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card border-secondary">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-file-earmark text-secondary" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['draft'] ?></h3>
                        <small class="text-muted">Draft</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-broadcast text-info" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['published'] ?></h3>
                        <small class="text-muted">Publiés</small>
                    </div>
                </div>
            </div>
        </div>

        <?php // --- Toolbar de filtrage --- ?>
        <?= $this->Form->create(null, ['type' => 'get', 'class' => 'filters-toolbar mb-4 p-3 bg-light border rounded']) ?>
            <div class="row">
                <div class="col-md-5 mb-2">
                    <label for="search-name" class="form-label small text-muted mb-1">
                        <i class="bi bi-search"></i> Nom
                    </label>
                    <?= $this->Form->text('search_name', [
                        'class' => 'form-control form-control-sm',
                        'placeholder' => 'Rechercher par nom...',
                        'value' => $this->request->getQuery('search_name')
                    ]) ?>
                </div>
                <div class="col-md-3 mb-2">
                    <label for="status" class="form-label small text-muted mb-1">
                        <i class="bi bi-tag"></i> Statut
                    </label>
                    <?= $this->Form->select('status', [
                        'draft' => 'Draft',
                        'queued' => 'Queued',
                        'running' => 'Running',
                        'completed' => 'Completed',
                        'failed' => 'Failed'
                    ], [
                        'empty' => 'Tous les statuts',
                        'class' => 'form-control form-control-sm',
                        'value' => $this->request->getQuery('status')
                    ]) ?>
                </div>
                <div class="col-md-2 mb-2 d-flex flex-column align-items-stretch">
                    <?= $this->Form->button('<i class="bi bi-search"></i> Filtrer', [
                        'type' => 'submit',
                        'class' => 'btn btn-sm btn-primary mb-1',
                        'escapeTitle' => false
                    ]) ?>
                    <?= $this->Html->link('<i class="bi bi-arrow-counterclockwise"></i> Réinitialiser', 
                        ['action' => 'index'], 
                        ['class' => 'btn btn-sm btn-outline-secondary', 'escape' => false]
                    ) ?>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-12">
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i> 
                        <?= $this->Paginator->counter('{{count}} scénario(s) au total, affichant {{current}} sur cette page') ?>
                    </small>
                </div>
            </div>
        <?= $this->Form->end() ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm">
                <thead>
                    <tr>
                        <th scope="col"><?= $this->Paginator->sort('id', 'ID') ?></th>
                        <th scope="col"><?= $this->Paginator->sort('name', 'Nom') ?></th>
                        <th scope="col"><?= $this->Paginator->sort('start_date', 'Date début') ?></th>
                        <th scope="col"><?= $this->Paginator->sort('end_date', 'Date fin') ?></th>
                        <th scope="col"><?= $this->Paginator->sort('status', 'Statut') ?></th>
                        <th scope="col"><?= $this->Paginator->sort('modified', 'Modifié le') ?></th>
                        <th scope="col" class="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($scenarios) === 0): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="empty-state">
                                    <i class="bi bi-diagram-3" style="font-size: 4rem; color: #dee2e6;"></i>
                                    <h4 class="mt-3 text-muted">Aucun scénario trouvé</h4>
                                    <p class="text-muted">
                                        <?php if ($this->request->getQuery()): ?>
                                            Aucun scénario ne correspond aux critères de recherche.
                                        <?php else: ?>
                                            Commencez par créer votre premier scénario de prévision.
                                        <?php endif; ?>
                                    </p>
                                    <?php if (!$this->request->getQuery()): ?>
                                        <?= $this->Html->link(
                                            '<i class="bi bi-plus-circle mr-2"></i> Créer mon premier scénario',
                                            ['action' => 'add'],
                                            ['class' => 'btn btn-primary mt-2', 'escape' => false]
                                        ) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($scenarios as $s):
                        $canLaunch = in_array((string)$s->status, ['draft', 'failed', 'completed'], true);
                        $isInProgress = in_array((string)$s->status, ['queued', 'running'], true);
                        // Définir les couleurs selon le statut
                        $rowClass = '';
                        $borderColor = '#dee2e6';
                        if ($s->status === 'draft') {
                            $rowClass = 'table-row-draft';
                            $borderColor = '#6c757d';
                        } elseif ($s->status === 'queued') {
                            $rowClass = 'table-row-queued';
                            $borderColor = '#ffc107';
                        } elseif ($s->status === 'running') {
                            $rowClass = 'table-row-running';
                            $borderColor = '#ffc107';
                        } elseif ($s->status === 'completed') {
                            $rowClass = 'table-row-completed';
                            $borderColor = '#28a745';
                        } elseif ($s->status === 'failed') {
                            $rowClass = 'table-row-failed';
                            $borderColor = '#dc3545';
                        }
                        $offersDone = (int)($s->progress_offers_done ?? 0);
                        $offersTotal = (int)($s->progress_offers_total ?? 0);
                        $daysDone = (int)($s->progress_days_done ?? 0);
                        $daysTotal = (int)($s->progress_days_total ?? 0);
                        $pctDays = $daysTotal > 0 ? (int)round(($daysDone / $daysTotal) * 100) : 0;
                    ?>
                        <tr class="<?= $rowClass ?> scenario-row"
                            style="border-left: 4px solid <?= $borderColor ?>;"
                            data-scenario-id="<?= (int)$s->id ?>"
                            data-status="<?= h((string)$s->status) ?>"
                            data-status-url="<?= h($this->Url->build(['action' => 'status', $s->id, '_ext' => 'json'])) ?>">
                            <td><?= $this->Number->format($s->id) ?></td>
                            <td><?= h($s->name) ?></td>
                            <td>
                                <?= h($s->start_date ? (new \Cake\I18n\FrozenDate($s->start_date))->i18nFormat('dd/MM/yyyy') : '') ?>
                                <?php if ($s->start_date && $s->end_date): 
                                    $start = new \DateTime($s->start_date->format('Y-m-d'));
                                    $end = new \DateTime($s->end_date->format('Y-m-d'));
                                    $duration = $start->diff($end)->days + 1;
                                ?>
                                    <br>
                                    <small class="text-muted">
                                        <i class="bi bi-calendar-range"></i> <?= $duration ?> jour<?= $duration > 1 ? 's' : '' ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><?= h($s->end_date ? (new \Cake\I18n\FrozenDate($s->end_date))->i18nFormat('dd/MM/yyyy') : '') ?></td>
                            <td>
                                <?php
                                $badgeClass = 'badge-secondary';
                                $badgeIcon = 'bi-file-earmark';
                                if ($s->status === 'queued') {
                                    $badgeClass = 'badge-warning';
                                    $badgeIcon = 'bi-hourglass-split';
                                } elseif ($s->status === 'running') {
                                    $badgeClass = 'badge-warning';
                                    $badgeIcon = 'bi-arrow-repeat';
                                } elseif ($s->status === 'completed') {
                                    $badgeClass = 'badge-success';
                                    $badgeIcon = 'bi-check-circle';
                                } elseif ($s->status === 'failed') {
                                    $badgeClass = 'badge-danger';
                                    $badgeIcon = 'bi-exclamation-triangle';
                                }
                                $isPublished = !empty($s->forecast_scenario_publications);
                                ?>
                                <span class="badge <?= $badgeClass ?> scenario-status-badge">
                                    <i class="bi <?= $badgeIcon ?>"></i> <span class="scenario-status-text"><?= h(ucfirst($s->status)) ?></span>
                                </span>
                                <?php if ($isPublished): ?>
                                    <span class="badge badge-info ml-1" data-toggle="tooltip" title="Publié (<?= count($s->forecast_scenario_publications) ?> jour(s))">
                                        <i class="bi bi-broadcast"></i> Publié
                                    </span>
                                <?php endif; ?>
                                <?php
                                // Badge(s) méthode(s) de prévision, calculées par offre
                                $hasProphet = false;
                                $hasHistorical = false;
                                if (!empty($s->forecast_scenarios_offers)) {
                                    foreach ($s->forecast_scenarios_offers as $link) {
                                        $m = $link->forecast_method ?? 'historical';
                                        if ($m === 'prophet') {
                                            $hasProphet = true;
                                        } else {
                                            $hasHistorical = true;
                                        }
                                    }
                                } else {
                                    $hasHistorical = true; // par défaut si aucune info
                                }
                                ?>
                                <br>
                                <?php if ($hasProphet && !$hasHistorical): 
                                    // Récupérer les métriques si disponibles
                                    $metrics = null;
                                    if (!empty($s->prophet_metrics_json)) {
                                        $metrics = json_decode($s->prophet_metrics_json, true);
                                    }
                                    $tooltipText = 'Prévisions Prophet (Machine Learning) pour toutes les offres';
                                    if ($metrics && isset($metrics['mape'])) {
                                        $tooltipText .= ' - MAPE moyenne: ' . $metrics['mape'] . '%';
                                    }
                                ?>
                                    <span class="badge badge-primary mt-1" data-toggle="tooltip" title="<?= h($tooltipText) ?>">
                                        <i class="bi bi-stars"></i> Prophet (toutes offres)
                                    </span>
                                <?php elseif ($hasProphet && $hasHistorical): ?>
                                    <span class="badge badge-primary mt-1" data-toggle="tooltip" title="Certaines offres en Prophet, d'autres en moyenne historique">
                                        <i class="bi bi-arrow-left-right"></i> Mixte
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-secondary mt-1" data-toggle="tooltip" title="Moyenne historique par jour de semaine pour toutes les offres">
                                        <i class="bi bi-clock-history"></i> Historique
                                    </span>
                                <?php endif; ?>
                                <div class="scenario-inline-progress<?= $isInProgress ? '' : ' d-none' ?>">
                                    <div class="d-flex align-items-center text-warning mb-1">
                                        <div class="spinner-border spinner-border-sm mr-2" role="status"></div>
                                        <span class="scenario-progress-label"><?= $s->status === 'queued' ? 'En file…' : 'Calcul…' ?></span>
                                    </div>
                                    <div>
                                        <span class="scenario-progress-offer"><?= h($s->progress_offer_name ?: '—') ?></span>
                                    </div>
                                    <div class="text-muted">
                                        Offres <span class="scenario-offers-done"><?= $offersDone ?></span>/<span class="scenario-offers-total"><?= $offersTotal ?></span>
                                        · Jours <span class="scenario-days-done"><?= $daysDone ?></span>/<span class="scenario-days-total"><?= $daysTotal ?></span>
                                    </div>
                                    <div class="progress mt-1" style="height: 8px;">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-warning scenario-progress-bar"
                                             role="progressbar"
                                             style="width: <?= $pctDays ?>%;"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($s->modified): 
                                    $now = new \Cake\I18n\FrozenTime();
                                    $diff = $now->diffInDays($s->modified);
                                    $timeAgo = '';
                                    if ($diff == 0) {
                                        $timeAgo = "Aujourd'hui";
                                    } elseif ($diff == 1) {
                                        $timeAgo = 'Hier';
                                    } elseif ($diff < 7) {
                                        $timeAgo = 'Il y a ' . $diff . ' jours';
                                    } elseif ($diff < 30) {
                                        $weeks = floor($diff / 7);
                                        $timeAgo = 'Il y a ' . $weeks . ' semaine' . ($weeks > 1 ? 's' : '');
                                    } elseif ($diff < 365) {
                                        $months = floor($diff / 30);
                                        $timeAgo = 'Il y a ' . $months . ' mois';
                                    } else {
                                        $years = floor($diff / 365);
                                        $timeAgo = 'Il y a ' . $years . ' an' . ($years > 1 ? 's' : '');
                                    }
                                ?>
                                    <span data-toggle="tooltip" title="<?= h($s->modified->i18nFormat('dd/MM/yyyy HH:mm')) ?>">
                                        <?= h($timeAgo) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <div class="dropdown actions-dropdown" data-entity-id="<?= (int)$s->id ?>">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownActions<?= $s->id ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="bi bi-three-dots-vertical"></i> Actions
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right actions-dropdown-menu" data-entity-id="<?= (int)$s->id ?>" aria-labelledby="dropdownActions<?= $s->id ?>">
                                        <?= $this->Html->link(
                                            '<i class="bi bi-eye mr-2"></i> Voir',
                                            ['action' => 'view', $s->id],
                                            ['class' => 'dropdown-item', 'escape' => false]
                                        ) ?>
                                        <?= $this->Html->link(
                                            '<i class="bi bi-pencil mr-2"></i> Modifier',
                                            ['action' => 'edit', $s->id],
                                            ['class' => 'dropdown-item', 'escape' => false]
                                        ) ?>
                                        <?php if ($canLaunch): ?>
                                            <?= $this->Html->link(
                                                '<i class="bi bi-play-circle mr-2"></i> Lancer',
                                                ['action' => 'run', $s->id],
                                                ['class' => 'dropdown-item scenario-run-link', 'escape' => false]
                                            ) ?>
                                        <?php endif; ?>
                                        <div class="dropdown-divider"></div>
                                        <?= $this->Form->postLink(
                                            '<i class="bi bi-trash mr-2"></i> Supprimer',
                                            ['action' => 'delete', $s->id],
                                            [
                                                'confirm' => 'Supprimer le scénario #' . $s->id . ' ?',
                                                'class' => 'dropdown-item text-danger',
                                                'escape' => false
                                            ]
                                        ) ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="paginator mt-3">
            <ul class="pagination justify-content-center">
                <?= $this->Paginator->first('<< ' . 'Première') ?>
                <?= $this->Paginator->prev('< ' . 'Précédente') ?>
                <?= $this->Paginator->numbers() ?>
                <?= $this->Paginator->next('Suivante' . ' >') ?>
                <?= $this->Paginator->last('Dernière' . ' >>') ?>
            </ul>
            <p class="text-center"><?= $this->Paginator->counter('Page {{page}} sur {{pages}}, affichant {{current}} enregistrement(s) sur {{count}} au total') ?></p>
        </div>
    </div>
    <div id="queueIndicator" class="card-body text-center p-5 d-none">
        <div class="spinner-border text-success" style="width: 3rem; height: 3rem;" role="status">
            <span class="sr-only">Mise en file...</span>
        </div>
        <h4 class="mt-3 text-success mb-2">
            <i class="bi bi-hourglass-split"></i> Mise en file d'attente…
        </h4>
        <p class="text-muted mb-0">
            Redirection vers le suivi du scénario.
        </p>
    </div>
</div>

<?php $this->Html->scriptStart(['block' => true]); ?>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.scenario-run-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            var indicator = document.getElementById('queueIndicator');
            var content = document.getElementById('scenarioListContent');
            if (indicator) { indicator.classList.remove('d-none'); }
            if (content) { content.classList.add('d-none'); }
            setTimeout(function() { window.location.href = link.href; }, 50);
        });
    });

    var pollMs = 2500;
    var activeRows = Array.prototype.slice.call(document.querySelectorAll('.scenario-row'))
        .filter(function(row) {
            var st = row.getAttribute('data-status');
            return st === 'queued' || st === 'running';
        });

    if (!activeRows.length) {
        return;
    }

    function pct(done, total) {
        if (!total || total <= 0) { return 0; }
        return Math.min(100, Math.round((done / total) * 100));
    }

    function updateRow(row, scenario) {
        var status = String(scenario.status || '');
        var inProgress = status === 'queued' || status === 'running';
        row.setAttribute('data-status', status);

        var badge = row.querySelector('.scenario-status-badge');
        var statusText = row.querySelector('.scenario-status-text');
        if (statusText) {
            statusText.textContent = status ? status.charAt(0).toUpperCase() + status.slice(1) : '';
        }
        if (badge) {
            badge.className = 'badge scenario-status-badge ' + (
                status === 'completed' ? 'badge-success' :
                status === 'failed' ? 'badge-danger' :
                (status === 'running' || status === 'queued') ? 'badge-warning' : 'badge-secondary'
            );
        }

        var progress = row.querySelector('.scenario-inline-progress');
        if (progress) {
            if (inProgress) {
                progress.classList.remove('d-none');
            } else {
                progress.classList.add('d-none');
            }
            var label = progress.querySelector('.scenario-progress-label');
            if (label) {
                label.textContent = status === 'queued' ? 'En file…' : 'Calcul…';
            }
            var offer = progress.querySelector('.scenario-progress-offer');
            if (offer) { offer.textContent = scenario.progress_offer_name || '—'; }
            var od = progress.querySelector('.scenario-offers-done');
            var ot = progress.querySelector('.scenario-offers-total');
            var dd = progress.querySelector('.scenario-days-done');
            var dt = progress.querySelector('.scenario-days-total');
            if (od) { od.textContent = String(scenario.progress_offers_done || 0); }
            if (ot) { ot.textContent = String(scenario.progress_offers_total || 0); }
            if (dd) { dd.textContent = String(scenario.progress_days_done || 0); }
            if (dt) { dt.textContent = String(scenario.progress_days_total || 0); }
            var bar = progress.querySelector('.scenario-progress-bar');
            if (bar) {
                bar.style.width = pct(scenario.progress_days_done || 0, scenario.progress_days_total || 0) + '%';
            }
        }

        return inProgress;
    }

    var pollingStopped = false;

    function pollActiveRows() {
        if (pollingStopped || !activeRows.length) {
            return;
        }

        var stillActive = [];
        var pending = activeRows.map(function(row) {
            var url = row.getAttribute('data-status-url');
            return fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function(res) { return res.json(); })
                .then(function(json) {
                    if (!json || !json.success || !json.scenario) {
                        stillActive.push(row);
                        return;
                    }
                    if (updateRow(row, json.scenario)) {
                        stillActive.push(row);
                    }
                })
                .catch(function() { stillActive.push(row); });
        });

        // Prochain cycle uniquement après résolution de tous les fetch du cycle courant
        Promise.all(pending).then(function() {
            activeRows = stillActive;
            if (!activeRows.length) {
                pollingStopped = true;
                window.location.reload();
                return;
            }
            setTimeout(pollActiveRows, pollMs);
        });
    }

    pollActiveRows();
});
<?php $this->Html->scriptEnd(); ?>


