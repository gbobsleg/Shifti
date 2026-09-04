<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\PlanningEventMapping $planningEventMapping
 */
$titleLabel = !empty($planningEventMapping->keywords)
    ? $planningEventMapping->keywords
    : (!empty($planningEventMapping->color_code) ? '#' . $planningEventMapping->color_code : 'Correspondance #' . $planningEventMapping->id);
$priorityHint = 'Priorité basse - testé en dernier';
if ($planningEventMapping->priority >= 80) {
    $priorityHint = 'Priorité élevée - testé en premier';
} elseif ($planningEventMapping->priority >= 50) {
    $priorityHint = 'Priorité moyenne';
}
?>
<?php $this->assign('title', 'Détails de la correspondance'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app planning-event-mappings view content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-link-45deg"></i>
            <?= h($titleLabel) ?>
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-pencil me-1"></i> Modifier',
                ['action' => 'edit', $planningEventMapping->id],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list me-1"></i> Liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash me-1"></i> Supprimer',
                ['action' => 'delete', $planningEventMapping->id],
                [
                    'confirm' => 'Voulez-vous vraiment supprimer cette correspondance ?',
                    'class' => 'btn btn-outline-danger',
                    'escape' => false,
                ]
            ) ?>
        </div>
    </div>

    <section class="crud-section">
        <h2 class="crud-section-title">Informations de la correspondance</h2>
        <dl class="crud-fields">
            <div>
                <dt>Motif Excel</dt>
                <dd>
                    <?php if (!empty($planningEventMapping->keywords)): ?>
                        <?= h($planningEventMapping->keywords) ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </dd>
            </div>
            <div>
                <dt>Offre d'absence</dt>
                <dd>
                    <?= $planningEventMapping->hasValue('offer')
                        ? h($planningEventMapping->offer->name)
                        : '—' ?>
                </dd>
            </div>
            <div>
                <dt>Priorité</dt>
                <dd>
                    <?= $this->Number->format($planningEventMapping->priority) ?>
                    <span class="text-muted">(<?= h($priorityHint) ?>)</span>
                </dd>
            </div>
            <div>
                <dt>Créé le</dt>
                <dd>
                    <?= $planningEventMapping->created
                        ? h($planningEventMapping->created->i18nFormat('dd/MM/yyyy à HH:mm'))
                        : '—' ?>
                </dd>
            </div>
            <div>
                <dt>Modifié le</dt>
                <dd>
                    <?= $planningEventMapping->modified
                        ? h($planningEventMapping->modified->i18nFormat('dd/MM/yyyy à HH:mm'))
                        : '—' ?>
                </dd>
            </div>
        </dl>
    </section>

    <section class="crud-section">
        <h2 class="crud-section-title">Comment ça fonctionne ?</h2>
        <p class="small text-muted mb-2">
            Lors de l'import d'un fichier Excel, le système recherche dans chaque commentaire d'absence
            <?php if (!empty($planningEventMapping->keywords)): ?>
                le mot-clé <strong><?= h($planningEventMapping->keywords) ?></strong>
            <?php elseif (!empty($planningEventMapping->color_code)): ?>
                le code couleur <strong>#<?= h($planningEventMapping->color_code) ?></strong>
            <?php endif; ?>.
        </p>
        <p class="small text-muted mb-2">
            Si le motif est trouvé, l'absence sera automatiquement associée à l'offre
            <strong><?= h($planningEventMapping->offer->name ?? '—') ?></strong>.
        </p>
        <p class="small text-muted mb-0">
            La priorité <strong><?= $planningEventMapping->priority ?></strong> détermine l'ordre de test
            parmi toutes les correspondances (du plus élevé au plus bas).
        </p>
    </section>
</div>
