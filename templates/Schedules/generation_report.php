<?php
/**
 * @var \App\View\AppView $this
 * @var string $date
 * @var string $status
 * @var array|null $diagnostics
 * @var array $preSolverDiagnostics
 * @var array $agentDiagnosticsList
 * @var array $coverage
 * @var array $excludedOffers
 * @var array $offers
 * @var \App\Model\Entity\WfmSetting|null $settings
 * @var string $errorMessage
 * @var int $totalNeedSlots
 * @var int $totalAvailableSlots
 * @var float $coverageRate
 * @var int $agentsWithSkills
 * @var int $agentsWithNoSkills
 * @var int $agentsWithNoAvailability
 * @var int $agentsWithLunchIssues
 * @var int $agentsWithBreakIssues
 * @var int $totalAgentsChecked
 * @var array $offersNeeds
 * @var int|null $scheduleCount
 */
?>
<?php $this->assign('title', 'Rapport de génération'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="row">
    <div class="col-12">
        <div class="card shadow mb-4">
            <?php
            $headerClass = 'danger';
            $scheduleCount = $scheduleCount ?? 0;
            $isSuccess = in_array($status, ['success', 'FEASIBLE', 'OPTIMAL']) && $scheduleCount > 0;
            $isEmptySchedule = in_array($status, ['success', 'FEASIBLE', 'OPTIMAL']) && ($scheduleCount === 0 || strpos($errorMessage ?? '', 'vide') !== false);
            if ($isSuccess) {
                $headerClass = 'success';
            } elseif ($isEmptySchedule) {
                $headerClass = 'warning';
            } elseif ($status === 'UNKNOWN') {
                $headerClass = 'warning';
            }
            ?>
            <?php
            $isSuccess = in_array($status, ['success', 'FEASIBLE', 'OPTIMAL']) && ($scheduleCount ?? 0) > 0;
            ?>
            <div class="card-header bg-<?= $headerClass ?> text-white d-flex justify-content-between align-items-center">
                <h3 class="mb-0">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    Rapport de génération
                </h3>
                <?php if ($isSuccess): ?>
                    <?php
                    // Convertir la date au format d/m/Y attendu par GridsController
                    $dateFormatted = is_string($date) ? date('d/m/Y', strtotime($date)) : $date->i18nFormat('dd/MM/yyyy');
                    ?>
                    <?= $this->Html->link(
                        '<i class="bi bi-calendar-week"></i> Voir le planning',
                        [
                            'controller' => 'Grids',
                            'action' => 'index',
                            '?' => ['date_start' => $dateFormatted],
                        ],
                        ['class' => 'btn btn-light btn-sm', 'escape' => false]
                    ) ?>
                <?php else: ?>
                    <?= $this->Html->link(
                        '<i class="bi bi-arrow-left"></i> Réessayer',
                        ['action' => 'generate'],
                        ['class' => 'btn btn-light btn-sm', 'escape' => false]
                    ) ?>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <!-- En-tête -->
                <?php
                $alertClass = 'danger';
                $alertIcon = 'bi-x-circle';
                $alertTitle = 'Planning non généré';
                $scheduleCount = $scheduleCount ?? 0;
                $isSuccess = in_array($status, ['success', 'FEASIBLE', 'OPTIMAL']) && $scheduleCount > 0;
                $isEmptySchedule = in_array($status, ['success', 'FEASIBLE', 'OPTIMAL']) && ($scheduleCount === 0 || strpos($errorMessage ?? '', 'vide') !== false);
                
                // Cas 1 : Succès avec planning non vide
                if ($isSuccess) {
                    $alertClass = 'success';
                    $alertIcon = 'bi-check-circle';
                    $alertTitle = 'Planning généré avec succès';
                }
                // Cas 2 : Statut FEASIBLE/OPTIMAL mais aucun segment WORK (planning vide)
                elseif ($isEmptySchedule) {
                    $alertClass = 'warning';
                    $alertIcon = 'bi-exclamation-triangle';
                    $alertTitle = 'Statut ' . h($status) . ' mais planning vide';
                }
                // Cas 3 : Échec INFEASIBLE
                elseif ($status === 'INFEASIBLE') {
                    $alertClass = 'danger';
                    $alertIcon = 'bi-x-circle';
                    $alertTitle = 'Planning infaisable';
                }
                // Cas 4 : Statut UNKNOWN
                elseif ($status === 'UNKNOWN') {
                    $alertClass = 'warning';
                    $alertIcon = 'bi-question-circle';
                    $alertTitle = 'Statut inconnu';
                }
                // Cas 5 : Erreur du solveur
                elseif (in_array($status, ['ERROR', 'error'])) {
                    $alertClass = 'danger';
                    $alertIcon = 'bi-exclamation-triangle';
                    $alertTitle = 'Erreur du solveur';
                }
                // Cas par défaut
                else {
                    $alertClass = 'warning';
                    $alertIcon = 'bi-question-circle';
                    $alertTitle = 'Statut : ' . h($status);
                }
                ?>
                <div class="alert alert-<?= $alertClass ?>">
                    <h5 class="alert-heading">
                        <i class="bi <?= $alertIcon ?>"></i> <?= h($alertTitle) ?>
                    </h5>
                    <hr>
                    <p class="mb-1"><strong>Date :</strong> <?= h($date) ?></p>
                    <p class="mb-1"><strong>Statut :</strong> <span class="badge bg-<?= $alertClass ?>"><?= h($status) ?></span></p>
                    <?php if ($isSuccess): ?>
                        <p class="mb-1"><strong>Nombre de segments de travail générés :</strong> <?= number_format($scheduleCount) ?></p>
                        <p class="mb-1"><strong>Planning sauvegardé :</strong> Le planning a été généré et sauvegardé avec succès dans la base de données.</p>
                    <?php elseif ($isEmptySchedule): ?>
                        <p class="mb-1"><strong>Nombre de segments générés :</strong> 0 (aucun segment de travail WORK)</p>
                        <?php if (!empty($errorMessage)): ?>
                            <p class="mb-1 text-warning"><strong>Note :</strong> <?= h($errorMessage) ?></p>
                        <?php else: ?>
                            <p class="mb-1 text-warning"><strong>Note :</strong> Le solveur a retourné le statut "<?= h($status) ?>" mais aucun segment de travail (WORK) n'a été généré. Cela peut indiquer que les contraintes empêchent toute affectation, même si le problème est techniquement "faisable".</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if (!empty($errorMessage)): ?>
                            <p class="mb-1"><strong>Détails :</strong> <?= h($errorMessage) ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($settings): ?>
                        <p class="mb-0"><strong>Paramètres :</strong> <?= h($settings->name) ?></p>
                    <?php endif; ?>
                </div>

                <!-- Statistiques globales -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Statistiques globales</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card border-primary">
                                    <div class="card-body text-center">
                                        <h3 class="text-primary"><?= number_format($totalNeedSlots) ?></h3>
                                        <p class="mb-0">Besoin total (créneaux)</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-success">
                                    <div class="card-body text-center">
                                        <h3 class="text-success"><?= number_format($totalAvailableSlots) ?></h3>
                                        <p class="mb-0">Capacité totale (créneaux)</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-<?= $coverageRate > 100 ? 'danger' : ($coverageRate < 50 ? 'warning' : 'info') ?>">
                                    <div class="card-body text-center">
                                        <h3 class="text-<?= $coverageRate > 100 ? 'danger' : ($coverageRate < 50 ? 'warning' : 'info') ?>"><?= $coverageRate ?>%</h3>
                                        <p class="mb-0">Taux de couverture nécessaire</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php if ($coverageRate > 100): ?>
                            <div class="alert alert-danger mt-3">
                                <i class="bi bi-exclamation-triangle"></i>
                                <strong>Capacité insuffisante :</strong> Le besoin total (<?= number_format($totalNeedSlots) ?> créneaux) dépasse la capacité disponible (<?= number_format($totalAvailableSlots) ?> créneaux) de <?= number_format($totalNeedSlots - $totalAvailableSlots) ?> créneaux.
                                <br><small>Il manque <?= round((($totalNeedSlots - $totalAvailableSlots) / $totalNeedSlots) * 100, 1) ?>% de capacité pour couvrir tous les besoins.</small>
                            </div>
                        <?php elseif ($coverageRate < 50 && $status === 'INFEASIBLE'): ?>
                            <div class="alert alert-warning mt-3">
                                <i class="bi bi-info-circle"></i>
                                <strong>Capacité largement suffisante</strong> (<?= $coverageRate ?>%), mais le problème reste infaisable. Cela indique que les contraintes (pauses, déjeuners, fenêtres de travail, activités fixes) ne peuvent pas être satisfaites simultanément.
                                <br><small>La capacité globale est suffisante, mais les contraintes individuelles (disponibilités, compétences, fenêtres de pauses) empêchent une affectation faisable.</small>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($status === 'INFEASIBLE'): ?>
                            <div class="alert alert-info mt-3">
                                <i class="bi bi-lightbulb"></i>
                                <strong>Analyse de l'infaisabilité :</strong>
                                <ul class="mb-0 mt-2">
                                    <li><strong>Agents exclus :</strong> <?= count($excludedAgents ?? []) ?> agent(s) ont été exclus avant l'envoi au solveur (voir section ci-dessous).</li>
                                    <li><strong>Agents envoyés :</strong> <?= $agentsSentToSolver ?? 0 ?> agent(s) avec compétences et disponibilité valides ont été envoyés au solveur.</li>
                                    <li><strong>Offres exclues :</strong> <?= count($excludedOffers ?? []) ?> offre(s) ont été exclues car aucun agent n'a la compétence requise.</li>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Analyse de capacité par type -->
                        <?php if (!empty($preSolverDiagnostics['capacity_by_need_type'] ?? [])): ?>
                            <?php $capacityData = $preSolverDiagnostics['capacity_by_need_type']; ?>
                            <div class="alert alert-info mt-3">
                                <h6><i class="bi bi-pie-chart"></i> Analyse de capacité par type de besoin</h6>
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <strong>Besoins forecastables :</strong>
                                        <ul class="mb-0">
                                            <li>Besoin total : <?= number_format($capacityData['forecastable']['total_need'] ?? 0) ?> créneaux</li>
                                            <li>Capacité disponible : <?= number_format($capacityData['forecastable']['total_capacity'] ?? 0) ?> créneaux</li>
                                            <li>Taux de couverture : <strong><?= $capacityData['forecastable']['coverage_rate'] ?? 0 ?>%</strong></li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Activités fixes :</strong>
                                        <ul class="mb-0">
                                            <li>Besoin total : <?= number_format($capacityData['fixed_activities']['total_need'] ?? 0) ?> créneaux</li>
                                            <li>Capacité disponible : <?= number_format($capacityData['fixed_activities']['total_capacity'] ?? 0) ?> créneaux</li>
                                            <li>Taux de couverture : <strong class="<?= ($capacityData['fixed_activities']['coverage_rate'] ?? 0) > 100 ? 'text-danger' : 'text-success' ?>"><?= $capacityData['fixed_activities']['coverage_rate'] ?? 0 ?>%</strong></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Analyse de répartition des compétences -->
                        <?php if (!empty($preSolverDiagnostics['skill_distribution'] ?? [])): ?>
                            <?php 
                            $highRiskOffers = array_filter($preSolverDiagnostics['skill_distribution'], fn($s) => ($s['risk_level'] ?? '') === 'high');
                            $mediumRiskOffers = array_filter($preSolverDiagnostics['skill_distribution'], fn($s) => ($s['risk_level'] ?? '') === 'medium');
                            ?>
                            <?php if (!empty($highRiskOffers) || !empty($mediumRiskOffers)): ?>
                                <div class="alert alert-warning mt-3">
                                    <h6><i class="bi bi-exclamation-triangle"></i> Analyse de répartition des compétences</h6>
                                    <?php if (!empty($highRiskOffers)): ?>
                                        <p class="mb-2"><strong class="text-danger">⚠️ Offres à haut risque (ratio > 0.8) :</strong></p>
                                        <ul class="mb-2">
                                            <?php foreach ($highRiskOffers as $offer): ?>
                                                <li>
                                                    <strong><?= h($offer['offer']) ?></strong>
                                                    : <?= $offer['agents_count'] ?> agent(s) compétent(s),
                                                    besoin total : <?= $offer['total_need_slots'] ?> créneaux
                                                    (ratio : <?= $offer['ratio'] ?>)
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                    <?php if (!empty($mediumRiskOffers)): ?>
                                        <p class="mb-0"><strong class="text-warning">⚠️ Offres à risque moyen (ratio > 0.5) :</strong></p>
                                        <ul class="mb-0">
                                            <?php foreach (array_slice($mediumRiskOffers, 0, 5) as $offer): ?>
                                                <li>
                                                    <strong><?= h($offer['offer']) ?></strong>
                                                    : <?= $offer['agents_count'] ?> agent(s) compétent(s),
                                                    ratio : <?= $offer['ratio'] ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Offres exclues -->
                <?php if (!empty($excludedOffers)): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="bi bi-x-octagon-fill"></i> Offres exclues (sans agents compétents)</h5>
                        </div>
                        <div class="card-body">
                            <p>Les offres suivantes ont été exclues car aucun agent n'a la compétence requise :</p>
                            <ul>
                                <?php foreach ($excludedOffers as $excludedOffer): ?>
                                    <li><strong><?= h($excludedOffer) ?></strong></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Agents à risque -->
                <?php if (!empty($preSolverDiagnostics['agents_at_risk'] ?? [])): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-warning text-dark cursor-pointer"
                             data-toggle="collapse"
                             data-target="#report-agents-at-risk"
                             aria-expanded="false"
                             aria-controls="report-agents-at-risk">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    Agents à risque (<?= count($preSolverDiagnostics['agents_at_risk']) ?> agents)
                                </h5>
                                <i class="bi bi-chevron-down"></i>
                            </div>
                        </div>
                        <div id="report-agents-at-risk" class="collapse">
                            <div class="card-body">
                                <p class="text-muted mb-3">
                                    Ces agents ont des compétences limitées qui peuvent causer des problèmes lors de la génération du planning.
                                </p>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID Agent</th>
                                                <th>Nom</th>
                                                <th>Site</th>
                                                <th>Problème</th>
                                                <th>Compétences</th>
                                                <th>Recommandation</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($preSolverDiagnostics['agents_at_risk'] as $agent): ?>
                                                <tr>
                                                    <td><strong>#<?= h($agent['agent_id'] ?? '?') ?></strong></td>
                                                    <td><?= h($agent['agent_name'] ?? 'Nom inconnu') ?></td>
                                                    <td><?= h($agent['site'] ?? 'Site inconnu') ?></td>
                                                    <td>
                                                        <?php
                                                        $issueLabels = [
                                                            'mono_skill_fixed_activity_only' => 'N\'a que des compétences pour des activités fixes dont l\'offre de base n\'est pas dans le scénario',
                                                            'single_fixed_activity_only' => 'Ne peut couvrir qu\'une seule activité fixe',
                                                        ];
                                                        $issueLabel = $issueLabels[$agent['issue'] ?? ''] ?? 'Problème de compétences';
                                                        ?>
                                                        <span class="badge bg-warning text-dark"><?= h($issueLabel) ?></span>
                                                    </td>
                                                    <td>
                                                        <small>
                                                            <?php
                                                            $skillsList = is_array($agent['skills'] ?? []) ? $agent['skills'] : [];
                                                            echo implode(', ', array_map('h', array_slice($skillsList, 0, 3)));
                                                            if (count($skillsList) > 3) {
                                                                echo ' <span class="text-muted">(+' . (count($skillsList) - 3) . ' autres)</span>';
                                                            }
                                                            ?>
                                                        </small>
                                                        <?php if (!empty($agent['missing_base_offers'])): ?>
                                                            <br><small class="text-danger">Offres manquantes : <?= implode(', ', array_map('h', $agent['missing_base_offers'])) ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <small class="text-info"><?= h($agent['recommendation'] ?? '') ?></small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Conflits d'activités fixes -->
                <?php if (!empty($preSolverDiagnostics['fixed_activity_conflicts'] ?? [])): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-danger text-white cursor-pointer"
                             data-toggle="collapse"
                             data-target="#report-fixed-conflicts"
                             aria-expanded="false"
                             aria-controls="report-fixed-conflicts">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-x-octagon"></i>
                                    Conflits d'activités fixes (<?= count($preSolverDiagnostics['fixed_activity_conflicts']) ?> site(s))
                                </h5>
                                <i class="bi bi-chevron-down"></i>
                            </div>
                        </div>
                        <div id="report-fixed-conflicts" class="collapse">
                            <div class="card-body">
                                <p class="text-muted mb-3">
                                    Plusieurs activités fixes sur le même site nécessitent plus d'agents que disponibles, ou se chevauchent dans le temps.
                                </p>
                                <?php foreach ($preSolverDiagnostics['fixed_activity_conflicts'] as $conflict): ?>
                                    <div class="alert alert-warning mb-3">
                                        <h6><i class="bi bi-building"></i> Site "<?= h($conflict['site']) ?>"</h6>
                                        <ul class="mb-2">
                                            <li><strong>Nombre d'activités fixes :</strong> <?= count($conflict['activities']) ?></li>
                                            <li><strong>Besoin total :</strong> <?= $conflict['total_needed_agents'] ?> agent(s)</li>
                                            <li><strong>Agents compétents pour toutes les activités :</strong> <?= count($conflict['agents_competent_for_all']) ?> agent(s)</li>
                                            <?php if ($conflict['has_temporal_overlap']): ?>
                                                <li><strong class="text-danger">⚠️ Chevauchement temporel détecté</strong></li>
                                            <?php endif; ?>
                                        </ul>
                                        <p class="mb-2"><strong>Activités concernées :</strong></p>
                                        <ul>
                                            <?php foreach ($conflict['activities'] as $activity): ?>
                                                <li>
                                                    <strong><?= h($activity['offer']) ?></strong>
                                                    (besoin: <?= $activity['need_sum'] ?> agent(s),
                                                    plage: <?= h($activity['time_range']['start'] ?? '?') ?> - <?= h($activity['time_range']['end'] ?? '?') ?>)
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <?php if (!empty($conflict['agents_competent_for_all'])): ?>
                                            <p class="mb-1"><strong>Agents compétents :</strong> <?= implode(', ', array_map(fn($id) => '#' . $id, $conflict['agents_competent_for_all'])) ?></p>
                                        <?php endif; ?>
                                        <p class="mb-0 mt-2"><strong>Recommandation :</strong> <span class="text-info"><?= h($conflict['recommendation'] ?? '') ?></span></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Problèmes détectés -->
                <?php 
                $hasProblems = !empty($preSolverDiagnostics['fixed_activities_no_competent_agents'] ?? []) ||
                               !empty($preSolverDiagnostics['fixed_activities_outside_work_hours'] ?? []) ||
                               !empty($preSolverDiagnostics['site_competency_issues'] ?? []) ||
                               !empty($preSolverDiagnostics['excluded_agents_potentially_useful'] ?? []) ||
                               !empty($preSolverDiagnostics['fixed_activities_remote_work_incompatibilities'] ?? []);
                ?>
                <?php if ($hasProblems): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white cursor-pointer"
                             data-toggle="collapse"
                             data-target="#report-problems"
                             aria-expanded="false"
                             aria-controls="report-problems">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-info-circle"></i>
                                    Problèmes détectés
                                </h5>
                                <i class="bi bi-chevron-down"></i>
                            </div>
                        </div>
                        <div id="report-problems" class="collapse">
                            <div class="card-body">
                            <!-- Activités fixes sur des sites sans agents compétents -->
                            <?php if (!empty($preSolverDiagnostics['fixed_activities_no_competent_agents'] ?? [])): ?>
                                <div class="alert alert-warning mb-3">
                                    <h6><i class="bi bi-exclamation-triangle"></i> Activités fixes sans agents compétents</h6>
                                    <ul class="mb-0">
                                        <?php foreach ($preSolverDiagnostics['fixed_activities_no_competent_agents'] as $issue): ?>
                                            <li>
                                                <strong><?= h($issue['activity']) ?></strong> sur le site "<?= h($issue['site']) ?>"
                                                : Aucun agent sur ce site n'a la compétence "<?= h($issue['base_offer']) ?>"
                                                <br><small class="text-info">→ <?= h($issue['recommendation'] ?? '') ?></small>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <!-- Activités fixes en dehors des heures de travail -->
                            <?php if (!empty($preSolverDiagnostics['fixed_activities_outside_work_hours'] ?? [])): ?>
                                <div class="alert alert-warning mb-3">
                                    <h6><i class="bi bi-clock"></i> Activités fixes en dehors des heures de travail</h6>
                                    <ul class="mb-0">
                                        <?php foreach ($preSolverDiagnostics['fixed_activities_outside_work_hours'] as $issue): ?>
                                            <li>
                                                <strong><?= h($issue['activity']) ?></strong>
                                                : Plage horaire (<?= h($issue['time_range']['start']) ?> - <?= h($issue['time_range']['end']) ?>)
                                                dépasse les heures de travail (<?= h($issue['work_hours']['start']) ?> - <?= h($issue['work_hours']['end']) ?>)
                                                <br><small class="text-info">→ <?= h($issue['recommendation'] ?? '') ?></small>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <!-- Problèmes de compétences par site -->
                            <?php if (!empty($preSolverDiagnostics['site_competency_issues'] ?? [])): ?>
                                <div class="alert alert-warning mb-3">
                                    <h6><i class="bi bi-building"></i> Problèmes de compétences par site</h6>
                                    <ul class="mb-0">
                                        <?php foreach ($preSolverDiagnostics['site_competency_issues'] as $issue): ?>
                                            <li>
                                                <strong>Site "<?= h($issue['site']) ?>"</strong>
                                                : <?= $issue['agents_on_site'] ?> agent(s) sur le site, mais aucun n'a les compétences requises
                                                (<?= implode(', ', array_map('h', $issue['required_skills'])) ?>)
                                                <br><small class="text-info">→ <?= h($issue['recommendation'] ?? '') ?></small>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <!-- Agents exclus qui auraient pu être utiles -->
                            <?php if (!empty($preSolverDiagnostics['excluded_agents_potentially_useful'] ?? [])): ?>
                                <div class="alert alert-info mb-0">
                                    <h6><i class="bi bi-lightbulb"></i> Agents exclus qui auraient pu être utiles</h6>
                                    <ul class="mb-0">
                                        <?php foreach ($preSolverDiagnostics['excluded_agents_potentially_useful'] as $agent): ?>
                                            <li>
                                                <strong>Agent #<?= h($agent['agent_id']) ?></strong>
                                                (exclu : <?= h($agent['exclusion_reason']) ?>)
                                                : Aurait pu couvrir <?= implode(', ', array_map('h', $agent['could_cover'])) ?>
                                                <?php if (!empty($agent['if_condition'])): ?>
                                                    <br><small class="text-muted"><?= h($agent['if_condition']) ?></small>
                                                <?php endif; ?>
                                                <br><small class="text-info">→ <?= h($agent['recommendation'] ?? '') ?></small>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <!-- Incompatibilités télétravail sur activités fixes -->
                            <?php if (!empty($preSolverDiagnostics['fixed_activities_remote_work_incompatibilities'] ?? [])): ?>
                                <div class="alert alert-warning mb-0 mt-3">
                                    <h6><i class="bi bi-house-x"></i> Activités fixes incompatibles avec le télétravail</h6>
                                    <p class="text-muted mb-2">
                                        Certains créneaux demandent une activité marquée incompatible télétravail, mais trop d'agents compétents sont en télétravail.
                                    </p>
                                    <ul class="mb-0">
                                        <?php foreach (array_slice($preSolverDiagnostics['fixed_activities_remote_work_incompatibilities'], 0, 50) as $issue): ?>
                                            <li>
                                                <strong><?= h($issue['activity'] ?? '?') ?></strong> à <?= h($issue['time_slot'] ?? '?') ?>
                                                : requis <?= (int)($issue['required'] ?? 0) ?>, éligibles (hors TT) <?= (int)($issue['eligible_non_remote'] ?? 0) ?>,
                                                agents en TT <?= (int)($issue['remote_work_agents'] ?? 0) ?>
                                                <br><small class="text-info">→ <?= h($issue['recommendation'] ?? '') ?></small>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php if (count($preSolverDiagnostics['fixed_activities_remote_work_incompatibilities']) > 50): ?>
                                        <small class="text-muted d-block mt-2">
                                            Affichage limité aux 50 premiers éléments (<?= count($preSolverDiagnostics['fixed_activities_remote_work_incompatibilities']) ?> au total).
                                        </small>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Agents exclus avant l'envoi au solveur -->
                <?php if (!empty($excludedAgents)): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-danger text-white cursor-pointer"
                             data-toggle="collapse"
                             data-target="#report-excluded-agents"
                             aria-expanded="false"
                             aria-controls="report-excluded-agents">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-person-fill-dash"></i>
                                    Agents exclus avant l'envoi au solveur (<?= count($excludedAgents) ?> agents)
                                </h5>
                                <i class="bi bi-chevron-down"></i>
                            </div>
                        </div>
                        <div id="report-excluded-agents" class="collapse">
                            <div class="card-body">
                                <p class="text-muted mb-3">
                                    Ces agents ont été exclus avant l'envoi au solveur car ils ne remplissent pas les critères nécessaires (pas de disponibilité, pas de compétences, disponibilité invalide, ou pas de compétences pour les offres restantes).
                                </p>
                                <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID Agent</th>
                                            <th>Nom</th>
                                            <th>Site</th>
                                            <th>Raison d'exclusion</th>
                                            <th>Disponibilité</th>
                                            <th>Compétences</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($excludedAgents as $excludedAgent): ?>
                                            <tr>
                                                <td><strong>#<?= h($excludedAgent['id'] ?? '?') ?></strong></td>
                                                <td><?= h($excludedAgent['name'] ?? 'Nom inconnu') ?></td>
                                                <td><?= h($excludedAgent['site'] ?? 'Site inconnu') ?></td>
                                                <td>
                                                    <span class="badge text-bg-danger fw-semibold px-3 py-2" style="font-size: 0.9em; white-space: normal; line-height: 1.4; display: inline-block; max-width: 100%; word-wrap: break-word; background-color: #f8d7da !important; color: #721c24 !important; border: 1px solid #f5c2c7;">
                                                        <i class="bi bi-x-circle me-1"></i><?= h($excludedAgent['reason'] ?? 'Raison inconnue') ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($excludedAgent['availability']) && !empty($excludedAgent['availability']['start'])): ?>
                                                        <?= h($excludedAgent['availability']['start'] ?? '?') ?> - <?= h($excludedAgent['availability']['end'] ?? '?') ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($excludedAgent['skills'])): ?>
                                                        <small>
                                                            <?php
                                                            $skillsList = is_array($excludedAgent['skills']) ? $excludedAgent['skills'] : [];
                                                            $skillsDisplay = array_slice($skillsList, 0, 3);
                                                            echo implode(', ', array_map('h', $skillsDisplay));
                                                            if (count($skillsList) > 3) {
                                                                echo ' <span class="text-muted">(+' . (count($skillsList) - 3) . ' autres)</span>';
                                                            }
                                                            ?>
                                                        </small>
                                                    <?php else: ?>
                                                        <span class="text-muted">Aucune</span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($excludedAgent['offers_remaining'])): ?>
                                                        <br><small class="text-muted">Offres restantes : <?= count($excludedAgent['offers_remaining']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Agents envoyés au solveur -->
                <?php if (!empty($agentsSentToSolver)): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="bi bi-person-fill-add"></i>
                                Agents envoyés au solveur
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-0"><strong><?= $agentsSentToSolver ?> agent(s)</strong> ont été envoyés au solveur avec des compétences et une disponibilité valides.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Activités fixes (Passe 1) -->
                <?php if (!empty($fixedActivities)): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-calendar-check"></i> Activités fixes (Passe 1)</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">
                                Ces activités ont été assignées lors de la Passe 1 (activités fixes). 
                                <?php if ($fixedActivityAssignmentsCount > 0): ?>
                                    <strong><?= $fixedActivityAssignmentsCount ?> assignation(s)</strong> ont été effectuée(s).
                                <?php endif; ?>
                            </p>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Activité</th>
                                            <th class="text-center">Plage horaire</th>
                                            <th class="text-end">Quantité requise</th>
                                            <th class="text-end">Couverture</th>
                                            <th class="text-end">Pénurie</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($fixedActivities as $activity): ?>
                                            <?php
                                            $activityName = $activity['offer_name'] ?? '';
                                            $assignmentsForActivity = array_filter($fixedActivityAssignments ?? [], fn($a) => ($a['activity'] ?? '') === $activityName);
                                            $assignmentsCount = count($assignmentsForActivity);
                                            $shortfall = $fixedActivityShortfalls[$activityName] ?? 0;
                                            ?>
                                            <tr>
                                                <td><strong><?= h($activityName) ?></strong></td>
                                                <td class="text-center">
                                                    <?= h($activity['start_time'] ?? '') ?> - <?= h($activity['end_time'] ?? '') ?>
                                                </td>
                                                <td class="text-end"><?= number_format($activity['quantity'] ?? 0) ?></td>
                                                <td class="text-end">
                                                    <?php if ($assignmentsCount > 0): ?>
                                                        <span class="badge fw-bold px-2 py-1" style="font-size: 0.95em; background-color: #d1e7dd !important; color: #0f5132 !important; border: 1px solid #badbcc;">
                                                            <?= number_format($assignmentsCount) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge fw-bold px-2 py-1" style="font-size: 0.95em; background-color: #f8d7da !important; color: #721c24 !important; border: 1px solid #f5c2c7;">
                                                            0
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <?php if ($shortfall > 0): ?>
                                                        <span class="badge fw-bold px-2 py-1" style="font-size: 0.95em; background-color: #f8d7da !important; color: #721c24 !important; border: 1px solid #f5c2c7;"><?= number_format($shortfall) ?></span>
                                                    <?php else: ?>
                                                        <span class="badge fw-normal px-2 py-1" style="font-size: 0.95em; background-color: #e9ecef !important; color: #495057 !important; border: 1px solid #dee2e6;">0</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Besoins par offre -->
                <?php if (!empty($offersNeeds)): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-list-ul"></i> Besoins par offre (Passe 2 - Forecast)</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Offre</th>
                                            <th class="text-end">Besoin total</th>
                                            <th class="text-end">Couverture</th>
                                            <th class="text-end">Pénurie</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($offersNeeds as $offerName => $needs): ?>
                                            <tr>
                                                <td><strong><?= h($offerName) ?></strong></td>
                                                <td class="text-end"><?= number_format($needs['need']) ?></td>
                                                <td class="text-end">
                                                    <?php if ($needs['covered'] > 0): ?>
                                                        <span class="badge fw-bold px-2 py-1" style="font-size: 0.95em; background-color: #d1e7dd !important; color: #0f5132 !important; border: 1px solid #badbcc;">
                                                            <?= number_format($needs['covered']) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge fw-bold px-2 py-1" style="font-size: 0.95em; background-color: #f8d7da !important; color: #721c24 !important; border: 1px solid #f5c2c7;">
                                                            0
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <?php if ($needs['shortage'] > 0): ?>
                                                        <span class="badge fw-bold px-2 py-1" style="font-size: 0.95em; background-color: #f8d7da !important; color: #721c24 !important; border: 1px solid #f5c2c7;"><?= number_format($needs['shortage']) ?></span>
                                                    <?php else: ?>
                                                        <span class="badge fw-normal px-2 py-1" style="font-size: 0.95em; background-color: #e9ecef !important; color: #495057 !important; border: 1px solid #dee2e6;">0</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Diagnostics solveur améliorés -->
                <?php if (!empty($diagnostics) && isset($diagnostics['agents']) && is_array($diagnostics['agents'])): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-danger text-white cursor-pointer"
                             data-toggle="collapse"
                             data-target="#report-solver-diagnostics"
                             aria-expanded="false"
                             aria-controls="report-solver-diagnostics">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-bug"></i>
                                    Diagnostics du solveur
                                </h5>
                                <i class="bi bi-chevron-down"></i>
                            </div>
                        </div>
                        <div id="report-solver-diagnostics" class="collapse">
                            <div class="card-body">
                            <?php if (!empty($diagnostics['conflicts'] ?? [])): ?>
                                <div class="alert alert-danger mb-3">
                                    <h6><i class="bi bi-x-octagon"></i> Conflits entre activités fixes détectés par le solveur</h6>
                                    <ul class="mb-0">
                                        <?php foreach ($diagnostics['conflicts'] as $conflict): ?>
                                            <li>
                                                <strong><?= implode('</strong> et <strong>', array_map('h', $conflict['offers'] ?? [])) ?></strong>
                                                sur le site "<?= h($conflict['site'] ?? '') ?>"
                                                : <?= $conflict['overlapping_slots'] ?? 0 ?> créneaux en chevauchement
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <?php 
                            $agentsWithLimitedSkills = array_filter($diagnostics['agents'], fn($a) => ($a['is_mono_skill'] ?? false) === true);
                            ?>
                            <?php if (!empty($agentsWithLimitedSkills)): ?>
                                <div class="alert alert-warning mb-3">
                                    <h6><i class="bi bi-exclamation-triangle"></i> Agents avec compétences limitées</h6>
                                    <ul class="mb-0">
                                        <?php foreach (array_slice($agentsWithLimitedSkills, 0, 10) as $agent): ?>
                                            <li>
                                                <strong>Agent #<?= $agent['agent_id'] ?? '?' ?></strong>
                                                : N'a qu'une seule compétence (<?= implode(', ', array_map('h', $agent['agent_offers'] ?? [])) ?>)
                                                <?php if (!empty($agent['reasons_fr'])): ?>
                                                    <br><small class="text-muted"><?= implode('; ', array_map('h', $agent['reasons_fr'])) ?></small>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                        <?php if (count($agentsWithLimitedSkills) > 10): ?>
                                            <li class="text-muted">... et <?= count($agentsWithLimitedSkills) - 10 ?> autre(s) agent(s)</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($agentDiagnosticsList)): ?>
                                <p class="text-muted mb-2"><strong><?= count($agentDiagnosticsList) ?> agent(s)</strong> analysé(s) par le solveur.</p>
                            <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Solutions possibles -->
                <?php
                $hasSolutionHints =
                    $isEmptySchedule ||
                    ($agentsWithNoSkills ?? 0) > 0 ||
                    ($agentsWithNoAvailability ?? 0) > 0 ||
                    ($agentsWithLunchIssues ?? 0) > 0 ||
                    ($agentsWithBreakIssues ?? 0) > 0 ||
                    ($coverageRate ?? 0) > 100 ||
                    ($coverageRate ?? 0) < 50 ||
                    !empty($excludedOffers);
                ?>
                <?php if ($hasSolutionHints): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="bi bi-lightbulb"></i> Solutions possibles</h5>
                        </div>
                        <div class="card-body">
                            <ul>
                                <?php if ($isEmptySchedule): ?>
                                    <li><strong>Planning vide :</strong> Le solveur a retourné un statut de succès mais aucun segment de travail n'a été généré. Cela peut indiquer que les contraintes empêchent toute affectation, même si le problème est techniquement "faisable".</li>
                                    <li>Vérifiez que les agents ont bien les compétences nécessaires pour les offres demandées.</li>
                                    <li>Vérifiez que les fenêtres de disponibilité des agents chevauchent bien les créneaux où il y a des besoins.</li>
                                    <li>Vérifiez que les contraintes de pauses/déjeuners ne bloquent pas toutes les possibilités d'affectation.</li>
                                <?php endif; ?>
                                <?php if (($agentsWithNoSkills ?? 0) > 0): ?>
                                    <li><strong><?= $agentsWithNoSkills ?> agent(s)</strong> n'ont aucune compétence pour les offres demandées. Vérifiez les compétences des agents.</li>
                                <?php endif; ?>
                                <?php if (($agentsWithNoAvailability ?? 0) > 0): ?>
                                    <li><strong><?= $agentsWithNoAvailability ?> agent(s)</strong> n'ont aucune disponibilité pour cette date. Vérifiez les disponibilités.</li>
                                <?php endif; ?>
                                <?php if (($agentsWithLunchIssues ?? 0) > 0): ?>
                                    <li><strong><?= $agentsWithLunchIssues ?> agent(s)</strong> ne peuvent pas placer leur déjeuner dans la fenêtre autorisée. Vérifiez les fenêtres de déjeuner.</li>
                                <?php endif; ?>
                                <?php if (($agentsWithBreakIssues ?? 0) > 0): ?>
                                    <li><strong><?= $agentsWithBreakIssues ?> agent(s)</strong> ne peuvent pas placer leurs pauses (matin/après-midi) dans les fenêtres autorisées. Vérifiez les fenêtres de pauses.</li>
                                <?php endif; ?>
                                <?php if (($coverageRate ?? 0) > 100): ?>
                                    <li><strong>Capacité insuffisante :</strong> Augmentez le nombre d'agents disponibles ou réduisez les besoins.</li>
                                <?php elseif (($coverageRate ?? 0) < 50): ?>
                                    <li><strong>Contraintes incompatibles :</strong> Vérifiez que les fenêtres de pauses/déjeuners chevauchent bien les disponibilités des agents.</li>
                                    <li>Vérifiez que les besoins ne sont pas trop concentrés sur des créneaux où peu d'agents sont disponibles.</li>
                                    <li>Augmentez la flexibilité des agents (horaires, pauses).</li>
                                <?php endif; ?>
                                <?php if (!empty($excludedOffers)): ?>
                                    <li><strong>Offres exclues :</strong> Ajoutez des compétences aux agents pour les offres exclues ou retirez ces offres du planning.</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Boutons d'action -->
                <div class="text-center">
                    <?php if ($isSuccess): ?>
                        <?php
                        // Convertir la date au format d/m/Y attendu par GridsController
                        $dateFormatted = is_string($date) ? date('d/m/Y', strtotime($date)) : $date->i18nFormat('dd/MM/yyyy');
                        ?>
                        <?= $this->Html->link(
                            '<i class="bi bi-calendar-week"></i> Voir le planning',
                            [
                                'controller' => 'Grids',
                                'action' => 'index',
                                '?' => ['date_start' => $dateFormatted],
                            ],
                            ['class' => 'btn btn-success btn-lg me-2', 'escape' => false]
                        ) ?>
                        <?= $this->Html->link(
                            '<i class="bi bi-arrow-clockwise"></i> Régénérer',
                            ['action' => 'generate'],
                            ['class' => 'btn btn-primary btn-lg', 'escape' => false]
                        ) ?>
                    <?php else: ?>
                        <?= $this->Html->link(
                            '<i class="bi bi-arrow-clockwise"></i> Réessayer la génération',
                            ['action' => 'generate'],
                            ['class' => 'btn btn-primary btn-lg', 'escape' => false]
                        ) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

