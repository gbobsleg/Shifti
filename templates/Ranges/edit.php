<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Range $range
 * @var string[]|\Cake\Collection\CollectionInterface $users
 * @var string[]|\Cake\Collection\CollectionInterface $offers
 */
?>
<?php $this->assign('title', 'Modifier Plage Horaire #' . $range->id); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app ranges form content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-pencil"></i>
            Modifier la Plage Horaire
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-x-circle me-1"></i> Annuler',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <?= $this->Form->create($range) ?>
    <section class="crud-section">
        <h2 class="crud-section-title">Informations de la plage</h2>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Date et Heure de Début</label>
                <?= $this->Form->control('date_start', [
                    'label' => false,
                    'class' => 'form-control',
                    'step' => 900,
                ]) ?>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Date et Heure de Fin</label>
                <?= $this->Form->control('date_end', [
                    'label' => false,
                    'class' => 'form-control',
                    'step' => 900,
                ]) ?>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Utilisateur</label>
                <?= $this->Form->control('user_id', [
                    'options' => $users,
                    'label' => false,
                    'class' => 'form-control',
                    'empty' => '-- Sélectionner un utilisateur --',
                ]) ?>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Offre</label>
                <?= $this->Form->control('offer_id', [
                    'options' => $offers,
                    'label' => false,
                    'class' => 'form-control',
                    'empty' => '-- Sélectionner une offre --',
                ]) ?>
            </div>
            <div class="col-12 mb-3">
                <label class="form-label">Commentaire</label>
                <?= $this->Form->control('comment', [
                    'label' => false,
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Ajouter un commentaire...',
                ]) ?>
            </div>
        </div>
    </section>
    <div class="crud-actions-bar">
        <?= $this->Form->button('<i class="bi bi-save me-2"></i> Enregistrer', [
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
