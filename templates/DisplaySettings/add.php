<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\DisplaySetting $displaySetting
 */
?>
<?php $this->assign('title', 'Nouveau Paramètre'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="mb-0">
            <i class="bi bi-plus-circle text-success"></i> Nouveau Paramètre d'Affichage
        </h3>
        <div>
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
                    'label' => 'Clé (unique)',
                    'class' => 'form-control',
                    'placeholder' => 'ex: grid_start_hour'
                ]);
                echo $this->Form->control('value', [
                    'label' => 'Valeur',
                    'class' => 'form-control',
                    'placeholder' => 'ex: 8'
                ]);
                echo $this->Form->control('description', [
                    'label' => 'Description',
                    'class' => 'form-control',
                    'type' => 'textarea',
                    'rows' => 2,
                    'placeholder' => 'Description du paramètre'
                ]);
                echo $this->Form->control('type', [
                    'label' => 'Type',
                    'class' => 'form-control',
                    'options' => ['int' => 'Entier', 'string' => 'Chaîne', 'boolean' => 'Booléen'],
                    'empty' => 'Sélectionnez un type'
                ]);
            ?>
        </fieldset>
        <div class="mt-3">
            <?= $this->Form->button('<i class="bi bi-check-circle mr-2"></i> Enregistrer', [
                'class' => 'btn btn-success',
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
