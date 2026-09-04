<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\DisplaySetting $displaySetting
 */
?>
<?php $this->assign('title', 'Détail Paramètre'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app displaySettings view content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-sliders"></i>
            <?= h($displaySetting->key) ?>
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-pencil me-1"></i> Modifier',
                ['action' => 'edit', $displaySetting->id],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list me-1"></i> Liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash me-1"></i> Supprimer',
                ['action' => 'delete', $displaySetting->id],
                [
                    'confirm' => 'Voulez-vous vraiment supprimer ce paramètre ?',
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
                <dt>Clé</dt>
                <dd><?= h($displaySetting->key) ?></dd>
            </div>
            <div>
                <dt>Valeur</dt>
                <dd><?= h($displaySetting->value) ?></dd>
            </div>
            <div>
                <dt>Type</dt>
                <dd><?= h($displaySetting->type) ?></dd>
            </div>
            <div>
                <dt>Description</dt>
                <dd><?= h($displaySetting->description) ?: '—' ?></dd>
            </div>
        </dl>
    </section>
</div>
