<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Skill> $skills
 */
?>
<?php $this->assign('title', 'Liste des Compétences'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php $this->Html->script('skills-filters', ['block' => true]); ?>

<style>
.skills .table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.08) !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.2s ease;
}
</style>

<div class="skills index content card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="mb-0">
            <i class="bi bi-award text-primary"></i> Liste des Compétences
        </h3>
        <div class="btn-toolbar">
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle mr-1"></i> Nouvelle Compétence',
                ['action' => 'add'],
                ['class' => 'btn btn-success mr-2', 'escape' => false]
            ) ?>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bi bi-three-dots-vertical"></i> Raccourcis
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                    <?= $this->Html->link(
                        '<i class="bi bi-people-fill mr-2"></i> Liste des Utilisateurs',
                        ['controller' => 'Users', 'action' => 'index'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                    <?= $this->Html->link(
                        '<i class="bi bi-tags-fill mr-2"></i> Liste des Offres',
                        ['controller' => 'Offers', 'action' => 'index'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                </div>
            </div>
        </div>
    </div>
        <div class="card-body"> <?php // Ajout card-body ?>
            <?php // --- Cards de statistiques --- ?>
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card border-primary">
                        <div class="card-body text-center py-3">
                            <i class="bi bi-award text-primary" style="font-size: 2rem;"></i>
                            <h3 class="mb-0 mt-2"><?= $stats['total'] ?></h3>
                            <small class="text-muted">Total</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-success">
                        <div class="card-body text-center py-3">
                            <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                            <h3 class="mb-0 mt-2"><?= $stats['active'] ?></h3>
                            <small class="text-muted">Actives</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-danger">
                        <div class="card-body text-center py-3">
                            <i class="bi bi-x-circle text-danger" style="font-size: 2rem;"></i>
                            <h3 class="mb-0 mt-2"><?= $stats['expired'] ?></h3>
                            <small class="text-muted">Expirées</small>
                        </div>
                    </div>
                </div>
            </div>

            <?php // --- Toolbar de filtrage --- ?>
            <?= $this->Form->create(null, ['type' => 'get', 'class' => 'filters-toolbar mb-4 p-3 bg-light border rounded']) ?>
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <label for="user-id" class="form-label small text-muted mb-1">
                            <i class="bi bi-person"></i> Agent
                        </label>
                        <?= $this->Form->select('user_id', $users, [
                            'empty' => 'Tous les agents',
                            'class' => 'form-control form-control-sm',
                            'value' => $this->request->getQuery('user_id')
                        ]) ?>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label for="offer-id" class="form-label small text-muted mb-1">
                            <i class="bi bi-award"></i> Compétence
                        </label>
                        <?= $this->Form->select('offer_id', $offers, [
                            'empty' => 'Toutes les compétences',
                            'class' => 'form-control form-control-sm',
                            'value' => $this->request->getQuery('offer_id')
                        ]) ?>
                    </div>
                    <div class="col-md-2 mb-2 d-flex flex-column align-items-stretch">
                        <?= $this->Form->button('<i class="bi bi-search"></i> Filtrer', [
                            'type' => 'submit',
                            'class' => 'btn btn-sm btn-primary mb-1',
                            'escapeTitle' => false
                        ]) ?>
                        <?= $this->Html->link('<i class="bi bi-arrow-counterclockwise"></i> Réinitialiser', 
                            ['action' => 'index'], 
                            ['class' => 'btn btn-sm btn-outline-secondary', 'escape' => false]
                        ) ?>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-12">
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i> 
                            <?= $this->Paginator->counter('{{count}} compétence(s) au total, affichant {{current}} sur cette page') ?>
                        </small>
                    </div>
                </div>
            <?= $this->Form->end() ?>
            
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm"> <?php // Ajout classes Bootstrap ?>
                    <thead>
                    <tr>
                        <th scope="col"><?= $this->Paginator->sort('id', 'ID') ?></th> <?php // Label Français ?>
                        <th scope="col"><?= $this->Paginator->sort('user_id', 'Utilisateur') ?></th> <?php // Label Français ?>
                        <th scope="col"><?= $this->Paginator->sort('offer_id', 'Offre/Compétence') ?></th> <?php // Label Français ?>
                        <th scope="col"><?= $this->Paginator->sort('validity_start', 'Début Validité') ?></th> <?php // Label Français ?>
                        <th scope="col"><?= $this->Paginator->sort('validity_end', 'Fin Validité') ?></th> <?php // Label Français ?>
                        <th scope="col"><?= $this->Paginator->sort('created', 'Créé le') ?></th> <?php // Label Français ?>
                        <th scope="col" class="actions"><?= __('Actions') ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (count($skills) === 0): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="empty-state">
                                    <i class="bi bi-award" style="font-size: 4rem; color: #dee2e6;"></i>
                                    <h4 class="mt-3 text-muted">Aucune compétence trouvée</h4>
                                    <p class="text-muted">
                                        <?php if ($this->request->getQuery()): ?>
                                            Aucune compétence ne correspond aux critères de recherche.
                                        <?php else: ?>
                                            Commencez par créer votre première compétence.
                                        <?php endif; ?>
                                    </p>
                                    <?php if (!$this->request->getQuery()): ?>
                                        <?= $this->Html->link(
                                            '<i class="bi bi-plus-circle mr-2"></i> Créer la première compétence',
                                            ['action' => 'add'],
                                            ['class' => 'btn btn-primary mt-2', 'escape' => false]
                                        ) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($skills as $skill): ?>
                        <?php
                        $isExpired = $skill->validity_end && $skill->validity_end < new \Cake\I18n\FrozenDate();
                        $statusBadge = $isExpired ? 'badge-danger' : 'badge-success';
                        $statusIcon = $isExpired ? 'bi-x-circle' : 'bi-check-circle';
                        $statusLabel = $isExpired ? 'Expirée' : 'Active';
                        ?>
                        <tr>
                            <td><span class="badge badge-secondary"><?= $this->Number->format($skill->id) ?></span></td>
                            <td>
                                <?php if ($skill->hasValue('user')): ?>
                                    <strong><?= h($skill->user->last_name . ' ' . $skill->user->first_name) ?></strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($skill->hasValue('offer')): ?>
                                    <span class="badge badge-info">
                                        <i class="bi bi-tag"></i> <?= h($skill->offer->name) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><span class="d-inline-flex align-items-center"><i class="bi bi-calendar-event mr-1"></i> <?= h($skill->validity_start ? $skill->validity_start->i18nFormat('dd/MM/yyyy') : '—') ?></span></td>
                            <td>
                                <span class="d-inline-flex align-items-center">
                                    <i class="bi bi-calendar-x mr-1"></i> <?= h($skill->validity_end ? $skill->validity_end->i18nFormat('dd/MM/yyyy') : '—') ?>
                                </span>
                                <span class="badge <?= $statusBadge ?> ml-1" style="font-size: 0.7rem;">
                                    <i class="bi <?= $statusIcon ?>"></i> <?= $statusLabel ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($skill->created): 
                                    $now = new \Cake\I18n\FrozenTime();
                                    $diff = $now->diffInDays($skill->created);
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
                                    <span data-toggle="tooltip" title="<?= h($skill->created->i18nFormat('dd/MM/yyyy HH:mm')) ?>">
                                        <?= h($timeAgo) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <div class="dropdown actions-dropdown" data-entity-id="<?= (int)$skill->id ?>">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownActions<?= $skill->id ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="bi bi-three-dots-vertical"></i> Actions
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right actions-dropdown-menu" data-entity-id="<?= (int)$skill->id ?>" aria-labelledby="dropdownActions<?= $skill->id ?>">
                                        <?= $this->Html->link(
                                            '<i class="bi bi-eye mr-2"></i> Voir',
                                            ['action' => 'view', $skill->id],
                                            ['class' => 'dropdown-item', 'escape' => false]
                                        ) ?>
                                        <?= $this->Html->link(
                                            '<i class="bi bi-pencil mr-2"></i> Modifier',
                                            ['action' => 'edit', $skill->id],
                                            ['class' => 'dropdown-item', 'escape' => false]
                                        ) ?>
                                        <div class="dropdown-divider"></div>
                                        <?= $this->Form->postLink(
                                            '<i class="bi bi-trash mr-2"></i> Supprimer',
                                            ['action' => 'delete', $skill->id],
                                            [
                                                'confirm' => 'Voulez-vous vraiment supprimer cette compétence ?',
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

        </div> <?php // Fin card-body ?>
    </div> <?php // Fin content card ?>
