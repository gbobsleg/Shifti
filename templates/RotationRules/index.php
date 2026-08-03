<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\ResultSetInterface $rules
 * @var array $stats
 * @var array $offers
 */
?>
<?php $this->assign('title', 'Règles de rotation'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="rotation-rules index content card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="mb-0">
            <i class="bi bi-arrow-repeat text-primary"></i> Règles de rotation
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
            <div class="col-md-4">
                <div class="card border-primary">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-arrow-repeat text-primary" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['total'] ?></h3>
                        <small class="text-muted">Total</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-info">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-calendar-week text-info" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['weekly'] ?></h3>
                        <small class="text-muted">Hebdomadaires</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-warning">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-calendar-month text-warning" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['monthly'] ?></h3>
                        <small class="text-muted">Mensuelles</small>
                    </div>
                </div>
            </div>
        </div>

        <?php // --- Toolbar de filtrage --- ?>
        <?= $this->Form->create(null, ['type' => 'get', 'class' => 'filters-toolbar mb-4 p-3 bg-light border rounded']) ?>
            <div class="row">
                <div class="col-md-5 mb-2">
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
                <div class="col-md-4 mb-2">
                    <label for="period-type" class="form-label small text-muted mb-1">
                        <i class="bi bi-calendar"></i> Type de période
                    </label>
                    <?= $this->Form->select('period_type', [
                        'WEEKLY' => 'Hebdomadaire',
                        'MONTHLY' => 'Mensuelle'
                    ], [
                        'empty' => 'Tous les types',
                        'class' => 'form-control form-control-sm',
                        'value' => $this->request->getQuery('period_type'),
                        'id' => 'period-type'
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
                        <th scope="col"><?= $this->Paginator->sort('name', 'Nom') ?></th>
                        <th scope="col"><?= $this->Paginator->sort('offer_id', 'Offre') ?></th>
                        <th scope="col"><?= $this->Paginator->sort('period_type', 'Période') ?></th>
                        <th scope="col">Cible</th>
                        <th scope="col">Durée</th>
                        <th scope="col">Fenêtre horaire</th>
                        <th scope="col" class="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($rules) === 0): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="empty-state">
                                    <i class="bi bi-arrow-repeat" style="font-size: 4rem; color: #dee2e6;"></i>
                                    <h4 class="mt-3 text-muted">Aucune règle trouvée</h4>
                                    <p class="text-muted">
                                        <?php if ($this->request->getQuery()): ?>
                                            Aucune règle ne correspond aux critères de recherche.
                                        <?php else: ?>
                                            Commencez par créer votre première règle de rotation.
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
                        <tr>
                            <td><strong><?= h($r->name) ?></strong></td>
                            <td>
                                <?php if ($r->offer): ?>
                                    <span class="badge badge-info"><?= h($r->offer->name) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Générique</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r->period_type === 'WEEKLY'): ?>
                                    <span class="badge badge-info">
                                        <i class="bi bi-calendar-week"></i> Hebdomadaire
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-warning">
                                        <i class="bi bi-calendar-month"></i> Mensuelle
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-primary"><?= h($r->target_count) ?></span>
                            </td>
                            <td>
                                <small><?= h($r->shift_duration) ?> min</small>
                            </td>
                            <td>
                                <small>
                                    <i class="bi bi-clock"></i> 
                                    <?= h(substr($r->time_window_start ?? '', 0, 5)) ?> – <?= h(substr($r->time_window_end ?? '', 0, 5)) ?>
                                </small>
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
                                            '<i class="bi bi-trash mr-2"></i> Supprimer',
                                            ['action' => 'delete', $r->id],
                                            [
                                                'confirm' => 'Supprimer la règle "' . h($r->name) . '" ?',
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
