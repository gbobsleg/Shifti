<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\CalendarException> $exceptions
 * @var int $year
 */
$this->assign('title', 'Calendrier d\'ouverture');
$this->extend('/layout/TwitterBootstrap/dashtron_fullwidth');
?>
<div class="crud-app calendar-exceptions index content">
    <div class="crud-header">
        <div>
            <h1><i class="bi bi-calendar-x"></i> Calendrier d'ouverture</h1>
            <p class="crud-header-meta">
                Fériés et fermetures. Un jour de la trame hebdomadaire reste visible dans la grille, mais n'est pas généré s'il est fermé.
            </p>
        </div>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle me-1"></i> Ajouter un pont',
                ['action' => 'add', '?' => ['year' => $year]],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
        </div>
    </div>

    <section class="crud-section">
        <div class="d-flex flex-wrap gap-3 align-items-end">
            <?= $this->Form->create(null, ['type' => 'get', 'class' => 'd-flex gap-2 align-items-end']) ?>
                <div>
                    <label class="form-label" for="year">Année</label>
                    <?= $this->Form->control('year', [
                        'type' => 'number',
                        'label' => false,
                        'value' => $year,
                        'min' => 2000,
                        'max' => 2100,
                        'class' => 'form-control',
                        'id' => 'year',
                    ]) ?>
                </div>
                <?= $this->Form->button('Afficher', ['class' => 'btn btn-outline-secondary']) ?>
            <?= $this->Form->end() ?>
            <?= $this->Form->create(null, [
                'url' => ['action' => 'seed'],
                'onsubmit' => "return confirm('Ajouter les fériés légaux manquants ? Les dates déjà présentes ne sont pas modifiées.');",
            ]) ?>
                <?= $this->Form->hidden('year', ['value' => $year]) ?>
                <?= $this->Form->button(
                    'Régénérer les fériés ' . (int)$year,
                    ['class' => 'btn btn-outline-primary']
                ) ?>
            <?= $this->Form->end() ?>
        </div>
    </section>

    <div class="table-responsive">
        <table class="table table-hover table-sm crud-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Libellé</th>
                    <th>Ouvert</th>
                    <th>Source</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (count($exceptions) === 0): ?>
                <tr>
                    <td colspan="5" class="crud-empty">Aucune date pour <?= (int)$year ?>.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($exceptions as $row): ?>
                <?php
                $date = $row->date;
                $ymd = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : (string)$date;
                ?>
                <tr>
                    <td><?= h($ymd) ?></td>
                    <td><?= h($row->label) ?></td>
                    <td><?= $row->is_open ? 'Oui' : 'Non' ?></td>
                    <td><?= $row->source === 'legal_fr' ? 'Férié légal' : 'Manuel' ?></td>
                    <td class="actions">
                        <?= $this->Form->postLink(
                            $row->is_open ? 'Fermer' : 'Ouvrir',
                            ['action' => 'toggle', $row->id],
                            ['class' => 'btn btn-sm btn-outline-secondary']
                        ) ?>
                        <?= $this->Html->link(
                            'Modifier',
                            ['action' => 'edit', $row->id],
                            ['class' => 'btn btn-sm btn-outline-primary']
                        ) ?>
                        <?php if ($row->source === 'manual'): ?>
                            <?= $this->Form->postLink(
                                'Supprimer',
                                ['action' => 'delete', $row->id],
                                [
                                    'class' => 'btn btn-sm btn-outline-danger',
                                    'confirm' => 'Supprimer cette fermeture manuelle ?',
                                ]
                            ) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
