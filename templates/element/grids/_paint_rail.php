<?php
/**
 * @var \App\View\AppView $this
 * @var iterable $offers_list
 * @var bool $interactive
 */
$interactive = $interactive ?? true;
?>
<aside class="grids-rail<?= $interactive ? '' : ' is-legend' ?>" aria-label="<?= $interactive ? 'Pinceau offres' : 'Légende des offres' ?>">
    <div class="grids-rail-inner">
        <?php foreach ($offers_list as $offer):
            $type = (string)($offer->offer_type ?? 'normal');
            $isPause = $type === 'pause' || $type === 'lunch';
            $class = 'grids-rail-swatch' . ($isPause ? ' is-' . h($type) : '');
            if ($interactive) {
                $class = 'offerColor ' . $class;
            }
            $attrs = sprintf(
                'class="%s" data-id="%d" data-color="%s" data-offer-type="%s" data-offer-label="%s" title="%s" aria-label="%s"',
                $class,
                (int)$offer->id,
                h((string)$offer->color),
                h($type),
                h((string)$offer->name),
                h((string)$offer->name),
                h((string)$offer->name),
            );
            ?>
            <?php if ($interactive): ?>
                <button type="button" <?= $attrs ?>>
            <?php else: ?>
                <div <?= $attrs ?>>
            <?php endif; ?>
                <span class="swatch-color" style="background-color: <?= h((string)$offer->color) ?>"></span>
                <span class="swatch-name"><?= h((string)$offer->name) ?></span>
            <?php if ($interactive): ?>
                </button>
            <?php else: ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</aside>
