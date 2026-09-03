<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\RotationRule $rule
 */
?>
<?php $this->assign('title', 'Détail de la règle de rotation'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-arrow-repeat text-primary"></i>
            <?= h($rule->name) ?>
        </h3>
        <div>
            <?= $this->Html->link(
                '<i class="bi bi-pencil mr-1"></i> Modifier',
                ['action' => 'edit', $rule->id],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-arrow-left mr-1"></i> Retour',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="card border-primary mb-3">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-info-circle"></i> Informations générales
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-4">Nom :</dt>
                            <dd class="col-sm-8"><strong><?= h($rule->name) ?></strong></dd>
                            
                            <dt class="col-sm-4">Offre :</dt>
                            <dd class="col-sm-8">
                                <?php if ($rule->offer): ?>
                                    <span class="badge badge-info"><?= h($rule->offer->name) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Générique</span>
                                <?php endif; ?>
                            </dd>
                            
                            <dt class="col-sm-4">Type de période :</dt>
                            <dd class="col-sm-8">
                                <?php if ($rule->period_type === 'WEEKLY'): ?>
                                    <span class="badge badge-info">
                                        <i class="bi bi-calendar-week"></i> Hebdomadaire
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-warning">
                                        <i class="bi bi-calendar-month"></i> Mensuelle
                                    </span>
                                <?php endif; ?>
                            </dd>
                            <dt class="col-sm-4">Exclusivité jour :</dt>
                            <dd class="col-sm-8">
                                <?= !empty($rule->exclusive_day) ? 'Un duty par jour' : 'Cumul autorisé (non-chevauchement)' ?>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card border-info mb-3">
                    <div class="card-header bg-info text-white">
                        <i class="bi bi-clock"></i> Paramètres du shift
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-5">Durée :</dt>
                            <dd class="col-sm-7">
                                <strong><?= h($rule->shift_duration) ?></strong> minutes
                                <small class="text-muted">(<?= round($rule->shift_duration / 60, 1) ?>h)</small>
                            </dd>
                            
                            <dt class="col-sm-5">Fenêtre horaire :</dt>
                            <dd class="col-sm-7">
                                <i class="bi bi-clock"></i>
                                <?= h(substr($rule->time_window_start ?? '', 0, 5)) ?> – 
                                <?= h(substr($rule->time_window_end ?? '', 0, 5)) ?>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($rule->rotation_rule_lines)): ?>
            <div class="card border-info mb-3">
                <div class="card-header bg-info text-white">
                    <i class="bi bi-layers"></i> Lignes
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Rang</th>
                                <th>Type</th>
                                <th>Offre</th>
                                <th>Paramètres</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rule->rotation_rule_lines as $line): ?>
                                <tr>
                                    <td><?= (int)$line->sort_order ?></td>
                                    <td>
                                        <?= $line->line_type === 'quota' ? 'Quota' : 'Couverture' ?>
                                    </td>
                                    <td><?= h($line->offer->name ?? '—') ?></td>
                                    <td>
                                        <?php if ($line->line_type === 'quota'): ?>
                                            <?= (int)$line->target_count ?> × <?= (int)$line->shift_duration ?> min
                                            (<?= h(substr((string)$line->time_window_start, 0, 5)) ?>–<?= h(substr((string)$line->time_window_end, 0, 5)) ?>)
                                        <?php else: ?>
                                            <?= (int)($line->quantity ?? 1) ?> / plage
                                            <?php foreach ($line->rotation_rule_line_slots ?? [] as $sl): ?>
                                                <span class="badge badge-light"><?= h(substr((string)$sl->start_time, 0, 5)) ?>–<?= h(substr((string)$sl->end_time, 0, 5)) ?></span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($rule->users_rotation_rules)): ?>
            <div class="card border-success mb-3">
                <div class="card-header bg-success text-white">
                    <i class="bi bi-people"></i> Agents assignés
                </div>
                <div class="card-body">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Matricule</th>
                                <th>Agent</th>
                                <th>Site</th>
                                <th>Cible override</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rule->users_rotation_rules as $userRule): ?>
                                <tr>
                                    <td>
                                        <?php if ($userRule->user): ?>
                                            <code><?= h($userRule->user->user_code ?? '') ?></code>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($userRule->user): ?>
                                            <?= h($userRule->user->last_name ?? '') ?> <?= h($userRule->user->first_name ?? '') ?>
                                        <?php else: ?>
                                            User #<?= h($userRule->user_id) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($userRule->user && $userRule->user->site): ?>
                                            <span class="badge badge-info"><?= h($userRule->user->site->name) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($userRule->target_count_override !== null): ?>
                                            <span class="badge badge-warning"><?= h($userRule->target_count_override) ?></span>
                                            <small class="text-muted">(au lieu de <?= h($rule->target_count) ?>)</small>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Par défaut (<?= h($rule->target_count) ?>)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($userRule->user): ?>
                                            <?= $this->Html->link(
                                                '<i class="bi bi-eye"></i>',
                                                ['controller' => 'Users', 'action' => 'view', $userRule->user->id],
                                                ['class' => 'btn btn-sm btn-outline-primary', 'escape' => false, 'title' => 'Voir l\'utilisateur']
                                            ) ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                Aucun agent n'est assigné à cette règle. Assignez des agents depuis la page d'édition d'un utilisateur.
            </div>
        <?php endif; ?>

        <div class="mt-3">
            <small class="text-muted">
                <i class="bi bi-clock"></i>
                Créé le <?= $rule->created ? $rule->created->i18nFormat('dd/MM/yyyy à HH:mm') : 'N/A' ?>
                <?php if ($rule->modified && $rule->modified != $rule->created): ?>
                    | Modifié le <?= $rule->modified->i18nFormat('dd/MM/yyyy à HH:mm') ?>
                <?php endif; ?>
            </small>
        </div>
    </div>
</div>
