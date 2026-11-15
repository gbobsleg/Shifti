<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\CalendarException $exception
 */
$this->assign('title', 'Modifier une date du calendrier');
$this->extend('/layout/TwitterBootstrap/dashtron_fullwidth');
$date = $exception->date;
$ymd = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : (string)$date;
$year = $date instanceof \DateTimeInterface ? $date->format('Y') : date('Y');
?>
<div class="crud-app calendar-exceptions form content">
    <div class="crud-header">
        <h1><i class="bi bi-pencil"></i> <?= h($ymd) ?></h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                'Annuler',
                ['action' => 'index', '?' => ['year' => $year]],
                ['class' => 'btn btn-outline-secondary']
            ) ?>
        </div>
    </div>
    <?= $this->Form->create($exception) ?>
    <section class="crud-section">
        <p>Source : <?= $exception->source === 'legal_fr' ? 'férié légal' : 'manuel' ?>. La date ne change pas.</p>
        <div class="mb-3">
            <label class="form-label">Libellé</label>
            <?= $this->Form->control('label', [
                'label' => false,
                'class' => 'form-control',
                'required' => true,
            ]) ?>
        </div>
        <div class="form-check mb-3">
            <?= $this->Form->control('is_open', [
                'type' => 'checkbox',
                'label' => 'Jour ouvert',
                'class' => 'form-check-input',
                'checked' => (bool)$exception->is_open,
            ]) ?>
        </div>
        <p class="text-muted">Exemple : décocher le lundi de Pentecôte le laisse fermé. Le cocher le rend ouvrable.</p>
        <?= $this->Form->button('Enregistrer', ['class' => 'btn btn-primary']) ?>
    </section>
    <?= $this->Form->end() ?>
</div>
