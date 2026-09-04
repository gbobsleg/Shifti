<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\ResultSetInterface $rules
 */
?>
<?php $this->assign('title', 'Activités fixes'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php $this->Html->script('fixed-activity-rules', ['block' => true]); ?>

<div class="crud-app fixed-activity-rules index content">
    <div class="crud-header">
        <div>
            <h1>
                <i class="bi bi-calendar-check"></i>
                Activités fixes
            </h1>
            <p class="crud-header-meta"><?= $this->Paginator->counter('{{count}} règles') ?></p>
        </div>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle me-1"></i> Nouvelle règle',
                ['action' => 'add'],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
        </div>
    </div>

    <?= $this->Form->create(null, ['type' => 'get', 'class' => 'filters-toolbar mb-3']) ?>
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="offer-id" class="form-label small text-muted mb-1">Offre</label>
                <?= $this->Form->select('offer_id', $offers, [
                    'empty' => 'Toutes les offres',
                    'class' => 'form-control form-control-sm',
                    'value' => $this->request->getQuery('offer_id'),
                    'id' => 'offer-id',
                ]) ?>
            </div>
            <div class="col-md-3">
                <label for="site-mode" class="form-label small text-muted mb-1">Mode</label>
                <?= $this->Form->select('site_mode', [
                    'per_site' => 'Par site',
                    'pooled' => 'Mutualisé',
                    'global' => 'Global',
                ], [
                    'empty' => 'Tous les modes',
                    'class' => 'form-control form-control-sm',
                    'value' => $this->request->getQuery('site_mode'),
                    'id' => 'site-mode',
                ]) ?>
            </div>
            <div class="col-md-2">
                <label for="active-status" class="form-label small text-muted mb-1">Statut</label>
                <?= $this->Form->select('active', [
                    '1' => 'Active',
                    '0' => 'Inactive',
                ], [
                    'empty' => 'Tous',
                    'class' => 'form-control form-control-sm',
                    'value' => $this->request->getQuery('active'),
                    'id' => 'active-status',
                ]) ?>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <?= $this->Form->button('Filtrer', [
                    'type' => 'submit',
                    'class' => 'btn btn-sm btn-primary',
                ]) ?>
                <?= $this->Html->link(
                    'Réinitialiser',
                    ['action' => 'index'],
                    ['class' => 'btn btn-sm btn-outline-secondary']
                ) ?>
            </div>
        </div>
    <?= $this->Form->end() ?>

    <div class="table-responsive">
        <table class="table table-hover table-sm crud-table">
            <?php
            $columns = ['Offre', 'Ordre', 'Couverture', 'Mode', 'Sites', 'Horaires', 'Jours', 'Qté', 'Options', 'Actions'];
            $colCount = count($columns);
            ?>
            <thead>
            <tr>
                <th scope="col"><?= $this->Paginator->sort('offer_id', $columns[0]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('sort_order', $columns[1]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('allow_shortfall', $columns[2]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('site_mode', $columns[3]) ?></th>
                <th scope="col"><?= h($columns[4]) ?></th>
                <th scope="col"><?= h($columns[5]) ?></th>
                <th scope="col"><?= h($columns[6]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('quantity', $columns[7]) ?></th>
                <th scope="col"><?= h($columns[8]) ?></th>
                <th scope="col" class="actions"><?= h($columns[9]) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($rules) === 0): ?>
                <tr>
                    <td colspan="<?= (int)$colCount ?>" class="crud-empty">
                        <p>Aucune règle.</p>
                        <?php if (!$this->request->getQuery()): ?>
                            <?= $this->Html->link(
                                '<i class="bi bi-plus-circle me-1"></i> Créer une règle',
                                ['action' => 'add'],
                                ['class' => 'btn btn-sm btn-primary', 'escape' => false]
                            ) ?>
                        <?php endif; ?>
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
                    $dow = [];
                    if (!empty($r->days_of_week)) {
                        $decoded = is_string($r->days_of_week) ? json_decode($r->days_of_week, true) : (array)$r->days_of_week;
                        $labels = [1=>'L',2=>'M',3=>'M',4=>'J',5=>'V',6=>'S',7=>'D'];
                        foreach ((array)$decoded as $v) { if (isset($labels[(int)$v])) { $dow[] = $labels[(int)$v]; } }
                    }
                    $daysDisplay = !empty($dow) ? implode(', ', $dow) : 'Tous';
                    $offerLabel = $r->offer->name ?? ('#' . $r->id);
                    $options = [];
                    if ($r->is_splittable) {
                        $options[] = 'S';
                    }
                    if ($r->equity_enabled === true) {
                        $options[] = 'E';
                    } elseif ($r->equity_enabled === null) {
                        $options[] = 'E (héritage)';
                    }
                    if (!$r->active) {
                        $options[] = 'Inactif';
                    }
                ?>
                <tr>
                    <td>
                        <?= $this->Html->link(
                            $offerLabel,
                            ['action' => 'view', $r->id],
                            ['class' => 'crud-row-link']
                        ) ?>
                    </td>
                    <td><?= h((int)($r->sort_order ?? 0)) ?></td>
                    <td><?= $r->allow_shortfall ? 'Optionnel' : 'Obligatoire' ?></td>
                    <td><?= h($modeLabel) ?></td>
                    <td><?= h($sitesDisplay) ?></td>
                    <td>
                        <?= h(substr($r->start_time ?? '', 0, 5)) ?> – <?= h(substr($r->end_time ?? '', 0, 5)) ?>
                    </td>
                    <td><?= h($daysDisplay) ?></td>
                    <td><?= h($r->quantity) ?></td>
                    <td><?= h(implode(' · ', $options)) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(
                            '<i class="bi bi-pencil" aria-hidden="true"></i>',
                            ['action' => 'edit', $r->id],
                            [
                                'class' => 'crud-action',
                                'escape' => false,
                                'title' => 'Modifier',
                                'aria-label' => 'Modifier',
                                'data-bs-toggle' => 'tooltip',
                            ]
                        ) ?>
                        <?= $this->Form->postLink(
                            '<i class="bi bi-' . ($r->active ? 'toggle-off' : 'toggle-on') . '" aria-hidden="true"></i>',
                            ['action' => 'toggle', $r->id],
                            [
                                'class' => 'crud-action',
                                'escape' => false,
                                'title' => $r->active ? 'Désactiver' : 'Activer',
                                'aria-label' => $r->active ? 'Désactiver' : 'Activer',
                                'data-bs-toggle' => 'tooltip',
                            ]
                        ) ?>
                        <?= $this->Form->postLink(
                            '<i class="bi bi-trash" aria-hidden="true"></i>',
                            ['action' => 'delete', $r->id],
                            [
                                'confirm' => 'Supprimer la règle "' . h($r->offer->name ?? '') . '" ?',
                                'class' => 'crud-action crud-action-danger',
                                'escape' => false,
                                'title' => 'Supprimer',
                                'aria-label' => 'Supprimer',
                                'data-bs-toggle' => 'tooltip',
                            ]
                        ) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="paginator">
        <ul class="pagination justify-content-center">
            <?= $this->Paginator->first('<< ' . 'Première') ?>
            <?= $this->Paginator->prev('< ' . 'Précédente') ?>
            <?= $this->Paginator->numbers() ?>
            <?= $this->Paginator->next('Suivante' . ' >') ?>
            <?= $this->Paginator->last('Dernière' . ' >>') ?>
        </ul>
        <p><?= $this->Paginator->counter('Page {{page}} sur {{pages}}, affichant {{current}} sur {{count}}') ?></p>
    </div>
</div>
