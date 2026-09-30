<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Offer[]|\Cake\Collection\CollectionInterface $colorOffers
 * @var \App\Model\Entity\OfferColorFamily[]|\Cake\Collection\CollectionInterface $colorFamilies
 */

use App\Service\OfferColors\FamilyShadeGenerator;

$assignedIds = [];
foreach ($colorFamilies as $family) {
    foreach ($family->offer_color_family_offers as $row) {
        $assignedIds[(int)$row->offer_id] = true;
    }
}
$unassignedOffers = [];
foreach ($colorOffers as $offer) {
    if (!isset($assignedIds[(int)$offer->id])) {
        $unassignedOffers[] = $offer;
    }
}

$renderHueSelect = function (?int $selected, int $index) {
    ?>
    <select
        class="form-select form-select-sm offer-color-family-hue"
        data-field="hue"
        name="families[<?= $index ?>][hue]"
        aria-label="Teinte de la famille">
        <option value=""<?= $selected === null ? ' selected' : '' ?>>Automatique</option>
        <?php foreach (FamilyShadeGenerator::CATALOG as $hue => $label): ?>
            <option value="<?= (int)$hue ?>"<?= $selected === (int)$hue ? ' selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
    </select>
    <?php
};

$renderOffer = function ($offer, ?int $familyIndex = null) {
    $id = (int)$offer->id;
    $name = (string)$offer->name;
    $color = strtolower((string)$offer->color);
    if (preg_match('/^#[0-9a-f]{6}$/', $color) !== 1) {
        $color = '#000000';
    }
    ?>
    <li class="offer-color-family-offer" draggable="true" data-offer-id="<?= $id ?>" data-original-color="<?= h($color) ?>">
        <span class="offer-color-family-arrows">
            <button type="button" class="btn btn-sm btn-outline-secondary js-offer-up" aria-label="Monter <?= h($name) ?>"><i class="bi bi-chevron-up" aria-hidden="true"></i></button>
            <button type="button" class="btn btn-sm btn-outline-secondary js-offer-down" aria-label="Descendre <?= h($name) ?>"><i class="bi bi-chevron-down" aria-hidden="true"></i></button>
        </span>
        <label class="offer-color-family-swatch">
            <input type="color" class="js-offer-color" value="<?= h($color) ?>" aria-label="Couleur de <?= h($name) ?>">
        </label>
        <span class="offer-color-family-offer-name"><?= h($name) ?></span>
        <select class="form-select form-select-sm js-family-select" aria-label="Famille de <?= h($name) ?>"></select>
        <?php if ($familyIndex !== null): ?>
            <input type="hidden" class="js-offer-id" name="families[<?= $familyIndex ?>][offer_ids][]" value="<?= $id ?>">
        <?php endif; ?>
    </li>
    <?php
};

$shadeConfig = json_encode([
    'hues' => array_map('intval', array_keys(FamilyShadeGenerator::CATALOG)),
    'saturation' => FamilyShadeGenerator::SATURATION,
    'minLightness' => FamilyShadeGenerator::MIN_LIGHTNESS,
    'maxLightness' => FamilyShadeGenerator::MAX_LIGHTNESS,
], JSON_THROW_ON_ERROR);
?>
<?php $this->Html->css('offer-color-families', ['block' => true]); ?>
<?php $this->Html->script('offer-color-families', ['block' => true]); ?>

<h2 class="h5">Familles</h2>
<p class="text-muted">
    Choisir une teinte répartit les nuances dans la colonne, du plus foncé en haut au plus clair en bas. Un clic sur une pastille modifie une seule offre. Appliquer enregistre les couleurs affichées et l'ordre. Enregistrer le rangement ne change pas le planning.
</p>

<?= $this->Form->create(null, [
    'url' => ['action' => 'saveColorFamilies'],
    'id' => 'offer-color-families-form',
    'class' => 'mb-4',
]) ?>
    <div class="offer-color-families-board" id="offer-color-families-board" data-shade-config="<?= h($shadeConfig) ?>">
        <section class="offer-color-family-column" data-family-column="unassigned" data-column-id="unassigned">
            <h3 class="offer-color-family-title">Sans famille</h3>
            <ul class="offer-color-family-list">
                <?php foreach ($unassignedOffers as $offer): ?>
                    <?php $renderOffer($offer); ?>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php foreach ($colorFamilies as $index => $family): ?>
            <section class="offer-color-family-column" data-family-column="family" data-column-id="saved-<?= (int)$index ?>">
                <div class="offer-color-family-head">
                    <div class="offer-color-family-head-row">
                        <input
                            class="form-control form-control-sm"
                            data-field="name"
                            name="families[<?= (int)$index ?>][name]"
                            value="<?= h($family->name) ?>"
                            maxlength="255"
                            required
                            aria-label="Nom de la famille">
                        <input
                            type="hidden"
                            data-field="position"
                            name="families[<?= (int)$index ?>][position]"
                            value="<?= (int)$index ?>">
                        <button type="button" class="btn btn-sm btn-outline-danger js-remove-family">Retirer</button>
                    </div>
                    <?php $renderHueSelect($family->hue === null ? null : (int)$family->hue, (int)$index); ?>
                </div>
                <ul class="offer-color-family-list">
                    <?php foreach ($family->offer_color_family_offers as $row): ?>
                        <?php if ($row->offer === null) {
                            continue;
                        } ?>
                        <?php $renderOffer($row->offer, (int)$index); ?>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    </div>
    <div class="d-flex gap-2 mt-3">
        <button type="button" class="btn btn-outline-secondary" id="offer-color-family-add">Ajouter une famille</button>
        <?= $this->Form->button('Enregistrer le rangement', ['class' => 'btn btn-primary']) ?>
        <button
            type="submit"
            class="btn btn-outline-primary"
            id="offer-color-family-apply"
            formaction="<?= h($this->Url->build(['action' => 'applyColorFamilies'])) ?>">
            Appliquer au planning
        </button>
    </div>
<?= $this->Form->end() ?>

<template id="offer-color-family-template">
    <section class="offer-color-family-column" data-family-column="family" data-column-id="">
        <div class="offer-color-family-head">
            <div class="offer-color-family-head-row">
                <input
                    class="form-control form-control-sm"
                    data-field="name"
                    name="families[0][name]"
                    value=""
                    maxlength="255"
                    required
                    aria-label="Nom de la famille"
                    placeholder="Nom de la famille">
                <input type="hidden" data-field="position" name="families[0][position]" value="0">
                <button type="button" class="btn btn-sm btn-outline-danger js-remove-family">Retirer</button>
            </div>
            <select class="form-select form-select-sm offer-color-family-hue" data-field="hue" name="families[0][hue]" aria-label="Teinte de la famille">
                <option value="" selected>Automatique</option>
                <?php foreach (FamilyShadeGenerator::CATALOG as $hue => $label): ?>
                    <option value="<?= (int)$hue ?>"><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <ul class="offer-color-family-list"></ul>
    </section>
</template>
