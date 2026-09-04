<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Alert> $alerts
 */
?>
<?php $this->assign('title', 'Liste des Alertes'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php $this->Html->script('alerts-filters', ['block' => true]); ?>

<div class="crud-app alerts index content">
    <div class="crud-header">
        <div>
            <h1>
                <i class="bi bi-bell"></i>
                Alertes
            </h1>
            <p class="crud-header-meta"><?= $this->Paginator->counter('{{count}} alertes') ?></p>
        </div>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle me-1"></i> Nouvelle Alerte',
                ['action' => 'add'],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
        </div>
    </div>

    <?= $this->Form->create(null, ['type' => 'get', 'class' => 'filters-toolbar mb-3']) ?>
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label for="content" class="form-label small text-muted mb-1">Contenu</label>
                <?= $this->Form->text('content', [
                    'class' => 'form-control form-control-sm',
                    'placeholder' => 'Rechercher dans le contenu...',
                    'value' => $this->request->getQuery('content'),
                    'id' => 'content',
                ]) ?>
            </div>
            <div class="col-md-3">
                <label for="priority" class="form-label small text-muted mb-1">Priorité</label>
                <?= $this->Form->select('priority', [
                    1 => '1 - Urgent',
                    2 => '2 - Important',
                    3 => '3 - Information',
                ], [
                    'empty' => 'Toutes les priorités',
                    'class' => 'form-control form-control-sm',
                    'value' => $this->request->getQuery('priority'),
                    'id' => 'priority',
                ]) ?>
            </div>
            <div class="col-md-4 d-flex gap-2">
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
            $columns = ['Contenu', 'Début', 'Fin', 'Priorité', 'Actions'];
            $colCount = count($columns);
            ?>
            <thead>
            <tr>
                <th scope="col"><?= $this->Paginator->sort('content', $columns[0]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('date_start', $columns[1]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('date_end', $columns[2]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('priority', $columns[3]) ?></th>
                <th scope="col" class="actions"><?= h($columns[4]) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($alerts) === 0): ?>
                <tr>
                    <td colspan="<?= (int)$colCount ?>" class="crud-empty">
                        <p>Aucune alerte.</p>
                        <?php if (!$this->request->getQuery()): ?>
                            <?= $this->Html->link(
                                '<i class="bi bi-plus-circle me-1"></i> Créer une alerte',
                                ['action' => 'add'],
                                ['class' => 'btn btn-sm btn-primary', 'escape' => false]
                            ) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($alerts as $alert) : ?>
                <?php
                $priorityLabel = 'Information';
                if ($alert->priority == 1) {
                    $priorityLabel = 'Urgent';
                } elseif ($alert->priority == 2) {
                    $priorityLabel = 'Important';
                }
                $contentLabel = $alert->content !== null && $alert->content !== ''
                    ? $alert->content
                    : '#' . $alert->id;
                ?>
                <tr>
                    <td>
                        <?= $this->Html->link(
                            $contentLabel,
                            ['action' => 'view', $alert->id],
                            ['class' => 'crud-row-link']
                        ) ?>
                    </td>
                    <td><?= h($alert->date_start ? $alert->date_start->i18nFormat('dd/MM/yyyy') : '') ?></td>
                    <td><?= h($alert->date_end ? $alert->date_end->i18nFormat('dd/MM/yyyy') : '') ?></td>
                    <td><?= h($priorityLabel) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(
                            '<i class="bi bi-pencil" aria-hidden="true"></i>',
                            ['action' => 'edit', $alert->id],
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
                            ['action' => 'delete', $alert->id],
                            [
                                'confirm' => 'Voulez-vous vraiment supprimer cette alerte ?',
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
