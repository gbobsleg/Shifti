<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 */
?>
<?php $this->assign('title', 'Détails Utilisateur : ' . h($user->full_name)); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app users view crud-app-wide content">
    <div class="crud-header">
        <div>
            <h1>
                <i class="bi bi-person-circle"></i>
                <?= h($user->full_name) ?>
            </h1>
            <?= $this->element('users/header_dates', ['user' => $user]) ?>
        </div>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-pencil me-1"></i> Modifier',
                ['action' => 'edit', $user->id],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list me-1"></i> Liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash me-1"></i> Supprimer',
                ['action' => 'delete', $user->id],
                [
                    'confirm' => 'Voulez-vous vraiment supprimer ' . h($user->full_name) . ' ?',
                    'class' => 'btn btn-outline-danger',
                    'escape' => false,
                ]
            ) ?>
        </div>
    </div>

    <?= $this->element('users/tabs_nav') ?>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="user-tab-identity" role="tabpanel" aria-labelledby="user-tab-identity-btn">
            <section class="crud-section">
                <h2 class="crud-section-title">Informations générales</h2>
                <dl class="crud-fields">
                    <div>
                        <dt>Rôle</dt>
                        <dd><?= $user->hasValue('role') ? h($user->role->name) : '—' ?></dd>
                    </div>
                    <div>
                        <dt>Code utilisateur</dt>
                        <dd><?= h($user->user_code) ?></dd>
                    </div>
                    <div>
                        <dt>Nom</dt>
                        <dd><?= h($user->last_name) ?></dd>
                    </div>
                    <div>
                        <dt>Prénom</dt>
                        <dd><?= h($user->first_name) ?></dd>
                    </div>
                    <div>
                        <dt>Site</dt>
                        <dd><?= $user->hasValue('site') ? h($user->site->name) : '—' ?></dd>
                    </div>
                    <div>
                        <dt>Email</dt>
                        <dd>
                            <?php if ($user->email): ?>
                                <a href="mailto:<?= h($user->email) ?>"><?= h($user->email) ?></a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </dd>
                    </div>
                </dl>
            </section>
        </div>

        <div class="tab-pane fade" id="user-tab-contracts" role="tabpanel" aria-labelledby="user-tab-contracts-btn">
            <section class="crud-section">
                <h2 class="crud-section-title">Contrats</h2>
                <?php if (!empty($user->user_contracts)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm crud-table">
                            <thead>
                            <tr>
                                <th scope="col">Date début</th>
                                <th scope="col">Date fin</th>
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
                                            Indéterminée (CDI)
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $todayStr = date('Y-m-d');
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
                                            $isActive = $contractStartStr <= $todayStr && ($contractEndStr === null || $contractEndStr >= $todayStr);
                                        }
                                        ?>
                                        <?= $isActive ? 'Actif' : 'Terminé' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Aucun contrat défini.</p>
                <?php endif; ?>
            </section>
            <?= $this->element('users/contractual_availabilities_readonly', ['user' => $user]) ?>
        </div>

        <div class="tab-pane fade" id="user-tab-remote" role="tabpanel" aria-labelledby="user-tab-remote-btn">
            <?= $this->element('users/remote_work_readonly', ['user' => $user]) ?>
        </div>

        <div class="tab-pane fade" id="user-tab-skills" role="tabpanel" aria-labelledby="user-tab-skills-btn">
            <section class="crud-section">
                <h2 class="crud-section-title">Compétences</h2>
                <?php if (!empty($user->skills)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm crud-table">
                            <thead>
                            <tr>
                                <th scope="col">Offre</th>
                                <th scope="col">Validité début</th>
                                <th scope="col">Validité fin</th>
                                <th scope="col" class="actions">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($user->skills as $skill): ?>
                                <tr>
                                    <td>
                                        <?= $this->Html->link(
                                            $skill->offer->name ?? '—',
                                            ['controller' => 'Skills', 'action' => 'view', $skill->id],
                                            ['class' => 'crud-row-link']
                                        ) ?>
                                    </td>
                                    <td><?= $skill->validity_start ? h($skill->validity_start->i18nFormat('dd/MM/yyyy')) : '—' ?></td>
                                    <td><?= $skill->validity_end ? h($skill->validity_end->i18nFormat('dd/MM/yyyy')) : '—' ?></td>
                                    <td class="actions">
                                        <?= $this->Html->link(
                                            '<i class="bi bi-pencil" aria-hidden="true"></i>',
                                            ['controller' => 'Skills', 'action' => 'edit', $skill->id],
                                            [
                                                'class' => 'crud-action',
                                                'escape' => false,
                                                'title' => 'Modifier',
                                                'aria-label' => 'Modifier',
                                                'data-bs-toggle' => 'tooltip',
                                            ]
                                        ) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Aucune compétence définie.</p>
                <?php endif; ?>
            </section>
        </div>

        <div class="tab-pane fade" id="user-tab-rotation" role="tabpanel" aria-labelledby="user-tab-rotation-btn">
            <section class="crud-section">
                <h2 class="crud-section-title">Règle de rotation</h2>
                <?php if (isset($user->users_rotation_rule) && $user->users_rotation_rule): ?>
                    <dl class="crud-fields">
                        <div>
                            <dt>Règle de rotation</dt>
                            <dd>
                                <?php if ($user->users_rotation_rule->hasValue('rotation_rule')): ?>
                                    <?= $this->Html->link(
                                        $user->users_rotation_rule->rotation_rule->name,
                                        ['controller' => 'RotationRules', 'action' => 'view', $user->users_rotation_rule->rotation_rule->id]
                                    ) ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div>
                            <dt>Cible personnalisée</dt>
                            <dd>
                                <?php if ($user->users_rotation_rule->target_count_override !== null): ?>
                                    <?= h($user->users_rotation_rule->target_count_override) ?>
                                    <span class="text-muted">(au lieu de la cible par défaut)</span>
                                <?php else: ?>
                                    Utilise la cible par défaut
                                <?php endif; ?>
                            </dd>
                        </div>
                    </dl>
                <?php else: ?>
                    <p class="text-muted mb-0">Aucune règle de rotation assignée.</p>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>
