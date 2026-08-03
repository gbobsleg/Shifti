<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\DisplaySetting $displaySetting
 */
?>
<?php $this->assign('title', 'Modifier Paramètre'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="mb-0">
            <i class="bi bi-pencil text-primary"></i> Modifier Paramètre
        </h3>
        <div class="btn-toolbar">
            <?= $this->Form->postLink(
                '<i class="bi bi-trash mr-1"></i> Supprimer',
                ['action' => 'delete', $displaySetting->id],
                [
                    'confirm' => 'Voulez-vous vraiment supprimer ce paramètre ?',
                    'class' => 'btn btn-danger mr-2',
                    'escape' => false
                ]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list mr-1"></i> Liste',
                ['action' => 'index'],
                ['class' => 'btn btn-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div class="card-body">
        <?= $this->Form->create($displaySetting) ?>
        <fieldset>
            <?php
                echo $this->Form->control('key', [
                    'label' => 'Clé',
                    'class' => 'form-control',
                    'disabled' => true,
                    'help' => 'La clé ne peut pas être modifiée'
                ]);
                echo $this->Form->control('value', [
                    'label' => 'Valeur',
                    'class' => 'form-control'
                ]);
                echo $this->Form->control('description', [
                    'label' => 'Description',
                    'class' => 'form-control',
                    'type' => 'textarea',
                    'rows' => 2
                ]);
                echo $this->Form->control('type', [
                    'label' => 'Type',
                    'class' => 'form-control',
                    'disabled' => true,
                    'help' => 'Le type ne peut pas être modifié'
                ]);
            ?>
        </fieldset>
        <div class="mt-3">
            <?= $this->Form->button('<i class="bi bi-check-circle mr-2"></i> Enregistrer', [
                'class' => 'btn btn-primary',
                'escapeTitle' => false
            ]) ?>
            <?= $this->Html->link(
                'Annuler',
                ['action' => 'index'],
                ['class' => 'btn btn-secondary']
            ) ?>
        </div>
        <?= $this->Form->end() ?>
    </div>
</div>
