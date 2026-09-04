<?php
/**
 * @var \App\View\AppView $this
 * @var iterable $offers_list
 */
?>
<aside class="grids-rail" aria-label="Pinceau offres">
    <div class="grids-rail-inner">
        <?php foreach ($offers_list as $offer):
            $type = (string)($offer->offer_type ?? 'normal');
            $isPause = $type === 'pause' || $type === 'lunch';
            ?>
            <button type="button"
                class="offerColor grids-rail-swatch<?= $isPause ? ' is-' . h($type) : '' ?>"
                data-id="<?= (int)$offer->id ?>"
                data-color="<?= h((string)$offer->color) ?>"
                data-offer-type="<?= h($type) ?>"
                data-offer-label="<?= h((string)$offer->name) ?>"
                title="<?= h((string)$offer->name) ?>"
                aria-label="<?= h((string)$offer->name) ?>">
                <span class="swatch-color" style="background-color: <?= h((string)$offer->color) ?>"></span>
                <span class="swatch-name"><?= h((string)$offer->name) ?></span>
            </button>
        <?php endforeach; ?>
    </div>
</aside>
