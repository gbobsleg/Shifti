<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\PlanningEventMapping> $planningEventMappings
 */

/**
 * Retourne la classe de badge selon l'ID de l'offre
 * 
 * @param int $offerId ID de l'offre
 * @return string Classe de badge Bootstrap
 */
function getOfferBadgeClass($offerId) {
    $badgeClasses = [
        'badge-primary',    // Bleu
        'badge-success',    // Vert
        'badge-warning',    // Jaune/Orange
        'badge-danger',     // Rouge
        'badge-info',       // Cyan
        'badge-secondary',  // Gris
        'badge-dark',       // Sombre
    ];
    
    // Utilise l'ID de l'offre avec modulo pour avoir une couleur cohérente
    $index = ($offerId - 1) % count($badgeClasses);
    return $badgeClasses[$index];
}
?>
<?php $this->assign('title', 'Mappings Absences'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<style>
.planning-event-mappings .table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.08) !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.2s ease;
}
</style>

<div class="planning-event-mappings index content card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-link-45deg text-secondary"></i>
            Mappings Absences
        </h3>
        <div class="btn-toolbar">
            <div class="btn-group mr-2">
                <?= $this->Html->link(
                    '<i class="bi bi-plus-circle mr-1"></i> Nouveau Mapping',
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
                        '<i class="bi bi-file-earmark-excel mr-2"></i> Upload Excel',
                        ['controller' => 'ExcelUploads', 'action' => 'upload'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                    <?= $this->Html->link(
                        '<i class="bi bi-basket-fill mr-2"></i> Liste des Offres',
                        ['controller' => 'Offers', 'action' => 'index'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php // --- Info Card --- ?>
        <div class="alert alert-info mb-4">
            <h5 class="alert-heading">
                <i class="bi bi-info-circle"></i> À propos des mappings
            </h5>
            <hr>
            <p class="mb-2">
                Les mappings permettent d'associer automatiquement les <strong>patterns</strong> trouvés dans les commentaires Excel 
                aux <strong>offres d'absence</strong> correspondantes dans la base de données.
            </p>
            <p class="mb-2">
                <strong>Fonctionnement :</strong> Lors de l'upload d'un fichier Excel, le système recherche dans chaque commentaire 
                d'absence les patterns définis ici (par ordre de priorité décroissante). Le premier pattern trouvé détermine l'offre à utiliser.
            </p>
            <p class="mb-0">
                <strong>Priorité :</strong> Plus la priorité est élevée, plus le mapping sera testé en premier. 
                Utilisez des priorités élevées pour les patterns les plus spécifiques.
            </p>
        </div>

        <?php // --- Statistiques --- ?>
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card border-primary">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-link-45deg text-primary" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['total'] ?></h3>
                        <small class="text-muted">Total mappings</small>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card border-secondary">
                    <div class="card-body py-3">
                        <h6 class="mb-3">
                            <i class="bi bi-pie-chart"></i> Répartition par offre
                        </h6>
                        <div class="row">
                            <?php foreach ($stats['by_offer'] as $offerName => $count): ?>
                                <div class="col-md-6 mb-2">
                                    <span class="badge badge-secondary mr-2"><?= $count ?></span>
                                    <small><?= h($offerName) ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php // --- Tableau --- ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm">
                <thead class="table-light">
                    <tr>
                        <th scope="col"><?= $this->Paginator->sort('keywords', 'Mots-clés') ?></th>
                        <th scope="col"><?= $this->Paginator->sort('color_code', 'Code couleur') ?></th>
                        <th scope="col"><?= $this->Paginator->sort('offer_id', 'Offre') ?></th>
                        <th scope="col"><?= $this->Paginator->sort('priority', 'Priorité') ?></th>
                        <th scope="col"><?= $this->Paginator->sort('modified', 'Modifié le') ?></th>
                        <th scope="col" class="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($planningEventMappings) || $planningEventMappings->isEmpty()): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="empty-state">
                                    <i class="bi bi-link-45deg" style="font-size: 4rem; color: #dee2e6;"></i>
                                    <h4 class="mt-3 text-muted">Aucun mapping trouvé</h4>
                                    <p class="text-muted">
                                        Commencez par créer votre premier mapping pour associer les patterns Excel aux offres.
                                    </p>
                                    <?= $this->Html->link(
                                        '<i class="bi bi-plus-circle mr-2"></i> Créer le premier mapping',
                                        ['action' => 'add'],
                                        ['class' => 'btn btn-primary mt-2', 'escape' => false]
                                    ) ?>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                    <?php foreach ($planningEventMappings as $mapping): ?>
                        <tr>
                            <td>
                                <?php if (!empty($mapping->keywords)): ?>
                                    <code class="text-primary"><?= h($mapping->keywords) ?></code>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($mapping->color_code)): ?>
                                    <span class="badge badge-secondary" style="background-color: #<?= h($mapping->color_code) ?>; color: white;">
                                        #<?= h($mapping->color_code) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($mapping->hasValue('offer')): ?>
                                    <span class="badge <?= getOfferBadgeClass($mapping->offer->id) ?>">
                                        <i class="bi bi-tag"></i> <?= h($mapping->offer->name) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $mapping->priority >= 80 ? 'success' : ($mapping->priority >= 50 ? 'warning' : 'secondary') ?>">
                                    <?= $this->Number->format($mapping->priority) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($mapping->modified): 
                                    $now = new \Cake\I18n\FrozenTime();
                                    $diff = $now->diffInDays($mapping->modified);
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
                                    <span data-toggle="tooltip" title="<?= h($mapping->modified->i18nFormat('dd/MM/yyyy HH:mm')) ?>">
                                        <?= h($timeAgo) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <div class="dropdown actions-dropdown" data-entity-id="<?= (int)$mapping->id ?>">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownActions<?= $mapping->id ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="bi bi-three-dots-vertical"></i> Actions
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right actions-dropdown-menu" data-entity-id="<?= (int)$mapping->id ?>" aria-labelledby="dropdownActions<?= $mapping->id ?>">
                                        <?= $this->Html->link(
                                            '<i class="bi bi-eye mr-2"></i> Voir',
                                            ['action' => 'view', $mapping->id],
                                            ['class' => 'dropdown-item', 'escape' => false]
                                        ) ?>
                                        <?= $this->Html->link(
                                            '<i class="bi bi-pencil mr-2"></i> Modifier',
                                            ['action' => 'edit', $mapping->id],
                                            ['class' => 'dropdown-item', 'escape' => false]
                                        ) ?>
                                        <div class="dropdown-divider"></div>
                                        <?= $this->Form->postLink(
                                            '<i class="bi bi-trash mr-2"></i> Supprimer',
                                            ['action' => 'delete', $mapping->id],
                                            [
                                                'confirm' => 'Voulez-vous vraiment supprimer ce mapping ?',
                                                'class' => 'dropdown-item text-danger',
                                                'escape' => false
                                            ]
                                        ) ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
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
