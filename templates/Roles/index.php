<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Role> $roles
 */
?>
<?php $this->assign('title', 'Liste des Rôles'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<style>
.roles .table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.08) !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.2s ease;
}
</style>

<div class="roles index content card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-shield-lock text-primary"></i>
            Liste des Rôles
        </h3>
        <div class="btn-toolbar">
            <div class="btn-group mr-2">
                <?= $this->Html->link(
                    '<i class="bi bi-shield-plus mr-1"></i> Nouveau Rôle',
                    ['action' => 'add'],
                    ['class' => 'btn btn-success', 'escape' => false]
                ) ?>
            </div>
            <div class="btn-group">
                <?= $this->Html->link(
                    '<i class="bi bi-people-fill mr-1"></i> Utilisateurs',
                    ['controller' => 'Users', 'action' => 'index'],
                    ['class' => 'btn btn-outline-secondary', 'escape' => false]
                ) ?>
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php // --- Cards de statistiques --- ?>
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-shield-lock text-primary" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['total'] ?></h3>
                        <small class="text-muted">Total</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-people text-success" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['total_users'] ?></h3>
                        <small class="text-muted">Utilisateurs</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-person-check text-info" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['with_users'] ?></h3>
                        <small class="text-muted">Rôles attribués</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-secondary">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-person-x text-secondary" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['without_users'] ?></h3>
                        <small class="text-muted">Rôles libres</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm">
                <thead>
                <tr>
                    <th scope="col"><?= $this->Paginator->sort('priority', 'Priorité') ?></th>
                    <th scope="col"><?= $this->Paginator->sort('name', 'Nom du Rôle') ?></th>
                    <th scope="col">Utilisateurs</th>
                    <th scope="col"><?= $this->Paginator->sort('created', 'Créé le') ?></th>
                    <th scope="col"><?= $this->Paginator->sort('modified', 'Modifié le') ?></th>
                    <th scope="col" class="actions">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (count($roles) === 0): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <div class="empty-state">
                                <i class="bi bi-shield-lock" style="font-size: 4rem; color: #dee2e6;"></i>
                                <h4 class="mt-3 text-muted">Aucun rôle trouvé</h4>
                                <p class="text-muted">Commencez par créer votre premier rôle.</p>
                                <?= $this->Html->link(
                                    '<i class="bi bi-shield-plus mr-2"></i> Créer le premier rôle',
                                    ['action' => 'add'],
                                    ['class' => 'btn btn-primary mt-2', 'escape' => false]
                                ) ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($roles as $role) : ?>
                    <tr>
                        <td>
                            <span class="badge badge-primary"><?= $this->Number->format($role->priority) ?></span>
                        </td>
                        <td><strong><?= h($role->name) ?></strong></td>
                        <td>
                            <?php if (isset($role->users) && count($role->users) > 0): ?>
                                <span class="badge badge-success">
                                    <i class="bi bi-people"></i> <?= count($role->users) ?> utilisateur<?= count($role->users) > 1 ? 's' : '' ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">
                                    <i class="bi bi-dash-circle"></i> Aucun
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($role->created): 
                                $now = new \Cake\I18n\FrozenTime();
                                $diff = $now->diffInDays($role->created);
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
                                <span data-toggle="tooltip" title="<?= h($role->created->i18nFormat('dd/MM/yyyy HH:mm')) ?>">
                                    <?= h($timeAgo) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($role->modified): 
                                $now = new \Cake\I18n\FrozenTime();
                                $diff = $now->diffInDays($role->modified);
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
                                <span data-toggle="tooltip" title="<?= h($role->modified->i18nFormat('dd/MM/yyyy HH:mm')) ?>">
                                    <?= h($timeAgo) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <div class="dropdown actions-dropdown" data-entity-id="<?= (int)$role->id ?>">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownActions<?= $role->id ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="bi bi-three-dots-vertical"></i> Actions
                                </button>
                                <div class="dropdown-menu dropdown-menu-right actions-dropdown-menu" data-entity-id="<?= (int)$role->id ?>" aria-labelledby="dropdownActions<?= $role->id ?>">
                                    <?= $this->Html->link(
                                        '<i class="bi bi-eye mr-2"></i> Voir',
                                        ['action' => 'view', $role->id],
                                        ['class' => 'dropdown-item', 'escape' => false]
                                    ) ?>
                                    <?= $this->Html->link(
                                        '<i class="bi bi-pencil mr-2"></i> Modifier',
                                        ['action' => 'edit', $role->id],
                                        ['class' => 'dropdown-item', 'escape' => false]
                                    ) ?>
                                    <div class="dropdown-divider"></div>
                                    <?= $this->Form->postLink(
                                        '<i class="bi bi-trash mr-2"></i> Supprimer',
                                        ['action' => 'delete', $role->id],
                                        [
                                            'confirm' => 'Voulez-vous vraiment supprimer le rôle "' . h($role->name) . '" ?',
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


