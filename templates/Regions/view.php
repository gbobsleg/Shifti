<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Region $region
 */
?>
<?php $this->assign('title', 'Détails Région : ' . h($region->name)); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app regions view content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-diagram-3"></i>
            <?= h($region->name) ?>
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-pencil me-1"></i> Modifier',
                ['action' => 'edit', $region->id],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list me-1"></i> Liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash me-1"></i> Supprimer',
                ['action' => 'delete', $region->id],
                [
                    'confirm' => 'Voulez-vous vraiment supprimer "' . h($region->name) . '" ?',
                    'class' => 'btn btn-outline-danger',
                    'escape' => false,
                ]
            ) ?>
        </div>
    </div>

    <section class="crud-section">
        <h2 class="crud-section-title">Informations</h2>
        <dl class="crud-fields">
            <div>
                <dt>Nom</dt>
                <dd><?= h($region->name) ?></dd>
            </div>
            <div>
                <dt>Numéro</dt>
                <dd><?= h($region->number) ?></dd>
            </div>
        </dl>
    </section>

    <section class="crud-section">
        <h2 class="crud-section-title">
            Sites Associés
            <?php if (!empty($region->sites)): ?>
                (<?= count($region->sites) ?>)
            <?php endif; ?>
        </h2>
        <?php if (!empty($region->sites)): ?>
            <div class="table-responsive">
                <table class="table table-hover table-sm crud-table">
                    <thead>
                    <tr>
                        <th scope="col">Nom</th>
                        <th scope="col">Numéro</th>
                        <th scope="col" class="actions">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($region->sites as $site): ?>
                        <tr>
                            <td>
                                <?= $this->Html->link(
                                    $site->name,
                                    ['controller' => 'Sites', 'action' => 'view', $site->id],
                                    ['class' => 'crud-row-link']
                                ) ?>
                            </td>
                            <td><?= h($site->number) ?></td>
                            <td class="actions">
                                <?= $this->Html->link(
                                    '<i class="bi bi-pencil" aria-hidden="true"></i>',
                                    ['controller' => 'Sites', 'action' => 'edit', $site->id],
                                    [
                                        'class' => 'crud-action',
                                        'escape' => false,
                                        'title' => 'Modifier',
                                        'aria-label' => 'Modifier',
                                        'data-bs-toggle' => 'tooltip',
                                    ]
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted mb-0">Aucun site associé à cette région.</p>
        <?php endif; ?>
    </section>
</div>
