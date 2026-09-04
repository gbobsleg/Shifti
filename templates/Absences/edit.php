<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Range $absence
 * @var string[]|\Cake\Collection\CollectionInterface $users
 * @var string[]|\Cake\Collection\CollectionInterface $offers
 */
?>
<?php $this->assign('title', 'Modifier Absence #' . $absence->id); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php echo $this->Html->css('daterangepicker', ['block' => true]); ?>
<?php echo $this->Html->script('moment.min', ['block' => true]); ?>
<?php echo $this->Html->script('daterangepicker', ['block' => true]); ?>
<?php echo $this->Html->script('picker', ['block' => true]); ?>

<div class="crud-app absences form content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-pencil"></i>
            Modifier Absence #<?= h($absence->id) ?>
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-x-circle me-1"></i> Annuler',
                ['controller' => 'Absences', 'action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <?= $this->Form->create($absence) ?>
    <section class="crud-section">
        <h2 class="crud-section-title">Informations</h2>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Agent</label>
                <?= $this->Form->control('user_id', [
                    'options' => $users,
                    'label' => false,
                    'empty' => 'Choisir...',
                    'class' => 'form-control',
                ]) ?>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Type d'Absence</label>
                <?= $this->Form->control('offer_id', [
                    'options' => $offers,
                    'label' => false,
                    'empty' => 'Choisir...',
                    'class' => 'form-control',
                ]) ?>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Début</label>
                <?= $this->Form->control('date_start', [
                    'type' => 'text',
                    'label' => false,
                    'class' => 'form-control',
                    'value' => $absence->date_start ? $absence->date_start->i18nFormat('dd/MM/yyyy HH:mm') : '',
                ]) ?>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Fin</label>
                <?= $this->Form->control('date_end', [
                    'type' => 'text',
                    'label' => false,
                    'class' => 'form-control',
                    'value' => $absence->date_end ? $absence->date_end->i18nFormat('dd/MM/yyyy HH:mm') : '',
                ]) ?>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Commentaire</label>
            <?= $this->Form->control('comment', [
                'label' => false,
                'rows' => 3,
                'class' => 'form-control',
            ]) ?>
        </div>
    </section>
    <div class="crud-actions-bar">
        <?= $this->Form->button('<i class="bi bi-save me-2"></i> Enregistrer', [
            'class' => 'btn btn-primary',
            'escapeTitle' => false,
        ]) ?>
        <?= $this->Html->link(
            '<i class="bi bi-x-circle me-2"></i> Annuler',
            ['controller' => 'Absences', 'action' => 'index'],
            ['class' => 'btn btn-outline-secondary', 'escape' => false]
        ) ?>
    </div>
    <?= $this->Form->end() ?>
</div>
