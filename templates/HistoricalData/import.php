<?php
/**
 * @var \App\View\AppView $this
 * @var array $importLog
 * @var array $offers
 * @var bool $excludeNonWorkedDaysDefault
 * @var array $workedDays
 */
?>
<?php $this->assign('title', 'Importer les Données Historiques'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app form historical-data import content">
    <div class="crud-header">
        <h1>Importer les données historiques</h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-arrow-left me-1"></i> Retour Administration',
                ['controller' => 'Pages', 'action' => 'display', 'admin'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>

    <section class="crud-section">
        <h2 class="crud-section-title">Format du fichier CSV</h2>
        <p class="mb-2">
            Ligne d'en-tête obligatoire, noms de colonnes exacts (l'ordre est libre).
        </p>
        <p class="mb-2">
            Requises : <code>datetime_interval</code>, <code>call_volume</code>, <code>avg_handle_time_seconds</code>.
            Optionnelles : <code>offer_id</code> ou <code>offer_name</code> (plusieurs offres, sélection ci-dessous inutile).
        </p>
        <p class="crud-header-meta mb-0">
            <code>datetime_interval</code> : <code>DD/MM/YYYY HH:MM:SS</code> ou <code>DD/MM/YYYY HH:MM</code>.
            Délimiteurs <code>;</code> / tabulation / <code>,</code>. Encodages UTF-8 et UTF-16 — détection automatique.
        </p>
    </section>

    <section class="crud-section">
        <h2 class="crud-section-title">Comportement de l'import</h2>
        <ul class="mb-0">
            <li>Données existantes (même offre + même date/heure) : mises à jour</li>
            <li>Nouvelles données : insérées</li>
            <li>Pas de duplication — le même fichier peut être ré-importé pour corriger des erreurs</li>
        </ul>
        <p class="crud-header-meta mb-0 mt-2">Avec la colonne <code>offer_id</code>, toutes les offres peuvent partir d'un seul fichier.</p>
    </section>

    <section class="crud-section" id="import-form-panel">
        <h2 class="crud-section-title">Fichier à importer</h2>
        <?= $this->Form->create(null, [
            'type' => 'file',
            'id' => 'importForm',
        ]) ?>

        <div class="mb-3">
            <label class="form-label" for="offer-id">Offre concernée (optionnelle)</label>
            <?= $this->Form->control('offer_id', [
                'type' => 'select',
                'options' => $offers,
                'empty' => '-- Laisser vide si votre CSV contient la colonne offer_id --',
                'value' => $selectedOfferId,
                'class' => 'form-control',
                'label' => false,
                'required' => false,
                'id' => 'offer-id',
                'templates' => ['inputContainer' => '{{content}}'],
            ]) ?>
            <small class="text-muted">
                Sélectionnez une offre si le CSV n'a pas de colonne <code>offer_id</code>.
                Laissez vide s'il la contient déjà.
            </small>
        </div>

        <?php
        $dayNames = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', 7 => 'Dim'];
        $workedDaysList = array_map(fn($d) => $dayNames[$d] ?? $d, $workedDays);
        ?>
        <div class="mb-3 form-check">
            <?= $this->Form->checkbox('exclude_non_worked_days', [
                'id' => 'exclude_non_worked_days',
                'class' => 'form-check-input',
                'checked' => $excludeNonWorkedDaysDefault,
            ]) ?>
            <label class="form-check-label" for="exclude_non_worked_days">
                Exclure les jours non travaillés
            </label>
            <small class="text-muted d-block mt-1">
                Jours travaillés configurés : <strong><?= implode(', ', $workedDaysList) ?></strong>
                — modifiable dans
                <?= $this->Html->link('Paramètres WFM', ['controller' => 'WfmSettings', 'action' => 'edit', 1]) ?>.
            </small>
        </div>

        <div class="mb-3">
            <label class="form-label" for="uploaded-file">Fichier CSV</label>
            <?= $this->Form->control('uploaded_file', [
                'type' => 'file',
                'label' => false,
                'class' => 'form-control',
                'required' => true,
                'id' => 'uploaded-file',
                'templates' => ['inputContainer' => '{{content}}'],
            ]) ?>
            <small class="text-muted">Formats acceptés : .csv</small>
        </div>

        <div class="crud-actions-bar">
            <?= $this->Form->button('Lancer l\'importation', [
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
    </section>

    <div id="loadingIndicator" class="text-center p-5 d-none">
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="visually-hidden">Chargement...</span>
        </div>
        <p class="mt-3 mb-1">Importation en cours…</p>
        <p id="progressText" class="text-muted mt-2 mb-0">
            Merci de ne pas fermer cette page.
            Cela peut prendre plusieurs minutes selon la taille du fichier.
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

    <?php $this->Html->scriptStart(['block' => true]); ?>
    $(document).ready(function() {
        $('#importForm').on('submit', function() {
            $('#import-form-panel').hide();
            $('#loadingIndicator').removeClass('d-none');
        });
    });
    <?php $this->Html->scriptEnd(); ?>

    <?php if (!empty($importLog)): ?>
        <section class="crud-section">
            <h2 class="crud-section-title">Erreurs d'importation</h2>
            <div class="crud-warn">
                Certaines lignes n'ont pas pu être importées. Vérifiez le format et réessayez.
            </div>
            <div class="p-3" style="max-height: 400px; overflow-y: auto; background: var(--crud-bg, #f4f6f7); border: 1px solid var(--crud-border, #e2e8ea); border-radius: 6px;">
                <pre class="mb-0" style="font-size: 0.875rem;"><?php foreach ($importLog as $line) echo h($line) . "\n"; ?></pre>
            </div>
            <div class="crud-actions-bar">
                <?= $this->Html->link(
                    'Réessayer l\'importation',
                    ['action' => 'import'],
                    ['class' => 'btn btn-primary']
                ) ?>
            </div>
        </section>
    <?php endif; ?>
</div>
