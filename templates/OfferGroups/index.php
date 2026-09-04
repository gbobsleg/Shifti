<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\OfferGroup[]|\Cake\Collection\CollectionInterface $offerGroups
 */
?>
<?php $this->assign('title', 'Groupes d\'offres'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app offerGroups index content">
    <div class="crud-header">
        <div>
            <h1>
                <i class="bi bi-diagram-3"></i>
                Groupes d'offres
            </h1>
            <p class="crud-header-meta"><?= $this->Paginator->counter('{{count}} groupes') ?></p>
        </div>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle me-1"></i> Nouveau groupe',
                ['action' => 'add'],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-list-ul"></i> Raccourcis
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?= $this->Html->link(
                        '<i class="bi bi-basket me-2"></i> Offres',
                        ['controller' => 'Offers', 'action' => 'index'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                </div>
            </div>
        </div>
    </div>

    <p class="text-muted">
        Un groupe lie des offres membres et un profil mixte pour la passe 2
        (prévision sur les membres ou sur le groupe avec ratios manuels).
    </p>

    <div class="table-responsive">
        <table class="table table-hover table-sm crud-table">
            <?php
            $columns = ['Nom', 'Offre mixte', 'Membres', 'Source prévision', 'Préf. mixte', 'Actions'];
            $colCount = count($columns);
            ?>
            <thead>
            <tr>
                <th scope="col"><?= $this->Paginator->sort('name', $columns[0]) ?></th>
                <th scope="col"><?= h($columns[1]) ?></th>
                <th scope="col"><?= h($columns[2]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('forecast_source', $columns[3]) ?></th>
                <th scope="col"><?= h($columns[4]) ?></th>
                <th scope="col" class="actions"><?= h($columns[5]) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($offerGroups) === 0): ?>
                <tr>
                    <td colspan="<?= (int)$colCount ?>" class="crud-empty">
                        <p>Aucun groupe.</p>
                        <?= $this->Html->link(
                            '<i class="bi bi-plus-circle me-1"></i> Créer un groupe',
                            ['action' => 'add'],
                            ['class' => 'btn btn-sm btn-primary', 'escape' => false]
                        ) ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($offerGroups as $group): ?>
                <tr>
                    <td>
                        <?= $this->Html->link(
                            $group->name,
                            ['action' => 'view', $group->id],
                            ['class' => 'crud-row-link']
                        ) ?>
                    </td>
                    <td>
                        <?php if (!empty($group->mixed_offer)): ?>
                            <?= $this->Html->link(
                                $group->mixed_offer->name,
                                ['controller' => 'Offers', 'action' => 'view', $group->mixed_offer_id]
                            ) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $names = [];
                        foreach ($group->offer_group_members ?? [] as $m) {
                            $names[] = h($m->offer->name ?? ('#' . $m->offer_id));
                        }
                        echo $names ? implode(', ', $names) : '—';
                        ?>
                    </td>
                    <td>
                        <?= $group->forecast_source === 'group' ? 'Groupe (ratio)' : 'Membres' ?>
                    </td>
                    <td>
                        <?= $group->prefer_mixed ? 'Oui' : 'Non' ?>
                    </td>
                    <td class="actions">
                        <?= $this->Html->link(
                            '<i class="bi bi-pencil" aria-hidden="true"></i>',
                            ['action' => 'edit', $group->id],
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
                            ['action' => 'delete', $group->id],
                            [
                                'confirm' => 'Supprimer le groupe « ' . h($group->name) . ' » ?',
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
