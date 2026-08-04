<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\OfferGroup[]|\Cake\Collection\CollectionInterface $offerGroups
 */
?>
<?php $this->assign('title', 'Groupes d\'offres'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="offerGroups index content card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-diagram-3 text-info"></i>
            Groupes d'offres
        </h3>
        <div>
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle mr-1"></i> Nouveau groupe',
                ['action' => 'add'],
                ['class' => 'btn btn-success', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-basket mr-1"></i> Offres',
                ['controller' => 'Offers', 'action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div class="card-body">
        <p class="text-muted">
            Un groupe lie des offres membres et un profil mixte pour la passe 2
            (forecast sur les membres ou sur le groupe avec ratios manuels).
        </p>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th><?= $this->Paginator->sort('name', 'Nom') ?></th>
                        <th>Offre mixte</th>
                        <th>Membres</th>
                        <th><?= $this->Paginator->sort('forecast_source', 'Source forecast') ?></th>
                        <th>Préf. mixte</th>
                        <th class="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($offerGroups as $group): ?>
                        <tr>
                            <td><strong><?= h($group->name) ?></strong></td>
                            <td>
                                <?php if (!empty($group->mixed_offer)): ?>
                                    <?= $this->Html->link(
                                        h($group->mixed_offer->name),
                                        ['controller' => 'Offers', 'action' => 'view', $group->mixed_offer_id]
                                    ) ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $names = [];
                                foreach ($group->offer_group_members ?? [] as $m) {
                                    $names[] = h($m->offer->name ?? ('#' . $m->offer_id));
                                }
                                echo $names ? implode(', ', $names) : '<span class="text-muted">—</span>';
                                ?>
                            </td>
                            <td>
                                <?php if ($group->forecast_source === 'group'): ?>
                                    <span class="badge badge-primary">Groupe (ratio)</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Membres</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $group->prefer_mixed
                                    ? '<span class="badge badge-success">ON</span>'
                                    : '<span class="badge badge-light">OFF</span>' ?>
                            </td>
                            <td class="actions">
                                <?= $this->Html->link(
                                    '<i class="bi bi-eye"></i>',
                                    ['action' => 'view', $group->id],
                                    ['escape' => false, 'class' => 'btn btn-sm btn-outline-info', 'title' => 'Voir']
                                ) ?>
                                <?= $this->Html->link(
                                    '<i class="bi bi-pencil"></i>',
                                    ['action' => 'edit', $group->id],
                                    ['escape' => false, 'class' => 'btn btn-sm btn-outline-primary', 'title' => 'Modifier']
                                ) ?>
                                <?= $this->Form->postLink(
                                    '<i class="bi bi-trash"></i>',
                                    ['action' => 'delete', $group->id],
                                    [
                                        'escape' => false,
                                        'class' => 'btn btn-sm btn-outline-danger',
                                        'confirm' => 'Supprimer le groupe « ' . h($group->name) . ' » ?',
                                        'title' => 'Supprimer',
                                    ]
                                ) ?>
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
