<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\OfferGroup $offerGroup
 */
use App\Model\Entity\OfferGroup;
?>
<?php $this->assign('title', 'Groupe : ' . h($offerGroup->name)); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="offerGroups view content card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-diagram-3 text-info"></i>
            <?= h($offerGroup->name) ?>
        </h3>
        <div>
            <?= $this->Html->link(
                '<i class="bi bi-pencil mr-1"></i> Modifier',
                ['action' => 'edit', $offerGroup->id],
                ['class' => 'btn btn-primary mr-2', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash mr-1"></i> Supprimer',
                ['action' => 'delete', $offerGroup->id],
                [
                    'confirm' => 'Supprimer le groupe « ' . h($offerGroup->name) . ' » ?',
                    'class' => 'btn btn-danger mr-2',
                    'escape' => false,
                ]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list mr-1"></i> Liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div class="card-body">
        <div class="card border-info mb-4">
            <div class="card-header bg-info text-white">
                <i class="bi bi-info-circle"></i> Paramètres
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small mb-1">Nom</label>
                        <div><strong><?= h($offerGroup->name) ?></strong></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small mb-1">Source forecast</label>
                        <div>
                            <?php if ($offerGroup->forecast_source === OfferGroup::FORECAST_SOURCE_GROUP): ?>
                                <span class="badge badge-primary">Groupe (ratio manuel)</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Membres</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small mb-1">Préférence mixte</label>
                        <div>
                            <?= $offerGroup->prefer_mixed
                                ? '<span class="badge badge-success">ON</span>'
                                : '<span class="badge badge-light">OFF</span>' ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1">Offre mixte</label>
                        <div>
                            <?php if (!empty($offerGroup->mixed_offer)): ?>
                                <?= $this->Html->link(
                                    h($offerGroup->mixed_offer->name),
                                    ['controller' => 'Offers', 'action' => 'view', $offerGroup->mixed_offer_id]
                                ) ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-primary mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-people"></i> Membres
            </div>
            <div class="card-body">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Ordre</th>
                            <th>Offre</th>
                            <th>Ratio %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($offerGroup->offer_group_members ?? [] as $member): ?>
                            <tr>
                                <td><?= (int)$member->display_order ?></td>
                                <td>
                                    <?php if (!empty($member->offer)): ?>
                                        <?= $this->Html->link(
                                            h($member->offer->name),
                                            ['controller' => 'Offers', 'action' => 'view', $member->offer_id]
                                        ) ?>
                                    <?php else: ?>
                                        #<?= (int)$member->offer_id ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $member->split_ratio_percent !== null
                                        ? h((string)$member->split_ratio_percent) . ' %'
                                        : '<span class="text-muted">—</span>' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
