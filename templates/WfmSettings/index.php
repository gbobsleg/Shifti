<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\WfmSetting[]|\Cake\Collection\CollectionInterface $wfmSettings
 */
?>
<?php $this->assign('title', 'Profils de Paramètres WFM'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php $this->Html->script('wfm-settings', ['block' => true]); ?>

<div class="crud-app wfm-settings index content">
    <div class="crud-header">
        <div>
            <h1>
                <i class="bi bi-gear"></i>
                Profils de Paramètres WFM
            </h1>
            <p class="crud-header-meta"><?= $this->Paginator->counter('{{count}} profils') ?></p>
        </div>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle me-1"></i> Nouveau Profil',
                ['action' => 'add'],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-sm crud-table">
            <?php
            $columns = ['Nom du Profil', 'Objectif QS (%)', 'Délai QS (sec)', 'Shrinkage (%)', 'Journée stricte', 'Actions'];
            $colCount = count($columns);
            ?>
            <thead>
            <tr>
                <th scope="col"><?= $this->Paginator->sort('name', $columns[0]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('service_level_percent', $columns[1]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('service_level_seconds', $columns[2]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('shrinkage_percent', $columns[3]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('strict_work_hours', $columns[4]) ?></th>
                <th scope="col" class="actions"><?= h($columns[5]) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (count($wfmSettings) === 0): ?>
                <tr>
                    <td colspan="<?= (int)$colCount ?>" class="crud-empty">
                        <p>Aucun profil.</p>
                        <?= $this->Html->link(
                            '<i class="bi bi-plus-circle me-1"></i> Créer un profil',
                            ['action' => 'add'],
                            ['class' => 'btn btn-sm btn-primary', 'escape' => false]
                        ) ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($wfmSettings as $wfmSetting): ?>
                <?php
                $isStrict = $wfmSetting->strict_work_hours === null || $wfmSetting->strict_work_hours;
                $label = $isStrict ? 'Strict' : 'Flexible';
                ?>
                <tr>
                    <td>
                        <?= $this->Html->link(
                            $wfmSetting->name,
                            ['action' => 'view', $wfmSetting->id],
                            ['class' => 'crud-row-link']
                        ) ?>
                    </td>
                    <td><?= $this->Number->format($wfmSetting->service_level_percent) ?>%</td>
                    <td><?= $this->Number->format($wfmSetting->service_level_seconds) ?>s</td>
                    <td><?= $this->Number->format($wfmSetting->shrinkage_percent) ?>%</td>
                    <td><?= h($label) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(
                            '<i class="bi bi-pencil" aria-hidden="true"></i>',
                            ['action' => 'edit', $wfmSetting->id],
                            [
                                'class' => 'crud-action',
                                'escape' => false,
                                'title' => 'Modifier',
                                'aria-label' => 'Modifier',
                                'data-bs-toggle' => 'tooltip',
                            ]
                        ) ?>
                        <?= $this->Form->postLink(
                            '<i class="bi bi-trash" aria-hidden="true"></i>',
                            ['action' => 'delete', $wfmSetting->id],
                            [
                                'confirm' => 'Voulez-vous vraiment supprimer "' . h($wfmSetting->name) . '" ?',
                                'class' => 'crud-action crud-action-danger',
                                'escape' => false,
                                'title' => 'Supprimer',
                                'aria-label' => 'Supprimer',
                                'data-bs-toggle' => 'tooltip',
                            ]
                        ) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="paginator">
        <ul class="pagination justify-content-center">
            <?= $this->Paginator->first('<< ' . 'Première') ?>
            <?= $this->Paginator->prev('< ' . 'Précédente') ?>
            <?= $this->Paginator->numbers() ?>
            <?= $this->Paginator->next('Suivante' . ' >') ?>
            <?= $this->Paginator->last('Dernière' . ' >>') ?>
        </ul>
        <p><?= $this->Paginator->counter('Page {{page}} sur {{pages}}, affichant {{current}} sur {{count}}') ?></p>
    </div>
</div>
