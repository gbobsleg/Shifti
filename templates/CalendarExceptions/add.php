<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\CalendarException $exception
 */
$this->assign('title', 'Ajouter une date au calendrier');
$this->extend('/layout/TwitterBootstrap/dashtron_fullwidth');
$year = (int)($this->request->getQuery('year') ?: date('Y'));
?>
<div class="crud-app calendar-exceptions form content">
    <div class="crud-header">
        <h1><i class="bi bi-plus-circle"></i> Ajouter une date</h1>
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
        <div class="mb-3">
            <label class="form-label">Date</label>
            <?= $this->Form->control('date', [
                'type' => 'date',
                'label' => false,
                'class' => 'form-control',
                'required' => true,
            ]) ?>
        </div>
        <div class="mb-3">
            <label class="form-label">Libellé</label>
            <?= $this->Form->control('label', [
                'label' => false,
                'class' => 'form-control',
                'required' => true,
                'placeholder' => 'Ex. Pont de l\'Ascension',
            ]) ?>
        </div>
        <div class="form-check mb-3">
            <?= $this->Form->control('is_open', [
                'type' => 'checkbox',
                'label' => 'Jour ouvert (sinon : fermeture)',
                'class' => 'form-check-input',
            ]) ?>
        </div>
        <p class="text-muted">
            Décochez pour un pont ou une fermeture. Cochez seulement pour forcer l'ouverture d'un jour habituellement fermé.
        </p>
        <?= $this->Form->button('Enregistrer', ['class' => 'btn btn-primary']) ?>
    </section>
    <?= $this->Form->end() ?>
</div>
