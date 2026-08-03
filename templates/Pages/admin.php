<?php
/**
 * @var \Cake\View\View $this
 */

$this->assign('title', 'Administration');
$this->extend('/layout/TwitterBootstrap/dashtron_fullwidth');
?>

<?php
$this->append('css', '<style>
.admin-card {
    transition: all 0.3s ease;
    border: 2px solid transparent;
}
.admin-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
    border-color: #007bff;
}
.admin-card .card-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}
.workflow-arrow {
    font-size: 1.5rem;
    color: #6c757d;
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 0.3; }
    50% { opacity: 1; }
}
.services-health-pill {
    font-size: 0.8rem;
    font-weight: 500;
    color: #495057;
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
$roleId = $identity ? (int)(is_object($identity) && method_exists($identity, 'get') ? $identity->get('role_id') : ($identity['role_id'] ?? 0)) : null;
$servicesHealth = $servicesHealth ?? [];
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <h3 class="mb-0">
            <i class="bi bi-speedometer2 text-primary"></i>
            Tableau de bord Administration
        </h3>
        <div class="d-flex align-items-center">
            <?php if (!empty($servicesHealth)): ?>
                <span class="services-health mr-3" title="État des services Python">
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
            <span class="badge badge-info badge-lg">
                <i class="bi bi-person-badge"></i>
                <?= $roleId === 1 ? 'Administrateur' : ($roleId === 2 ? 'Manager' : 'Utilisateur') ?>
            </span>
        </div>
    </div>
    <div class="card-body">
        <?php $isAdmin = ($roleId === 1); $isManager = ($roleId === 2); ?>

        <?php if ($isAdmin || $isManager): ?>
        <h4 class="mb-4">
            <i class="bi bi-diagram-2 text-success"></i> Workflow de planification
        </h4>
        <div class="row align-items-stretch text-center">
            <div class="col-md-2 mb-3">
                <div class="card admin-card border-warning h-100">
                    <div class="card-body d-flex flex-column">
                        <i class="bi bi-file-earmark-excel text-warning" style="font-size: 2.5rem; margin-bottom: 0.5rem;"></i>
                        <h6 class="card-title mb-2">Upload Excel</h6>
                        <small class="text-muted d-block mb-2">Importer plannings absences/télétravail</small>
                        <div class="mt-auto">
                            <?= $this->Html->link(
                                '<i class="bi bi-arrow-right-circle"></i> Importer',
                                ['controller' => 'ExcelUploads', 'action' => 'upload'],
                                ['class' => 'btn btn-warning btn-sm', 'escape' => false]
                            ) ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-auto d-none d-md-flex align-items-center">
                <i class="bi bi-arrow-right workflow-arrow"></i>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card admin-card border-info h-100">
                    <div class="card-body d-flex flex-column">
                        <i class="bi bi-graph-up-arrow text-info" style="font-size: 2.5rem; margin-bottom: 0.5rem;"></i>
                        <h6 class="card-title mb-2">Scénarios WFM</h6>
                        <small class="text-muted d-block mb-2">Prévisions besoin</small>
                        <div class="mt-auto">
                            <?= $this->Html->link(
                                '<i class="bi bi-arrow-right-circle"></i> Accéder',
                                ['controller' => 'ForecastScenarios', 'action' => 'index'],
                                ['class' => 'btn btn-info btn-sm', 'escape' => false]
                            ) ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-auto d-none d-md-flex align-items-center">
                <i class="bi bi-arrow-right workflow-arrow"></i>
            </div>

            <div class="col-md-2 mb-3">
                <div class="card admin-card border-primary h-100">
                    <div class="card-body d-flex flex-column">
                        <i class="bi bi-calendar-check text-primary" style="font-size: 2.5rem; margin-bottom: 0.5rem;"></i>
                        <h6 class="card-title mb-2">Activités fixes</h6>
                        <small class="text-muted d-block mb-2">Règles rigides</small>
                        <div class="mt-auto">
                            <?= $this->Html->link(
                                '<i class="bi bi-arrow-right-circle"></i> Accéder',
                                ['controller' => 'FixedActivityRules', 'action' => 'index'],
                                ['class' => 'btn btn-primary btn-sm', 'escape' => false]
                            ) ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-auto d-none d-md-flex align-items-center">
                <i class="bi bi-arrow-right workflow-arrow"></i>
            </div>

            <div class="col-md-2 mb-3">
                <div class="card admin-card border-warning h-100">
                    <div class="card-body d-flex flex-column">
                        <i class="bi bi-arrow-repeat text-warning" style="font-size: 2.5rem; margin-bottom: 0.5rem;"></i>
                        <h6 class="card-title mb-2">Règles de rotation</h6>
                        <small class="text-muted d-block mb-2">Rotation et équité</small>
                        <div class="mt-auto">
                            <?= $this->Html->link(
                                '<i class="bi bi-arrow-right-circle"></i> Accéder',
                                ['controller' => 'RotationRules', 'action' => 'index'],
                                ['class' => 'btn btn-warning btn-sm', 'escape' => false]
                            ) ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-auto d-none d-md-flex align-items-center">
                <i class="bi bi-arrow-right workflow-arrow"></i>
            </div>

            <div class="col-md-2 mb-3">
                <div class="card admin-card border-success h-100">
                    <div class="card-body d-flex flex-column">
                        <i class="bi bi-cpu-fill text-success" style="font-size: 2.5rem; margin-bottom: 0.5rem;"></i>
                        <h6 class="card-title mb-2">Générations de planning</h6>
                        <small class="text-muted d-block mb-2">Jobs multi-jours</small>
                        <div class="mt-auto">
                            <?= $this->Html->link(
                                '<i class="bi bi-arrow-right-circle"></i> Accéder',
                                ['controller' => 'PlanningGenerationJobs', 'action' => 'index'],
                                ['class' => 'btn btn-success btn-sm', 'escape' => false]
                            ) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($isAdmin || $isManager): ?>
        <h4 class="mt-5 mb-4">
            <i class="bi bi-list-check text-primary"></i> Opérations
        </h4>
        <div class="row">
            <div class="col-md-3 mb-4">
                <div class="card admin-card border-primary h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-people-fill text-primary card-icon"></i>
                        <h5 class="card-title">Utilisateurs</h5>
                        <p class="text-muted small">Gestion des agents et managers</p>
                        <?= $this->Html->link(
                            '<i class="bi bi-arrow-right-circle mr-2"></i> Accéder',
                            ['controller' => 'Users', 'action' => 'index'],
                            ['class' => 'btn btn-primary', 'escape' => false]
                        ) ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card admin-card border-warning h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-bell-fill text-warning card-icon"></i>
                        <h5 class="card-title">Alertes</h5>
                        <p class="text-muted small">Messages et notifications</p>
                        <?= $this->Html->link(
                            '<i class="bi bi-arrow-right-circle mr-2"></i> Accéder',
                            ['controller' => 'Alerts', 'action' => 'index'],
                            ['class' => 'btn btn-warning', 'escape' => false]
                        ) ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card admin-card border-danger h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-calendar-x-fill text-danger card-icon"></i>
                        <h5 class="card-title">Absences</h5>
                        <p class="text-muted small">Congés et indisponibilités</p>
                        <?= $this->Html->link(
                            '<i class="bi bi-arrow-right-circle mr-2"></i> Accéder',
                            ['controller' => 'Absences', 'action' => 'index'],
                            ['class' => 'btn btn-danger', 'escape' => false]
                        ) ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card admin-card border-info h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-house-door-fill text-info card-icon"></i>
                        <h5 class="card-title">Télétravail</h5>
                        <p class="text-muted small">Configuration par agent</p>
                        <?= $this->Html->link(
                            '<i class="bi bi-arrow-right-circle mr-2"></i> Configurer',
                            ['controller' => 'RemoteWork', 'action' => 'index'],
                            ['class' => 'btn btn-info', 'escape' => false]
                        ) ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
        <h4 class="mt-5 mb-4">
            <i class="bi bi-database text-secondary"></i> Référentiels
        </h4>
        <div class="row align-items-stretch">
            <div class="col-md-2 mb-4">
                <div class="card admin-card border-primary h-100">
                    <div class="card-body text-center d-flex flex-column">
                        <i class="bi bi-shield-lock-fill text-primary" style="font-size: 2.5rem; margin-bottom: 1rem;"></i>
                        <h5 class="card-title">Rôles</h5>
                        <p class="text-muted small">Droits d'accès</p>
                        <div class="mt-auto">
                            <?= $this->Html->link(
                                '<i class="bi bi-arrow-right-circle mr-1"></i> Accéder',
                                ['controller' => 'Roles', 'action' => 'index'],
                                ['class' => 'btn btn-primary btn-sm', 'escape' => false]
                            ) ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-4">
                <div class="card admin-card border-info h-100">
                    <div class="card-body text-center d-flex flex-column">
                        <i class="bi bi-diagram-3-fill text-info" style="font-size: 2.5rem; margin-bottom: 1rem;"></i>
                        <h5 class="card-title">Régions</h5>
                        <p class="text-muted small">Zones géographiques</p>
                        <div class="mt-auto">
                            <?= $this->Html->link(
                                '<i class="bi bi-arrow-right-circle mr-1"></i> Accéder',
                                ['controller' => 'Regions', 'action' => 'index'],
                                ['class' => 'btn btn-info btn-sm', 'escape' => false]
                            ) ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-4">
                <div class="card admin-card border-success h-100">
                    <div class="card-body text-center d-flex flex-column">
                        <i class="bi bi-geo-alt-fill text-success" style="font-size: 2.5rem; margin-bottom: 1rem;"></i>
                        <h5 class="card-title">Sites</h5>
                        <p class="text-muted small">Lieux de travail</p>
                        <div class="mt-auto">
                            <?= $this->Html->link(
                                '<i class="bi bi-arrow-right-circle mr-1"></i> Accéder',
                                ['controller' => 'Sites', 'action' => 'index'],
                                ['class' => 'btn btn-success btn-sm', 'escape' => false]
                            ) ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-4">
                <div class="card admin-card border-warning h-100">
                    <div class="card-body text-center d-flex flex-column">
                        <i class="bi bi-basket-fill text-warning" style="font-size: 2.5rem; margin-bottom: 1rem;"></i>
                        <h5 class="card-title">Offres</h5>
                        <p class="text-muted small">Types d'activités</p>
                        <div class="mt-auto">
                            <?= $this->Html->link(
                                '<i class="bi bi-arrow-right-circle mr-1"></i> Accéder',
                                ['controller' => 'Offers', 'action' => 'index'],
                                ['class' => 'btn btn-warning btn-sm', 'escape' => false]
                            ) ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-4">
                <div class="card admin-card border-secondary h-100">
                    <div class="card-body text-center d-flex flex-column">
                        <i class="bi bi-clock-history text-secondary" style="font-size: 2.5rem; margin-bottom: 1rem;"></i>
                        <h5 class="card-title">Plages</h5>
                        <p class="text-muted small">Horaires personnalisés</p>
                        <div class="mt-auto">
                            <?= $this->Html->link(
                                '<i class="bi bi-arrow-right-circle mr-1"></i> Accéder',
                                ['controller' => 'Ranges', 'action' => 'index'],
                                ['class' => 'btn btn-secondary btn-sm', 'escape' => false]
                            ) ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-4">
                <div class="card admin-card border-danger h-100">
                    <div class="card-body text-center d-flex flex-column">
                        <i class="bi bi-award-fill text-danger" style="font-size: 2.5rem; margin-bottom: 1rem;"></i>
                        <h5 class="card-title">Compétences</h5>
                        <p class="text-muted small">Habilitations agents</p>
                        <div class="mt-auto">
                            <?= $this->Html->link(
                                '<i class="bi bi-arrow-right-circle mr-1"></i> Accéder',
                                ['controller' => 'Skills', 'action' => 'index'],
                                ['class' => 'btn btn-danger btn-sm', 'escape' => false]
                            ) ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php if ($isAdmin): ?>
            <div class="col-md-2 mb-4">
                <div class="card admin-card border-dark h-100">
                    <div class="card-body text-center d-flex flex-column">
                        <i class="bi bi-clock text-dark" style="font-size: 2.5rem; margin-bottom: 1rem;"></i>
                        <h5 class="card-title">Disponibilités</h5>
                        <p class="text-muted small">Horaires contractuels</p>
                        <div class="mt-auto">
                            <?= $this->Html->link(
                                '<i class="bi bi-arrow-right-circle mr-1"></i> Accéder',
                                ['controller' => 'UserAvailabilities', 'action' => 'index'],
                                ['class' => 'btn btn-dark btn-sm', 'escape' => false]
                            ) ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-4">
                <div class="card admin-card border-primary h-100">
                    <div class="card-body text-center d-flex flex-column">
                        <i class="bi bi-sliders text-primary" style="font-size: 2.5rem; margin-bottom: 1rem;"></i>
                        <h5 class="card-title">Affichage</h5>
                        <p class="text-muted small">Paramètres visuels</p>
                        <div class="mt-auto">
                            <?= $this->Html->link(
                                '<i class="bi bi-arrow-right-circle mr-1"></i> Accéder',
                                ['controller' => 'DisplaySettings', 'action' => 'index'],
                                ['class' => 'btn btn-primary btn-sm', 'escape' => false]
                            ) ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-4">
                <div class="card admin-card border-info h-100">
                    <div class="card-body text-center d-flex flex-column">
                        <i class="bi bi-link-45deg text-info" style="font-size: 2.5rem; margin-bottom: 1rem;"></i>
                        <h5 class="card-title">Mappings absences</h5>
                        <p class="text-muted small">Pour GroomRH</p>
                        <div class="mt-auto">
                            <?= $this->Html->link(
                                '<i class="bi bi-arrow-right-circle mr-1"></i> Gérer',
                                ['controller' => 'PlanningEventMappings', 'action' => 'index'],
                                ['class' => 'btn btn-info btn-sm', 'escape' => false]
                            ) ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-4">
                <div class="card admin-card border-warning h-100">
                    <div class="card-body text-center d-flex flex-column">
                        <i class="bi bi-sliders text-warning" style="font-size: 2.5rem; margin-bottom: 1rem;"></i>
                        <h5 class="card-title">Paramètres WFM</h5>
                        <p class="text-muted small">Config solveur</p>
                        <div class="mt-auto">
                            <?= $this->Html->link(
                                '<i class="bi bi-arrow-right-circle mr-1"></i> Accéder',
                                ['controller' => 'WfmSettings', 'action' => 'index'],
                                ['class' => 'btn btn-warning btn-sm', 'escape' => false]
                            ) ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-4">
                <div class="card admin-card border-success h-100">
                    <div class="card-body text-center d-flex flex-column">
                        <i class="bi bi-robot text-success" style="font-size: 2.5rem; margin-bottom: 1rem;"></i>
                        <h5 class="card-title">Test 1 jour (legacy)</h5>
                        <p class="text-muted small">Ancien générateur synchrone — préférer Générations de planning</p>
                        <div class="mt-auto">
                            <?= $this->Html->link(
                                '<i class="bi bi-play-circle-fill mr-1"></i> Lancer',
                                ['controller' => 'Schedules', 'action' => 'generate'],
                                ['class' => 'btn btn-success btn-sm', 'escape' => false]
                            ) ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($isAdmin || $isManager): ?>
        <h4 class="mt-5 mb-4">
            <i class="bi bi-bar-chart-line text-secondary"></i> Données Historiques
        </h4>
        <div class="row">
            <?php if ($isAdmin): ?>
            <div class="col-md-6 mb-4">
                <div class="card admin-card border-secondary h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-upload text-secondary" style="font-size: 2.5rem; margin-bottom: 1rem;"></i>
                        <h5 class="card-title">Import CSV</h5>
                        <p class="text-muted small">Charger les données historiques depuis un fichier CSV</p>
                        <?= $this->Html->link(
                            '<i class="bi bi-arrow-right-circle mr-2"></i> Importer',
                            ['controller' => 'HistoricalData', 'action' => 'import'],
                            ['class' => 'btn btn-secondary', 'escape' => false]
                        ) ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <div class="col-md-6 mb-4">
                <div class="card admin-card border-info h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-graph-up text-info" style="font-size: 2.5rem; margin-bottom: 1rem;"></i>
                        <h5 class="card-title">Visualisation graphique</h5>
                        <p class="text-muted small">Analyser les données historiques avec des graphiques interactifs</p>
                        <?= $this->Html->link(
                            '<i class="bi bi-arrow-right-circle mr-2"></i> Consulter',
                            ['controller' => 'HistoricalData', 'action' => 'visualize'],
                            ['class' => 'btn btn-info', 'escape' => false]
                        ) ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>


        <?php if (!$isAdmin && !$isManager): ?>
            <div class="alert alert-info" role="alert">Aucun contenu d'administration n'est disponible pour votre rôle.</div>
        <?php endif; ?>
    </div>
</div>
