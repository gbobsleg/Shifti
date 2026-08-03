<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Alert $alert
 */
?>
<?php $this->assign('title', 'Détails Alerte #' . $alert->id); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php
$priorityBadge = 'badge-info';
$priorityIcon = 'bi-info-circle';
$priorityLabel = 'Information';
if ($alert->priority == 1) {
    $priorityBadge = 'badge-danger';
    $priorityIcon = 'bi-exclamation-triangle';
    $priorityLabel = 'Urgent';
} elseif ($alert->priority == 2) {
    $priorityBadge = 'badge-warning';
    $priorityIcon = 'bi-flag';
    $priorityLabel = 'Important';
}
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-bell text-primary"></i>
            Alerte #<?= h($alert->id) ?>
            <span class="badge <?= $priorityBadge ?> ml-2">
                <i class="bi <?= $priorityIcon ?>"></i> <?= $priorityLabel ?>
            </span>
        </h3>
        <div class="btn-toolbar">
            <?= $this->Html->link(
                '<i class="bi bi-pencil mr-1"></i> Modifier',
                ['action' => 'edit', $alert->id],
                ['class' => 'btn btn-primary mr-2', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash mr-1"></i> Supprimer',
                ['action' => 'delete', $alert->id],
                ['confirm' => 'Voulez-vous vraiment supprimer cette alerte ?', 'class' => 'btn btn-danger mr-2', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list mr-1"></i> Liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div class="card-body">
        <div class="card border-primary mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-info-circle"></i> Informations
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="text-muted small mb-1"><i class="bi bi-chat-left-text"></i> Contenu</label>
                    <div><strong><?= h($alert->content) ?></strong></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-calendar-event"></i> Date Début</label>
                        <div><?= h($alert->date_start ? $alert->date_start->i18nFormat('dd/MM/yyyy') : 'N/A') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small mb-1"><i class="bi bi-calendar-x"></i> Date Fin</label>
                        <div><?= h($alert->date_end ? $alert->date_end->i18nFormat('dd/MM/yyyy') : 'N/A') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <?= $this->Html->link(
                '<i class="bi bi-pencil mr-2"></i> Modifier',
                ['action' => 'edit', $alert->id],
                ['class' => 'btn btn-primary mr-3', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list-ul mr-2"></i> Retour à la liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash mr-2"></i> Supprimer',
                ['action' => 'delete', $alert->id],
                [
                    'confirm' => 'Voulez-vous vraiment supprimer cette alerte ?',
                    'class' => 'btn btn-outline-danger float-right',
                    'escape' => false
                ]
            ) ?>
        </div>
    </div>
</div>