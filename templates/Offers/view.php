<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Offer $offer
 * @var array $prophetDefaults
 * @var bool $hasOfferProphetProfile
 * @var string|null $historyMinDate
 * @var string|null $historyMaxDate
 */

$boolBadge = function (bool $value, string $yesLabel = 'Oui', string $noLabel = 'Non'): string {
    if ($value) {
        return '<span class="badge badge-success"><i class="bi bi-check-circle"></i> ' . h($yesLabel) . '</span>';
    }

    return '<span class="badge badge-secondary"><i class="bi bi-x-circle"></i> ' . h($noLabel) . '</span>';
};

$formatDateFr = function (?string $date): ?string {
    if (empty($date)) {
        return null;
    }

    return (new \DateTime($date))->format('d/m/Y');
};
?>
<?php $this->assign('title', 'Détails Offre : ' . h($offer->name)); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="offers view content card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-basket text-primary"></i>
            <?= h($offer->name) ?>
            <span style="display: inline-block; width: 30px; height: 30px; background-color: <?= h($offer->color) ?>; border: 2px solid #ddd; border-radius: 5px; vertical-align: middle; margin-left: 15px;"></span>
        </h3>
        <div class="btn-toolbar">
            <?= $this->Html->link(
                '<i class="bi bi-pencil mr-1"></i> Modifier',
                ['action' => 'edit', $offer->id],
                ['class' => 'btn btn-primary mr-2', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash mr-1"></i> Supprimer',
                ['action' => 'delete', $offer->id],
                ['confirm' => 'Voulez-vous vraiment supprimer "' . h($offer->name) . '" ?', 'class' => 'btn btn-danger mr-2', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-list mr-1"></i> Liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>
    <div class="card-body">
        <?php // --- Informations de l'offre --- ?>
        <div class="card border-primary mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-info-circle"></i> Informations de l'offre
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-tag"></i> Nom</label>
                        <div><strong><?= h($offer->name) ?></strong></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-sort-numeric-up"></i> Ordre d'affichage</label>
                        <div>
                            <span class="badge badge-primary" style="font-size: 1rem;">
                                <?= $this->Number->format($offer->display_order) ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-tag"></i> Type d'offre</label>
                        <div>
                            <?php
                            $typeLabels = [
                                'normal' => '<span class="badge badge-primary">Normale</span>',
                                'absence' => '<span class="badge badge-secondary">Absence</span>',
                                'remote_work' => '<span class="badge badge-info">Télétravail</span>',
                                'pause' => '<span class="badge badge-warning">Pause</span>',
                                'lunch' => '<span class="badge badge-success">Repas</span>',
                            ];
                            echo $typeLabels[$offer->offer_type] ?? '<span class="badge badge-light">Inconnu</span>';
                            ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-grid"></i> Affichage dans le planning</label>
                        <div>
                            <?php if ($offer->is_displayed_in_grid): ?>
                                <span class="badge badge-success"><i class="bi bi-eye"></i> Oui</span>
                            <?php else: ?>
                                <span class="badge badge-secondary"><i class="bi bi-eye-slash"></i> Non</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-graph-up"></i> Utilisable en prévision</label>
                        <div>
                            <?php if ($offer->is_forecastable): ?>
                                <span class="badge badge-primary"><i class="bi bi-graph-up"></i> Oui</span>
                            <?php else: ?>
                                <span class="badge badge-secondary"><i class="bi bi-graph-down"></i> Non</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($offer->is_forecastable)): ?>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-diagram-3"></i> Méthode de prévision par défaut</label>
                        <div>
                            <?php if (($offer->default_forecast_method ?? 'historical') === 'prophet'): ?>
                                <span class="badge badge-info"><i class="bi bi-stars"></i> Prophet</span>
                            <?php else: ?>
                                <span class="badge badge-secondary"><i class="bi bi-clock-history"></i> Moyenne historique</span>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted d-block">Pré-sélectionnée à la création d’un scénario de forecast.</small>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-people"></i> Équité (sur la période)</label>
                        <div>
                            <?php if (!empty($offer->equity_enabled)): ?>
                                <span class="badge badge-success"><i class="bi bi-check-circle"></i> Activée</span>
                            <?php else: ?>
                                <span class="badge badge-secondary"><i class="bi bi-x-circle"></i> Désactivée</span>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted d-block">Utilisé comme défaut pour les règles fixes en “héritage”, et (plus tard) pour l’équité des forecastables.</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-house-check"></i> Compatible télétravail</label>
                        <div>
                            <?php if (!empty($offer->is_remote_work_compatible)): ?>
                                <span class="badge badge-success"><i class="bi bi-check-circle"></i> Oui</span>
                            <?php else: ?>
                                <span class="badge badge-secondary"><i class="bi bi-x-circle"></i> Non</span>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted d-block">Si non, l'offre est interdite sur les créneaux de télétravail quand l'option WFM est activée.</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-palette"></i> Couleur</label>
                        <div>
                            <span style="display: inline-block; width: 40px; height: 40px; background-color: <?= h($offer->color) ?>; border: 2px solid #ddd; border-radius: 5px; vertical-align: middle;"></span>
                            <span class="ml-2" style="font-size: 1rem;"><code><?= h($offer->color) ?></code></span>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1"><i class="bi bi-calendar-range"></i> Période de validité</label>
                        <div>
                            <?php if ($offer->start_date || $offer->end_date): ?>
                                <?= h($offer->start_date ? $offer->start_date->i18nFormat('dd/MM/yyyy') : '...') ?>
                                <i class="bi bi-arrow-right"></i>
                                <?= h($offer->end_date ? $offer->end_date->i18nFormat('dd/MM/yyyy') : '...') ?>
                            <?php else: ?>
                                <span class="text-muted">Non définie</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($offer->is_forecastable)): ?>
            <?php
            $seasonalityMode = $prophetDefaults['seasonality_mode'] ?? 'multiplicative';
            $seasonalityModeLabel = $seasonalityMode === 'additive'
                ? 'Additif (y = trend + seasonality)'
                : 'Multiplicatif (y = trend × seasonality)';
            $historyStartFr = $formatDateFr($prophetDefaults['history_start_date'] ?? null);
            $historyEndFr = $formatDateFr($prophetDefaults['history_end_date'] ?? null);
            $historyMinFr = $formatDateFr($historyMinDate);
            $historyMaxFr = $formatDateFr($historyMaxDate);
            ?>
            <div class="card border-info mb-4">
                <div class="card-header bg-info text-white">
                    <i class="bi bi-graph-up-arrow"></i> Paramètres Prophet par défaut (profil administrateur)
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">
                        Base automatique pour les scénarios Prophet incluant cette offre.
                        <?php if ($hasOfferProphetProfile): ?>
                            <span class="badge badge-info">Profil spécifique à l'offre</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Défauts système (WFM)</span>
                        <?php endif; ?>
                    </p>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="bg-light p-3 border rounded h-100">
                                <label class="text-muted small mb-1">Mode de saisonnalité</label>
                                <div><strong><?= h($seasonalityModeLabel) ?></strong></div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="bg-light p-3 border rounded h-100">
                                <label class="text-muted small mb-1">Jours fériés</label>
                                <div><?php echo $boolBadge(!empty($prophetDefaults['use_french_holidays']), 'Français activés', 'Désactivés'); ?></div>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary mt-2 mb-2"><i class="bi bi-calendar-event"></i> Saisonnalités</h6>
                    <div class="bg-light p-3 border rounded mb-3">
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <span class="text-muted small d-block">Annuelle</span>
                                <?php echo $boolBadge(!empty($prophetDefaults['yearly_seasonality']), 'Activée', 'Désactivée'); ?>
                            </div>
                            <div class="col-md-6 mb-2">
                                <span class="text-muted small d-block">Hebdomadaire</span>
                                <?php echo $boolBadge(!empty($prophetDefaults['weekly_seasonality']), 'Activée', 'Désactivée'); ?>
                            </div>
                            <div class="col-md-6 mb-2">
                                <span class="text-muted small d-block">Journalière</span>
                                <?php echo $boolBadge(!empty($prophetDefaults['daily_seasonality']), 'Activée', 'Désactivée'); ?>
                            </div>
                            <div class="col-md-6 mb-2">
                                <span class="text-muted small d-block">Mensuelle</span>
                                <?php echo $boolBadge(!empty($prophetDefaults['monthly_seasonality']), 'Activée', 'Désactivée'); ?>
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted small d-block">Complexité mensuelle (Fourier order)</span>
                                <strong><?= h((string)($prophetDefaults['monthly_fourier_order'] ?? 5)) ?></strong>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary mt-3 mb-2"><i class="bi bi-sliders"></i> Sensibilité & saisonnalité</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="bg-light p-3 border rounded h-100">
                                <label class="text-muted small mb-1">Sensibilité aux changements (changepoint_prior_scale)</label>
                                <div><strong><?= h((string)($prophetDefaults['changepoint_prior_scale'] ?? 0.1)) ?></strong></div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="bg-light p-3 border rounded h-100">
                                <label class="text-muted small mb-1">Force de la saisonnalité (seasonality_prior_scale)</label>
                                <div><strong><?= h((string)($prophetDefaults['seasonality_prior_scale'] ?? 10.0)) ?></strong></div>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary mt-2 mb-2"><i class="bi bi-graph-up"></i> Changepoints (tendance)</h6>
                    <div class="bg-light p-3 border rounded mb-3">
                        <label class="text-muted small mb-1">Nombre de changepoints (n_changepoints)</label>
                        <div><strong><?= h((string)($prophetDefaults['n_changepoints'] ?? 25)) ?></strong></div>
                    </div>

                    <h6 class="text-primary mt-3 mb-2"><i class="bi bi-calendar-range"></i> Plage de données historiques par défaut</h6>
                    <div class="bg-light p-3 border rounded">
                        <p class="small text-muted mb-2">
                            Fenêtre par défaut pour les prévisions, quelle que soit la méthode (Moyenne historique ou Prophet).
                        </p>
                        <div class="mb-2">
                            <?php if ($historyStartFr || $historyEndFr): ?>
                                <?= h($historyStartFr ?? '...') ?>
                                <i class="bi bi-arrow-right"></i>
                                <?= h($historyEndFr ?? '...') ?>
                            <?php else: ?>
                                <strong>Tout l'historique disponible</strong>
                            <?php endif; ?>
                        </div>
                        <?php if ($historyMinFr && $historyMaxFr): ?>
                            <small class="text-muted d-block">
                                Données historiques disponibles du <strong><?= h($historyMinFr) ?></strong>
                                au <strong><?= h($historyMaxFr) ?></strong>.
                            </small>
                        <?php else: ?>
                            <small class="text-muted d-block">
                                Aucune donnée historique trouvée pour cette offre.
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php // --- Boutons d'action --- ?>
        <div class="mt-4">
            <?= $this->Html->link(
                '<i class="bi bi-pencil mr-2"></i> Modifier',
                ['action' => 'edit', $offer->id],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-arrow-left mr-2"></i> Retour à la liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary ml-2', 'escape' => false]
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-trash mr-2"></i> Supprimer',
                ['action' => 'delete', $offer->id],
                [
                    'confirm' => 'Voulez-vous vraiment supprimer "' . h($offer->name) . '" ?',
                    'class' => 'btn btn-outline-danger ml-2',
                    'escape' => false
                ]
            ) ?>
        </div>
    </div>
</div>
