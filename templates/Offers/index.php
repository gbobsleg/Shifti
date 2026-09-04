<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Offer[]|\Cake\Collection\CollectionInterface $offers
 */
$typeLabels = [
    'normal' => 'Normale',
    'absence' => 'Absence',
    'meeting' => 'Réunion, Formation, Mandat',
    'remote_work' => 'Télétravail',
    'pause' => 'Pause',
    'lunch' => 'Repas',
];
?>
<?php $this->assign('title', 'Liste des Offres'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app offers index content">
    <div class="crud-header">
        <div>
            <h1>
                <i class="bi bi-basket"></i>
                Offres
            </h1>
            <p class="crud-header-meta"><?= $this->Paginator->counter('{{count}} offres') ?></p>
        </div>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle me-1"></i> Nouvelle Offre',
                ['action' => 'add'],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-list-ul"></i> Raccourcis
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?= $this->Html->link(
                        '<i class="bi bi-award-fill me-2"></i> Compétences',
                        ['controller' => 'Skills', 'action' => 'index'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                    <?= $this->Html->link(
                        '<i class="bi bi-clock-history me-2"></i> Plages',
                        ['controller' => 'Ranges', 'action' => 'index'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-sm crud-table">
            <?php
            $columns = ['Nom', 'Couleur', 'Type', 'Ordre', 'Options', 'Validité', 'Modifié le', 'Actions'];
            $colCount = count($columns);
            ?>
            <thead>
            <tr>
                <th scope="col"><?= $this->Paginator->sort('name', $columns[0]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('color', $columns[1]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('offer_type', $columns[2]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('display_order', $columns[3]) ?></th>
                <th scope="col"><?= h($columns[4]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('start_date', $columns[5]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('modified', $columns[6]) ?></th>
                <th scope="col" class="actions"><?= h($columns[7]) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($offers) === 0): ?>
                <tr>
                    <td colspan="<?= (int)$colCount ?>" class="crud-empty">
                        <p>Aucune offre.</p>
                        <?= $this->Html->link(
                            '<i class="bi bi-plus-circle me-1"></i> Créer une offre',
                            ['action' => 'add'],
                            ['class' => 'btn btn-sm btn-primary', 'escape' => false]
                        ) ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($offers as $offer) : ?>
                <tr>
                    <td>
                        <?= $this->Html->link(
                            $offer->name,
                            ['action' => 'view', $offer->id],
                            ['class' => 'crud-row-link']
                        ) ?>
                    </td>
                    <td>
                        <span class="crud-color">
                            <span class="crud-swatch" style="background-color: <?= h($offer->color) ?>"></span>
                            <span class="crud-color-hex"><?= h($offer->color) ?></span>
                        </span>
                    </td>
                    <td><?= h($typeLabels[$offer->offer_type] ?? $offer->offer_type) ?></td>
                    <td><?= $this->Number->format($offer->display_order) ?></td>
                    <td>
                        <i class="bi bi-<?= $offer->is_displayed_in_grid ? 'eye text-success' : 'eye-slash text-muted' ?>"
                           data-bs-toggle="tooltip" title="<?= $offer->is_displayed_in_grid ? 'Affiché dans le planning' : 'Non affiché dans le planning' ?>"></i>
                        <i class="bi bi-<?= $offer->is_forecastable ? 'graph-up text-primary' : 'graph-down text-muted' ?>"
                           data-bs-toggle="tooltip" title="<?= $offer->is_forecastable ? 'Utilisable en prévision' : 'Non utilisable en prévision' ?>"></i>
                        <i class="bi bi-<?= !empty($offer->equity_enabled) ? 'people text-success' : 'people text-muted' ?>"
                           data-bs-toggle="tooltip" title="<?= !empty($offer->equity_enabled) ? 'Équité activée' : 'Équité désactivée' ?>"></i>
                    </td>
                    <td>
                        <?php if ($offer->start_date || $offer->end_date): ?>
                            <?= h($offer->start_date ? $offer->start_date->i18nFormat('dd/MM/yyyy') : '...') ?>
                            <i class="bi bi-arrow-right"></i>
                            <?= h($offer->end_date ? $offer->end_date->i18nFormat('dd/MM/yyyy') : '...') ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($offer->modified):
                            $now = new \Cake\I18n\FrozenTime();
                            $diff = $now->diffInDays($offer->modified);
                            $timeAgo = '';
                            if ($diff == 0) {
                                $timeAgo = "Aujourd'hui";
                            } elseif ($diff == 1) {
                                $timeAgo = 'Hier';
                            } elseif ($diff < 7) {
                                $timeAgo = 'Il y a ' . $diff . ' jours';
                            } elseif ($diff < 30) {
                                $weeks = floor($diff / 7);
                                $timeAgo = 'Il y a ' . $weeks . ' semaine' . ($weeks > 1 ? 's' : '');
                            } else {
                                $months = floor($diff / 30);
                                $timeAgo = 'Il y a ' . $months . ' mois';
                            }
                        ?>
                            <span data-bs-toggle="tooltip" title="<?= h($offer->modified->i18nFormat('dd/MM/yyyy HH:mm')) ?>">
                                <?= h($timeAgo) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <?= $this->Html->link(
                            '<i class="bi bi-pencil" aria-hidden="true"></i>',
                            ['action' => 'edit', $offer->id],
                            [
                                'class' => 'crud-action',
                                'escape' => false,
                                'title' => 'Modifier',
                                'aria-label' => 'Modifier',
                                'data-bs-toggle' => 'tooltip',
                            ]
                        ) ?>
                        <?= $this->Form->postLink(
                            '<i class="bi bi-trash" aria-hidden="true"></i>',
                            ['action' => 'delete', $offer->id],
                            [
                                'confirm' => 'Voulez-vous vraiment supprimer "' . h($offer->name) . '" ?',
                                'class' => 'crud-action crud-action-danger',
                                'escape' => false,
                                'title' => 'Supprimer',
                                'aria-label' => 'Supprimer',
                                'data-bs-toggle' => 'tooltip',
                            ]
                        ) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="paginator">
        <ul class="pagination justify-content-center">
            <?= $this->Paginator->first('<< ' . 'Première') ?>
            <?= $this->Paginator->prev('< ' . 'Précédente') ?>
            <?= $this->Paginator->numbers() ?>
            <?= $this->Paginator->next('Suivante' . ' >') ?>
            <?= $this->Paginator->last('Dernière' . ' >>') ?>
        </ul>
        <p><?= $this->Paginator->counter('Page {{page}} sur {{pages}}, affichant {{current}} sur {{count}}') ?></p>
    </div>
</div>

<script>
$(document).ready(function() {
    if (typeof window.initTooltips === 'function') {
        window.initTooltips();
    }
});
</script>
