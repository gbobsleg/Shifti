<?php echo $this->Form->create(null, array(
    'url'=> array(
        'controller'=>'alerts',
        'action'=>'add'
    ),
    'class' => 'form-inline',
    'id' => 'alertsForm'
)); ?>
<div class="form-row align-items-center w-100">
    <div class="col-md-5 pr-2">
        <?php echo $this->Form->text('content', [
            'type' => 'text',
            'class' => 'form-control form-control-sm w-100',
            'rows' => '1',
            'placeholder' => 'Ajouter une alerte'
        ]); ?>
    </div>
    <div class="col-auto pl-1">
        <div class="input-group input-group-sm">
            <div class="input-group-prepend">
                <div class="input-group-text">Priorité</div>
            </div>
            <?php echo $this->Form->select('priority', [
                '1' => '1',
                '2' => '2',
                '3' => '3',
            ], [
                'default' => 3,
                'class' => 'form-control form-control-sm',
                'style' => 'width: 60px;'
            ]); ?>
        </div>
    </div>
    <div class="col-auto">
        <div class="input-group input-group-sm">
            <div class="input-group-prepend">
                <div class="input-group-text">Début</div>
            </div>
            <?php echo $this->Form->date('date_start', [
                'default' => $day_ranges['begin'],
                'class' => 'form-control form-control-sm',
                'style' => 'width: 150px;'
            ]); ?>
        </div>
    </div>
    <div class="col-auto">
        <div class="input-group input-group-sm">
            <div class="input-group-prepend">
                <div class="input-group-text">Fin</div>
            </div>
            <?php echo $this->Form->date('date_end', [
                'default' => $day_ranges['end'],
                'class' => 'form-control form-control-sm',
                'style' => 'width: 150px;'
            ]); ?>
        </div>
    </div>
    <div class="col-auto">
        <?php echo $this->Form->button('Ok', [
            'action' => 'submit',
            'confirm' => "Attention, sauvegardez les modifications effectuées sur le planning avant d'ajouter une alerte !\r\n\r\nCliquez sur Ok pour ajouter l'alerte.",
            'class' => 'btn btn-outline-primary btn-sm'
        ]); ?>
    </div>
</div>
<?php echo $this->Form->end(); ?>
