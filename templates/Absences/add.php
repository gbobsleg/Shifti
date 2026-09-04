<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Range $range
 * @var \Cake\Collection\CollectionInterface|string[] $users
 * @var \Cake\Collection\CollectionInterface|string[] $offers
 */
?>
<?php $this->assign('title', 'Ajouter une Absence'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php echo $this->Html->css('daterangepicker', ['block' => true]); ?>
<?php echo $this->Html->script('moment.min', ['block' => true]); ?>
<?php echo $this->Html->script('daterangepicker', ['block' => true]); ?>
<?php echo $this->Html->script('picker', ['block' => true]); ?>

<div class="crud-app absences form content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-plus-circle"></i>
            Nouvelle Absence
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-x-circle me-1"></i> Annuler',
                ['controller' => 'Absences', 'action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <?= $this->Form->create($range) ?>
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
                ]) ?>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Fin</label>
                <?= $this->Form->control('date_end', [
                    'type' => 'text',
                    'label' => false,
                    'class' => 'form-control',
                ]) ?>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Jours concernés (optionnel)</label>
            <div class="d-flex flex-wrap" style="gap: 2rem;">
                <?php
                $daysOfWeek = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'];
                foreach ($daysOfWeek as $index => $day):
                ?>
                    <div class="form-check">
                        <?= $this->Form->checkbox('days[]', [
                            'value' => $index + 1,
                            'class' => 'form-check-input',
                            'id' => "day-{$index}",
                        ]) ?>
                        <label class="form-check-label" for="day-<?= $index ?>">
                            <?= h($day) ?>
                        </label>
                    </div>
                <?php endforeach; ?>
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
        <?= $this->Form->button('<i class="bi bi-save me-2"></i> Créer', [
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
