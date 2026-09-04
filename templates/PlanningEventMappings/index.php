<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\PlanningEventMapping> $planningEventMappings
 */
?>
<?php $this->assign('title', 'Correspondances absences'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app planning-event-mappings index content">
    <div class="crud-header">
        <div>
            <h1>
                <i class="bi bi-link-45deg"></i>
                Correspondances absences
            </h1>
            <p class="crud-header-meta"><?= $this->Paginator->counter('{{count}} correspondances') ?></p>
        </div>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle me-1"></i> Nouvelle correspondance',
                ['action' => 'add'],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-list-ul"></i> Raccourcis
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?= $this->Html->link(
                        '<i class="bi bi-file-earmark-excel me-2"></i> Importer Excel',
                        ['controller' => 'ExcelUploads', 'action' => 'upload'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                    <?= $this->Html->link(
                        '<i class="bi bi-basket-fill me-2"></i> Liste des Offres',
                        ['controller' => 'Offers', 'action' => 'index'],
                        ['class' => 'dropdown-item', 'escape' => false]
                    ) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-sm crud-table">
            <?php
            $columns = ['Mots-clés', 'Code couleur', 'Offre', 'Priorité', 'Modifié le', 'Actions'];
            $colCount = count($columns);
            ?>
            <thead>
            <tr>
                <th scope="col"><?= $this->Paginator->sort('keywords', $columns[0]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('color_code', $columns[1]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('offer_id', $columns[2]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('priority', $columns[3]) ?></th>
                <th scope="col"><?= $this->Paginator->sort('modified', $columns[4]) ?></th>
                <th scope="col" class="actions"><?= h($columns[5]) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($planningEventMappings) || $planningEventMappings->isEmpty()): ?>
                <tr>
                    <td colspan="<?= (int)$colCount ?>" class="crud-empty">
                        <p>Aucune correspondance.</p>
                        <?= $this->Html->link(
                            '<i class="bi bi-plus-circle me-1"></i> Créer une correspondance',
                            ['action' => 'add'],
                            ['class' => 'btn btn-sm btn-primary', 'escape' => false]
                        ) ?>
                    </td>
                </tr>
            <?php else: ?>
            <?php foreach ($planningEventMappings as $mapping): ?>
                <?php
                $rowLabel = !empty($mapping->keywords)
                    ? $mapping->keywords
                    : (!empty($mapping->color_code) ? '#' . $mapping->color_code : 'Correspondance #' . $mapping->id);
                ?>
                <tr>
                    <td>
                        <?= $this->Html->link(
                            $rowLabel,
                            ['action' => 'view', $mapping->id],
                            ['class' => 'crud-row-link']
                        ) ?>
                    </td>
                    <td>
                        <?php if (!empty($mapping->color_code)): ?>
                            <span class="crud-color">
                                <span class="crud-swatch" style="background-color: #<?= h($mapping->color_code) ?>"></span>
                                <span class="crud-color-hex">#<?= h($mapping->color_code) ?></span>
                            </span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?= $mapping->hasValue('offer') ? h($mapping->offer->name) : '—' ?></td>
                    <td><?= $this->Number->format($mapping->priority) ?></td>
                    <td>
                        <?php if ($mapping->modified):
                            $now = new \Cake\I18n\FrozenTime();
                            $diff = $now->diffInDays($mapping->modified);
                            if ($diff == 0) {
                                $timeAgo = "Aujourd'hui";
                            } elseif ($diff == 1) {
                                $timeAgo = 'Hier';
                            } elseif ($diff < 7) {
                                $timeAgo = 'Il y a ' . $diff . ' jours';
                            } elseif ($diff < 30) {
                                $weeks = (int)floor($diff / 7);
                                $timeAgo = 'Il y a ' . $weeks . ' semaine' . ($weeks > 1 ? 's' : '');
                            } else {
                                $months = (int)floor($diff / 30);
                                $timeAgo = 'Il y a ' . $months . ' mois';
                            }
                        ?>
                            <span data-bs-toggle="tooltip" title="<?= h($mapping->modified->i18nFormat('dd/MM/yyyy HH:mm')) ?>">
                                <?= h($timeAgo) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <?= $this->Html->link(
                            '<i class="bi bi-pencil" aria-hidden="true"></i>',
                            ['action' => 'edit', $mapping->id],
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
                            ['action' => 'delete', $mapping->id],
                            [
                                'confirm' => 'Voulez-vous vraiment supprimer cette correspondance ?',
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
            <?php endif; ?>
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
