<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Skill $skill
 * @var \Cake\Collection\CollectionInterface|string[] $users
 * @var \Cake\Collection\CollectionInterface|string[] $offers
 */
?>
<?php $this->assign('title', 'Ajouter une Compétence'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app skills form content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-plus-circle"></i>
            Nouvelle compétence
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-x-circle me-1"></i> Annuler',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <?= $this->Form->create($skill) ?>
    <section class="crud-section">
        <h2 class="crud-section-title">Informations</h2>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Utilisateur</label>
                <?= $this->Form->control('user_id', [
                    'options' => $users,
                    'label' => false,
                    'empty' => 'Choisir...',
                    'class' => 'form-control',
                ]) ?>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Offre</label>
                <?= $this->Form->control('offer_id', [
                    'options' => $offers,
                    'label' => false,
                    'empty' => 'Choisir...',
                    'class' => 'form-control',
                ]) ?>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Début de validité</label>
                <?= $this->Form->control('validity_start', [
                    'label' => false,
                    'empty' => true,
                    'class' => 'form-control',
                ]) ?>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Fin de validité</label>
                <?= $this->Form->control('validity_end', [
                    'label' => false,
                    'empty' => true,
                    'class' => 'form-control',
                ]) ?>
            </div>
        </div>
    </section>
    <div class="crud-actions-bar">
        <?= $this->Form->button('<i class="bi bi-save me-2"></i> Créer', [
            'class' => 'btn btn-primary',
            'escapeTitle' => false,
        ]) ?>
        <?= $this->Html->link(
            '<i class="bi bi-x-circle me-2"></i> Annuler',
            ['action' => 'index'],
            ['class' => 'btn btn-outline-secondary', 'escape' => false]
        ) ?>
    </div>
    <?= $this->Form->end() ?>
</div>
