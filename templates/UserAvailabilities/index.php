<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\UserAvailability[]|\Cake\Collection\CollectionInterface $userAvailabilities
 * @var array $roles
 * @var array $sites
 */
if (!function_exists('getRoleBadgeClass')) {
    /**
     * @param int $roleId
     * @return string
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

        return $badgeClasses[($roleId - 1) % count($badgeClasses)];
    }
}
?>
<?php $this->assign('title', 'Disponibilités des Agents'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php $this->Html->script('crud-filters', ['block' => true, 'timestamp' => 'force']); ?>
<?php $this->Html->script('user-availabilities-filters', ['block' => true]); ?>

<div class="crud-app user-availabilities index content">
    <div class="crud-header">
        <div>
            <h1>
                <i class="bi bi-clock"></i>
                Disponibilités des Agents
            </h1>
            <p class="crud-header-meta"><?= $this->Paginator->counter('{{count}} disponibilités') ?></p>
        </div>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle me-1"></i> Nouvelle Disponibilité',
                ['action' => 'add'],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-list-ul"></i> Raccourcis
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?= $this->Html->link(
                        '<i class="bi bi-people-fill me-2"></i> Liste des Utilisateurs',
                        ['controller' => 'Users', 'action' => 'index'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                </div>
            </div>
        </div>
    </div>

    <?= $this->Form->create(null, ['type' => 'get', 'class' => 'filters-toolbar mb-3']) ?>
        <div class="d-flex flex-wrap align-items-end gap-2">
            <div class="flex-grow-1" style="min-width: 8rem;">
                <label for="search-name" class="form-label small text-muted mb-1">Nom</label>
                <?= $this->Form->text('search_name', [
                    'class' => 'form-control form-control-sm',
                    'placeholder' => 'Rechercher par nom...',
                    'value' => $this->request->getQuery('search_name'),
                    'id' => 'search-name',
                    'autocomplete' => 'off',
                ]) ?>
            </div>
            <div class="flex-grow-1" style="min-width: 8rem;">
                <label for="search-firstname" class="form-label small text-muted mb-1">Prénom</label>
                <?= $this->Form->text('search_firstname', [
                    'class' => 'form-control form-control-sm',
                    'placeholder' => 'Rechercher par prénom...',
                    'value' => $this->request->getQuery('search_firstname'),
                    'id' => 'search-firstname',
                    'autocomplete' => 'off',
                ]) ?>
            </div>
            <div style="min-width: 8rem;">
                <label for="role-id" class="form-label small text-muted mb-1">Rôle</label>
                <?= $this->Form->select('role_id', $roles, [
                    'empty' => 'Tous les rôles',
                    'class' => 'form-control form-control-sm',
                    'value' => $this->request->getQuery('role_id'),
                    'id' => 'role-id',
                ]) ?>
            </div>
            <div style="min-width: 8rem;">
                <label for="site-id" class="form-label small text-muted mb-1">Site</label>
                <?= $this->Form->select('site_id', $sites, [
                    'empty' => 'Tous les sites',
                    'class' => 'form-control form-control-sm',
                    'value' => $this->request->getQuery('site_id'),
                    'id' => 'site-id',
                ]) ?>
            </div>
            <div style="min-width: 8rem;">
                <label for="day-of-week" class="form-label small text-muted mb-1">Jour</label>
                <?= $this->Form->select('day_of_week', [
                    1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi',
                    5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche',
                ], [
                    'empty' => 'Tous les jours',
                    'class' => 'form-control form-control-sm',
                    'id' => 'day-of-week',
                    'value' => $this->request->getQuery('day_of_week'),
                ]) ?>
            </div>
            <div>
                <label class="form-label small mb-1 d-block" aria-hidden="true">&nbsp;</label>
                <div class="d-flex gap-2">
                    <?= $this->Form->button('Filtrer', [
                        'type' => 'submit',
                        'class' => 'btn btn-sm btn-primary',
                    ]) ?>
                    <?= $this->Html->link(
                        'Réinitialiser',
                        ['action' => 'index'],
                        ['class' => 'btn btn-sm btn-outline-secondary']
                    ) ?>
                </div>
            </div>
        </div>
    <?= $this->Form->end() ?>

    <div class="table-responsive">
        <table class="table table-hover table-sm crud-table">
            <?php
            $columns = ['Site', 'Rôle', 'Code', 'Nom', 'Prénom', 'Jour', 'Début Dispo', 'Fin Dispo', 'Fin la plus tôt', 'Actions'];
            $colCount = count($columns);
            ?>
            <thead>
            <tr>
                <th scope="col"><?= $this->Paginator->sort('Users.site_id', $columns[0]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('Users.role_id', $columns[1]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('Users.user_code', $columns[2]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('Users.last_name', $columns[3]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('Users.first_name', $columns[4]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('day_of_week', $columns[5]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('availability_start_time', $columns[6]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('availability_end_time', $columns[7]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('earliest_end_time', $columns[8]) ?></th>
                <th scope="col" class="actions"><?= h($columns[9]) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($userAvailabilities) === 0): ?>
                <tr>
                    <td colspan="<?= (int)$colCount ?>" class="crud-empty">
                        <p>
                            <?php if ($this->request->getQuery()): ?>
                                Aucune disponibilité ne correspond aux critères de recherche.
                            <?php else: ?>
                                Aucune disponibilité.
                            <?php endif; ?>
                        </p>
                        <?php if (!$this->request->getQuery()): ?>
                            <?= $this->Html->link(
                                '<i class="bi bi-plus-circle me-1"></i> Créer une disponibilité',
                                ['action' => 'add'],
                                ['class' => 'btn btn-sm btn-primary', 'escape' => false]
                            ) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($userAvailabilities as $userAvailability): ?>
                <?php
                $user = $userAvailability->user ?? null;
                ?>
                <tr>
                    <td><?= $user && $user->hasValue('site') ? h($user->site->name) : '—' ?></td>
                    <td>
                        <?php if ($user && $user->hasValue('role')): ?>
                            <span class="badge <?= getRoleBadgeClass((int)$user->role->id) ?>">
                                <?= h($user->role->name) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td><?= $user ? h($user->user_code) : '—' ?></td>
                    <td>
                        <?= $this->Html->link(
                            $user ? $user->last_name : ('#' . $userAvailability->id),
                            ['action' => 'view', $userAvailability->id],
                            ['class' => 'crud-row-link']
                        ) ?>
                    </td>
                    <td><?= $user ? h($user->first_name) : '—' ?></td>
                    <td><?= $this->DayOfWeek->format($userAvailability->day_of_week) ?></td>
                    <td><?= h($userAvailability->availability_start_time) ?></td>
                    <td><?= h($userAvailability->availability_end_time) ?></td>
                    <td><?= h($userAvailability->earliest_end_time ?: '—') ?></td>
                    <td class="actions">
                        <?= $this->Html->link(
                            '<i class="bi bi-pencil" aria-hidden="true"></i>',
                            ['action' => 'edit', $userAvailability->id],
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
                            ['action' => 'delete', $userAvailability->id],
                            [
                                'confirm' => 'Voulez-vous vraiment supprimer cette disponibilité ?',
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
