<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\OfferGroup $offerGroup
 * @var array<int, string> $mixedOffers
 * @var list<array{offer_id:int,name:string,selected:bool,split_ratio_percent:int|null,display_order:int}> $memberOfferRows
 */
?>
<?php $this->assign('title', 'Nouveau groupe d\'offres'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app offerGroups form content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-plus-circle"></i>
            Nouveau groupe d'offres
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-x-circle me-1"></i> Annuler',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <?= $this->element('OfferGroups/form') ?>
</div>
