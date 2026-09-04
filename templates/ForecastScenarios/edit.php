<?php
/** @var \App\Model\Entity\ForecastScenario $scenario */
/** @var array $offers */
/** @var array $selectedOfferIds */
/** @var array $snapshot */
/** @var array $wfmSettingsList */
/** @var array $offerVolatility */
?>
<?php $this->assign('title', 'Éditer Scénario #' . h($scenario->id)); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>



<div class="crud-app forecast-scenarios form content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-pencil"></i>
            Éditer Scénario #<?= h($scenario->id) ?> — <?= h($scenario->name) ?>
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-x-circle me-1"></i> Annuler',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
        <?php
        // Map méthode par offre pour pré-remplir les sélecteurs
        $methodsByOffer = [];
        foreach ($scenario->forecast_scenarios_offers as $link) {
            $methodsByOffer[(int)$link->offer_id] = $link->forecast_method ?? 'historical';
        }
        ?>
        <?php
        $statusLabels = [
            'draft' => 'Brouillon',
            'queued' => 'En file',
            'running' => 'En cours',
            'completed' => 'Terminé',
            'failed' => 'Échec',
        ];
        ?>
        <div class="crud-notice">
            <div>
                <strong>Statut actuel : <?= h($statusLabels[$scenario->status] ?? (string)$scenario->status) ?></strong>
                — La modification d'un scénario le repassera en statut <strong>Brouillon</strong>. Il faudra relancer un calcul.
            </div>
        </div>

        <?= $this->Form->create($scenario) ?>

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
                                <?php
                                    $startVal = '';
                                    if ($scenario->start_date instanceof \DateTimeInterface) {
                                        $startVal = $scenario->start_date->format('Y-m-d');
                                    } else {
                                        $raw = (string)$scenario->start_date;
                                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
                                            $startVal = $raw;
                                        } elseif (strpos($raw, '/') !== false) {
                                            $dt = \DateTime::createFromFormat('d/m/Y', $raw);
                                            if ($dt) { $startVal = $dt->format('Y-m-d'); }
                                        }
                                    }
                                ?>
                                <?= $this->Form->control('start_date', [
                                    'type' => 'date', 
                                    'label' => '<i class="bi bi-calendar-event"></i> Date de début', 
                                    'default' => $startVal, 
                                    'value' => $startVal,
                                    'escape' => false,
                                    'class' => 'form-control'
                                ]) ?>
                            </div>
                            <div class="col-md-6">
                                <?php
                                    $endVal = '';
                                    if ($scenario->end_date instanceof \DateTimeInterface) {
                                        $endVal = $scenario->end_date->format('Y-m-d');
                                    } else {
                                        $raw = (string)$scenario->end_date;
                                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
                                            $endVal = $raw;
                                        } elseif (strpos($raw, '/') !== false) {
                                            $dt = \DateTime::createFromFormat('d/m/Y', $raw);
                                            if ($dt) { $endVal = $dt->format('Y-m-d'); }
                                        }
                                    }
                                ?>
                                <?= $this->Form->control('end_date', [
                                    'type' => 'date', 
                                    'label' => '<i class="bi bi-calendar-check"></i> Date de fin', 
                                    'default' => $endVal, 
                                    'value' => $endVal,
                                    'escape' => false,
                                    'class' => 'form-control'
                                ]) ?>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <?= $this->Form->control('wfm_setting_id', [
                                    'label' => '<i class="bi bi-sliders"></i> Profil WFM',
                                    'options' => $wfmSettingsList,
                                    'empty' => '— Conserver le snapshot actuel —',
                                    'class' => 'form-control',
                                    'escape' => false
                                ]) ?>
                                <small class="form-text text-muted">
                                    Laisser vide pour conserver les paramètres actuels
                                </small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><i class="bi bi-file-text"></i> Snapshot actuel</label>
                                <div class="border rounded p-3 bg-light">
                                    <div class="mb-1"><i class="bi bi-clock"></i> <strong>Heures:</strong> <?= h($snapshot['day_start_time'] ?? '') ?> - <?= h($snapshot['day_end_time'] ?? '') ?></div>
                                    <div class="mb-1"><i class="bi bi-percent"></i> <strong>Shrinkage:</strong> <?= h($snapshot['shrinkage_percent'] ?? '') ?>%</div>
                                    <div><i class="bi bi-speedometer"></i> <strong>QS:</strong> <?= h($snapshot['service_level_percent'] ?? '') ?>% / <?= h($snapshot['service_level_seconds'] ?? '') ?>s</div>
                                </div>
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
                            Cochez les offres à inclure dans le scénario et choisissez, pour chacune,
                            si les prévisions doivent être calculées en <strong>Moyenne historique</strong> ou avec <strong>Prophet</strong>.
                        </p>
                        <div class="alert alert-info mb-3">
                            <small>
                                <i class="bi bi-info-circle"></i>
                                <strong>Règle produit — méthode &amp; paramètres Prophet :</strong>
                                Pré-sélectionné selon les paramètres de l'offre. Toute modification ici sera figée pour ce scénario.
                                Les offres déjà présentes conservent leur snapshot (méthode + paramètres).
                                Une <strong>nouvelle offre ajoutée</strong> reçoit le profil et la méthode par défaut
                                <strong>actuels</strong> de l’offre au moment de l’enregistrement.
                                Une modification ultérieure de l’offre en administration
                                <strong>ne met pas à jour</strong> ce scénario.
                            </small>
                        </div>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="width:60px;">Inclure</th>
                                        <th>Offre</th>
                                        <th style="width:220px;">Méthode de calcul</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($offers as $offerId => $offerName):
                                        $id = (int)$offerId;
                                        $checked = in_array($id, $selectedOfferIds, true);
                                        $method = $methodsByOffer[$id] ?? $defaultMethodsByOffer[$id] ?? 'historical';
                                    ?>
                                        <tr>
                                            <td class="text-center">
                                                <input type="checkbox"
                                                       name="offer_ids[]"
                                                       value="<?= h($offerId) ?>"
                                                       <?= $checked ? 'checked' : '' ?>>
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
                                                        'default' => $method,
                                                        'value' => $method,
                                                        'class' => 'form-control form-control-sm',
                                                    ]
                                                ) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <small class="form-text text-muted mb-3 d-block">
                            <i class="bi bi-info-circle"></i>
                            Si vous décochez une offre, elle sera retirée du scénario même si une méthode est sélectionnée.
                        </small>

                        <?php if (!empty($offerVolatility)): ?>
                        <div class="mt-4">
                            <h6 class="text-secondary"><i class="bi bi-graph-up"></i> Analyse de Volatilité des Offres</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Offre</th>
                                            <th class="text-center">Coefficient de Variation</th>
                                            <th class="text-center">Niveau</th>
                                            <th>Configuration Recommandée</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($selectedOfferIds as $offerId): 
                                            $volatility = $offerVolatility[$offerId] ?? null;
                                            if (!$volatility) continue;
                                        ?>
                                        <tr>
                                            <td><strong><?= h($offers[$offerId] ?? "Offre #{$offerId}") ?></strong></td>
                                            <td class="text-center">
                                                <span class="badge bg-<?= $volatility['level'] === 'high' ? 'danger' : ($volatility['level'] === 'medium' ? 'warning' : ($volatility['level'] === 'low' ? 'success' : 'secondary')) ?> badge-lg">
                                                    <?= h($volatility['coefficient']) ?>%
                                                </span>
                                            </td>
                                            <td class="text-center"><?= h($volatility['label']) ?></td>
                                            <td>
                                                <?php if ($volatility['level'] === 'high'): ?>
                                                    <small class="text-danger">
                                                        <i class="bi bi-exclamation-triangle"></i> 
                                                        <strong>Configuration Prophet spécifique fortement recommandée</strong><br>
                                                        Demander à l’administrateur d’ajuster le profil Prophet de cette offre
                                                        (plage historique, sensibilité, saisonnalités, etc.).
                                                    </small>
                                                <?php elseif ($volatility['level'] === 'medium'): ?>
                                                    <small class="text-warning">
                                                        <i class="bi bi-info-circle"></i> 
                                                        Configuration Prophet personnalisée conseillée<br>
                                                        En cas de prévisions instables, solliciter l’administrateur pour affiner
                                                        le profil Prophet de cette offre.
                                                    </small>
                                                <?php else: ?>
                                                    <small class="text-success">
                                                        <i class="bi bi-check-circle"></i> 
                                                        Paramètres globaux et profils Prophet actuels adaptés
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="alert alert-light">
                                <small>
                                    <i class="bi bi-lightbulb"></i> <strong>Info :</strong> 
                                    Le coefficient de variation mesure la variabilité des données. Plus il est élevé, plus les prévisions seront difficiles et nécessiteront un paramétrage spécifique.
                                </small>
                            </div>
                        </div>
                        <?php endif; ?>
                </div>
            </section>
        </div>

        <div class="crud-actions-bar">
            <?= $this->Form->button('<i class="bi bi-save me-2"></i> Enregistrer les modifications', [
                'class' => 'btn btn-primary',
                'escapeTitle' => false
            ]) ?>
            <?= $this->Html->link(
                '<i class="bi bi-x-circle me-2"></i> Annuler',
                ['action' => 'view', $scenario->id],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>

        <?= $this->Form->end() ?>
</div>

<?php $this->Html->scriptStart(['block' => true]); ?>
// JS simplifié : UI Prophet avancée supprimée, on conserve uniquement
// le petit comportement visuel des chevrons sur les accordéons.
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(function(button) {
        button.addEventListener('click', function() {
            const icon = this.querySelector('.bi-chevron-right, .bi-chevron-down');
            if (icon) {
                if (icon.classList.contains('bi-chevron-right')) {
                    icon.classList.remove('bi-chevron-right');
                    icon.classList.add('bi-chevron-down');
                } else {
                    icon.classList.remove('bi-chevron-down');
                    icon.classList.add('bi-chevron-right');
                }
            }
        });
    });
});
<?php $this->Html->scriptEnd(); ?>
