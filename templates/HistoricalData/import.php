<?php
/**
 * @var \App\View\AppView $this
 * @var array $importLog // Variable passée par le contrôleur
 * @var array $offers // Liste des offres
 * @var bool $excludeNonWorkedDaysDefault // État par défaut de la checkbox
 * @var array $workedDays // Jours travaillés (1=Lundi à 7=Dimanche)
 */
?>
<?php $this->assign('title', 'Importer les Données Historiques'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="historical-data import content card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-upload text-primary"></i>
            Importer les Données Historiques
        </h3>
        <div>
            <?= $this->Html->link(
                '<i class="bi bi-arrow-left mr-1"></i> Retour Administration',
                ['controller' => 'Pages', 'action' => 'display', 'admin'],
                ['class' => 'btn btn-secondary btn-sm', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div class="card-body">

        <?php // --- Info Card --- ?>
        <div class="alert alert-info mb-3">
            <h5 class="alert-heading">
                <i class="bi bi-info-circle"></i> Format du fichier CSV
            </h5>
            <hr>
            <p class="mb-2">
                Le fichier CSV doit contenir une ligne d'en-tête avec les noms de colonnes exacts ci-dessous.<br>
                L'ordre des colonnes n'a pas d'importance, seuls les noms doivent correspondre.
            </p>
            <p class="mb-2">
                <strong>Colonnes requises :</strong><br>
                <span class="badge badge-secondary mr-1">datetime_interval</span>
                <span class="badge badge-secondary mr-1">call_volume</span>
                <span class="badge badge-secondary mr-1">avg_handle_time_seconds</span>
            </p>
            <p class="mb-2">
                <strong>Colonnes optionnelles :</strong><br>
                <span class="badge badge-info mr-1">offer_id</span> ou
                <span class="badge badge-info mr-1">offer_name</span>
                <small class="text-muted">
                    (Si présente, permet d'importer <strong>plusieurs offres</strong> dans le même fichier et rend inutile la sélection d'offre ci-dessous.)
                </small>
            </p>
            <p class="mb-2">
                <strong>Format datetime_interval :</strong> 
                <code>DD/MM/YYYY HH:MM:SS</code> ou <code>DD/MM/YYYY HH:MM</code>
                <small class="text-muted">(exemples : 15/01/2024 09:30:00 ou 15/01/2024 09:30)</small>
            </p>
            <p class="mb-2">
                <strong>Délimiteurs supportés :</strong> 
                <code>;</code> (point-virgule), <code>TAB</code> (tabulation), <code>,</code> (virgule)
                <small class="text-muted">— détection automatique</small>
            </p>
            <p class="mb-0">
                <strong>Encodages supportés :</strong> 
                <code>UTF-8</code>, <code>UTF-16</code> (Excel)
                <small class="text-muted">— conversion automatique</small>
            </p>
        </div>

        <?php // --- Comportement Import --- ?>
        <div class="alert alert-success mb-4">
            <h5 class="alert-heading">
                <i class="bi bi-arrow-repeat"></i> Comportement de l'import
            </h5>
            <hr>
            <p class="mb-2">
                <strong>L'import fonctionne en mode mise à jour intelligente :</strong>
            </p>
            <ul class="mb-2">
                <li>
                    <i class="bi bi-pencil-square text-warning"></i> 
                    <strong>Données existantes</strong> (même offre + même date/heure) : <span class="badge badge-warning">Mises à jour</span>
                </li>
                <li>
                    <i class="bi bi-plus-circle text-success"></i> 
                    <strong>Nouvelles données</strong> : <span class="badge badge-success">Insérées</span>
                </li>
                <li>
                    <i class="bi bi-shield-check text-info"></i> 
                    <strong>Aucune duplication possible</strong> - vous pouvez ré-importer le même fichier pour corriger des erreurs
                </li>
            </ul>
            <div class="alert alert-light border-left border-primary mb-0">
                <strong><i class="bi bi-lightbulb text-warning"></i> Astuce :</strong>
                Avec la colonne <code>offer_id</code>, importez toutes vos offres d'un coup !
            </div>
        </div>

        <?php // --- Section Upload --- ?>
        <div class="card border-primary mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-file-earmark-arrow-up"></i> Fichier à importer
            </div>
            <div class="card-body">
                <?= $this->Form->create(null, [
                    'type' => 'file',
                    'id' => 'importForm'
                ]) ?>

                <div class="mb-4">
                    <label class="form-label font-weight-bold">
                        <i class="bi bi-basket-fill text-primary"></i> Offre concernée
                        <span class="badge badge-secondary ml-1">Optionnelle</span>
                    </label>
                    <?= $this->Form->control('offer_id', [
                        'type' => 'select',
                        'options' => $offers,
                        'empty' => '-- Laisser vide si votre CSV contient la colonne offer_id --',
                        'value' => $selectedOfferId,
                        'class' => 'form-control',
                        'label' => false,
                        'required' => false
                    ]) ?>
                    <small class="text-muted">
                        <i class="bi bi-lightbulb text-warning"></i> 
                        <strong>Sélectionnez une offre</strong> si votre CSV ne contient PAS la colonne <code>offer_id</code>.<br>
                        <i class="bi bi-check-circle text-success"></i> 
                        <strong>Laissez vide</strong> si votre CSV contient déjà la colonne <code>offer_id</code>.
                    </small>
                </div>

                <?php
                // Construire la liste des jours travaillés pour l'affichage
                $dayNames = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', 7 => 'Dim'];
                $workedDaysList = array_map(fn($d) => $dayNames[$d] ?? $d, $workedDays);
                ?>
                <div class="mb-4">
                    <div class="custom-control custom-checkbox">
                        <?= $this->Form->checkbox('exclude_non_worked_days', [
                            'id' => 'exclude_non_worked_days',
                            'class' => 'custom-control-input',
                            'checked' => $excludeNonWorkedDaysDefault
                        ]) ?>
                        <label class="custom-control-label" for="exclude_non_worked_days">
                            <i class="bi bi-calendar-x text-warning"></i>
                            <strong>Exclure les jours non travaillés</strong>
                        </label>
                    </div>
                    <small class="text-muted d-block mt-1">
                        <i class="bi bi-info-circle"></i>
                        Jours travaillés configurés : <strong><?= implode(', ', $workedDaysList) ?></strong>
                        <br>
                        <i class="bi bi-gear text-secondary"></i>
                        <em>Modifiable dans <a href="<?= $this->Url->build(['controller' => 'WfmSettings', 'action' => 'edit', 1]) ?>">Paramètres WFM</a></em>
                    </small>
                </div>

                <div class="mb-3">
                    <label class="form-label font-weight-bold">
                        <i class="bi bi-file-earmark-spreadsheet"></i> Fichier CSV
                    </label>
                    <?= $this->Form->control('uploaded_file', [
                        'type' => 'file',
                        'label' => false,
                        'class' => 'form-control',
                        'required' => true
                    ]) ?>
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i> Formats acceptés : .csv
                    </small>
                </div>

                <div class="mt-3">
                    <?= $this->Form->button('<i class="bi bi-cloud-upload mr-2"></i> Lancer l\'importation', [
                        'type' => 'submit',
                        'class' => 'btn btn-success',
                        'escapeTitle' => false
                    ]) ?>
                    <?= $this->Html->link(
                        '<i class="bi bi-x-circle mr-2"></i> Annuler',
                        ['controller' => 'Pages', 'action' => 'display', 'admin'],
                        ['class' => 'btn btn-outline-secondary', 'escape' => false]
                    ) ?>
                </div>

                <?= $this->Form->end() ?>
            </div>
        </div>

        <div id="loadingIndicator" class="text-center p-5 d-none">
            <div class="spinner-border text-success" style="width: 4rem; height: 4rem;" role="status">
                <span class="sr-only">Chargement...</span>
            </div>
            <h3 class="mt-4 text-success">
                <i class="bi bi-cloud-arrow-up-fill"></i> Importation en cours...
            </h3>
            <p id="progressText" class="text-muted mt-3">
                <i class="bi bi-clock"></i> Merci de ne pas fermer cette page.<br>
                Cela peut prendre plusieurs minutes selon la taille du fichier.
            </p>
            <div class="progress mt-4" style="height: 35px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                     role="progressbar" 
                     style="width: 100%; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; font-weight: 500;"
                     aria-valuenow="100" 
                     aria-valuemin="0" 
                     aria-valuemax="100">
                    <span style="display: inline-flex; align-items: center;">
                        <i class="bi bi-hourglass-split" style="margin-right: 8px;"></i>
                        <span>Traitement en cours...</span>
                    </span>
                </div>
            </div>
        </div>

        <?php $this->Html->scriptStart(['block' => true]); ?>
        $(document).ready(function() {
            $('#importForm').on('submit', function() {
                // Cache le formulaire
                $(this).closest('.card.border-primary').hide();
                
                // Affiche le bloc de chargement
                $('#loadingIndicator').removeClass('d-none');
            });
        });
        <?php $this->Html->scriptEnd(); ?>

        <?php // --- Section d'erreurs d'import --- ?>
        <?php if (!empty($importLog)): ?>
            <div class="card border-danger mt-4">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-exclamation-triangle-fill"></i> Erreurs d'importation
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-info-circle"></i>
                        <strong>Attention :</strong> Certaines lignes n'ont pas pu être importées. Veuillez vérifier le format de ces lignes et réessayer.
                    </div>
                    <div class="bg-light p-3 rounded" style="max-height: 400px; overflow-y: auto;">
                        <pre class="mb-0" style="font-size: 0.875rem;"><?php foreach ($importLog as $line) echo h($line) . "\n"; ?></pre>
                    </div>
                    <div class="mt-3">
                        <?= $this->Html->link(
                            '<i class="bi bi-arrow-repeat mr-2"></i> Réessayer l\'importation',
                            ['action' => 'import'],
                            ['class' => 'btn btn-primary', 'escape' => false]
                        ) ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
