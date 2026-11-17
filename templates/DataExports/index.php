<?php
/**
 * @var \App\View\AppView $this
 * @var bool $canScenario
 * @var list<array{id: int, name: string, forecastable: bool}> $offers
 * @var list<array{id: int, name: string, start: string, end: string, offers: list<int>}> $scenarios
 * @var array{type: string, scenario_id: string, start_date: string, end_date: string, granularity: string, offers: list<int>} $filters
 */
$this->assign('title', 'Export');
$this->extend('/layout/TwitterBootstrap/dashtron_fullwidth');
$this->Html->script('data-exports', ['block' => true, 'timestamp' => 'force']);

$selectedOffers = array_fill_keys($filters['offers'], true);
$type = $filters['type'];
if (!in_array($type, ['actual', 'published', 'scenario'], true)) {
    $type = 'actual';
}
$granularity = $filters['granularity'];
if (!in_array($granularity, ['15min', 'hour', 'day'], true)) {
    $granularity = '15min';
}
?>
<div
    class="crud-app content"
    id="data-exports"
    data-can-scenario="<?= $canScenario ? '1' : '0' ?>"
    data-scenarios="<?= h(json_encode($scenarios, JSON_UNESCAPED_UNICODE)) ?>"
>
    <div class="crud-header">
        <h1>Export</h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-arrow-left me-1"></i> Retour Administration',
                ['controller' => 'Pages', 'action' => 'display', 'admin'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>

    <section class="crud-section">
        <h2 class="crud-section-title">Télécharger un CSV</h2>
        <p class="text-muted">
            Un jour sans donnée n'apparaît pas. Un quart d'heure à zéro dans une journée ouverte est conservé.
            Le besoin à l'heure ou au jour est la moyenne des créneaux ouverts, en agents.
        </p>
        <?= $this->Form->create(null, ['url' => ['action' => 'download']]) ?>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="export-type">Jeu de données</label>
                <select name="type" id="export-type" class="form-select">
                    <option value="actual" <?= $type === 'actual' ? 'selected' : '' ?>>Réel</option>
                    <option value="published" <?= $type === 'published' ? 'selected' : '' ?>>Prévision publiée</option>
                    <?php if ($canScenario): ?>
                        <option value="scenario" <?= $type === 'scenario' ? 'selected' : '' ?>>Prévision d'un scénario</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-8" id="export-scenario-field" <?= $type === 'scenario' ? '' : 'hidden' ?>>
                <label class="form-label" for="export-scenario">Scénario terminé</label>
                <select name="scenario_id" id="export-scenario" class="form-select">
                    <option value="">Choisir</option>
                    <?php foreach ($scenarios as $scenario): ?>
                        <option
                            value="<?= (int)$scenario['id'] ?>"
                            <?= (string)$scenario['id'] === (string)$filters['scenario_id'] ? 'selected' : '' ?>
                        ><?= h($scenario['name']) ?> (<?= h($scenario['start']) ?> – <?= h($scenario['end']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="export-start">Début</label>
                <input type="date" class="form-control" id="export-start" name="start_date" required value="<?= h($filters['start_date']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="export-end">Fin</label>
                <input type="date" class="form-control" id="export-end" name="end_date" required value="<?= h($filters['end_date']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="export-granularity">Granularité</label>
                <select name="granularity" id="export-granularity" class="form-select">
                    <option value="15min" <?= $granularity === '15min' ? 'selected' : '' ?>>15 minutes</option>
                    <option value="hour" <?= $granularity === 'hour' ? 'selected' : '' ?>>Heure</option>
                    <option value="day" <?= $granularity === 'day' ? 'selected' : '' ?>>Jour</option>
                </select>
            </div>
        </div>
        <div class="mt-3">
            <div class="form-label">Offres</div>
            <?php if ($offers === []): ?>
                <p class="text-muted mb-0">Aucune offre disponible.</p>
            <?php endif; ?>
            <?php foreach ($offers as $offer): ?>
                <div class="form-check export-offer" data-offer-id="<?= (int)$offer['id'] ?>" data-forecastable="<?= $offer['forecastable'] ? '1' : '0' ?>">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="offers[]"
                        value="<?= (int)$offer['id'] ?>"
                        id="export-offer-<?= (int)$offer['id'] ?>"
                        <?= isset($selectedOffers[$offer['id']]) ? 'checked' : '' ?>
                    >
                    <label class="form-check-label" for="export-offer-<?= (int)$offer['id'] ?>"><?= h($offer['name']) ?></label>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-primary">Télécharger</button>
        </div>
        <?= $this->Form->end() ?>
    </section>
</div>
