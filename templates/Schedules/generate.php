<?php
/**
 * @var \App\View\AppView $this
 * @var array $wfmSettingsList
 * @var array $scenariosList
 */
?>
<?php $this->assign('title', 'Générer un Planning'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app form schedules generate content">
    <div class="crud-header">
        <div>
            <h1>Générer un planning</h1>
            <p class="crud-header-meta">Test 1 jour (legacy) — préférer Générations de planning.</p>
        </div>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-arrow-left me-1"></i> Administration',
                ['controller' => 'Pages', 'action' => 'display', 'admin'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>

    <?= $this->Form->create(null, ['id' => 'generateForm']) ?>

    <section class="crud-section">
        <h2 class="crud-section-title">Processus</h2>
        <ol class="mb-0">
            <li>Calcul du besoin (PHP / Erlang C)</li>
            <li>Appel du solveur (Python / OR-Tools) pour assigner les agents</li>
            <li>Sauvegarde du résultat dans le planning</li>
        </ol>
    </section>

    <section class="crud-section">
        <h2 class="crud-section-title">Paramètres de génération</h2>
        <div class="mb-3">
            <label class="form-label" for="date">Date à planifier</label>
            <?= $this->Form->control('date', [
                'label' => false,
                'type' => 'date',
                'class' => 'form-control',
                'required' => true,
                'id' => 'date',
                'templates' => ['inputContainer' => '{{content}}'],
                'value' => (new DateTime('tomorrow'))->format('Y-m-d'),
            ]) ?>
        </div>
        <div class="mb-3">
            <label class="form-label" for="wfm-setting-id">Profil de paramètres à utiliser</label>
            <?= $this->Form->control('wfm_setting_id', [
                'label' => false,
                'options' => $wfmSettingsList,
                'class' => 'form-control',
                'required' => true,
                'id' => 'wfm-setting-id',
                'templates' => ['inputContainer' => '{{content}}'],
            ]) ?>
        </div>
        <div class="mb-3">
            <label class="form-label" for="scenario-id">Scénario (optionnel)</label>
            <?= $this->Form->control('scenario_id', [
                'label' => false,
                'options' => $scenariosList,
                'empty' => '— Calculer en live —',
                'class' => 'form-control',
                'id' => 'scenario-id',
                'templates' => ['inputContainer' => '{{content}}'],
            ]) ?>
            <small class="text-muted">Si aucun scénario n'est sélectionné, le besoin sera calculé en temps réel.</small>
        </div>
    </section>

    <section class="crud-section">
        <h2 class="crud-section-title">Options de génération</h2>
        <div class="form-check form-switch">
            <?= $this->Form->control('ignore_fixed_activities', [
                'type' => 'checkbox',
                'label' => 'Ignorer les activités fixes — exclut les activités fixes (Accueil, RDV, etc.) de la génération',
                'class' => 'form-check-input',
                'templates' => [
                    'inputContainer' => '<div class="form-check">{{content}}</div>',
                    'checkboxFormGroup' => '{{input}}<label class="form-check-label" for="{{name}}">{{label}}</label>',
                ],
            ]) ?>
        </div>
        <div class="form-check form-switch mt-2">
            <?= $this->Form->control('ignore_forecast_solver', [
                'type' => 'checkbox',
                'label' => 'Ignorer la Passe 2 (forecast) — ne calculer que les activités fixes',
                'class' => 'form-check-input',
                'templates' => [
                    'inputContainer' => '<div class="form-check">{{content}}</div>',
                    'checkboxFormGroup' => '{{input}}<label class="form-check-label" for="{{name}}">{{label}}</label>',
                ],
            ]) ?>
        </div>
        <div class="form-check form-switch mt-2">
            <?= $this->Form->control('debug_solvers', [
                'type' => 'checkbox',
                'label' => 'Activer les logs détaillés des solveurs (mode debug, très verbeux)',
                'class' => 'form-check-input',
                'templates' => [
                    'inputContainer' => '<div class="form-check">{{content}}</div>',
                    'checkboxFormGroup' => '{{input}}<label class="form-check-label" for="{{name}}">{{label}}</label>',
                ],
            ]) ?>
            <small class="text-muted d-block">À n’activer qu’en cas de diagnostic : les journaux peuvent devenir très volumineux.</small>
        </div>
    </section>

    <div class="crud-actions-bar">
        <?= $this->Form->button('Lancer la génération', [
            'type' => 'submit',
            'class' => 'btn btn-primary',
        ]) ?>
        <?= $this->Html->link(
            'Annuler',
            ['controller' => 'Pages', 'action' => 'display', 'admin'],
            ['class' => 'btn btn-outline-secondary']
        ) ?>
    </div>
    <?= $this->Form->end() ?>

    <div id="loadingIndicator" class="text-center p-5 d-none">
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="visually-hidden">Chargement...</span>
        </div>
        <p class="mt-3 mb-1">Génération en cours…</p>
        <p id="progressText" class="text-muted mt-2 mb-0">
            Le solveur calcule. Cela peut prendre entre 30 secondes et 5 minutes.
        </p>
        <div class="progress mt-4" style="height: 8px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated"
                 role="progressbar"
                 style="width: 100%;"
                 aria-valuenow="100"
                 aria-valuemin="0"
                 aria-valuemax="100"></div>
        </div>
    </div>
</div>

<?php $this->Html->scriptStart(['block' => true]); ?>
$(document).ready(function() {
    $('#generateForm').on('submit', function() {
        $(this).hide();
        $('#loadingIndicator').removeClass('d-none');
    });
});
<?php $this->Html->scriptEnd(); ?>
