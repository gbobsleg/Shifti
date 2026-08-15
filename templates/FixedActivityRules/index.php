<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\ResultSetInterface $rules
 */
?>
<?php $this->assign('title', 'Activités fixes'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php $this->Html->script('fixed-activity-rules', ['block' => true]); ?>

<style>
.table-row-inactive {
    background-color: rgba(108, 117, 125, 0.05);
}
.table-row-inactive > td:not(.actions) {
    opacity: 0.7;
}
.fixed-activity-rules .table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.08) !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.2s ease;
}
</style>

<div class="fixed-activity-rules index content card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="mb-0">
            <i class="bi bi-calendar-check text-primary"></i> Activités fixes
        </h3>
        <div>
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle mr-1"></i> Nouvelle règle',
                ['action' => 'add'],
                ['class' => 'btn btn-success', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div class="card-body">
        <?php // --- Cards de statistiques --- ?>
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card border-primary">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-calendar-check text-primary" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['total'] ?></h3>
                        <small class="text-muted">Total</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card border-success">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['active'] ?></h3>
                        <small class="text-muted">Actives</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card border-secondary">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-x-circle text-secondary" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['inactive'] ?></h3>
                        <small class="text-muted">Inactives</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card border-info">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-building text-info" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['per_site'] ?></h3>
                        <small class="text-muted">Par site</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card border-warning">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-diagram-3 text-warning" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['pooled'] ?></h3>
                        <small class="text-muted">Mutualisé</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card border-dark">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-globe text-dark" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['global'] ?></h3>
                        <small class="text-muted">Global</small>
                    </div>
                </div>
            </div>
        </div>

        <?php // --- Toolbar de filtrage --- ?>
        <?= $this->Form->create(null, ['type' => 'get', 'class' => 'filters-toolbar mb-4 p-3 bg-light border rounded']) ?>
            <div class="row">
                <div class="col-md-4 mb-2">
                    <label for="offer-id" class="form-label small text-muted mb-1">
                        <i class="bi bi-tag"></i> Offre
                    </label>
                    <?= $this->Form->select('offer_id', $offers, [
                        'empty' => 'Toutes les offres',
                        'class' => 'form-control form-control-sm',
                        'value' => $this->request->getQuery('offer_id'),
                        'id' => 'offer-id'
                    ]) ?>
                </div>
                <div class="col-md-3 mb-2">
                    <label for="site-mode" class="form-label small text-muted mb-1">
                        <i class="bi bi-diagram-3"></i> Mode
                    </label>
                    <?= $this->Form->select('site_mode', [
                        'per_site' => 'Par site',
                        'pooled' => 'Mutualisé',
                        'global' => 'Global'
                    ], [
                        'empty' => 'Tous les modes',
                        'class' => 'form-control form-control-sm',
                        'value' => $this->request->getQuery('site_mode'),
                        'id' => 'site-mode'
                    ]) ?>
                </div>
                <div class="col-md-2 mb-2">
                    <label for="active-status" class="form-label small text-muted mb-1">
                        <i class="bi bi-power"></i> Statut
                    </label>
                    <?= $this->Form->select('active', [
                        '1' => 'Active',
                        '0' => 'Inactive'
                    ], [
                        'empty' => 'Tous',
                        'class' => 'form-control form-control-sm',
                        'value' => $this->request->getQuery('active'),
                        'id' => 'active-status'
                    ]) ?>
                </div>
                <div class="col-md-3 mb-2 d-flex flex-column align-items-stretch">
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
                        <?= $this->Paginator->counter('{{count}} règle(s) au total, affichant {{current}} sur cette page') ?>
                    </small>
                </div>
            </div>
        <?= $this->Form->end() ?>

        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm">
                <thead>
                    <tr>
                        <th scope="col" class="text-center"><?= $this->Paginator->sort('sort_order', 'Ordre') ?></th>
                        <th scope="col" class="text-center"><?= $this->Paginator->sort('allow_shortfall', 'Couverture') ?></th>
                        <th scope="col"><?= $this->Paginator->sort('offer_id', 'Offre') ?></th>
                        <th scope="col"><?= $this->Paginator->sort('site_mode', 'Mode') ?></th>
                        <th scope="col">Sites</th>
                        <th scope="col">Horaires</th>
                        <th scope="col">Jours</th>
                        <th scope="col" class="text-center"><?= $this->Paginator->sort('quantity', 'Qté') ?></th>
                        <th scope="col">Options</th>
                        <th scope="col" class="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($rules) === 0): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5">
                                <div class="empty-state">
                                    <i class="bi bi-calendar-check" style="font-size: 4rem; color: #dee2e6;"></i>
                                    <h4 class="mt-3 text-muted">Aucune règle trouvée</h4>
                                    <p class="text-muted">
                                        <?php if ($this->request->getQuery()): ?>
                                            Aucune règle ne correspond aux critères de recherche.
                                        <?php else: ?>
                                            Commencez par créer votre première règle d'activité fixe.
                                        <?php endif; ?>
                                    </p>
                                    <?php if (!$this->request->getQuery()): ?>
                                        <?= $this->Html->link(
                                            '<i class="bi bi-plus-circle mr-2"></i> Créer ma première règle',
                                            ['action' => 'add'],
                                            ['class' => 'btn btn-primary mt-2', 'escape' => false]
                                        ) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($rules as $r): ?>
                        <?php
                            $modeLabels = ['per_site' => 'Par site', 'pooled' => 'Mutualisé', 'global' => 'Global'];
                            $modeLabel = $modeLabels[$r->site_mode ?? 'per_site'] ?? '';
                            $sitesDisplay = implode(', ', array_map(fn($s) => $s->name, (array)$r->sites));
                            if (empty($sitesDisplay) && ($r->site_mode ?? 'per_site') === 'global') {
                                $sitesDisplay = 'Tous';
                            }
                            // Jours
                            $dow = [];
                            if (!empty($r->days_of_week)) {
                                $decoded = is_string($r->days_of_week) ? json_decode($r->days_of_week, true) : (array)$r->days_of_week;
                                $labels = [1=>'L',2=>'M',3=>'M',4=>'J',5=>'V',6=>'S',7=>'D'];
                                foreach ((array)$decoded as $v) { if (isset($labels[(int)$v])) { $dow[] = $labels[(int)$v]; } }
                            }
                            $daysDisplay = !empty($dow) ? implode(', ', $dow) : 'Tous';
                            
                            // Badge mode
                            $modeBadgeClass = 'badge-info';
                            $modeIcon = 'bi-building';
                            if ($r->site_mode === 'pooled') {
                                $modeBadgeClass = 'badge-warning';
                                $modeIcon = 'bi-diagram-3';
                            } elseif ($r->site_mode === 'global') {
                                $modeBadgeClass = 'badge-dark';
                                $modeIcon = 'bi-globe';
                            }
                            
                            $rowClass = !$r->active ? 'table-row-inactive' : '';
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td class="text-center">
                                <span class="badge badge-light text-dark"><?= h((int)($r->sort_order ?? 0)) ?></span>
                            </td>
                            <td class="text-center">
                                <?php if ($r->allow_shortfall): ?>
                                    <span class="badge badge-secondary" title="Couverture optionnelle : cette activité peut ne pas être couverte">
                                        <i class="bi bi-check-circle"></i> Optionnel
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-danger" title="Couverture obligatoire : cette activité doit être couverte en priorité">
                                        <i class="bi bi-x-circle"></i> Obligatoire
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= h($r->offer->name ?? '') ?></strong></td>
                            <td>
                                <span class="badge <?= $modeBadgeClass ?>">
                                    <i class="bi <?= $modeIcon ?>"></i> <?= h($modeLabel) ?>
                                </span>
                            </td>
                            <td><small class="text-muted"><?= h($sitesDisplay) ?></small></td>
                            <td>
                                <small>
                                    <i class="bi bi-clock"></i> 
                                    <?= h(substr($r->start_time ?? '', 0, 5)) ?> – <?= h(substr($r->end_time ?? '', 0, 5)) ?>
                                </small>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <i class="bi bi-calendar-week"></i> <?= h($daysDisplay) ?>
                                </small>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-primary"><?= h($r->quantity) ?></span>
                            </td>
                            <td>
                                <?php if ($r->is_splittable): ?>
                                    <span class="badge badge-secondary" title="Divisible">
                                        <i class="bi bi-scissors"></i> S
                                    </span>
                                <?php endif; ?>
                                <?php if ($r->equity_enabled === true): ?>
                                    <span class="badge badge-success" title="Équité (période): Activée">
                                        <i class="bi bi-people"></i> E
                                    </span>
                                <?php elseif ($r->equity_enabled === null): ?>
                                    <span class="badge badge-info" title="Équité (période): Hérite de l’offre">
                                        <i class="bi bi-arrow-repeat"></i> E
                                    </span>
                                <?php endif; ?>
                                <?php if (!$r->active): ?>
                                    <span class="badge badge-light text-muted" title="Inactive">
                                        <i class="bi bi-power"></i> Inactif
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <div class="dropdown actions-dropdown" data-entity-id="<?= (int)$r->id ?>">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownActions<?= $r->id ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="bi bi-three-dots-vertical"></i> Actions
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right actions-dropdown-menu" data-entity-id="<?= (int)$r->id ?>" aria-labelledby="dropdownActions<?= $r->id ?>">
                                        <?= $this->Html->link(
                                            '<i class="bi bi-eye mr-2"></i> Voir',
                                            ['action' => 'view', $r->id],
                                            ['class' => 'dropdown-item', 'escape' => false]
                                        ) ?>
                                        <?= $this->Html->link(
                                            '<i class="bi bi-pencil mr-2"></i> Modifier',
                                            ['action' => 'edit', $r->id],
                                            ['class' => 'dropdown-item', 'escape' => false]
                                        ) ?>
                                        <div class="dropdown-divider"></div>
                                        <?= $this->Form->postLink(
                                            $r->active 
                                                ? '<i class="bi bi-toggle-off mr-2"></i> Désactiver' 
                                                : '<i class="bi bi-toggle-on mr-2"></i> Activer',
                                            ['action' => 'toggle', $r->id],
                                            [
                                                'class' => 'dropdown-item',
                                                'escape' => false
                                            ]
                                        ) ?>
                                        <div class="dropdown-divider"></div>
                                        <?= $this->Form->postLink(
                                            '<i class="bi bi-trash mr-2"></i> Supprimer',
                                            ['action' => 'delete', $r->id],
                                            [
                                                'confirm' => 'Supprimer la règle "' . h($r->offer->name ?? '') . '" ?',
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
</div>
