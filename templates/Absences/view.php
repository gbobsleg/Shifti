<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Range $absence
 */
$userLabel = $absence->hasValue('user')
    ? $absence->user->last_name . ' ' . $absence->user->first_name
    : 'Absence #' . $absence->id;
?>
<?php $this->assign('title', 'Détails Absence #' . $absence->id); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app absences view content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-calendar-x"></i>
            Absence #<?= h($absence->id) ?>
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-pencil me-1"></i> Modifier',
                ['controller' => 'Ranges', 'action' => 'edit', $absence->id],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list me-1"></i> Liste',
                ['controller' => 'Absences', 'action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash me-1"></i> Supprimer',
                ['controller' => 'Ranges', 'action' => 'delete', $absence->id],
                [
                    'confirm' => 'Voulez-vous vraiment supprimer cette absence ?',
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
                <dd><?= $absence->hasValue('user') ? h($userLabel) : '—' ?></dd>
            </div>
            <div>
                <dt>Type d'Absence</dt>
                <dd><?= $absence->hasValue('offer') ? h($absence->offer->name) : '—' ?></dd>
            </div>
            <div>
                <dt>Date Début</dt>
                <dd><?= h($absence->date_start ? $absence->date_start->i18nFormat('dd/MM/yyyy HH:mm') : '—') ?></dd>
            </div>
            <div>
                <dt>Date Fin</dt>
                <dd><?= h($absence->date_end ? $absence->date_end->i18nFormat('dd/MM/yyyy HH:mm') : '—') ?></dd>
            </div>
            <?php if ($absence->comment): ?>
            <div>
                <dt>Commentaire</dt>
                <dd><?= h($absence->comment) ?></dd>
            </div>
            <?php endif; ?>
        </dl>
    </section>
</div>
