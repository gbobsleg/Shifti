<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Offer[] $colorOffers
 * @var \App\Model\Entity\OfferColorFamily[]|\Cake\Collection\CollectionInterface $colorFamilies
 * @var array<int, string> $swatchColors
 * @var int $revision
 * @var string $boardName
 * @var int|null $boardPresetId
 * @var string $paletteTitle
 * @var bool $conflict
 * @var bool $missingPreset
 * @var int|null $overwriteRevision
 * @var string $conflictAction
 * @var bool $boardDirty
 * @var bool $liveSwatches
 */

use App\Service\OfferColors\FamilyShadeGenerator;

$shadeGenerator = new FamilyShadeGenerator();

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

$renderPastel = function (bool $checked, int $index) {
    ?>
    <label class="offer-color-pastel">
        <input type="checkbox" data-field="pastel" name="families[<?= $index ?>][pastel]" value="1"<?= $checked ? ' checked' : '' ?>>
        Pastel
    </label>
    <?php
};

$renderHueSelect = function (?int $selected, int $index) use ($shadeGenerator) {
    ?>
    <select
        class="form-select form-select-sm offer-color-family-hue"
        data-field="hue"
        name="families[<?= $index ?>][hue]"
        aria-label="Teinte de la famille">
        <option value=""<?= $selected === null ? ' selected' : '' ?>>Automatique</option>
        <?php foreach (FamilyShadeGenerator::CATALOG as $hue => $label): ?>
            <option value="<?= (int)$hue ?>" data-base="<?= h($shadeGenerator->baseHex((int)$hue)) ?>"<?= $selected === (int)$hue ? ' selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
    </select>
    <?php
};

$renderOffer = function ($offer, ?int $familyIndex = null) use ($swatchColors) {
    $id = (int)$offer->id;
    $name = (string)$offer->name;
    $color = strtolower((string)($swatchColors[$id] ?? $offer->color));
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
    'saturationDark' => FamilyShadeGenerator::SATURATION_DARK,
    'saturationLight' => FamilyShadeGenerator::SATURATION_LIGHT,
    'saturationWarm' => FamilyShadeGenerator::SATURATION_WARM,
    'warmHueMin' => FamilyShadeGenerator::WARM_HUE_MIN,
    'warmHueMax' => FamilyShadeGenerator::WARM_HUE_MAX,
    'hueArcLeft' => FamilyShadeGenerator::HUE_ARC_LEFT,
    'hueArcRight' => FamilyShadeGenerator::HUE_ARC_RIGHT,
    'contrastLight' => FamilyShadeGenerator::CONTRAST_LIGHT,
    'contrastDarkWarm' => FamilyShadeGenerator::CONTRAST_DARK_WARM,
    'contrastDark' => FamilyShadeGenerator::CONTRAST_DARK,
    'lightnessMin' => FamilyShadeGenerator::LIGHTNESS_MIN,
    'lightnessMax' => FamilyShadeGenerator::LIGHTNESS_MAX,
    'contrastTolerance' => FamilyShadeGenerator::CONTRAST_TOLERANCE,
    'maxIterations' => FamilyShadeGenerator::MAX_ITERATIONS,
    'pairLightness' => FamilyShadeGenerator::PAIR_LIGHTNESS,
    'pastelSaturation' => FamilyShadeGenerator::PASTEL_SATURATION,
    'pastelLightness' => FamilyShadeGenerator::PASTEL_LIGHTNESS,
    'pastelPairSaturation' => FamilyShadeGenerator::PASTEL_PAIR_SATURATION,
    'pastelPairLightness' => FamilyShadeGenerator::PASTEL_PAIR_LIGHTNESS,
], JSON_THROW_ON_ERROR);

$displayName = $boardPresetId ? ($boardName !== '' ? $boardName : $paletteTitle) : '';
$title = $displayName !== '' ? $displayName : 'Brouillon';
?>
<?php $this->Html->css('offer-color-families', ['block' => true, 'timestamp' => 'force']); ?>
<?php $this->Html->script('offer-color-families', ['block' => true, 'timestamp' => 'force']); ?>

<div class="d-flex align-items-center gap-2 mb-2">
    <h2 class="h5 mb-0" id="offer-color-palette-title"><?= h($title) ?></h2>
    <?php if ($boardPresetId): ?>
        <button type="button" class="btn btn-sm btn-outline-secondary js-rename-palette" aria-label="Renommer la palette">
            <i class="bi bi-pencil" aria-hidden="true"></i>
        </button>
        <span class="js-palette-rename d-none gap-2 align-items-center">
            <input class="form-control form-control-sm" id="palette-name-edit" maxlength="255" value="<?= h($displayName) ?>" aria-label="Nom de la palette">
            <button type="button" class="btn btn-sm btn-primary js-rename-commit">OK</button>
        </span>
    <?php endif; ?>
</div>
<p class="text-muted">
    Choisir une teinte répartit les nuances dans la colonne, du plus foncé en haut au plus clair en bas. Un clic sur une pastille modifie une seule offre.
</p>

<?= $this->Form->create(null, [
    'url' => ['action' => 'saveColorPreset'],
    'id' => 'offer-color-families-form',
    'class' => 'mb-4',
]) ?>
    <?= $this->Form->hidden('revision', ['value' => (int)$revision, 'id' => 'offer-color-revision']) ?>
    <?php if ($boardPresetId): ?>
        <?= $this->Form->hidden('preset_id', ['value' => (int)$boardPresetId, 'id' => 'offer-color-preset-id']) ?>
    <?php endif; ?>
    <?php if ($conflict && $overwriteRevision !== null): ?>
        <div class="alert alert-warning">
            <p class="mb-2">Poursuivre détruit le travail de quelqu'un d'autre. Cette version n'est pas affichée.</p>
            <div class="d-flex gap-2">
                <?= $this->Html->link(
                    'Abandonner et charger la version en cours',
                    ['action' => 'index', '?' => ['tab' => 'couleurs']],
                    ['class' => 'btn btn-sm btn-outline-secondary']
                ) ?>
                <button
                    type="submit"
                    class="btn btn-sm btn-outline-danger"
                    name="overwrite_revision"
                    value="<?= (int)$overwriteRevision ?>"
                    formaction="<?= h($this->Url->build(['action' => 'saveColorPreset'])) ?>">
                    Écraser la version de l'autre
                </button>
            </div>
        </div>
    <?php elseif ($missingPreset): ?>
        <div class="alert alert-warning">
            Cette palette a été supprimée. Créez-en une pour garder cet arrangement.
        </div>
    <?php endif; ?>
    <input type="hidden" name="name" id="palette-name" value="<?= h($boardPresetId ? $displayName : '') ?>">
    <div
        class="offer-color-families-board"
        id="offer-color-families-board"
        data-shade-config="<?= h($shadeConfig) ?>"
        data-preserve-colors="<?= ($boardPresetId || $boardDirty || $liveSwatches) ? '1' : '0' ?>"
        data-board-dirty="<?= $boardDirty ? '1' : '0' ?>">
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
                    <div class="offer-color-family-hue-row">
                        <?php $renderHueSelect($family->hue === null ? null : (int)$family->hue, (int)$index); ?>
                        <?php $renderPastel((bool)$family->pastel, (int)$index); ?>
                    </div>
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
    <div class="d-flex flex-wrap gap-2 mt-3 align-items-center">
        <button type="button" class="btn btn-outline-secondary" id="offer-color-family-add">Ajouter une famille</button>
        <?php if ($boardPresetId): ?>
            <?= $this->Form->button('Enregistrer', ['class' => 'btn btn-primary', 'id' => 'offer-color-family-save']) ?>
        <?php endif; ?>
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
            <div class="offer-color-family-hue-row">
                <select class="form-select form-select-sm offer-color-family-hue" data-field="hue" name="families[0][hue]" aria-label="Teinte de la famille">
                    <option value="" selected>Automatique</option>
                    <?php foreach (FamilyShadeGenerator::CATALOG as $hue => $label): ?>
                        <option value="<?= (int)$hue ?>" data-base="<?= h($shadeGenerator->baseHex((int)$hue)) ?>"><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="offer-color-pastel">
                    <input type="checkbox" data-field="pastel" name="families[0][pastel]" value="1">
                    Pastel
                </label>
            </div>
        </div>
        <ul class="offer-color-family-list"></ul>
    </section>
</template>
