<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Offer[]|\Cake\Collection\CollectionInterface $offers
 */
?>
<?php $this->assign('title', 'Liste des Offres'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<style>
.offers .table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.08) !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.2s ease;
}
</style>

<div class="offers index content card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-basket text-primary"></i>
            Liste des Offres
        </h3>
        <div class="btn-toolbar">
            <div class="btn-group mr-2">
                <?= $this->Html->link(
                    '<i class="bi bi-plus-circle mr-1"></i> Nouvelle Offre',
                    ['action' => 'add'],
                    ['class' => 'btn btn-success', 'escape' => false]
                ) ?>
            </div>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-toggle="dropdown">
                    <i class="bi bi-list-ul"></i> Raccourcis
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                    <?= $this->Html->link(
                        '<i class="bi bi-award-fill mr-2"></i> Compétences',
                        ['controller' => 'Skills', 'action' => 'index'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                    <?= $this->Html->link(
                        '<i class="bi bi-clock-history mr-2"></i> Plages',
                        ['controller' => 'Ranges', 'action' => 'index'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php // --- Cards de statistiques --- ?>
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-basket text-primary" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['total'] ?></h3>
                        <small class="text-muted">Total</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-award text-success" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['with_skills'] ?></h3>
                        <small class="text-muted">Avec compétences</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-secondary">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-x-circle text-secondary" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['without_skills'] ?></h3>
                        <small class="text-muted">Sans compétences</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-clock-history text-info" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['total_ranges'] ?></h3>
                        <small class="text-muted">Plages</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm">
                <thead>
                <tr>
                    <th scope="col"><?= $this->Paginator->sort('name', 'Nom') ?></th>
                    <th scope="col"><?= $this->Paginator->sort('color', 'Couleur') ?></th>
                    <th scope="col"><?= $this->Paginator->sort('offer_type', 'Type') ?></th>
                    <th scope="col"><?= $this->Paginator->sort('display_order', 'Ordre') ?></th>
                    <th scope="col">Options</th>
                    <th scope="col"><?= $this->Paginator->sort('start_date', 'Validité') ?></th>
                    <th scope="col"><?= $this->Paginator->sort('modified', 'Modifié le') ?></th>
                    <th scope="col" class="actions">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (count($offers) === 0): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <div class="empty-state">
                                <i class="bi bi-basket" style="font-size: 4rem; color: #dee2e6;"></i>
                                <h4 class="mt-3 text-muted">Aucune offre trouvée</h4>
                                <p class="text-muted">Commencez par créer votre première offre.</p>
                                <?= $this->Html->link(
                                    '<i class="bi bi-plus-circle mr-2"></i> Créer la première offre',
                                    ['action' => 'add'],
                                    ['class' => 'btn btn-primary mt-2', 'escape' => false]
                                ) ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($offers as $offer) : ?>
                    <tr>
                        <td><strong><?= h($offer->name) ?></strong></td>
                        <td>
                            <span style="display: inline-block; width: 30px; height: 30px; background-color: <?= h($offer->color) ?>; border: 2px solid #ddd; border-radius: 5px; vertical-align: middle;"></span>
                            <span class="ml-2"><?= h($offer->color) ?></span>
                        </td>
                        <td>
                            <?php
                            $typeLabels = [
                                'normal' => '<span class="badge badge-primary">Normale</span>',
                                'absence' => '<span class="badge badge-secondary">Absence</span>',
                                'meeting' => '<span class="badge badge-dark">Réunion, Formation, Mandat</span>',
                                'remote_work' => '<span class="badge badge-info">Télétravail</span>',
                                'pause' => '<span class="badge badge-warning">Pause</span>',
                                'lunch' => '<span class="badge badge-success">Repas</span>',
                            ];
                            echo $typeLabels[$offer->offer_type] ?? '<span class="badge badge-light">?</span>';
                            ?>
                        </td>
                        <td>
                            <span class="badge badge-light"><?= $this->Number->format($offer->display_order) ?></span>
                        </td>
                        <td>
                            <i class="bi bi-<?= $offer->is_displayed_in_grid ? 'eye text-success' : 'eye-slash text-muted' ?>" 
                               data-toggle="tooltip" title="<?= $offer->is_displayed_in_grid ? 'Affiché dans planning' : 'Non affiché' ?>"></i>
                            <i class="bi bi-<?= $offer->is_forecastable ? 'graph-up text-primary' : 'graph-down text-muted' ?>" 
                               data-toggle="tooltip" title="<?= $offer->is_forecastable ? 'Forecastable' : 'Non forecastable' ?>"></i>
                            <i class="bi bi-<?= !empty($offer->equity_enabled) ? 'people text-success' : 'people text-muted' ?>"
                               data-toggle="tooltip" title="<?= !empty($offer->equity_enabled) ? 'Équité période activée' : 'Équité période désactivée' ?>"></i>
                        </td>
                        <td>
                            <?php if ($offer->start_date || $offer->end_date): ?>
                                <span>
                                    <?= h($offer->start_date ? $offer->start_date->i18nFormat('dd/MM/yyyy') : '...') ?>
                                    <i class="bi bi-arrow-right"></i>
                                    <?= h($offer->end_date ? $offer->end_date->i18nFormat('dd/MM/yyyy') : '...') ?>
                                </span>
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
                                <span data-toggle="tooltip" title="<?= h($offer->modified->i18nFormat('dd/MM/yyyy HH:mm')) ?>">
                                    <?= h($timeAgo) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <div class="dropdown actions-dropdown" data-entity-id="<?= (int)$offer->id ?>">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownActions<?= $offer->id ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="bi bi-three-dots-vertical"></i> Actions
                                </button>
                                <div class="dropdown-menu dropdown-menu-right actions-dropdown-menu" data-entity-id="<?= (int)$offer->id ?>" aria-labelledby="dropdownActions<?= $offer->id ?>">
                                    <?= $this->Html->link(
                                        '<i class="bi bi-eye mr-2"></i> Voir',
                                        ['action' => 'view', $offer->id],
                                        ['class' => 'dropdown-item', 'escape' => false]
                                    ) ?>
                                    <?= $this->Html->link(
                                        '<i class="bi bi-pencil mr-2"></i> Modifier',
                                        ['action' => 'edit', $offer->id],
                                        ['class' => 'dropdown-item', 'escape' => false]
                                    ) ?>
                                    <div class="dropdown-divider"></div>
                                    <?= $this->Form->postLink(
                                        '<i class="bi bi-trash mr-2"></i> Supprimer',
                                        ['action' => 'delete', $offer->id],
                                        [
                                            'confirm' => 'Voulez-vous vraiment supprimer "' . h($offer->name) . '" ?',
                                            'class' => 'dropdown-item text-danger',
                                            'escape' => false
                                        ]
                                    ) ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="paginator mt-3">
            <ul class="pagination justify-content-center">
                <?= $this->Paginator->first('<< ' . 'Première') ?>
                <?= $this->Paginator->prev('< ' . 'Précédente') ?>
                <?= $this->Paginator->numbers() ?>
                <?= $this->Paginator->next('Suivante' . ' >') ?>
                <?= $this->Paginator->last('Dernière' . ' >>') ?>
            </ul>
            <p class="text-center"><?= $this->Paginator->counter('Page {{page}} sur {{pages}}, affichant {{current}} enregistrement(s) sur {{count}} au total') ?></p>
        </div>

    </div>
</div>

<script>
$(document).ready(function() {
    $('[data-toggle="tooltip"]').tooltip();
});
</script>
