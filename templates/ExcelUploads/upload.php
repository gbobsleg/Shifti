<?php
/**
 * @var \App\View\AppView $this
 * @var array $unrecognizedAgents Agents non reconnus lors du dernier upload
 */
?>
<?php $this->assign('title', 'Uploader un fichier Excel'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app form excel-uploads upload content">
    <div class="crud-header">
        <h1>Uploader un fichier Excel</h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-x-circle me-1"></i> Annuler',
                ['controller' => 'Pages', 'action' => 'display', 'admin'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>

    <section class="crud-section">
        <h2 class="crud-section-title">Format du fichier Excel</h2>
        <p class="mb-2">
            Fichiers acceptés : <strong>XML Excel 2003</strong> (<code>.xml</code> ou <code>.xls</code>).
            Le fichier doit contenir un planning avec les agents, leurs absences et leurs jours de télétravail.
        </p>
        <p class="crud-header-meta mb-0">
            Les fichiers Excel binaires (.xls BIFF ou .xlsx) ne sont pas supportés.
        </p>
    </section>

    <?php if (!empty($unrecognizedAgents)): ?>
    <div class="crud-warn">
        <strong><?= count($unrecognizedAgents) ?> agent(s) non reconnu(s)</strong>
        — matricules absents de la base, données non importées.
        <div class="table-responsive mt-2" style="max-height: 250px; overflow-y: auto;">
            <table class="table table-sm crud-table mb-0 small">
                <thead>
                    <tr>
                        <th>Nom dans le fichier</th>
                        <th>Code/Matricule</th>
                        <th>Absences</th>
                        <th>Télétravail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($unrecognizedAgents as $agent): ?>
                    <tr>
                        <td><strong><?= h($agent['name']) ?></strong></td>
                        <td><?= h($agent['code'] ?: '-') ?></td>
                        <td class="text-center"><?= (int)$agent['absences_count'] ?></td>
                        <td class="text-center"><?= (int)$agent['remote_work_count'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="mb-0 mt-2">
            <?= $this->Html->link('Gestion des utilisateurs', ['controller' => 'Users', 'action' => 'index']) ?>
        </p>
    </div>
    <?php endif; ?>

    <section class="crud-section">
        <h2 class="crud-section-title">Fichier à uploader</h2>
        <?= $this->Form->create(null, [
            'type' => 'file',
            'url' => ['action' => 'upload'],
            'id' => 'uploadForm',
        ]) ?>

        <div class="mb-3">
            <label class="form-label" for="file">Fichier Excel</label>
            <?= $this->Form->control('file', [
                'type' => 'file',
                'label' => false,
                'class' => 'form-control',
                'required' => true,
                'accept' => '.xml,.xls',
                'id' => 'file',
                'templates' => ['inputContainer' => '{{content}}'],
            ]) ?>
            <small class="form-text text-muted">Formats acceptés : <code>.xml</code> ou <code>.xls</code> (XML Excel 2003)</small>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label" for="context-month">Mois du planning</label>
                <?= $this->Form->control('context_month', [
                    'type' => 'select',
                    'label' => false,
                    'class' => 'form-control',
                    'required' => true,
                    'id' => 'context-month',
                    'templates' => ['inputContainer' => '{{content}}'],
                    'options' => [
                        1 => 'Janvier',
                        2 => 'Février',
                        3 => 'Mars',
                        4 => 'Avril',
                        5 => 'Mai',
                        6 => 'Juin',
                        7 => 'Juillet',
                        8 => 'Août',
                        9 => 'Septembre',
                        10 => 'Octobre',
                        11 => 'Novembre',
                        12 => 'Décembre',
                    ],
                    'default' => (int)date('n'),
                ]) ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="context-year">Année du planning</label>
                <?= $this->Form->control('context_year', [
                    'type' => 'number',
                    'label' => false,
                    'class' => 'form-control',
                    'required' => true,
                    'id' => 'context-year',
                    'templates' => ['inputContainer' => '{{content}}'],
                    'min' => 2000,
                    'max' => 2100,
                    'default' => (int)date('Y'),
                ]) ?>
            </div>
        </div>

        <div class="crud-actions-bar">
            <?= $this->Form->button('Uploader et analyser', [
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
</div>
