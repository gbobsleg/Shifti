<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Skill> $skills
 */
?>
<?php $this->assign('title', 'Liste des Compétences'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php $this->Html->script('skills-filters', ['block' => true]); ?>

<div class="crud-app skills index content">
    <div class="crud-header">
        <div>
            <h1>
                <i class="bi bi-award"></i>
                Compétences
            </h1>
            <p class="crud-header-meta"><?= $this->Paginator->counter('{{count}} compétences') ?></p>
        </div>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle me-1"></i> Nouvelle compétence',
                ['action' => 'add'],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-list-ul"></i> Raccourcis
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?= $this->Html->link(
                        '<i class="bi bi-people-fill me-2"></i> Utilisateurs',
                        ['controller' => 'Users', 'action' => 'index'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                    <?= $this->Html->link(
                        '<i class="bi bi-basket me-2"></i> Offres',
                        ['controller' => 'Offers', 'action' => 'index'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                </div>
            </div>
        </div>
    </div>

    <?= $this->Form->create(null, ['type' => 'get', 'class' => 'filters-toolbar mb-3']) ?>
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Agent</label>
                <?= $this->Form->select('user_id', $users, [
                    'empty' => 'Tous les agents',
                    'class' => 'form-control form-control-sm',
                    'value' => $this->request->getQuery('user_id'),
                ]) ?>
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Offre</label>
                <?= $this->Form->select('offer_id', $offers, [
                    'empty' => 'Toutes les offres',
                    'class' => 'form-control form-control-sm',
                    'value' => $this->request->getQuery('offer_id'),
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
            $columns = ['Utilisateur', 'Offre', 'Début', 'Fin', 'Créé le', 'Actions'];
            $colCount = count($columns);
            ?>
            <thead>
            <tr>
                <th scope="col"><?= $this->Paginator->sort('user_id', $columns[0]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('offer_id', $columns[1]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('validity_start', $columns[2]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('validity_end', $columns[3]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('created', $columns[4]) ?></th>
                <th scope="col" class="actions"><?= h($columns[5]) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($skills) === 0): ?>
                <tr>
                    <td colspan="<?= (int)$colCount ?>" class="crud-empty">
                        <p>Aucune compétence.</p>
                        <?php if (!$this->request->getQuery()): ?>
                            <?= $this->Html->link(
                                '<i class="bi bi-plus-circle me-1"></i> Créer une compétence',
                                ['action' => 'add'],
                                ['class' => 'btn btn-sm btn-primary', 'escape' => false]
                            ) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($skills as $skill): ?>
                <?php
                $isExpired = $skill->validity_end && $skill->validity_end < new \Cake\I18n\FrozenDate();
                $userLabel = $skill->hasValue('user')
                    ? $skill->user->last_name . ' ' . $skill->user->first_name
                    : '#' . $skill->id;
                ?>
                <tr>
                    <td>
                        <?= $this->Html->link(
                            $userLabel,
                            ['action' => 'view', $skill->id],
                            ['class' => 'crud-row-link']
                        ) ?>
                    </td>
                    <td><?= $skill->hasValue('offer') ? h($skill->offer->name) : '' ?></td>
                    <td><?= h($skill->validity_start ? $skill->validity_start->i18nFormat('dd/MM/yyyy') : '—') ?></td>
                    <td>
                        <?= h($skill->validity_end ? $skill->validity_end->i18nFormat('dd/MM/yyyy') : '—') ?>
                        <?php if ($isExpired): ?>
                            <span class="text-muted">Expirée</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($skill->created):
                            $now = new \Cake\I18n\FrozenTime();
                            $diff = $now->diffInDays($skill->created);
                            if ($diff == 0) {
                                $timeAgo = "Aujourd'hui";
                            } elseif ($diff == 1) {
                                $timeAgo = 'Hier';
                            } elseif ($diff < 7) {
                                $timeAgo = 'Il y a ' . $diff . ' jours';
                            } elseif ($diff < 30) {
                                $weeks = (int)floor($diff / 7);
                                $timeAgo = 'Il y a ' . $weeks . ' semaine' . ($weeks > 1 ? 's' : '');
                            } else {
                                $months = (int)floor($diff / 30);
                                $timeAgo = 'Il y a ' . $months . ' mois';
                            }
                        ?>
                            <span data-bs-toggle="tooltip" title="<?= h($skill->created->i18nFormat('dd/MM/yyyy HH:mm')) ?>">
                                <?= h($timeAgo) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <?= $this->Html->link(
                            '<i class="bi bi-pencil" aria-hidden="true"></i>',
                            ['action' => 'edit', $skill->id],
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
                            ['action' => 'delete', $skill->id],
                            [
                                'confirm' => 'Voulez-vous vraiment supprimer cette compétence ?',
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
