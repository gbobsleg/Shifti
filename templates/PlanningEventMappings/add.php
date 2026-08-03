<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\PlanningEventMapping $planningEventMapping
 */
?>
<?php $this->assign('title', 'Ajouter Mapping'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="planning-event-mappings form content card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-plus-circle text-success"></i>
            Ajouter un mapping
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
        <?php // --- Info Card --- ?>
        <div class="alert alert-info mb-4">
            <h5 class="alert-heading">
                <i class="bi bi-info-circle"></i> Comment créer un mapping ?
            </h5>
            <hr>
            <p class="mb-2">
                Un mapping associe un <strong>pattern</strong> (mots-clés ou code couleur) présent dans les commentaires Excel 
                à une <strong>offre</strong> dans la base de données.
            </p>
            <p class="mb-2">
                <strong>Mots-clés :</strong> Texte à rechercher dans les commentaires Excel (ex: "Congés Principaux", "télétravail").
                La recherche est insensible à la casse.
            </p>
            <p class="mb-2">
                <strong>Code couleur :</strong> Code hexadécimal de 6 caractères (sans #) pour matcher une couleur de cellule Excel.
            </p>
            <p class="mb-0">
                <strong>Priorité :</strong> Plus la priorité est élevée, plus le mapping sera testé en premier. 
                Les mots-clés ont généralement une priorité plus élevée que les codes couleur.
            </p>
        </div>

        <?= $this->Form->create($planningEventMapping) ?>
        
        <div class="card border-primary mb-4">
            <div class="card-header bg-primary text-white">
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
                        'max' => 1000,
                        'value' => 50
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
            <?= $this->Form->button('<i class="bi bi-save mr-2"></i> Créer le mapping', [
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
