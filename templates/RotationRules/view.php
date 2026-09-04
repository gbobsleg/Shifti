<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\RotationRule $rule
 */
?>
<?php $this->assign('title', 'Détail de la règle de rotation'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app rotation-rules view content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-arrow-repeat"></i>
            <?= h($rule->name) ?>
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-pencil me-1"></i> Modifier',
                ['action' => 'edit', $rule->id],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list me-1"></i> Liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash me-1"></i> Supprimer',
                ['action' => 'delete', $rule->id],
                [
                    'confirm' => 'Supprimer la règle "' . h($rule->name) . '" ?',
                    'class' => 'btn btn-outline-danger',
                    'escape' => false,
                ]
            ) ?>
        </div>
    </div>

    <section class="crud-section">
        <h2 class="crud-section-title">Informations générales</h2>
        <dl class="crud-fields">
            <div>
                <dt>Nom</dt>
                <dd><?= h($rule->name) ?></dd>
            </div>
            <div>
                <dt>Offre</dt>
                <dd><?= $rule->offer ? h($rule->offer->name) : 'Générique' ?></dd>
            </div>
            <div>
                <dt>Type de période</dt>
                <dd><?= $rule->period_type === 'WEEKLY' ? 'Hebdomadaire' : 'Mensuelle' ?></dd>
            </div>
            <div>
                <dt>Exclusivité jour</dt>
                <dd><?= !empty($rule->exclusive_day) ? 'Un duty par jour' : 'Cumul autorisé (non-chevauchement)' ?></dd>
            </div>
        </dl>
    </section>

    <section class="crud-section">
        <h2 class="crud-section-title">Paramètres du shift</h2>
        <dl class="crud-fields">
            <div>
                <dt>Durée</dt>
                <dd>
                    <?= h($rule->shift_duration) ?> minutes
                    <span class="text-muted">(<?= round($rule->shift_duration / 60, 1) ?>h)</span>
                </dd>
            </div>
            <div>
                <dt>Fenêtre horaire</dt>
                <dd>
                    <?= h(substr($rule->time_window_start ?? '', 0, 5)) ?> –
                    <?= h(substr($rule->time_window_end ?? '', 0, 5)) ?>
                </dd>
            </div>
        </dl>
    </section>

    <?php if (!empty($rule->rotation_rule_lines)): ?>
        <section class="crud-section">
            <h2 class="crud-section-title">Lignes</h2>
            <div class="table-responsive">
                <table class="table table-hover table-sm crud-table">
                    <thead>
                    <tr>
                        <th scope="col">Rang</th>
                        <th scope="col">Type</th>
                        <th scope="col">Offre</th>
                        <th scope="col">Paramètres</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rule->rotation_rule_lines as $line): ?>
                        <tr>
                            <td><?= (int)$line->sort_order ?></td>
                            <td><?= $line->line_type === 'quota' ? 'Quota' : 'Couverture' ?></td>
                            <td><?= h($line->offer->name ?? '—') ?></td>
                            <td>
                                <?php if ($line->line_type === 'quota'): ?>
                                    <?= (int)$line->target_count ?> × <?= (int)$line->shift_duration ?> min
                                    (<?= h(substr((string)$line->time_window_start, 0, 5)) ?>–<?= h(substr((string)$line->time_window_end, 0, 5)) ?>)
                                <?php else: ?>
                                    <?= (int)($line->quantity ?? 1) ?> / plage
                                    <?php foreach ($line->rotation_rule_line_slots ?? [] as $sl): ?>
                                        <span class="text-muted"><?= h(substr((string)$sl->start_time, 0, 5)) ?>–<?= h(substr((string)$sl->end_time, 0, 5)) ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <section class="crud-section">
        <h2 class="crud-section-title">Agents assignés</h2>
        <?php if (!empty($rule->users_rotation_rules)): ?>
            <div class="table-responsive">
                <table class="table table-hover table-sm crud-table">
                    <thead>
                    <tr>
                        <th scope="col">Matricule</th>
                        <th scope="col">Agent</th>
                        <th scope="col">Site</th>
                        <th scope="col">Cible personnalisée</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rule->users_rotation_rules as $userRule): ?>
                        <?php
                        $agentLabel = $userRule->user
                            ? trim(($userRule->user->last_name ?? '') . ' ' . ($userRule->user->first_name ?? ''))
                            : 'Utilisateur #' . $userRule->user_id;
                        ?>
                        <tr>
                            <td><?= $userRule->user ? h($userRule->user->user_code ?? '') : '—' ?></td>
                            <td>
                                <?php if ($userRule->user): ?>
                                    <?= $this->Html->link(
                                        $agentLabel,
                                        ['controller' => 'Users', 'action' => 'view', $userRule->user->id],
                                        ['class' => 'crud-row-link']
                                    ) ?>
                                <?php else: ?>
                                    <?= h($agentLabel) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $userRule->user && $userRule->user->site
                                    ? h($userRule->user->site->name)
                                    : '—' ?>
                            </td>
                            <td>
                                <?php if ($userRule->target_count_override !== null): ?>
                                    <?= h($userRule->target_count_override) ?>
                                    <span class="text-muted">(au lieu de <?= h($rule->target_count) ?>)</span>
                                <?php else: ?>
                                    Par défaut (<?= h($rule->target_count) ?>)
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted mb-0">
                Aucun agent n'est assigné à cette règle. Assignez des agents depuis la page d'édition d'un utilisateur.
            </p>
        <?php endif; ?>
    </section>
</div>
