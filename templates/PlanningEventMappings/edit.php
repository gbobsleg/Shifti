<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\PlanningEventMapping $planningEventMapping
 */
?>
<?php $this->assign('title', 'Modifier Mapping'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="planning-event-mappings form content card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-pencil text-warning"></i>
            Modifier un mapping
        </h3>
        <div>
            <?= $this->Html->link(
                '<i class="bi bi-x-circle mr-1"></i> Annuler',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div class="card-body">
        <?= $this->Form->create($planningEventMapping) ?>
        
        <div class="card border-warning mb-4">
            <div class="card-header bg-warning text-dark">
                <i class="bi bi-link-45deg"></i> Informations du mapping
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label font-weight-bold">
                        <i class="bi bi-code"></i> Mots-clés (optionnel)
                    </label>
                    <?= $this->Form->control('keywords', [
                        'label' => false,
                        'class' => 'form-control',
                        'placeholder' => 'Ex: Congés Principaux, télétravail, maladie...',
                        'maxlength' => 255
                    ]) ?>
                    <small class="form-text text-muted">
                        <i class="bi bi-info-circle"></i> 
                        Texte à rechercher dans les commentaires Excel. La recherche est insensible à la casse. 
                        Laissez vide si vous utilisez uniquement le code couleur.
                    </small>
                </div>

                <div class="mb-3">
                    <label class="form-label font-weight-bold">
                        <i class="bi bi-palette"></i> Code couleur (optionnel)
                    </label>
                    <?= $this->Form->control('color_code', [
                        'label' => false,
                        'class' => 'form-control',
                        'placeholder' => 'Ex: 99cc00 (sans #)',
                        'maxlength' => 6,
                        'pattern' => '[0-9a-fA-F]{6}'
                    ]) ?>
                    <small class="form-text text-muted">
                        <i class="bi bi-info-circle"></i> 
                        Code couleur hexadécimal de 6 caractères (sans #). Laissez vide si vous utilisez uniquement les mots-clés.
                        <strong>Note :</strong> Au moins un des deux champs (mots-clés ou code couleur) doit être rempli.
                    </small>
                </div>

                <div class="mb-3">
                    <label class="form-label font-weight-bold">
                        <i class="bi bi-tag"></i> Offre
                    </label>
                    <?= $this->Form->control('offer_id', [
                        'type' => 'select',
                        'options' => $offers,
                        'label' => false,
                        'class' => 'form-control',
                        'required' => true,
                        'empty' => '-- Sélectionner une offre --'
                    ]) ?>
                    <small class="form-text text-muted">
                        <i class="bi bi-info-circle"></i> 
                        Sélectionnez l'offre à associer lorsque ce mapping est trouvé.
                    </small>
                </div>

                <div class="mb-3">
                    <label class="form-label font-weight-bold">
                        <i class="bi bi-sort-numeric-down"></i> Priorité
                    </label>
                    <?= $this->Form->control('priority', [
                        'type' => 'number',
                        'label' => false,
                        'class' => 'form-control',
                        'required' => true,
                        'min' => 0,
                        'max' => 1000
                    ]) ?>
                    <small class="form-text text-muted">
                        <i class="bi bi-info-circle"></i> 
                        Priorité de test du mapping (0-1000). Plus la valeur est élevée, plus le mapping sera testé en premier. 
                        Recommandé : 20 pour mots-clés, 10 pour codes couleur.
                    </small>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <?= $this->Form->button('<i class="bi bi-save mr-2"></i> Sauvegarder', [
                'class' => 'btn btn-success mr-3',
                'escapeTitle' => false
            ]) ?>
            <?= $this->Html->link(
                '<i class="bi bi-x-circle mr-2"></i> Annuler',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
        
        <?= $this->Form->end() ?>
    </div>
</div>
