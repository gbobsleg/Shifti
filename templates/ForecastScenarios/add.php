<?php
/** @var array $offers */
/** @var \App\Model\Entity\WfmSetting $wfm */
/** @var array $wfmSettingsList */
?>
<?php $this->assign('title', 'Nouveau Scénario'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>



<div class="forecast-scenarios add content card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="mb-0">
            <i class="bi bi-plus-circle text-success"></i>
            Nouveau Scénario
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
        <?= $this->Form->create(null) ?>
        
        <?php // ========== ACCORDION STRUCTURE ========== ?>
        <div class="accordion" id="scenarioAccordion">
            
            <?php // ========== 1. INFORMATIONS GÉNÉRALES ========== ?>
            <div class="card mb-3 border-primary">
                <div class="card-header bg-primary text-white" id="heading1">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-white text-decoration-none w-100 text-left" type="button" 
                                data-toggle="collapse" data-target="#collapse1" aria-expanded="true">
                            <i class="bi bi-info-circle"></i> 1. Informations Générales
                            <i class="bi bi-chevron-down float-right"></i>
                        </button>
                    </h5>
                </div>
                <div id="collapse1" class="collapse show" aria-labelledby="heading1" data-parent="#scenarioAccordion">
                    <div class="card-body">
                        <?= $this->Form->control('name', ['label' => 'Nom du scénario', 'class' => 'form-control']) ?>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <?= $this->Form->control('start_date', [
                                    'type' => 'date', 
                                    'label' => '<i class="bi bi-calendar-event"></i> Date de début', 
                                    'escape' => false,
                                    'class' => 'form-control'
                                ]) ?>
                            </div>
                            <div class="col-md-6">
                                <?= $this->Form->control('end_date', [
                                    'type' => 'date', 
                                    'label' => '<i class="bi bi-calendar-check"></i> Date de fin', 
                                    'escape' => false,
                                    'class' => 'form-control'
                                ]) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php // ========== 2. OFFRES CONCERNÉES & MÉTHODE PAR OFFRE ========== ?>
            <div class="card mb-3 border-success">
                <div class="card-header bg-success text-white" id="heading2">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-white text-decoration-none w-100 text-left collapsed" type="button" 
                                data-toggle="collapse" data-target="#collapse2" aria-expanded="false">
                            <i class="bi bi-tags"></i> 2. Offres Concernées
                            <i class="bi bi-chevron-down float-right"></i>
                        </button>
                    </h5>
                </div>
                <div id="collapse2" class="collapse" aria-labelledby="heading2" data-parent="#scenarioAccordion">
                    <div class="card-body">
                        <p class="small text-muted">
                            Sélectionnez les offres à inclure dans le scénario et choisissez, pour chacune,
                            si les prévisions doivent être calculées en <strong>Moyenne historique</strong> ou avec <strong>Prophet</strong>.
                        </p>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="width:60px;">Inclure</th>
                                        <th>Offre</th>
                                        <th style="width:220px;">Méthode de calcul</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($offers as $offerId => $offerName): ?>
                                        <tr>
                                            <td class="text-center">
                                                <input type="checkbox"
                                                       name="offer_ids[]"
                                                       value="<?= h($offerId) ?>">
                                            </td>
                                            <td><?= h($offerName) ?></td>
                                            <td>
                                                <?= $this->Form->select(
                                                    "offer_methods[$offerId]",
                                                    [
                                                        'historical' => 'Moyenne historique',
                                                        'prophet' => 'Prophet',
                                                    ],
                                                    [
                                                        'default' => $defaultMethodsByOffer[$offerId] ?? 'historical',
                                                        'class' => 'form-control form-control-sm',
                                                    ]
                                                ) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="alert alert-info mt-3 mb-2">
                            <small>
                                <i class="bi bi-info-circle"></i>
                                Pré-sélectionné selon les paramètres de l'offre. Toute modification ici sera figée pour ce scénario.
                            </small>
                        </div>
                        <small class="form-text text-muted">
                            <i class="bi bi-info-circle"></i>
                            Si vous laissez une offre décochée, elle ne sera pas incluse dans le scénario,
                            même si une méthode est sélectionnée.
                        </small>
                    </div>
                </div>
            </div>
            
            <?php // ========== 3. PARAMÈTRES WFM / PROFIL GLOBAL ========== ?>
            <div class="card mb-3 border-info">
                <div class="card-header bg-info text-white" id="heading3">
                    <h5 class="mb-0">
                        <button class="btn btn-link text-white text-decoration-none w-100 text-left collapsed" type="button" 
                                data-toggle="collapse" data-target="#collapse3" aria-expanded="false">
                            <i class="bi bi-graph-up-arrow"></i> 3. Méthode de Prévision & Paramètres Globaux Prophet
                            <i class="bi bi-chevron-down float-right"></i>
                        </button>
                    </h5>
        </div>
                <div id="collapse3" class="collapse" aria-labelledby="heading3" data-parent="#scenarioAccordion">
                    <div class="card-body">
                        
                        <?php // --- Paramètres WFM --- ?>
                        <h6 class="text-secondary mb-3"><i class="bi bi-gear"></i> Paramètres WFM</h6>
                        <div class="row mb-4">
            <div class="col-md-6">
                <?= $this->Form->control('wfm_setting_id', [
                    'label' => 'Profil WFM',
                    'options' => $wfmSettingsList,
                    'empty' => false,
                    'class' => 'form-control'
                ]) ?>
            </div>
            <div class="col-md-6">
                                <label class="form-label">Aperçu du profil</label>
                <div class="border rounded p-2 bg-light">
                    <div><strong>Heures:</strong> <?= h($wfm->day_start_time ?? 'N/A') ?> - <?= h($wfm->day_end_time ?? 'N/A') ?></div>
                    <div><strong>Shrinkage:</strong> <?= h($wfm->shrinkage_percent ?? 'N/A') ?><?= $wfm->shrinkage_percent !== null ? '%' : '' ?></div>
                    <div><strong>QS:</strong> 
                        <?= h($wfm->service_level_percent ?? 'N/A') ?><?= $wfm->service_level_percent !== null ? '%' : '' ?> 
                        / <?= h($wfm->service_level_seconds ?? 'N/A') ?><?= $wfm->service_level_seconds !== null ? 's' : '' ?>
                    </div>
                </div>
            </div>
        </div>
                        
                        <hr>
                        
                        <div class="alert alert-info mt-3 mb-0">
                            <small>
                                <i class="bi bi-info-circle"></i>
                                <strong>Règle produit — méthode &amp; paramètres Prophet :</strong>
                                Pré-sélectionné selon les paramètres de l'offre. Toute modification ici sera figée pour ce scénario.
                                Les paramètres Prophet (saisonnalités, sensibilité, changepoints, plage historique, etc.)
                                et le choix de méthode sont <strong>figés à la création</strong> ;
                                une modification ultérieure du profil d’une offre
                                <strong>ne met pas à jour</strong> les scénarios déjà créés.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php // ========== 4. CONFIGURATION PAR OFFRE ========== ?>
        </div>
        
        <?php // ========== BOUTONS D'ACTION ========== ?>
        <div class="mt-4 d-flex justify-content-between align-items-center p-3 bg-light border rounded">
            <div>
                <?= $this->Html->link(
                    '<i class="bi bi-arrow-left"></i> Annuler', 
                    ['action' => 'index'], 
                    ['class' => 'btn btn-secondary', 'escape' => false]
                ) ?>
            </div>
            <div>
                <?= $this->Form->button('<i class="bi bi-save"></i> Créer le Scénario', [
                    'class' => 'btn btn-primary btn-lg',
                    'escapeTitle' => false
                ]) ?>
            </div>
        </div>
        
        <?= $this->Form->end() ?>
    </div>
</div>

<?php $this->Html->scriptStart(['block' => true]); ?>
// UI spécifique Prophet retirée pour les managers : aucun JS nécessaire ici pour l’instant.
<?php $this->Html->scriptEnd(); ?>
