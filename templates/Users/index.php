<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User[]|\Cake\Collection\CollectionInterface $users
 */

/**
 * Retourne la classe de badge selon l'ID du rôle
 * 
 * @param int $roleId ID du rôle
 * @return string Classe de badge Bootstrap
 */
function getRoleBadgeClass($roleId) {
    $badgeClasses = [
        'badge-primary',    // Bleu
        'badge-success',    // Vert
        'badge-warning',    // Jaune/Orange
        'badge-danger',     // Rouge
        'badge-info',       // Cyan
        'badge-secondary',  // Gris
        'badge-dark',       // Sombre
    ];
    
    // Utilise l'ID du rôle avec modulo pour avoir une couleur cohérente
    $index = ($roleId - 1) % count($badgeClasses);
    return $badgeClasses[$index];
}
?>
<?php $this->assign('title', 'Liste des Utilisateurs'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php $this->Html->script('users-filters', ['block' => true]); ?>

<style>
.users .table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.08) !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.2s ease;
}
</style>

<div class="users index content card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-people text-primary"></i>
            Liste des Utilisateurs
        </h3>
        <div class="btn-toolbar" role="toolbar">
            <div class="btn-group mr-2" role="group">
                <?= $this->Html->link(
                    '<i class="bi bi-person-plus-fill mr-1"></i> Nouvel Utilisateur',
                    ['action' => 'add'],
                    ['class' => 'btn btn-success', 'escape' => false]
                ) ?>
            </div>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bi bi-list-ul"></i> Raccourcis
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                    <?= $this->Html->link(
                        '<i class="bi bi-shield-lock-fill mr-2"></i> Liste des Rôles',
                        ['controller' => 'Roles', 'action' => 'index'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                    <?= $this->Html->link(
                        '<i class="bi bi-shield-plus mr-2"></i> Nouveau Rôle',
                        ['controller' => 'Roles', 'action' => 'add'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                    <div class="dropdown-divider"></div>
                    <?= $this->Html->link(
                        '<i class="bi bi-geo-alt-fill mr-2"></i> Liste des Sites',
                        ['controller' => 'Sites', 'action' => 'index'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                    <?= $this->Html->link(
                        '<i class="bi bi-plus-circle mr-2"></i> Nouveau Site',
                        ['controller' => 'Sites', 'action' => 'add'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php // --- Cards de statistiques --- ?>
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card border-primary">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-people text-primary" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['total'] ?></h3>
                        <small class="text-muted">Total</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-success">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-person-check text-success" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['active'] ?></h3>
                        <small class="text-muted">Actifs</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-secondary">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-person-x text-secondary" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['inactive'] ?></h3>
                        <small class="text-muted">Inactifs</small>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($stats['roles'])): ?>
        <div class="row mb-4">
            <?php foreach ($stats['roles'] as $roleId => $roleStat): ?>
            <div class="col-md-4">
                <div class="card border-info">
                    <div class="card-body text-center py-2">
                        <i class="bi bi-shield-lock text-info" style="font-size: 1.5rem;"></i>
                        <h4 class="mb-0 mt-1"><?= $roleStat['count'] ?></h4>
                        <small class="text-muted"><?= h($roleStat['name']) ?></small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php // --- Toolbar de filtrage --- ?>
        <?= $this->Form->create(null, ['type' => 'get', 'class' => 'filters-toolbar mb-4 p-3 bg-light border rounded']) ?>
            <div class="row">
                <div class="col-md-3 mb-2">
                    <label for="search-name" class="form-label small text-muted mb-1">
                        <i class="bi bi-search"></i> Nom
                    </label>
                    <?= $this->Form->text('search_name', [
                        'class' => 'form-control form-control-sm',
                        'placeholder' => 'Rechercher par nom...',
                        'value' => $filters['search_name'] ?? ''
                    ]) ?>
                </div>
                <div class="col-md-3 mb-2">
                    <label for="search-firstname" class="form-label small text-muted mb-1">
                        <i class="bi bi-search"></i> Prénom
                    </label>
                    <?= $this->Form->text('search_firstname', [
                        'class' => 'form-control form-control-sm',
                        'placeholder' => 'Rechercher par prénom...',
                        'value' => $filters['search_firstname'] ?? ''
                    ]) ?>
                </div>
                <div class="col-md-2 mb-2">
                    <label for="role-id" class="form-label small text-muted mb-1">
                        <i class="bi bi-shield-lock"></i> Rôle
                    </label>
                    <?= $this->Form->select('role_id', $roles, [
                        'empty' => 'Tous les rôles',
                        'class' => 'form-control form-control-sm',
                        'value' => $filters['role_id'] ?? ''
                    ]) ?>
                </div>
                <div class="col-md-2 mb-2">
                    <label for="site-id" class="form-label small text-muted mb-1">
                        <i class="bi bi-geo-alt"></i> Site
                    </label>
                    <?= $this->Form->select('site_id', $sites, [
                        'empty' => 'Tous les sites',
                        'class' => 'form-control form-control-sm',
                        'value' => $filters['site_id'] ?? ''
                    ]) ?>
                </div>
                <div class="col-md-2 mb-2 d-flex flex-column align-items-stretch">
                    <?= $this->Form->button('<i class="bi bi-search"></i> Filtrer', [
                        'type' => 'submit',
                        'class' => 'btn btn-sm btn-primary mb-1',
                        'escapeTitle' => false
                    ]) ?>
                    <?= $this->Html->link('<i class="bi bi-arrow-counterclockwise"></i> Réinitialiser', 
                        ['action' => 'index', '?' => ['reset' => '1']], 
                        ['class' => 'btn btn-sm btn-outline-secondary', 'escape' => false]
                    ) ?>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-12">
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i> 
                        <?= $this->Paginator->counter('{{count}} utilisateur(s) au total, affichant {{current}} sur cette page') ?>
                    </small>
                </div>
            </div>
        <?= $this->Form->end() ?>
        
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm">
                <thead>
                <tr>
                    <th scope="col"><?= $this->Paginator->sort('id', 'ID') ?></th>
                    <th scope="col"><?= $this->Paginator->sort('user_code', 'Code') ?></th>
                    <th scope="col"><?= $this->Paginator->sort('last_name', 'Nom') ?></th>
                    <th scope="col"><?= $this->Paginator->sort('first_name', 'Prénom') ?></th>
                    <th scope="col"><?= $this->Paginator->sort('role_id', 'Rôle') ?></th>
                    <th scope="col"><?= $this->Paginator->sort('site_id', 'Site') ?></th>
                    <th scope="col"><?= $this->Paginator->sort('email', 'Email') ?></th>
                    <th scope="col"><?= $this->Paginator->sort('modified', 'Modifié le') ?></th>
                    <th scope="col" class="actions"><?= 'Actions' ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (count($users) === 0): ?>
                    <?php 
                    $hasActiveFilters = !empty($filters['search_name']) || 
                                        !empty($filters['search_firstname']) || 
                                        !empty($filters['role_id']) || 
                                        !empty($filters['site_id']);
                    ?>
                    <tr>
                        <td colspan="9" class="text-center py-5">
                            <div class="empty-state">
                                <i class="bi bi-people" style="font-size: 4rem; color: #dee2e6;"></i>
                                <h4 class="mt-3 text-muted">Aucun utilisateur trouvé</h4>
                                <p class="text-muted">
                                    <?php if ($hasActiveFilters): ?>
                                        Aucun utilisateur ne correspond aux critères de recherche.
                                    <?php else: ?>
                                        Commencez par créer votre premier utilisateur.
                                    <?php endif; ?>
                                </p>
                                <?php if (!$hasActiveFilters): ?>
                                    <?= $this->Html->link(
                                        '<i class="bi bi-person-plus-fill mr-2"></i> Créer le premier utilisateur',
                                        ['action' => 'add'],
                                        ['class' => 'btn btn-primary mt-2', 'escape' => false]
                                    ) ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($users as $user) : ?>
                    <tr>
                        <td><?= $this->Number->format($user->id) ?></td>
                        <td><span class="badge badge-secondary"><?= h($user->user_code) ?></span></td>
                        <td><strong><?= h($user->last_name) ?></strong></td>
                        <td><?= h($user->first_name) ?></td>
                        <td>
                            <?php if ($user->hasValue('role')): ?>
                                <span class="badge <?= getRoleBadgeClass($user->role->id) ?>">
                                    <i class="bi bi-shield-lock"></i> <?= h($user->role->name) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user->hasValue('site')): ?>
                                <span class="text-muted">
                                    <i class="bi bi-geo-alt"></i> <?= h($user->site->name) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?= h($user->email) ?></td>
                        <td>
                            <?php if ($user->modified): 
                                $now = new \Cake\I18n\FrozenTime();
                                $diff = $now->diffInDays($user->modified);
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
                                <span data-toggle="tooltip" title="<?= h($user->modified->i18nFormat('dd/MM/yyyy HH:mm')) ?>">
                                    <?= h($timeAgo) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <div class="dropdown actions-dropdown" data-entity-id="<?= (int)$user->id ?>">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownActions<?= $user->id ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="bi bi-three-dots-vertical"></i> Actions
                                </button>
                                <div class="dropdown-menu dropdown-menu-right actions-dropdown-menu" data-entity-id="<?= (int)$user->id ?>" aria-labelledby="dropdownActions<?= $user->id ?>">
                                    <?= $this->Html->link(
                                        '<i class="bi bi-eye mr-2"></i> Voir',
                                        ['action' => 'view', $user->id],
                                        ['class' => 'dropdown-item', 'escape' => false]
                                    ) ?>
                                    <?= $this->Html->link(
                                        '<i class="bi bi-pencil mr-2"></i> Modifier',
                                        ['action' => 'edit', $user->id],
                                        ['class' => 'dropdown-item', 'escape' => false]
                                    ) ?>
                                    <div class="dropdown-divider"></div>
                                    <?= $this->Form->postLink(
                                        '<i class="bi bi-trash mr-2"></i> Supprimer',
                                        ['action' => 'delete', $user->id],
                                        [
                                            'confirm' => 'Voulez-vous vraiment supprimer ' . h($user->first_name . ' ' . $user->last_name) . ' ?',
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

