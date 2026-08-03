<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Skill $skill
 */
?>
<?php $this->assign('title', 'Détails Compétence #' . $skill->id); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php
$isExpired = $skill->validity_end && $skill->validity_end < new \Cake\I18n\FrozenDate();
$badgeClass = $isExpired ? 'badge-danger' : 'badge-success';
$badgeIcon = $isExpired ? 'bi-x-circle' : 'bi-check-circle';
$badgeLabel = $isExpired ? 'Expirée' : 'Active';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-award text-primary"></i>
            Compétence #<?= h($skill->id) ?>
            <span class="badge <?= $badgeClass ?> ml-2">
                <i class="bi <?= $badgeIcon ?>"></i> <?= $badgeLabel ?>
            </span>
        </h3>
        <div class="btn-toolbar">
            <?= $this->Html->link(
                '<i class="bi bi-pencil mr-1"></i> Modifier',
                ['action' => 'edit', $skill->id],
                ['class' => 'btn btn-primary mr-2', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash mr-1"></i> Supprimer',
                ['action' => 'delete', $skill->id],
                ['confirm' => 'Voulez-vous vraiment supprimer cette compétence ?', 'class' => 'btn btn-danger mr-2', 'escape' => false]
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
                    <label class="text-muted small mb-1"><i class="bi bi-person"></i> Utilisateur</label>
                    <div>
                        <?php if ($skill->hasValue('user')): ?>
                            <strong><?= h($skill->user->last_name . ' ' . $skill->user->first_name) ?></strong>
                        <?php else: ?>
                            <span class="text-muted">N/A</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="text-muted small mb-1"><i class="bi bi-tag"></i> Offre/Compétence</label>
                    <div>
                        <?php if ($skill->hasValue('offer')): ?>
                            <span class="badge badge-info"><?= h($skill->offer->name) ?></span>
                        <?php else: ?>
                            <span class="text-muted">N/A</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-calendar-event"></i> Validité Début</label>
                        <div><?= h($skill->validity_start ? $skill->validity_start->i18nFormat('dd/MM/yyyy') : 'N/A') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small mb-1"><i class="bi bi-calendar-x"></i> Validité Fin</label>
                        <div><?= h($skill->validity_end ? $skill->validity_end->i18nFormat('dd/MM/yyyy') : 'N/A') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <?= $this->Html->link(
                '<i class="bi bi-pencil mr-2"></i> Modifier',
                ['action' => 'edit', $skill->id],
                ['class' => 'btn btn-primary mr-3', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list-ul mr-2"></i> Retour à la liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash mr-2"></i> Supprimer',
                ['action' => 'delete', $skill->id],
                [
                    'confirm' => 'Voulez-vous vraiment supprimer cette compétence ?',
                    'class' => 'btn btn-outline-danger float-right',
                    'escape' => false
                ]
            ) ?>
        </div>
    </div>
</div>
