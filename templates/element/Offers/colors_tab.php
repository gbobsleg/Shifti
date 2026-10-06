<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Offer[] $colorOffers
 * @var \App\Model\Entity\OfferColorPreset[]|\Cake\Collection\CollectionInterface $colorPresets
 * @var int $revision
 */
?>
<p class="text-muted">
    Le bandeau édite une palette. Enregistrer la fige. Appliquer, sur une palette de la liste, est la seule action qui modifie le planning.
</p>

<div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="h5 mb-0">Palettes</h2>
    <button type="button" class="btn btn-primary btn-sm" id="offer-color-create-toggle">Créer une palette</button>
</div>
<div id="offer-color-create-panel" class="mb-4 d-none">
    <?= $this->Form->create(null, [
        'url' => ['action' => 'createColorPreset'],
        'id' => 'offer-color-create-form',
        'class' => 'row g-2 align-items-end',
    ]) ?>
        <?= $this->Form->hidden('revision', [
            'value' => (int)$revision,
            'id' => 'offer-color-create-revision',
        ]) ?>
        <div class="col-md-4">
            <label class="form-label" for="offer-color-create-name">Nom</label>
            <input class="form-control" id="offer-color-create-name" name="name" maxlength="255" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="offer-color-create-source">Partir de</label>
            <select class="form-select" id="offer-color-create-source" name="source">
                <option value="current">Arrangement actuel</option>
                <?php foreach ($colorPresets as $preset): ?>
                    <option value="<?= (int)$preset->id ?>"><?= h($preset->name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary" id="offer-color-create-submit">Créer</button>
        </div>
    <?= $this->Form->end() ?>
</div>
<div class="table-responsive mb-4">
    <table class="table table-hover table-sm crud-table">
        <thead>
        <tr>
            <th scope="col">Nom</th>
            <th scope="col">Enregistré le</th>
            <th scope="col" class="actions">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php if (count($colorPresets) === 0): ?>
            <tr>
                <td colspan="3" class="crud-empty">Aucune palette.</td>
            </tr>
        <?php endif; ?>
        <?php foreach ($colorPresets as $preset): ?>
            <tr>
                <td><?= h($preset->name) ?></td>
                <td>
                    <?= $preset->created ? h($preset->created->i18nFormat('dd/MM/yyyy HH:mm')) : '' ?>
                </td>
                <td class="actions">
                    <?= $this->Form->create(null, [
                        'url' => ['action' => 'openColorPreset', $preset->id],
                        'class' => 'd-inline js-confirm-if-dirty',
                    ]) ?>
                        <?= $this->Form->hidden('revision', [
                            'value' => (int)$revision,
                            'id' => 'open-revision-' . (int)$preset->id,
                        ]) ?>
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Ouvrir</button>
                    <?= $this->Form->end() ?>
                    <?= $this->Form->create(null, [
                        'url' => ['action' => 'applyColorPreset', $preset->id],
                        'class' => 'd-inline js-confirm-if-dirty',
                        'data-confirm' => 'Appliquer « ' . $preset->name . ' » au planning ? C\'est immédiat pour tout le monde. Les offres absentes de cette palette gardent leur couleur.',
                    ]) ?>
                        <?= $this->Form->hidden('revision', [
                            'value' => (int)$revision,
                            'id' => 'apply-revision-' . (int)$preset->id,
                        ]) ?>
                        <button type="submit" class="btn btn-sm btn-outline-primary">Appliquer</button>
                    <?= $this->Form->end() ?>
                    <?= $this->Form->postLink(
                        'Supprimer',
                        ['action' => 'deleteColorPreset', $preset->id],
                        [
                            'confirm' => 'Supprimer la palette « ' . $preset->name . ' » ? Le bandeau et le planning ne changent pas.',
                            'class' => 'btn btn-sm btn-outline-danger',
                        ]
                    ) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?= $this->element('Offers/color_families') ?>
