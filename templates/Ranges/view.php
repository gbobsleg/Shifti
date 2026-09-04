<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Range $range
 */
?>
<?php $this->assign('title', 'Détails Plage Horaire #' . $range->id); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app ranges view content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-clock-history"></i>
            Plage Horaire #<?= h($range->id) ?>
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-pencil me-1"></i> Modifier',
                ['action' => 'edit', $range->id],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list me-1"></i> Liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash me-1"></i> Supprimer',
                ['action' => 'delete', $range->id],
                [
                    'confirm' => 'Voulez-vous vraiment supprimer cette plage ?',
                    'class' => 'btn btn-outline-danger',
                    'escape' => false,
                ]
            ) ?>
        </div>
    </div>

    <section class="crud-section">
        <h2 class="crud-section-title">Informations</h2>
        <dl class="crud-fields">
            <div>
                <dt>Utilisateur</dt>
                <dd>
                    <?php if ($range->hasValue('user')): ?>
                        <?= $this->Html->link(
                            $range->user->first_name . ' ' . $range->user->last_name,
                            ['controller' => 'Users', 'action' => 'view', $range->user->id]
                        ) ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </dd>
            </div>
            <div>
                <dt>Offre</dt>
                <dd>
                    <?php if ($range->hasValue('offer')): ?>
                        <?= $this->Html->link(
                            $range->offer->name,
                            ['controller' => 'Offers', 'action' => 'view', $range->offer->id]
                        ) ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </dd>
            </div>
            <div>
                <dt>Date de début</dt>
                <dd><?= h($range->date_start ? $range->date_start->i18nFormat('dd/MM/yyyy HH:mm') : '—') ?></dd>
            </div>
            <div>
                <dt>Date de fin</dt>
                <dd><?= h($range->date_end ? $range->date_end->i18nFormat('dd/MM/yyyy HH:mm') : '—') ?></dd>
            </div>
            <div>
                <dt>Commentaire</dt>
                <dd><?= $range->comment ? h($range->comment) : '—' ?></dd>
            </div>
        </dl>
    </section>

    <section class="crud-section">
        <h2 class="crud-section-title">Métadonnées</h2>
        <dl class="crud-fields">
            <div>
                <dt>Créé le</dt>
                <dd><?= h($range->created ? $range->created->i18nFormat('dd/MM/yyyy HH:mm') : '—') ?></dd>
            </div>
            <div>
                <dt>Modifié le</dt>
                <dd><?= h($range->modified ? $range->modified->i18nFormat('dd/MM/yyyy HH:mm') : '—') ?></dd>
            </div>
        </dl>
    </section>
</div>
