<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Offer[]|\Cake\Collection\CollectionInterface $colorOffers
 * @var \App\Model\Entity\OfferColorPreset[]|\Cake\Collection\CollectionInterface $colorPresets
 * @var \App\Model\Entity\OfferColorFamily[]|\Cake\Collection\CollectionInterface $colorFamilies
 */
?>
<p class="text-muted">
    Enregistre les couleurs et l'ordre d'affichage de toutes les offres.
    Restaurer réécrit ces deux valeurs. Une offre créée après l'enregistrement n'est pas modifiée.
</p>

<?= $this->Form->create(null, ['url' => ['action' => 'saveColorPreset'], 'class' => 'mb-4']) ?>
    <label class="form-label" for="name">Nom de la palette</label>
    <div class="row g-2 align-items-center">
        <div class="col-md-6">
            <?= $this->Form->control('name', [
                'label' => false,
                'id' => 'name',
                'class' => 'form-control',
                'required' => true,
                'maxlength' => 255,
                'templates' => [
                    'inputContainer' => '<div class="m-0">{{content}}</div>',
                ],
            ]) ?>
        </div>
        <div class="col-auto">
            <?= $this->Form->button('Enregistrer les couleurs actuelles', ['class' => 'btn btn-primary']) ?>
        </div>
    </div>
<?= $this->Form->end() ?>

<h2 class="h5">Palettes</h2>
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
                    <?= $this->Form->postLink(
                        'Restaurer',
                        ['action' => 'restoreColorPreset', $preset->id],
                        [
                            'confirm' => 'Restaurer « ' . h($preset->name) . ' » remplace les couleurs et l\'ordre d\'affichage des offres présentes dans cette palette. Les offres créées après ne sont pas modifiées.',
                            'class' => 'btn btn-sm btn-outline-primary',
                            'escape' => false,
                        ]
                    ) ?>
                    <?= $this->Form->postLink(
                        'Supprimer',
                        ['action' => 'deleteColorPreset', $preset->id],
                        [
                            'confirm' => 'Supprimer la palette « ' . h($preset->name) . ' » ? Les couleurs en cours ne changent pas.',
                            'class' => 'btn btn-sm btn-outline-danger',
                            'escape' => false,
                        ]
                    ) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?= $this->element('Offers/color_families') ?>

<h2 class="h5">Couleurs actuelles</h2>
<div class="table-responsive">
    <table class="table table-hover table-sm crud-table">
        <thead>
        <tr>
            <th scope="col">Nom</th>
            <th scope="col">Couleur</th>
            <th scope="col">Ordre</th>
        </tr>
        </thead>
        <tbody>
        <?php if (count($colorOffers) === 0): ?>
            <tr>
                <td colspan="3" class="crud-empty">Aucune offre.</td>
            </tr>
        <?php endif; ?>
        <?php foreach ($colorOffers as $offer): ?>
            <tr>
                <td><?= h($offer->name) ?></td>
                <td>
                    <span class="crud-color">
                        <span class="crud-swatch" style="background-color: <?= h($offer->color) ?>"></span>
                        <span class="crud-color-hex"><?= h($offer->color) ?></span>
                    </span>
                </td>
                <td><?= $this->Number->format($offer->display_order) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
