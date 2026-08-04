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

<div class="offerGroups form content card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-plus-circle text-success"></i>
            Nouveau groupe d'offres
        </h3>
    </div>
    <div class="card-body">
        <?= $this->element('OfferGroups/form') ?>
    </div>
</div>
