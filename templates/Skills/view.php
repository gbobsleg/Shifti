<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Skill $skill
 */
$isExpired = $skill->validity_end && $skill->validity_end < new \Cake\I18n\FrozenDate();
$userLabel = $skill->hasValue('user')
    ? $skill->user->last_name . ' ' . $skill->user->first_name
    : 'Compétence #' . $skill->id;
?>
<?php $this->assign('title', 'Détails Compétence #' . $skill->id); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app skills view content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-award"></i>
            <?= h($userLabel) ?>
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-pencil me-1"></i> Modifier',
                ['action' => 'edit', $skill->id],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list me-1"></i> Liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash me-1"></i> Supprimer',
                ['action' => 'delete', $skill->id],
                [
                    'confirm' => 'Voulez-vous vraiment supprimer cette compétence ?',
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
                <dd><?= $skill->hasValue('user') ? h($userLabel) : '—' ?></dd>
            </div>
            <div>
                <dt>Offre</dt>
                <dd><?= $skill->hasValue('offer') ? h($skill->offer->name) : '—' ?></dd>
            </div>
            <div>
                <dt>Début de validité</dt>
                <dd><?= h($skill->validity_start ? $skill->validity_start->i18nFormat('dd/MM/yyyy') : '—') ?></dd>
            </div>
            <div>
                <dt>Fin de validité</dt>
                <dd>
                    <?= h($skill->validity_end ? $skill->validity_end->i18nFormat('dd/MM/yyyy') : '—') ?>
                    <?php if ($isExpired): ?>
                        <span class="text-muted">Expirée</span>
                    <?php endif; ?>
                </dd>
            </div>
        </dl>
    </section>
</div>
