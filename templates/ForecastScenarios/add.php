<?php
/** @var array $offers */
/** @var \App\Model\Entity\WfmSetting $wfm */
/** @var array $wfmSettingsList */
?>
<?php $this->assign('title', 'Nouveau Scénario'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app forecast-scenarios form content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-plus-circle"></i>
            Nouveau Scénario
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-x-circle me-1"></i> Annuler',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <?= $this->Form->create(null) ?>

    <div class="accordion" id="scenarioAccordion">
        <section class="crud-section" id="heading1">
            <h2 class="crud-section-title">
                <button class="btn btn-link text-decoration-none p-0" type="button"
                        data-bs-toggle="collapse" data-bs-target="#collapse1" aria-expanded="true">
                    1. Informations Générales
                </button>
            </h2>
            <div id="collapse1" class="collapse show" aria-labelledby="heading1" data-parent="#scenarioAccordion">
                <?= $this->Form->control('name', ['label' => 'Nom du scénario', 'class' => 'form-control']) ?>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <?= $this->Form->control('start_date', [
                            'type' => 'date',
                            'label' => 'Date de début',
                            'escape' => false,
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                    <div class="col-md-6">
                        <?= $this->Form->control('end_date', [
                            'type' => 'date',
                            'label' => 'Date de fin',
                            'escape' => false,
                            'class' => 'form-control'
                        ]) ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="crud-section" id="heading2">
            <h2 class="crud-section-title">
                <button class="btn btn-link text-decoration-none p-0 collapsed" type="button"
                        data-bs-toggle="collapse" data-bs-target="#collapse2" aria-expanded="false">
                    2. Offres Concernées
                </button>
            </h2>
            <div id="collapse2" class="collapse" aria-labelledby="heading2" data-parent="#scenarioAccordion">
                <p class="small text-muted">
                    Sélectionnez les offres à inclure dans le scénario et choisissez, pour chacune,
                    si les prévisions doivent être calculées en <strong>Moyenne historique</strong> ou avec <strong>Prophet</strong>.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm crud-table">
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
                    Si vous laissez une offre décochée, elle ne sera pas incluse dans le scénario,
                    même si une méthode est sélectionnée.
                </small>
            </div>
        </section>

        <section class="crud-section" id="heading3">
            <h2 class="crud-section-title">
                <button class="btn btn-link text-decoration-none p-0 collapsed" type="button"
                        data-bs-toggle="collapse" data-bs-target="#collapse3" aria-expanded="false">
                    3. Méthode de Prévision & Paramètres Globaux Prophet
                </button>
            </h2>
            <div id="collapse3" class="collapse" aria-labelledby="heading3" data-parent="#scenarioAccordion">
                <h3 class="crud-subsection-title">Paramètres WFM</h3>
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
                            <div><strong>Heures:</strong> <?= h($wfm->day_start_time ?? '—') ?> - <?= h($wfm->day_end_time ?? '—') ?></div>
                            <div><strong>Shrinkage:</strong> <?= h($wfm->shrinkage_percent ?? '—') ?><?= $wfm->shrinkage_percent !== null ? '%' : '' ?></div>
                            <div><strong>QS:</strong>
                                <?= h($wfm->service_level_percent ?? '—') ?><?= $wfm->service_level_percent !== null ? '%' : '' ?>
                                / <?= h($wfm->service_level_seconds ?? '—') ?><?= $wfm->service_level_seconds !== null ? 's' : '' ?>
                            </div>
                        </div>
                    </div>
                </div>
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
        </section>
    </div>

    <div class="crud-actions-bar">
        <?= $this->Form->button('<i class="bi bi-save me-2"></i> Créer le Scénario', [
            'class' => 'btn btn-primary',
            'escapeTitle' => false
        ]) ?>
        <?= $this->Html->link(
            '<i class="bi bi-x-circle me-2"></i> Annuler',
            ['action' => 'index'],
            ['class' => 'btn btn-outline-secondary', 'escape' => false]
        ) ?>
    </div>

    <?= $this->Form->end() ?>
</div>
