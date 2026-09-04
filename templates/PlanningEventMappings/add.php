<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\PlanningEventMapping $planningEventMapping
 */
?>
<?php $this->assign('title', 'Ajouter une correspondance'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app planning-event-mappings form content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-plus-circle"></i>
            Ajouter une correspondance
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-x-circle me-1"></i> Annuler',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <?= $this->Form->create($planningEventMapping) ?>
    <section class="crud-section">
        <h2 class="crud-section-title">Informations de la correspondance</h2>
        <div class="mb-3">
            <label class="form-label">Mots-clés (optionnel)</label>
            <?= $this->Form->control('keywords', [
                'label' => false,
                'class' => 'form-control',
                'placeholder' => 'Ex: Congés Principaux, télétravail, maladie...',
                'maxlength' => 255,
            ]) ?>
            <small class="form-text text-muted">
                Texte à rechercher dans les commentaires Excel. La recherche est insensible à la casse.
                Laissez vide si vous utilisez uniquement le code couleur.
            </small>
        </div>
        <div class="mb-3">
            <label class="form-label">Code couleur (optionnel)</label>
            <?= $this->Form->control('color_code', [
                'label' => false,
                'class' => 'form-control',
                'placeholder' => 'Ex: 99cc00 (sans #)',
                'maxlength' => 6,
                'pattern' => '[0-9a-fA-F]{6}',
            ]) ?>
            <small class="form-text text-muted">
                Code couleur hexadécimal de 6 caractères (sans #). Laissez vide si vous utilisez uniquement les mots-clés.
                <strong>Note :</strong> Au moins un des deux champs (mots-clés ou code couleur) doit être rempli.
            </small>
        </div>
        <div class="mb-3">
            <label class="form-label">Offre</label>
            <?= $this->Form->control('offer_id', [
                'type' => 'select',
                'options' => $offers,
                'label' => false,
                'class' => 'form-control',
                'required' => true,
                'empty' => '-- Sélectionner une offre --',
            ]) ?>
            <small class="form-text text-muted">
                Sélectionnez l'offre à associer lorsque cette correspondance est trouvée.
            </small>
        </div>
        <div class="mb-3">
            <label class="form-label">Priorité</label>
            <?= $this->Form->control('priority', [
                'type' => 'number',
                'label' => false,
                'class' => 'form-control',
                'required' => true,
                'min' => 0,
                'max' => 1000,
                'value' => 50,
            ]) ?>
            <small class="form-text text-muted">
                Priorité de test de la correspondance (0-1000). Plus la valeur est élevée, plus la correspondance sera testée en premier.
                Recommandé : 20 pour mots-clés, 10 pour codes couleur.
            </small>
        </div>
    </section>
    <div class="crud-actions-bar">
        <?= $this->Form->button('<i class="bi bi-save me-2"></i> Créer la correspondance', [
            'class' => 'btn btn-primary',
            'escapeTitle' => false,
        ]) ?>
        <?= $this->Html->link(
            '<i class="bi bi-x-circle me-2"></i> Annuler',
            ['action' => 'index'],
            ['class' => 'btn btn-outline-secondary', 'escape' => false]
        ) ?>
    </div>
    <?= $this->Form->end() ?>
</div>
