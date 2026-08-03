<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Range $range
 * @var \Cake\Collection\CollectionInterface|string[] $users
 * @var \Cake\Collection\CollectionInterface|string[] $offers
 */
use Cake\I18n\DateTime;
?>
<?php $this->assign('title', 'Ajouter une Absence'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php echo $this->Html->css('daterangepicker', ['block' => true]); ?>
<?php echo $this->Html->script('moment.min', ['block' => true]); ?>
<?php echo $this->Html->script('daterangepicker', ['block' => true]); ?>
<?php echo $this->Html->script('picker', ['block' => true]); ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-plus-circle text-success"></i>
            Nouvelle Absence
        </h3>
        <div>
            <?= $this->Html->link(
                '<i class="bi bi-x-circle mr-1"></i> Annuler',
                ['controller' => 'Absences', 'action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div class="card-body">
        <?= $this->Form->create($range) ?>
        
        <div class="card border-primary mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-info-circle"></i> Informations
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-person"></i> Agent</label>
                        <?= $this->Form->control('user_id', [
                            'options' => $users,
                            'label' => false,
                            'empty' => 'Choisir...',
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-tag"></i> Type d'Absence</label>
                        <?= $this->Form->control('offer_id', [
                            'options' => $offers,
                            'label' => false,
                            'empty' => 'Choisir...',
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-calendar-event"></i> Début</label>
                        <?= $this->Form->control('date_start', [
                            'type' => 'text',
                            'label' => false,
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-calendar-x"></i> Fin</label>
                        <?= $this->Form->control('date_end', [
                            'type' => 'text',
                            'label' => false,
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label mt-3">Jours concernés (optionnel)</label>
                    <div class="d-flex flex-wrap" style="gap: 2rem;">
                        <?php
                        $daysOfWeek = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'];
                        foreach ($daysOfWeek as $index => $day):
                        ?>
                            <div class="form-check">
                                <?= $this->Form->checkbox('days[]', [
                                    'value' => $index + 1,
                                    'class' => 'form-check-input',
                                    'id' => "day-{$index}"
                                ]) ?>
                                <label class="form-check-label" for="day-<?= $index ?>">
                                    <?= h($day) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-chat-left-text"></i> Commentaire</label>
                    <?= $this->Form->control('comment', [
                        'label' => false,
                        'rows' => 3,
                        'class' => 'form-control'
                    ]) ?>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <?= $this->Form->button('<i class="bi bi-save mr-2"></i> Créer', [
                'class' => 'btn btn-success mr-3',
                'escapeTitle' => false
            ]) ?>
            <?= $this->Html->link(
                '<i class="bi bi-x-circle mr-2"></i> Annuler',
                ['controller' => 'Absences', 'action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
        
        <?= $this->Form->end() ?>
    </div>
</div>
