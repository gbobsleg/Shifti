<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\ResultSetInterface $rules
 * @var array $offers
 */
?>
<?php $this->assign('title', 'Règles de rotation'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app rotation-rules index content">
    <div class="crud-header">
        <div>
            <h1>
                <i class="bi bi-arrow-repeat"></i>
                Règles de rotation
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
            <div class="col-md-5">
                <label for="offer-id" class="form-label small text-muted mb-1">Offre</label>
                <?= $this->Form->select('offer_id', $offers, [
                    'empty' => 'Toutes les offres',
                    'class' => 'form-control form-control-sm',
                    'value' => $this->request->getQuery('offer_id'),
                    'id' => 'offer-id',
                ]) ?>
            </div>
            <div class="col-md-4">
                <label for="period-type" class="form-label small text-muted mb-1">Type de période</label>
                <?= $this->Form->select('period_type', [
                    'WEEKLY' => 'Hebdomadaire',
                    'MONTHLY' => 'Mensuelle',
                ], [
                    'empty' => 'Tous les types',
                    'class' => 'form-control form-control-sm',
                    'value' => $this->request->getQuery('period_type'),
                    'id' => 'period-type',
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
            $columns = ['Nom', 'Lignes', 'Agents', 'Période', 'Exclusivité', 'Actions'];
            $colCount = count($columns);
            ?>
            <thead>
            <tr>
                <th scope="col"><?= $this->Paginator->sort('name', $columns[0]) ?></th>
                <th scope="col"><?= h($columns[1]) ?></th>
                <th scope="col"><?= h($columns[2]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('period_type', $columns[3]) ?></th>
                <th scope="col"><?= h($columns[4]) ?></th>
                <th scope="col" class="actions"><?= h($columns[5]) ?></th>
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
                $nLines = is_countable($r->rotation_rule_lines) ? count($r->rotation_rule_lines) : 0;
                $nQuota = 0;
                $nCov = 0;
                foreach ($r->rotation_rule_lines ?? [] as $ln) {
                    if (($ln->line_type ?? '') === 'quota') {
                        $nQuota++;
                    } else {
                        $nCov++;
                    }
                }
                $nAgents = is_countable($r->users_rotation_rules) ? count($r->users_rotation_rules) : 0;
                $linesLabel = (int)$nLines . ' ligne(s)';
                if ($nQuota) {
                    $linesLabel .= ' · Quota ×' . (int)$nQuota;
                }
                if ($nCov) {
                    $linesLabel .= ' · Couverture ×' . (int)$nCov;
                }
                ?>
                <tr>
                    <td>
                        <?= $this->Html->link(
                            $r->name,
                            ['action' => 'view', $r->id],
                            ['class' => 'crud-row-link']
                        ) ?>
                    </td>
                    <td><?= h($linesLabel) ?></td>
                    <td><?= (int)$nAgents ?></td>
                    <td><?= $r->period_type === 'WEEKLY' ? 'Hebdomadaire' : 'Mensuelle' ?></td>
                    <td><?= !empty($r->exclusive_day) ? '1 duty / jour' : 'Cumul possible' ?></td>
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
                            '<i class="bi bi-trash" aria-hidden="true"></i>',
                            ['action' => 'delete', $r->id],
                            [
                                'confirm' => 'Supprimer la règle "' . h($r->name) . '" ?',
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
