<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\PlanningEventMapping $planningEventMapping
 */
?>
<?php $this->assign('title', 'Détails Mapping'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="absence-mappings view content card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-eye text-info"></i>
            Détails du mapping
        </h3>
        <div class="btn-toolbar">
            <div class="btn-group mr-2">
                <?= $this->Html->link(
                    '<i class="bi bi-pencil mr-1"></i> Modifier',
                    ['action' => 'edit', $planningEventMapping->id],
                    ['class' => 'btn btn-warning', 'escape' => false]
                ) ?>
            </div>
            <div class="btn-group">
                <?= $this->Html->link(
                    '<i class="bi bi-arrow-left mr-1"></i> Retour',
                    ['action' => 'index'],
                    ['class' => 'btn btn-outline-secondary', 'escape' => false]
                ) ?>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-8">
                <div class="card border-primary mb-4">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-link-45deg"></i> Informations du mapping
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-4">
                                <i class="bi bi-code"></i> Pattern Excel
                            </dt>
                            <dd class="col-sm-8">
                                <?php if (!empty($planningEventMapping->keywords)): ?>
                                    <code class="text-primary" style="font-size: 1.1rem;"><?= h($planningEventMapping->keywords) ?></code>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </dd>

                            <dt class="col-sm-4">
                                <i class="bi bi-tag"></i> Offre d'absence
                            </dt>
                            <dd class="col-sm-8">
                                <?php if ($planningEventMapping->hasValue('offer')): ?>
                                    <span class="badge badge-warning badge-lg">
                                        <i class="bi bi-tag"></i> <?= h($planningEventMapping->offer->name) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </dd>

                            <dt class="col-sm-4">
                                <i class="bi bi-sort-numeric-down"></i> Priorité
                            </dt>
                            <dd class="col-sm-8">
                                <span class="badge badge-<?= $planningEventMapping->priority >= 80 ? 'success' : ($planningEventMapping->priority >= 50 ? 'warning' : 'secondary') ?> badge-lg">
                                    <?= $this->Number->format($planningEventMapping->priority) ?>
                                </span>
                                <small class="text-muted ml-2">
                                    <?php if ($planningEventMapping->priority >= 80): ?>
                                        (Priorité élevée - testé en premier)
                                    <?php elseif ($planningEventMapping->priority >= 50): ?>
                                        (Priorité moyenne)
                                    <?php else: ?>
                                        (Priorité basse - testé en dernier)
                                    <?php endif; ?>
                                </small>
                            </dd>

                            <dt class="col-sm-4">
                                <i class="bi bi-calendar-plus"></i> Créé le
                            </dt>
                            <dd class="col-sm-8">
                                <?php if ($planningEventMapping->created): ?>
                                    <small><?= h($planningEventMapping->created->i18nFormat('dd/MM/yyyy à HH:mm')) ?></small>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </dd>

                            <dt class="col-sm-4">
                                <i class="bi bi-calendar-check"></i> Modifié le
                            </dt>
                            <dd class="col-sm-8">
                                <?php if ($planningEventMapping->modified): ?>
                                    <small><?= h($planningEventMapping->modified->i18nFormat('dd/MM/yyyy à HH:mm')) ?></small>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card border-info mb-4">
                    <div class="card-header bg-info text-white">
                        <i class="bi bi-info-circle"></i> Comment ça fonctionne ?
                    </div>
                    <div class="card-body">
                        <p class="small mb-2">
                            Lors de l'upload d'un fichier Excel, le système recherche dans chaque commentaire d'absence 
                            <?php if (!empty($planningEventMapping->keywords)): ?>
                                le mot-clé <code><?= h($planningEventMapping->keywords) ?></code>
                            <?php elseif (!empty($planningEventMapping->color_code)): ?>
                                le code couleur <code>#<?= h($planningEventMapping->color_code) ?></code>
                            <?php endif; ?>.
                        </p>
                        <p class="small mb-2">
                            Si le pattern est trouvé, l'absence sera automatiquement associée à l'offre 
                            <strong><?= h($planningEventMapping->offer->name ?? 'N/A') ?></strong>.
                        </p>
                        <p class="small mb-0">
                            La priorité <strong><?= $planningEventMapping->priority ?></strong> détermine l'ordre de test 
                            parmi tous les mappings (du plus élevé au plus bas).
                        </p>
                    </div>
                </div>

                <div class="card border-secondary">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="bi bi-tools"></i> Actions
                        </h6>
                    </div>
                    <div class="card-body">
                        <?= $this->Html->link(
                            '<i class="bi bi-pencil mr-2"></i> Modifier',
                            ['action' => 'edit', $planningEventMapping->id],
                            ['class' => 'btn btn-warning btn-block mb-2', 'escape' => false]
                        ) ?>
                        <?= $this->Form->postLink(
                            '<i class="bi bi-trash mr-2"></i> Supprimer',
                            ['action' => 'delete', $planningEventMapping->id],
                            [
                                'confirm' => 'Voulez-vous vraiment supprimer ce mapping ?',
                                'class' => 'btn btn-danger btn-block',
                                'escape' => false
                            ]
                        ) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
