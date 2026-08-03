<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 */
?>
<?php $this->assign('title', 'Détails Utilisateur : ' . h($user->full_name)); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-person-circle text-primary"></i>
            <?= h($user->full_name) ?>
            <?php if ($user->hasValue('role')): ?>
                <span class="badge badge-info ml-2">
                    <i class="bi bi-shield-lock"></i> <?= h($user->role->name) ?>
                </span>
            <?php endif; ?>
        </h3>
        <div class="btn-toolbar">
            <?= $this->Html->link(
                '<i class="bi bi-pencil mr-1"></i> Modifier',
                ['action' => 'edit', $user->id],
                ['class' => 'btn btn-primary mr-2', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash mr-1"></i> Supprimer',
                ['action' => 'delete', $user->id],
                ['confirm' => 'Voulez-vous vraiment supprimer ' . h($user->full_name) . ' ?', 'class' => 'btn btn-danger mr-2', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list mr-1"></i> Liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <?php // --- Informations générales --- ?>
            <div class="col-md-6 mb-4">
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-info-circle"></i> Informations générales
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="text-muted small mb-1"><i class="bi bi-shield-lock"></i> Rôle</label>
                            <div>
                                <?php if ($user->hasValue('role')): ?>
                                    <span class="badge badge-info">
                                        <i class="bi bi-shield-lock"></i> <?= h($user->role->name) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">Non défini</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small mb-1"><i class="bi bi-person-badge"></i> Code Utilisateur</label>
                            <div><span class="badge badge-secondary" style="font-size: 1rem;"><?= h($user->user_code) ?></span></div>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small mb-1"><i class="bi bi-person"></i> Nom</label>
                            <div><strong><?= h($user->last_name) ?></strong></div>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small mb-1"><i class="bi bi-person"></i> Prénom</label>
                            <div><strong><?= h($user->first_name) ?></strong></div>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small mb-1"><i class="bi bi-geo-alt"></i> Site</label>
                            <div>
                                <?php if ($user->hasValue('site')): ?>
                                    <span class="badge badge-info">
                                        <i class="bi bi-building"></i> <?= h($user->site->name) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">Non défini</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <label class="text-muted small mb-1"><i class="bi bi-envelope"></i> Email</label>
                            <div>
                                <a href="mailto:<?= h($user->email) ?>"><?= h($user->email) ?></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php // --- Métadonnées --- ?>
            <div class="col-md-6 mb-4">
                <div class="card border-secondary">
                    <div class="card-header bg-secondary text-white">
                        <i class="bi bi-clock-history"></i> Métadonnées
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="text-muted small mb-1"><i class="bi bi-calendar-plus"></i> Créé le</label>
                            <div><?= h($user->created ? $user->created->i18nFormat('dd/MM/yyyy HH:mm') : 'N/A') ?></div>
                        </div>
                        <div>
                            <label class="text-muted small mb-1"><i class="bi bi-calendar-check"></i> Modifié le</label>
                            <div><?= h($user->modified ? $user->modified->i18nFormat('dd/MM/yyyy HH:mm') : 'N/A') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php // --- Section Contrats --- ?>
        <div class="card border-info mb-4">
            <div class="card-header bg-info text-white">
                <i class="bi bi-file-earmark-text"></i> Contrats
            </div>
            <div class="card-body">
                <?php if (!empty($user->user_contracts)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm mb-0">
                            <thead>
                            <tr>
                                <th scope="col"><i class="bi bi-calendar-event"></i> Date début</th>
                                <th scope="col"><i class="bi bi-calendar-x"></i> Date fin</th>
                                <th scope="col">Statut</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($user->user_contracts as $contract): ?>
                                <tr>
                                    <td>
                                        <?= h($contract->start_date instanceof \DateTimeInterface ? $contract->start_date->i18nFormat('dd/MM/yyyy') : $contract->start_date) ?>
                                    </td>
                                    <td>
                                        <?php if ($contract->end_date): ?>
                                            <?= h($contract->end_date instanceof \DateTimeInterface ? $contract->end_date->i18nFormat('dd/MM/yyyy') : $contract->end_date) ?>
                                        <?php else: ?>
                                            <span class="text-muted">Indéterminée (CDI)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $todayStr = date('Y-m-d');
                                        
                                        // Extraire les dates du contrat en format string Y-m-d
                                        $contractStartStr = null;
                                        $contractEndStr = null;
                                        
                                        if ($contract->start_date && is_object($contract->start_date) && method_exists($contract->start_date, 'format')) {
                                            $contractStartStr = $contract->start_date->format('Y-m-d');
                                        }
                                        
                                        if ($contract->end_date && is_object($contract->end_date) && method_exists($contract->end_date, 'format')) {
                                            $contractEndStr = $contract->end_date->format('Y-m-d');
                                        }
                                        
                                        $isActive = false;
                                        if ($contractStartStr) {
                                            $startMatch = $contractStartStr <= $todayStr;
                                            $endMatch = $contractEndStr === null || $contractEndStr >= $todayStr;
                                            $isActive = $startMatch && $endMatch;
                                        }
                                        ?>
                                        <?php if ($isActive): ?>
                                            <span class="badge bg-success">Actif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Terminé</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">
                        <i class="bi bi-info-circle"></i> Aucun contrat défini.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <?php // --- Section Compétences --- ?>
        <div class="card border-success mb-4">
            <div class="card-header bg-success text-white">
                <i class="bi bi-award"></i> Compétences (Offres)
            </div>
            <div class="card-body">
                <?php if (!empty($user->skills)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm mb-0">
                            <thead>
                            <tr>
                                <th scope="col"><i class="bi bi-tag"></i> Offre</th>
                                <th scope="col"><i class="bi bi-calendar-event"></i> Validité Début</th>
                                <th scope="col"><i class="bi bi-calendar-x"></i> Validité Fin</th>
                                <th scope="col" class="actions">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($user->skills as $skill): ?>
                                <tr>
                                    <td>
                                        <strong><?= h($skill->offer->name ?? 'N/A') ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($skill->validity_start): ?>
                                            <small><i class="bi bi-calendar"></i> <?= h($skill->validity_start->i18nFormat('dd/MM/yyyy')) ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">—</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($skill->validity_end): ?>
                                            <small><i class="bi bi-calendar"></i> <?= h($skill->validity_end->i18nFormat('dd/MM/yyyy')) ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">—</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions">
                                        <?= $this->Html->link(
                                            '<i class="bi bi-eye"></i>',
                                            ['controller' => 'Skills', 'action' => 'view', $skill->id],
                                            ['class' => 'btn btn-sm btn-outline-primary', 'escape' => false, 'title' => 'Voir']
                                        ) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">
                        <i class="bi bi-info-circle"></i> Aucune compétence définie.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <?= $this->element('users/contractual_availabilities_readonly', ['user' => $user]) ?>

        <?= $this->element('users/remote_work_readonly', ['user' => $user]) ?>

        <?php // --- Section Règle de rotation --- ?>
        <div class="card border-warning mb-4">
            <div class="card-header bg-warning text-dark">
                <i class="bi bi-arrow-repeat"></i> Règle de rotation
            </div>
            <div class="card-body">
                <?php if (isset($user->users_rotation_rule) && $user->users_rotation_rule): ?>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small mb-1"><i class="bi bi-arrow-repeat"></i> Règle de rotation</label>
                            <div>
                                <?php if ($user->users_rotation_rule->hasValue('rotation_rule')): ?>
                                    <span class="badge badge-warning">
                                        <i class="bi bi-arrow-repeat"></i> <?= h($user->users_rotation_rule->rotation_rule->name) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small mb-1"><i class="bi bi-123"></i> Cible override</label>
                            <div>
                                <?php if ($user->users_rotation_rule->target_count_override !== null): ?>
                                    <strong><?= h($user->users_rotation_rule->target_count_override) ?></strong>
                                    <small class="text-muted">(au lieu de la cible par défaut)</small>
                                <?php else: ?>
                                    <span class="text-muted">Utilise la cible par défaut</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">
                        <i class="bi bi-info-circle"></i> Aucune règle de rotation assignée.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <?php // --- Boutons d'action --- ?>
        <div class="mt-3">
            <?= $this->Html->link(
                '<i class="bi bi-pencil mr-2"></i> Modifier',
                ['action' => 'edit', $user->id],
                ['class' => 'btn btn-primary mr-3', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list-ul mr-2"></i> Retour à la liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash mr-2"></i> Supprimer',
                ['action' => 'delete', $user->id],
                [
                    'confirm' => 'Voulez-vous vraiment supprimer ' . h($user->full_name) . ' ?',
                    'class' => 'btn btn-outline-danger float-right',
                    'escape' => false
                ]
            ) ?>
        </div>
    </div>
</div>
