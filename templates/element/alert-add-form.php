<?php echo $this->Form->create(null, [
    'url' => [
        'controller' => 'alerts',
        'action' => 'add',
    ],
    'id' => 'alertsForm',
]); ?>
<div class="mb-3">
    <label>Contenu de l'alerte</label>
    <?= $this->Form->text('content', [
        'class' => 'form-control',
        'placeholder' => 'Message de l\'alerte...',
        'required' => true,
    ]) ?>
</div>
<div class="mb-3">
    <label>Priorité</label>
    <?= $this->Form->select('priority', [
        '1' => '1 - Urgent',
        '2' => '2 - Important',
        '3' => '3 - Information',
    ], [
        'default' => 3,
        'class' => 'form-control',
    ]) ?>
</div>
<div class="row">
    <div class="col-6">
        <div class="mb-0">
            <label>Date de début</label>
            <?= $this->Form->date('date_start', [
                'default' => $day_ranges['begin'],
                'class' => 'form-control',
            ]) ?>
        </div>
    </div>
    <div class="col-6">
        <div class="mb-0">
            <label>Date de fin</label>
            <?= $this->Form->date('date_end', [
                'default' => $day_ranges['end'],
                'class' => 'form-control',
            ]) ?>
        </div>
    </div>
</div>
<div class="mt-4 d-flex justify-content-end">
    <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">Annuler</button>
    <?= $this->Form->button('<i class="bi bi-check-lg me-1"></i> Ajouter', [
        'action' => 'submit',
        'confirm' => "Attention, sauvegardez les modifications effectuées sur le planning avant d'ajouter une alerte !\r\n\r\nCliquez sur Ajouter pour valider l'alerte.",
        'class' => 'btn btn-primary',
        'escapeTitle' => false
    ]) ?>
</div>
<?php echo $this->Form->end(); ?>
