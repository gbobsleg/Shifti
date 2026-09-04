<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\OfferGroup $offerGroup
 */
use App\Model\Entity\OfferGroup;
?>
<?php $this->assign('title', 'Groupe : ' . h($offerGroup->name)); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app offerGroups view content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-diagram-3"></i>
            <?= h($offerGroup->name) ?>
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-pencil me-1"></i> Modifier',
                ['action' => 'edit', $offerGroup->id],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list me-1"></i> Liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash me-1"></i> Supprimer',
                ['action' => 'delete', $offerGroup->id],
                [
                    'confirm' => 'Supprimer le groupe « ' . h($offerGroup->name) . ' » ?',
                    'class' => 'btn btn-outline-danger',
                    'escape' => false,
                ]
            ) ?>
        </div>
    </div>

    <section class="crud-section">
        <h2 class="crud-section-title">Paramètres</h2>
        <dl class="crud-fields">
            <div>
                <dt>Nom</dt>
                <dd><?= h($offerGroup->name) ?></dd>
            </div>
            <div>
                <dt>Source de prévision</dt>
                <dd>
                    <?= $offerGroup->forecast_source === OfferGroup::FORECAST_SOURCE_GROUP
                        ? 'Groupe (ratio manuel)'
                        : 'Membres' ?>
                </dd>
            </div>
            <div>
                <dt>Préférence mixte</dt>
                <dd><?= $offerGroup->prefer_mixed ? 'Oui' : 'Non' ?></dd>
            </div>
            <div>
                <dt>Offre mixte</dt>
                <dd>
                    <?php if (!empty($offerGroup->mixed_offer)): ?>
                        <?= $this->Html->link(
                            $offerGroup->mixed_offer->name,
                            ['controller' => 'Offers', 'action' => 'view', $offerGroup->mixed_offer_id]
                        ) ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </dd>
            </div>
        </dl>
    </section>

    <section class="crud-section">
        <h2 class="crud-section-title">Membres</h2>
        <div class="table-responsive">
            <table class="table table-hover table-sm crud-table">
                <thead>
                <tr>
                    <th scope="col">Ordre</th>
                    <th scope="col">Offre</th>
                    <th scope="col">Ratio %</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $members = $offerGroup->offer_group_members ?? [];
                if (!$members):
                ?>
                    <tr>
                        <td colspan="3" class="crud-empty">
                            <p>Aucun membre.</p>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($members as $member): ?>
                    <tr>
                        <td><?= (int)$member->display_order ?></td>
                        <td>
                            <?php if (!empty($member->offer)): ?>
                                <?= $this->Html->link(
                                    $member->offer->name,
                                    ['controller' => 'Offers', 'action' => 'view', $member->offer_id],
                                    ['class' => 'crud-row-link']
                                ) ?>
                            <?php else: ?>
                                #<?= (int)$member->offer_id ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $member->split_ratio_percent !== null
                                ? h((string)$member->split_ratio_percent) . ' %'
                                : '—' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
