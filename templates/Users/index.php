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
        'bg-primary',
        'bg-success',
        'bg-warning',
        'bg-danger',
        'bg-info',
        'bg-secondary',
        'bg-dark',
    ];

    $index = ($roleId - 1) % count($badgeClasses);
    return $badgeClasses[$index];
}
?>
<?php $this->assign('title', 'Liste des Utilisateurs'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php $this->Html->script('users-filters', ['block' => true]); ?>

<div class="crud-app users index content">
    <div class="crud-header">
        <div>
            <h1>
                <i class="bi bi-people"></i>
                Utilisateurs
            </h1>
            <p class="crud-header-meta"><?= $this->Paginator->counter('{{count}} utilisateurs') ?></p>
        </div>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-person-plus-fill me-1"></i> Nouvel Utilisateur',
                ['action' => 'add'],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bi bi-list-ul"></i> Raccourcis
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?= $this->Html->link(
                        '<i class="bi bi-shield-lock-fill me-2"></i> Liste des Rôles',
                        ['controller' => 'Roles', 'action' => 'index'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                    <?= $this->Html->link(
                        '<i class="bi bi-shield-plus me-2"></i> Nouveau Rôle',
                        ['controller' => 'Roles', 'action' => 'add'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                    <div class="dropdown-divider"></div>
                    <?= $this->Html->link(
                        '<i class="bi bi-geo-alt-fill me-2"></i> Liste des Sites',
                        ['controller' => 'Sites', 'action' => 'index'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                    <?= $this->Html->link(
                        '<i class="bi bi-plus-circle me-2"></i> Nouveau Site',
                        ['controller' => 'Sites', 'action' => 'add'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                </div>
            </div>
        </div>
    </div>

    <?= $this->Form->create(null, ['type' => 'get', 'class' => 'filters-toolbar mb-3']) ?>
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label for="search-name" class="form-label small text-muted mb-1">Nom</label>
                <?= $this->Form->text('search_name', [
                    'class' => 'form-control form-control-sm',
                    'placeholder' => 'Rechercher par nom...',
                    'value' => $filters['search_name'] ?? '',
                    'id' => 'search-name',
                ]) ?>
            </div>
            <div class="col-md-3">
                <label for="search-firstname" class="form-label small text-muted mb-1">Prénom</label>
                <?= $this->Form->text('search_firstname', [
                    'class' => 'form-control form-control-sm',
                    'placeholder' => 'Rechercher par prénom...',
                    'value' => $filters['search_firstname'] ?? '',
                    'id' => 'search-firstname',
                ]) ?>
            </div>
            <div class="col-md-2">
                <label for="role-id" class="form-label small text-muted mb-1">Rôle</label>
                <?= $this->Form->select('role_id', $roles, [
                    'empty' => 'Tous les rôles',
                    'class' => 'form-control form-control-sm',
                    'value' => $filters['role_id'] ?? '',
                    'id' => 'role-id',
                ]) ?>
            </div>
            <div class="col-md-2">
                <label for="site-id" class="form-label small text-muted mb-1">Site</label>
                <?= $this->Form->select('site_id', $sites, [
                    'empty' => 'Tous les sites',
                    'class' => 'form-control form-control-sm',
                    'value' => $filters['site_id'] ?? '',
                    'id' => 'site-id',
                ]) ?>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <?= $this->Form->button('Filtrer', [
                    'type' => 'submit',
                    'class' => 'btn btn-sm btn-primary',
                ]) ?>
                <?= $this->Html->link(
                    'Réinitialiser',
                    ['action' => 'index', '?' => ['reset' => '1']],
                    ['class' => 'btn btn-sm btn-outline-secondary']
                ) ?>
            </div>
        </div>
    <?= $this->Form->end() ?>

    <div class="table-responsive">
        <table class="table table-hover table-sm crud-table">
            <?php
            $columns = ['Nom', 'Code', 'Prénom', 'Rôle', 'Site', 'Email', 'Modifié le', 'Actions'];
            $colCount = count($columns);
            $hasActiveFilters = !empty($filters['search_name']) ||
                !empty($filters['search_firstname']) ||
                !empty($filters['role_id']) ||
                !empty($filters['site_id']);
            ?>
            <thead>
            <tr>
                <th scope="col"><?= $this->Paginator->sort('last_name', $columns[0]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('user_code', $columns[1]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('first_name', $columns[2]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('role_id', $columns[3]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('site_id', $columns[4]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('email', $columns[5]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('modified', $columns[6]) ?></th>
                <th scope="col" class="actions"><?= h($columns[7]) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($users) === 0): ?>
                <tr>
                    <td colspan="<?= (int)$colCount ?>" class="crud-empty">
                        <p><?= $hasActiveFilters ? 'Aucun utilisateur ne correspond aux critères de recherche.' : 'Aucun utilisateur.' ?></p>
                        <?php if (!$hasActiveFilters): ?>
                            <?= $this->Html->link(
                                '<i class="bi bi-person-plus-fill me-1"></i> Créer un utilisateur',
                                ['action' => 'add'],
                                ['class' => 'btn btn-sm btn-primary', 'escape' => false]
                            ) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($users as $user) : ?>
                <tr>
                    <td>
                        <?= $this->Html->link(
                            $user->last_name,
                            ['action' => 'view', $user->id],
                            ['class' => 'crud-row-link']
                        ) ?>
                    </td>
                    <td><?= h($user->user_code) ?></td>
                    <td><?= h($user->first_name) ?></td>
                    <td>
                        <?php if ($user->hasValue('role')): ?>
                            <span class="badge <?= getRoleBadgeClass($user->role->id) ?>">
                                <?= h($user->role->name) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td><?= $user->hasValue('site') ? h($user->site->name) : '—' ?></td>
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
                            <span data-bs-toggle="tooltip" title="<?= h($user->modified->i18nFormat('dd/MM/yyyy HH:mm')) ?>">
                                <?= h($timeAgo) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <div class="dropdown actions-dropdown" data-entity-id="<?= (int)$user->id ?>">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownActions<?= $user->id ?>" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="bi bi-three-dots-vertical"></i> Actions
                            </button>
                            <div class="dropdown-menu dropdown-menu-end actions-dropdown-menu" data-entity-id="<?= (int)$user->id ?>" aria-labelledby="dropdownActions<?= $user->id ?>">
                                <?= $this->Html->link(
                                    '<i class="bi bi-eye me-2"></i> Voir',
                                    ['action' => 'view', $user->id],
                                    ['class' => 'dropdown-item', 'escape' => false]
                                ) ?>
                                <?= $this->Html->link(
                                    '<i class="bi bi-pencil me-2"></i> Modifier',
                                    ['action' => 'edit', $user->id],
                                    ['class' => 'dropdown-item', 'escape' => false]
                                ) ?>
                                <div class="dropdown-divider"></div>
                                <?= $this->Form->postLink(
                                    '<i class="bi bi-trash me-2"></i> Supprimer',
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
