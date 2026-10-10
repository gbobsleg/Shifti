<?php
/**
 * @var \Cake\View\View $this
 */

$this->assign('title', 'Administration');
$this->extend('/layout/TwitterBootstrap/dashtron_fullwidth');

$this->append('css', '<style>
.admin-workflow {
    display: flex;
    flex-wrap: wrap;
    align-items: stretch;
    gap: 0.5rem 0.35rem;
}
.admin-workflow .admin-tile {
    flex: 1 1 9.5rem;
    min-width: 9.5rem;
}
.admin-workflow-sep {
    display: none;
    align-self: center;
    color: var(--crud-muted, #6b7a82);
    padding: 0 0.1rem;
    font-size: 0.85rem;
}
@media (min-width: 1100px) {
    .admin-workflow-sep { display: block; }
}
.admin-tiles {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(11rem, 1fr));
    gap: 0.65rem;
}
.admin-tile {
    display: flex;
    flex-direction: column;
    padding: 0.8rem 0.9rem;
    border: 1px solid var(--crud-border, #e2e8ea);
    border-radius: var(--crud-radius, 6px);
    text-decoration: none;
    color: var(--crud-text, #1f2a30);
    background: var(--crud-surface, #fff);
}
.admin-tile:hover,
.admin-tile:focus {
    border-color: var(--crud-accent, #318f9b);
    color: var(--crud-text, #1f2a30);
    text-decoration: none;
}
.admin-tile:focus-visible {
    outline: 2px solid var(--crud-accent, #318f9b);
    outline-offset: 2px;
}
.admin-tile .bi {
    font-size: 1.15rem;
    color: var(--crud-accent, #318f9b);
    margin-bottom: 0.4rem;
}
.admin-tile strong {
    display: block;
    font-size: 0.875rem;
    font-weight: 600;
}
.admin-tile small {
    display: block;
    margin-top: 0.15rem;
    font-size: 0.75rem;
    color: var(--crud-muted, #6b7a82);
    line-height: 1.35;
}
.admin-dir {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr));
    gap: 0 1.25rem;
}
.admin-dir-item {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    padding: 0.45rem 0.4rem;
    margin: 0 -0.4rem;
    border-radius: var(--crud-radius, 6px);
    text-decoration: none;
    color: var(--crud-text, #1f2a30);
}
.admin-dir-item:hover,
.admin-dir-item:focus {
    background: var(--crud-bg, #f4f6f7);
    color: var(--crud-text, #1f2a30);
    text-decoration: none;
}
.admin-dir-item:focus-visible {
    outline: 2px solid var(--crud-accent, #318f9b);
    outline-offset: 2px;
}
.admin-dir-item .bi {
    color: var(--crud-accent, #318f9b);
    margin-top: 0.15rem;
    flex-shrink: 0;
}
.admin-dir-item strong {
    display: block;
    font-size: 0.875rem;
    font-weight: 600;
}
.admin-dir-item small {
    display: block;
    font-size: 0.75rem;
    color: var(--crud-muted, #6b7a82);
    line-height: 1.35;
}
.admin-dir-item.is-legacy small {
    font-style: italic;
}
.services-health-pill {
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--crud-muted, #6b7a82);
    margin-right: 0.65rem;
    white-space: nowrap;
}
.services-health-pill .dot {
    display: inline-block;
    width: 0.55rem;
    height: 0.55rem;
    border-radius: 50%;
    margin-right: 0.25rem;
    vertical-align: middle;
}
.services-health-pill .dot.ok { background-color: #28a745; }
.services-health-pill .dot.ko { background-color: #dc3545; }
</style>');

$identity = $this->request->getAttribute('identity') ?? (isset($this->Identity) ? $this->Identity->get() : null);
$servicesHealth = $servicesHealth ?? [];
$roleLabel = 'Utilisateur';
if (is_object($identity) && method_exists($identity, 'getOriginalData')) {
    $original = $identity->getOriginalData();
    if (is_object($original) && isset($original->role->name)) {
        $roleLabel = (string)$original->role->name;
    }
}
$can = function (string $action, object $resource) use ($identity): bool {
    return $identity && method_exists($identity, 'can') && $identity->can($action, $resource);
};

$tile = function (string $icon, string $title, string $help, array $url) {
    return $this->Html->link(
        '<i class="bi ' . h($icon) . '" aria-hidden="true"></i>'
        . '<strong>' . h($title) . '</strong>'
        . '<small>' . h($help) . '</small>',
        $url,
        ['class' => 'admin-tile', 'escape' => false]
    );
};
$dirItem = function (string $icon, string $title, string $help, array $url, string $extra = '') {
    return $this->Html->link(
        '<i class="bi ' . h($icon) . '" aria-hidden="true"></i>'
        . '<span><strong>' . h($title) . '</strong><small>' . h($help) . '</small></span>',
        $url,
        ['class' => 'admin-dir-item' . ($extra !== '' ? ' ' . $extra : ''), 'escape' => false]
    );
};

$workflowSteps = [
    ['bi-file-earmark-excel', 'Upload Excel', 'Importer plannings absences/télétravail', ['controller' => 'ExcelUploads', 'action' => 'upload'], 'upload', new \App\Resource\ExcelUploadsResource()],
    ['bi-graph-up-arrow', 'Scénarios WFM', 'Prévisions besoin', ['controller' => 'ForecastScenarios', 'action' => 'index'], 'index', new \App\Resource\ForecastScenariosResource()],
    ['bi-calendar-check', 'Activités fixes', 'Règles rigides', ['controller' => 'FixedActivityRules', 'action' => 'index'], 'index', new \App\Resource\FixedActivityRulesResource()],
    ['bi-arrow-repeat', 'Règles de rotation', 'Rotation et équité', ['controller' => 'RotationRules', 'action' => 'index'], 'index', new \App\Resource\RotationRulesResource()],
    ['bi-cpu', 'Générations de planning', 'Jobs multi-jours', ['controller' => 'PlanningGenerationJobs', 'action' => 'index'], 'index', new \App\Resource\PlanningGenerationJobsResource()],
];
$operations = [
    ['bi-list-task', 'Jobs', 'File Optuna / prévisions / plannings', ['controller' => 'BackgroundJobs', 'action' => 'index'], 'index', new \App\Resource\BackgroundJobsResource()],
    ['bi-people', 'Utilisateurs', 'Gestion des agents et managers', ['controller' => 'Users', 'action' => 'index'], 'index', new \App\Resource\UsersResource()],
    ['bi-bell', 'Alertes', 'Messages et notifications', ['controller' => 'Alerts', 'action' => 'index'], 'index', new \App\Resource\AlertsResource()],
    ['bi-calendar-x', 'Absences', 'Congés et indisponibilités', ['controller' => 'Absences', 'action' => 'index'], 'index', new \App\Resource\AbsencesResource()],
    ['bi-house-door', 'Télétravail', 'Configuration par agent', ['controller' => 'RemoteWork', 'action' => 'index'], 'index', new \App\Resource\RemoteWorkResource()],
];
$referentials = [
    ['bi-shield-lock', 'Rôles', 'Droits d\'accès', ['controller' => 'Roles', 'action' => 'index'], 'index', new \App\Resource\RolesResource()],
    ['bi-diagram-3', 'Régions', 'Zones géographiques', ['controller' => 'Regions', 'action' => 'index'], 'index', new \App\Resource\RegionsResource()],
    ['bi-geo-alt', 'Sites', 'Lieux de travail', ['controller' => 'Sites', 'action' => 'index'], 'index', new \App\Resource\SitesResource()],
    ['bi-basket', 'Offres', 'Types d\'activités', ['controller' => 'Offers', 'action' => 'index'], 'index', new \App\Resource\OffersResource()],
    ['bi-collection', 'Groupes d\'offres', 'Profils mixtes (passe 2)', ['controller' => 'OfferGroups', 'action' => 'index'], 'index', new \App\Resource\OfferGroupsResource()],
    ['bi-clock-history', 'Plages', 'Horaires personnalisés', ['controller' => 'Ranges', 'action' => 'index'], 'index', new \App\Resource\RangesResource()],
    ['bi-award', 'Compétences', 'Habilitations agents', ['controller' => 'Skills', 'action' => 'index'], 'index', new \App\Resource\SkillsResource()],
    ['bi-clock', 'Disponibilités', 'Horaires contractuels', ['controller' => 'UserAvailabilities', 'action' => 'index'], 'index', new \App\Resource\UserAvailabilitiesResource()],
    ['bi-sliders', 'Affichage', 'Paramètres visuels', ['controller' => 'DisplaySettings', 'action' => 'index'], 'index', new \App\Resource\DisplaySettingsResource()],
    ['bi-link-45deg', 'Mappings absences', 'Pour GroomRH', ['controller' => 'PlanningEventMappings', 'action' => 'index'], 'index', new \App\Resource\PlanningEventMappingsResource()],
    ['bi-sliders', 'Paramètres WFM', 'Config solveur', ['controller' => 'WfmSettings', 'action' => 'index'], 'index', new \App\Resource\WfmSettingsResource()],
];
$allowed = static function (array $item) use ($can): bool {
    return $can($item[4], $item[5]);
};
$workflowSteps = array_values(array_filter($workflowSteps, $allowed));
$operations = array_values(array_filter($operations, $allowed));
$referentials = array_values(array_filter($referentials, $allowed));
$canImportHistory = $can('import', new \App\Resource\HistoricalDataResource());
$canVisualizeHistory = $can('visualize', new \App\Resource\HistoricalDataResource());
$canLegacySchedule = $can('generate', new \App\Resource\SchedulesResource());
$hasContent = $workflowSteps !== [] || $operations !== [] || $referentials !== [] || $canLegacySchedule || $canImportHistory || $canVisualizeHistory;
?>

<div class="crud-app content">
    <div class="crud-header">
        <h1>Administration</h1>
        <div class="crud-header-actions">
            <?php if (!empty($servicesHealth)): ?>
                <span class="services-health" title="État des services Python">
                    <?php foreach ($servicesHealth as $svc): ?>
                        <?php
                        $ok = !empty($svc['ok']);
                        $title = h((string)$svc['label']) . ($ok ? ' : OK' : ' : ' . h((string)($svc['detail'] ?? 'indisponible')));
                        ?>
                        <span class="services-health-pill" title="<?= $title ?>">
                            <span class="dot <?= $ok ? 'ok' : 'ko' ?>"></span><?= h((string)$svc['label']) ?>
                        </span>
                    <?php endforeach; ?>
                </span>
            <?php endif; ?>
            <span class="badge text-bg-light border"><?= h($roleLabel) ?></span>
        </div>
    </div>

    <?php if ($workflowSteps !== []): ?>
        <section class="crud-section">
            <h2 class="crud-section-title">Workflow de planification</h2>
            <div class="admin-workflow">
                <?php foreach ($workflowSteps as $i => $step): ?>
                    <?php if ($i > 0): ?>
                        <span class="admin-workflow-sep" aria-hidden="true"><i class="bi bi-chevron-right"></i></span>
                    <?php endif; ?>
                    <?= $tile($step[0], $step[1], $step[2], $step[3]) ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($operations !== []): ?>
        <section class="crud-section">
            <h2 class="crud-section-title">Opérations</h2>
            <div class="admin-tiles">
                <?php foreach ($operations as $item): ?>
                    <?= $tile($item[0], $item[1], $item[2], $item[3]) ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($referentials !== [] || $canLegacySchedule): ?>
        <section class="crud-section">
            <h2 class="crud-section-title">Référentiels</h2>
            <div class="admin-dir">
                <?php foreach ($referentials as $item): ?>
                    <?= $dirItem($item[0], $item[1], $item[2], $item[3]) ?>
                <?php endforeach; ?>
                <?php if ($canLegacySchedule): ?>
                    <?= $dirItem(
                        'bi-play-circle',
                        'Test 1 jour (legacy)',
                        'Ancien générateur synchrone — préférer Générations de planning',
                        ['controller' => 'Schedules', 'action' => 'generate'],
                        'is-legacy'
                    ) ?>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($canImportHistory || $canVisualizeHistory): ?>
        <section class="crud-section">
            <h2 class="crud-section-title">Données historiques</h2>
            <div class="admin-tiles">
                <?php if ($canImportHistory): ?>
                    <?= $tile('bi-upload', 'Import CSV', 'Charger les données historiques depuis un fichier CSV', ['controller' => 'HistoricalData', 'action' => 'import']) ?>
                <?php endif; ?>
                <?php if ($canVisualizeHistory): ?>
                    <?= $tile('bi-graph-up', 'Réel et prévision', 'Comparer le volume réel à la prévision publiée', ['controller' => 'HistoricalData', 'action' => 'visualize']) ?>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!$hasContent): ?>
        <div class="alert alert-info" role="alert">Aucun contenu d'administration n'est disponible pour votre rôle.</div>
    <?php endif; ?>
</div>
