<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Alert $alert
 */
$priorityLabel = 'Information';
if ($alert->priority == 1) {
    $priorityLabel = 'Urgent';
} elseif ($alert->priority == 2) {
    $priorityLabel = 'Important';
}
?>
<?php $this->assign('title', 'Détails Alerte #' . $alert->id); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app alerts view content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-bell"></i>
            Alerte #<?= h($alert->id) ?>
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-pencil me-1"></i> Modifier',
                ['action' => 'edit', $alert->id],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list me-1"></i> Liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash me-1"></i> Supprimer',
                ['action' => 'delete', $alert->id],
                [
                    'confirm' => 'Voulez-vous vraiment supprimer cette alerte ?',
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
                <dt>Contenu</dt>
                <dd><?= h($alert->content) ?></dd>
            </div>
            <div>
                <dt>Date Début</dt>
                <dd><?= h($alert->date_start ? $alert->date_start->i18nFormat('dd/MM/yyyy') : '—') ?></dd>
            </div>
            <div>
                <dt>Date Fin</dt>
                <dd><?= h($alert->date_end ? $alert->date_end->i18nFormat('dd/MM/yyyy') : '—') ?></dd>
            </div>
            <div>
                <dt>Priorité</dt>
                <dd><?= h($priorityLabel) ?></dd>
            </div>
        </dl>
    </section>
</div>
