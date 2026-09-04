<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Range $range
 * @var \Cake\Collection\CollectionInterface|string[] $users
 * @var int $remoteWorkOfferId
 */
use Cake\I18n\DateTime;
?>
<?php $this->assign('title', 'Ajouter un Jour de Télétravail'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php echo $this->Html->css('daterangepicker', ['block' => true]); ?>
<?php echo $this->Html->script('moment.min', ['block' => true]); ?>
<?php echo $this->Html->script('daterangepicker', ['block' => true]); ?>
<?php echo $this->Html->script('picker', ['block' => true]); ?>

<div class="crud-app remote-work-days form content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-plus-circle"></i>
            Nouveau Jour de Télétravail
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
        <h2 class="crud-section-title">Informations</h2>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Agent</label>
                <?= $this->Form->control('user_id', [
                    'options' => $users,
                    'label' => false,
                    'empty' => 'Choisir...',
                    'class' => 'form-control',
                    'required' => true,
                ]) ?>
                <small class="text-muted">Seuls les agents avec une configuration de télétravail sont affichés</small>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Date de début</label>
                <?= $this->Form->control('date_start', [
                    'type' => 'text',
                    'label' => false,
                    'class' => 'form-control',
                    'required' => true,
                    'id' => 'date-start',
                ]) ?>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Date de fin</label>
                <?= $this->Form->control('date_end', [
                    'type' => 'text',
                    'label' => false,
                    'class' => 'form-control',
                    'required' => true,
                    'id' => 'date-end',
                ]) ?>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Jours de la semaine (optionnel)</label>
            <div>
                <?php
                $daysOfWeek = [
                    1 => 'Lundi',
                    2 => 'Mardi',
                    3 => 'Mercredi',
                    4 => 'Jeudi',
                    5 => 'Vendredi',
                    6 => 'Samedi',
                    7 => 'Dimanche',
                ];
                foreach ($daysOfWeek as $dayNum => $dayName): ?>
                    <div class="form-check form-check-inline">
                        <?= $this->Form->checkbox('days[]', [
                            'value' => $dayNum,
                            'id' => 'day_' . $dayNum,
                            'class' => 'form-check-input',
                            'hiddenField' => false,
                        ]) ?>
                        <label class="form-check-label" for="day_<?= $dayNum ?>">
                            <?= $dayName ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            <small class="text-muted">Si sélectionnés, créera un range pour chaque occurrence de ces jours dans la période</small>
        </div>
        <div class="mb-3">
            <label class="form-label">Commentaire (optionnel)</label>
            <?= $this->Form->control('comment', [
                'type' => 'textarea',
                'label' => false,
                'class' => 'form-control',
                'rows' => 3,
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
            ['action' => 'index'],
            ['class' => 'btn btn-outline-secondary', 'escape' => false]
        ) ?>
    </div>
    <?= $this->Form->end() ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dateStart = document.getElementById('date-start');
    const dateEnd = document.getElementById('date-end');

    if (dateStart && dateEnd && typeof $ !== 'undefined' && $.fn.daterangepicker) {
        $(dateStart).daterangepicker({
            singleDatePicker: true,
            timePicker: true,
            timePicker24Hour: true,
            locale: {
                format: 'DD/MM/YYYY, HH:mm',
                separator: ' - ',
                applyLabel: 'Valider',
                cancelLabel: 'Annuler',
                fromLabel: 'De',
                toLabel: 'À',
                customRangeLabel: 'Personnalisé',
                weekLabel: 'S',
                daysOfWeek: ['Di', 'Lu', 'Ma', 'Me', 'Je', 'Ve', 'Sa'],
                monthNames: ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'],
                firstDay: 1
            }
        });

        $(dateEnd).daterangepicker({
            singleDatePicker: true,
            timePicker: true,
            timePicker24Hour: true,
            locale: {
                format: 'DD/MM/YYYY, HH:mm',
                separator: ' - ',
                applyLabel: 'Valider',
                cancelLabel: 'Annuler',
                fromLabel: 'De',
                toLabel: 'À',
                customRangeLabel: 'Personnalisé',
                weekLabel: 'S',
                daysOfWeek: ['Di', 'Lu', 'Ma', 'Me', 'Je', 'Ve', 'Sa'],
                monthNames: ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'],
                firstDay: 1
            }
        });
    }
});
</script>
