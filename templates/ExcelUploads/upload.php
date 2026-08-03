<?php
/**
 * @var \App\View\AppView $this
 * @var array $unrecognizedAgents Agents non reconnus lors du dernier upload
 */
?>
<?php $this->assign('title', 'Uploader un fichier Excel'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="excel-uploads upload content card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-upload text-primary"></i>
            Uploader un fichier Excel
        </h3>
        <div>
            <?= $this->Html->link(
                '<i class="bi bi-x-circle mr-1"></i> Annuler',
                ['controller' => 'Pages', 'action' => 'display', 'admin'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div class="card-body">
        <?php // --- Info Card --- ?>
        <div class="alert alert-info mb-4">
            <h5 class="alert-heading">
                <i class="bi bi-info-circle"></i> Format du fichier Excel
            </h5>
            <hr>
            <p class="mb-2">
                Les fichiers <strong>XML Excel 2003</strong> sont acceptés (extensions <code>.xml</code> ou <code>.xls</code>).<br>
                Le fichier doit contenir un planning avec les agents, leurs absences et leurs jours de télétravail.
            </p>
            <p class="mb-0">
                <strong>Format attendu :</strong><br>
                <span class="badge badge-secondary mr-1">.xml</span>
                <span class="badge badge-secondary mr-1">.xls</span>
                <span class="badge badge-info ml-2">XML Excel 2003 / SpreadsheetML</span>
            </p>
            <p class="mb-0 mt-2 small text-muted">
                <i class="bi bi-exclamation-triangle"></i> Les fichiers Excel binaires (.xls BIFF ou .xlsx) ne sont pas supportés.
            </p>
        </div>

        <?php if (!empty($unrecognizedAgents)): ?>
        <!-- Liste des agents non reconnus du dernier upload -->
        <div class="alert alert-danger mb-4">
            <h5 class="alert-heading">
                <i class="bi bi-person-x"></i> Agents non reconnus (<?= count($unrecognizedAgents) ?>)
            </h5>
            <hr>
            <p class="mb-2">
                Les agents suivants ont été détectés dans le fichier mais leurs matricules ne correspondent à aucun utilisateur en base de données.
                Veuillez vérifier les matricules ou créer les utilisateurs manquants.
            </p>
            <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                <table class="table table-sm table-bordered mb-0 small bg-white">
                    <thead class="thead-light">
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
            <p class="small text-muted mt-2 mb-0">
                <i class="bi bi-lightbulb"></i> <strong>Conseil :</strong> Créez ces utilisateurs via 
                <?= $this->Html->link('Gestion des utilisateurs', ['controller' => 'Users', 'action' => 'index']) ?>
                en vous assurant que les matricules correspondent.
            </p>
        </div>
        <?php endif; ?>

        <?php // --- Section Upload --- ?>
        <div class="card border-primary mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-file-earmark-arrow-up"></i> Fichier à uploader
            </div>
            <div class="card-body">
                <?= $this->Form->create(null, [
                    'type' => 'file',
                    'url' => ['action' => 'upload'],
                    'id' => 'uploadForm'
                ]) ?>

                <div class="mb-3">
                    <label class="form-label font-weight-bold">
                        <i class="bi bi-file-earmark-spreadsheet"></i> Fichier Excel
                    </label>
                    <?= $this->Form->control('file', [
                        'type' => 'file',
                        'label' => false,
                        'class' => 'form-control',
                        'required' => true,
                        'accept' => '.xml,.xls'
                    ]) ?>
                    <small class="form-text text-muted">
                        <i class="bi bi-info-circle"></i> Formats acceptés : <code>.xml</code> ou <code>.xls</code> (XML Excel 2003)
                    </small>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label font-weight-bold">
                            <i class="bi bi-calendar-month"></i> Mois du planning
                        </label>
                        <?= $this->Form->control('context_month', [
                            'type' => 'select',
                            'label' => false,
                            'class' => 'form-control',
                            'required' => true,
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
                                12 => 'Décembre'
                            ],
                            'default' => (int)date('n')
                        ]) ?>
                        <small class="form-text text-muted">
                            <i class="bi bi-info-circle"></i> Mois du planning à importer
                        </small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label font-weight-bold">
                            <i class="bi bi-calendar-year"></i> Année du planning
                        </label>
                        <?= $this->Form->control('context_year', [
                            'type' => 'number',
                            'label' => false,
                            'class' => 'form-control',
                            'required' => true,
                            'min' => 2000,
                            'max' => 2100,
                            'default' => (int)date('Y')
                        ]) ?>
                        <small class="form-text text-muted">
                            <i class="bi bi-info-circle"></i> Année du planning à importer
                        </small>
                    </div>
                </div>

                <div class="mt-3">
                    <?= $this->Form->button('<i class="bi bi-cloud-upload mr-2"></i> Uploader et analyser', [
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
    </div>
</div>

