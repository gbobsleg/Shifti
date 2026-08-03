<?php
/**
 * @var \App\View\AppView $this
 * @var array $wfmSettingsList
 * @var array $scenariosList
 */
?>
<?php $this->assign('title', 'Générer un Planning'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="row">
    <div class="col-lg-8 offset-lg-2">

        <div class="card shadow">
            <div class="card-header d-flex justify-content-between align-items-center bg-light">
                <h3 class="mb-0">
                    <i class="bi bi-cpu text-primary"></i>
                    Générer un Planning
                </h3>
            </div>

                <?= $this->Form->create(null, ['id' => 'generateForm']) ?>
                <div class="card-body">
                    <?php // --- Info Card --- ?>
                    <div class="alert alert-info mb-4">
                        <h5 class="alert-heading">
                            <i class="bi bi-info-circle"></i> Processus d'optimisation
                        </h5>
                        <hr>
                        <p class="mb-1"><strong>1.</strong> Calcul du besoin (PHP/Erlang C)</p>
                        <p class="mb-1"><strong>2.</strong> Appel du solveur (Python/OR-Tools) pour assigner les agents</p>
                        <p class="mb-0"><strong>3.</strong> Sauvegarde du résultat dans le planning</p>
                    </div>

                    <?php // --- Section Paramètres --- ?>
                    <div class="card border-primary mb-4">
                        <div class="card-header bg-primary text-white">
                            <i class="bi bi-sliders"></i> Paramètres de génération
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-calendar-event"></i> Date à planifier</label>
                                <?= $this->Form->control('date', [
                                    'label' => false,
                                    'type' => 'date',
                                    'class' => 'form-control',
                                    'required' => true,
                                    'value' => (new DateTime('tomorrow'))->format('Y-m-d')
                                ]) ?>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-gear"></i> Profil de paramètres à utiliser</label>
                                <?= $this->Form->control('wfm_setting_id', [
                                    'label' => false,
                                    'options' => $wfmSettingsList,
                                    'class' => 'form-control',
                                    'required' => true
                                ]) ?>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-diagram-3"></i> Scénario (optionnel)</label>
                                <?= $this->Form->control('scenario_id', [
                                    'label' => false,
                                    'options' => $scenariosList,
                                    'empty' => '— Calculer en live —',
                                    'class' => 'form-control'
                                ]) ?>
                                <small class="text-muted">
                                    <i class="bi bi-info-circle"></i>
                                    Si aucun scénario n'est sélectionné, le besoin sera calculé en temps réel.
                                </small>
                            </div>
                        </div>
                    </div>

                    <?php // --- Section Options --- ?>
                    <div class="card border-info mb-4">
                        <div class="card-header bg-info text-white">
                            <i class="bi bi-sliders"></i> Options de génération
                        </div>
                        <div class="card-body">
                            <div class="form-check form-switch">
                                <?= $this->Form->control('ignore_fixed_activities', [
                                    'type' => 'checkbox',
                                    'label' => '<i class="bi bi-x-circle"></i> Ignorer les activités fixes — exclut les activités fixes (Accueil, RDV, etc.) de la génération',
                                    'class' => 'form-check-input',
                                    'escape' => false,
                                    'templates' => [
                                        'inputContainer' => '<div class="form-check">{{content}}</div>',
                                        'checkboxFormGroup' => '{{input}}<label class="form-check-label" for="{{name}}">{{label}}</label>'
                                    ]
                                ]) ?>
                            </div>
                            <div class="form-check form-switch mt-2">
                                <?= $this->Form->control('ignore_forecast_solver', [
                                    'type' => 'checkbox',
                                    'label' => '<i class="bi bi-x-circle"></i> Ignorer la Passe 2 (forecast) — ne calculer que les activités fixes',
                                    'class' => 'form-check-input',
                                    'escape' => false,
                                    'templates' => [
                                        'inputContainer' => '<div class="form-check">{{content}}</div>',
                                        'checkboxFormGroup' => '{{input}}<label class="form-check-label" for="{{name}}">{{label}}</label>'
                                    ]
                                ]) ?>
                            </div>
                            <div class="form-check form-switch mt-2">
                                <?= $this->Form->control('debug_solvers', [
                                    'type' => 'checkbox',
                                    'label' => '<i class="bi bi-bug"></i> Activer les logs détaillés des solveurs (mode debug, très verbeux)',
                                    'class' => 'form-check-input',
                                    'escape' => false,
                                    'templates' => [
                                        'inputContainer' => '<div class="form-check">{{content}}</div>',
                                        'checkboxFormGroup' => '{{input}}<label class="form-check-label" for="{{name}}">{{label}}</label>'
                                    ]
                                ]) ?>
                                <small class="text-muted d-block">
                                    À n’activer qu’en cas de diagnostic : les journaux peuvent devenir très volumineux.
                                </small>
                            </div>
                        </div>
                    </div>

                    <?= $this->Form->button('<i class="bi bi-robot mr-2"></i> Lancer la génération', [
                        'type' => 'submit',
                        'class' => 'btn btn-success btn-lg w-100',
                        'escapeTitle' => false
                    ]) ?>
                </div>
                <?= $this->Form->end() ?>

                <div id="loadingIndicator" class="card-body text-center p-5 d-none">
                    <div class="spinner-border text-success" style="width: 4rem; height: 4rem;" role="status">
                        <span class="sr-only">Chargement...</span>
                    </div>
                    <h3 class="mt-4 text-success">
                        <i class="bi bi-gear-fill"></i> Génération en cours...
                    </h3>
                    <p id="progressText" class="text-muted mt-3">
                        <i class="bi bi-cpu"></i> Le solveur est en train de calculer...<br>
                        <i class="bi bi-clock"></i> Cela peut prendre entre 30 secondes et 5 minutes.
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

            </div>
        </div>
    </div>

<?php $this->Html->scriptStart(['block' => true]); ?>
    // On attend que le DOM (et jQuery) soit prêt
    $(document).ready(function() {
        // On écoute la soumission du formulaire
        $('#generateForm').on('submit', function() {
            // On cache le formulaire
            $(this).hide();
            
            // On affiche le bloc de chargement (en retirant la classe d-none)
            $('#loadingIndicator').removeClass('d-none');
        });
    });
<?php $this->Html->scriptEnd(); ?>
