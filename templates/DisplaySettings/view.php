<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\DisplaySetting $displaySetting
 */
?>
<?php $this->assign('title', 'Détail Paramètre'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="mb-0">
            <i class="bi bi-eye text-info"></i> Détail Paramètre
        </h3>
        <div class="btn-toolbar">
            <?= $this->Html->link(
                '<i class="bi bi-pencil mr-1"></i> Modifier',
                ['action' => 'edit', $displaySetting->id],
                ['class' => 'btn btn-primary mr-2', 'escape' => false]
            ) ?>
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
        <div class="row">
            <div class="col-md-6">
                <h5 class="mb-3"><i class="bi bi-info-circle text-primary"></i> Informations</h5>
                <table class="table table-bordered">
                    <tr>
                        <th style="width: 30%;">ID</th>
                        <td><?= $this->Number->format($displaySetting->id) ?></td>
                    </tr>
                    <tr>
                        <th>Clé</th>
                        <td><strong><?= h($displaySetting->key) ?></strong></td>
                    </tr>
                    <tr>
                        <th>Valeur</th>
                        <td>
                            <span class="badge badge-info badge-lg">
                                <?= h($displaySetting->value) ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Type</th>
                        <td>
                            <span class="badge badge-secondary">
                                <?= h($displaySetting->type) ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h5 class="mb-3"><i class="bi bi-file-text text-secondary"></i> Description</h5>
                <div class="alert alert-light">
                    <?= h($displaySetting->description) ?>
                </div>
            </div>
        </div>
    </div>
</div>
