<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\WfmSetting[]|\Cake\Collection\CollectionInterface $wfmSettings
 */
?>
<?php $this->assign('title', 'Profils de Paramètres WFM'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<?php $this->Html->script('wfm-settings', ['block' => true]); ?>

<style>
.wfm-settings .table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.08) !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.2s ease;
}
</style>

<div class="wfm-settings index content card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="mb-0">
            <i class="bi bi-gear text-primary"></i> Profils de Paramètres WFM
        </h3>
        <div>
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle mr-1"></i> Nouveau Profil',
                ['action' => 'add'],
                ['class' => 'btn btn-success', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div class="card-body">
        <?php // --- Cards de statistiques --- ?>
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-gear text-primary" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['total'] ?></h3>
                        <small class="text-muted">Total</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-lock text-info" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['strict'] ?></h3>
                        <small class="text-muted">Strictes</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-unlock text-success" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['flexible'] ?></h3>
                        <small class="text-muted">Flexibles</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center py-3">
                        <i class="bi bi-cup-hot text-warning" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['with_breaks'] ?></h3>
                        <small class="text-muted">Avec pauses</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
    <table class="table table-striped table-hover table-sm">
        <thead>
        <tr>
            <th scope="col"><?= $this->Paginator->sort('name', 'Nom du Profil') ?></th>
            <th scope="col"><?= $this->Paginator->sort('service_level_percent', 'Objectif QS (%)') ?></th>
            <th scope="col"><?= $this->Paginator->sort('service_level_seconds', 'Délai QS (sec)') ?></th>
            <th scope="col"><?= $this->Paginator->sort('shrinkage_percent', 'Shrinkage (%)') ?></th>
            <th scope="col"><?= $this->Paginator->sort('strict_work_hours', 'Journée stricte') ?></th>
            <th scope="col" class="actions"><?= __('Actions') ?></th>
        </tr>
        </thead>
        <tbody>
        <?php if (count($wfmSettings) === 0): ?>
            <tr>
                <td colspan="6" class="text-center py-5">
                    <div class="empty-state">
                        <i class="bi bi-gear" style="font-size: 4rem; color: #dee2e6;"></i>
                        <h4 class="mt-3 text-muted">Aucun profil trouvé</h4>
                        <p class="text-muted">Commencez par créer votre premier profil de paramètres WFM.</p>
                        <?= $this->Html->link(
                            '<i class="bi bi-plus-circle mr-2"></i> Créer le premier profil',
                            ['action' => 'add'],
                            ['class' => 'btn btn-primary mt-2', 'escape' => false]
                        ) ?>
                    </div>
                </td>
            </tr>
        <?php endif; ?>
        <?php foreach ($wfmSettings as $wfmSetting): ?>
            <tr>
                <td><strong><?= h($wfmSetting->name) ?></strong></td>
                <td>
                    <span class="badge badge-success">
                        <i class="bi bi-graph-up"></i> <?= $this->Number->format($wfmSetting->service_level_percent) ?>%
                    </span>
                </td>
                <td>
                    <span class="badge badge-info">
                        <i class="bi bi-clock"></i> <?= $this->Number->format($wfmSetting->service_level_seconds) ?>s
                    </span>
                </td>
                <td>
                    <span class="badge badge-warning">
                        <i class="bi bi-percent"></i> <?= $this->Number->format($wfmSetting->shrinkage_percent) ?>%
                    </span>
                </td>
                <td>
                    <?php
                    $isStrict = $wfmSetting->strict_work_hours === null || $wfmSetting->strict_work_hours;
                    $badgeClass = $isStrict ? 'badge-danger' : 'badge-success';
                    $icon = $isStrict ? 'bi-lock' : 'bi-unlock';
                    $label = $isStrict ? 'Strict' : 'Flexible';
                    ?>
                    <span class="badge <?= $badgeClass ?>">
                        <i class="bi <?= $icon ?>"></i> <?= $label ?>
                    </span>
                </td>
                <td class="actions">
                    <div class="dropdown actions-dropdown" data-entity-id="<?= (int)$wfmSetting->id ?>">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownActions<?= $wfmSetting->id ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="bi bi-three-dots-vertical"></i> Actions
                        </button>
                        <div class="dropdown-menu dropdown-menu-right actions-dropdown-menu" data-entity-id="<?= (int)$wfmSetting->id ?>" aria-labelledby="dropdownActions<?= $wfmSetting->id ?>">
                            <?= $this->Html->link(
                                '<i class="bi bi-eye mr-2"></i> Voir',
                                ['action' => 'view', $wfmSetting->id],
                                ['class' => 'dropdown-item', 'escape' => false]
                            ) ?>
                            <?= $this->Html->link(
                                '<i class="bi bi-pencil mr-2"></i> Modifier',
                                ['action' => 'edit', $wfmSetting->id],
                                ['class' => 'dropdown-item', 'escape' => false]
                            ) ?>
                            <div class="dropdown-divider"></div>
                            <?= $this->Form->postLink(
                                '<i class="bi bi-trash mr-2"></i> Supprimer',
                                ['action' => 'delete', $wfmSetting->id],
                                [
                                    'confirm' => 'Voulez-vous vraiment supprimer "' . h($wfmSetting->name) . '" ?',
                                    'class' => 'dropdown-item text-danger',
                                    'escape' => false
                                ]
                            ) ?>
                        </div>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="paginator mt-3">
    <ul class="pagination justify-content-center">
        <?= $this->Paginator->first('<< ' . 'Première') ?>
        <?= $this->Paginator->prev('< ' . 'Précédente') ?>
        <?= $this->Paginator->numbers() ?>
        <?= $this->Paginator->next('Suivante' . ' >') ?>
        <?= $this->Paginator->last('Dernière' . ' >>') ?>
    </ul>
    <p class="text-center"><?= $this->Paginator->counter('Page {{page}} sur {{pages}}, affichant {{current}} enregistrement(s) sur {{count}} au total') ?></p>
</div>
    </div>
</div>
