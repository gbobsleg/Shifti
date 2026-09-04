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

<div class="crud-app content">
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
            <div class="crud-header">
                <div>
                    <h1>Rapport de génération</h1>
                    <p class="crud-header-meta">Test 1 jour (legacy)</p>
                </div>
                <div class="crud-header-actions">
                    <span class="badge text-bg-<?= h($headerClass) ?>"><?= h($status) ?></span>
                    <?php if ($isSuccess): ?>
                        <?php
                        $dateFormatted = is_string($date) ? date('d/m/Y', strtotime($date)) : $date->i18nFormat('dd/MM/yyyy');
                        ?>
                        <?= $this->Html->link(
                            'Voir le planning',
                            [
                                'controller' => 'Grids',
                                'action' => 'index',
                                '?' => ['date_start' => $dateFormatted],
                            ],
                            ['class' => 'btn btn-primary']
                        ) ?>
                    <?php else: ?>
                        <?= $this->Html->link(
                            'Réessayer',
                            ['action' => 'generate'],
                            ['class' => 'btn btn-outline-secondary']
                        ) ?>
                    <?php endif; ?>
                </div>
            </div>
            <div>
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
                <section class="crud-section">
                    <h2 class="crud-section-title"><?= h($alertTitle) ?></h2>
                    <dl class="crud-fields">
                        <div>
                            <dt>Date</dt>
                            <dd><?= h($date) ?></dd>
                        </div>
                        <div>
                            <dt>Statut</dt>
                            <dd><span class="badge text-bg-<?= h($alertClass) ?>"><?= h($status) ?></span></dd>
                        </div>
                        <?php if ($isSuccess): ?>
                            <div>
                                <dt>Segments de travail</dt>
                                <dd><?= number_format($scheduleCount) ?></dd>
                            </div>
                            <div>
                                <dt>Planning</dt>
                                <dd>Généré et sauvegardé.</dd>
                            </div>
                        <?php elseif ($isEmptySchedule): ?>
                            <div>
                                <dt>Segments générés</dt>
                                <dd>0 (aucun segment WORK)</dd>
                            </div>
                            <div>
                                <dt>Note</dt>
                                <dd><?= !empty($errorMessage) ? h($errorMessage) : 'Le solveur a retourné « ' . h($status) . ' » sans segment de travail. Les contraintes peuvent bloquer toute affectation.' ?></dd>
                            </div>
                        <?php elseif (!empty($errorMessage)): ?>
                            <div>
                                <dt>Détails</dt>
                                <dd><?= h($errorMessage) ?></dd>
                            </div>
                        <?php endif; ?>
                        <?php if ($settings): ?>
                            <div>
                                <dt>Paramètres</dt>
                                <dd><?= h($settings->name) ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                </section>

                <!-- Statistiques globales -->
                <section class="crud-section">
                    <h2 class="crud-section-title">Statistiques globales</h2>
                        <dl class="crud-fields">
                            <div>
                                <dt>Besoin total (créneaux)</dt>
                                <dd><?= number_format($totalNeedSlots) ?></dd>
                            </div>
                            <div>
                                <dt>Capacité totale (créneaux)</dt>
                                <dd><?= number_format($totalAvailableSlots) ?></dd>
                            </div>
                            <div>
                                <dt>Taux de couverture nécessaire</dt>
                                <dd><?= $coverageRate ?>%</dd>
                            </div>
                        </dl>
                        <?php if ($coverageRate > 100): ?>
                            <div class="crud-warn">
                                Capacité insuffisante : le besoin (<?= number_format($totalNeedSlots) ?> créneaux) dépasse la capacité (<?= number_format($totalAvailableSlots) ?> créneaux) de <?= number_format($totalNeedSlots - $totalAvailableSlots) ?> créneaux
                                (<?= round((($totalNeedSlots - $totalAvailableSlots) / $totalNeedSlots) * 100, 1) ?>%).
                            </div>
                        <?php elseif ($coverageRate < 50 && $status === 'INFEASIBLE'): ?>
                            <div class="crud-warn">
                                Capacité largement suffisante (<?= $coverageRate ?>%), mais le problème reste infaisable : les contraintes (pauses, déjeuners, fenêtres, activités fixes) ne peuvent pas être satisfaites simultanément.
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($status === 'INFEASIBLE'): ?>
                            <p class="crud-header-meta">Analyse de l'infaisabilité</p>
                            <ul>
                                <li>Agents exclus : <?= count($excludedAgents ?? []) ?> (voir section ci-dessous)</li>
                                <li>Agents envoyés : <?= $agentsSentToSolver ?? 0 ?></li>
                                <li>Offres exclues : <?= count($excludedOffers ?? []) ?></li>
                            </ul>
                        <?php endif; ?>
                        
                        <?php if (!empty($preSolverDiagnostics['capacity_by_need_type'] ?? [])): ?>
                            <?php $capacityData = $preSolverDiagnostics['capacity_by_need_type']; ?>
                            <h3 class="crud-subsection-title">Capacité par type de besoin</h3>
                            <dl class="crud-fields">
                                <div>
                                    <dt>Forecastables — besoin</dt>
                                    <dd><?= number_format($capacityData['forecastable']['total_need'] ?? 0) ?></dd>
                                </div>
                                <div>
                                    <dt>Forecastables — capacité</dt>
                                    <dd><?= number_format($capacityData['forecastable']['total_capacity'] ?? 0) ?></dd>
                                </div>
                                <div>
                                    <dt>Forecastables — couverture</dt>
                                    <dd><?= $capacityData['forecastable']['coverage_rate'] ?? 0 ?>%</dd>
                                </div>
                                <div>
                                    <dt>Activités fixes — besoin</dt>
                                    <dd><?= number_format($capacityData['fixed_activities']['total_need'] ?? 0) ?></dd>
                                </div>
                                <div>
                                    <dt>Activités fixes — capacité</dt>
                                    <dd><?= number_format($capacityData['fixed_activities']['total_capacity'] ?? 0) ?></dd>
                                </div>
                                <div>
                                    <dt>Activités fixes — couverture</dt>
                                    <dd><?= $capacityData['fixed_activities']['coverage_rate'] ?? 0 ?>%</dd>
                                </div>
                            </dl>
                        <?php endif; ?>
                        
                        <!-- Analyse de répartition des compétences -->
                        <?php if (!empty($preSolverDiagnostics['skill_distribution'] ?? [])): ?>
                            <?php 
                            $highRiskOffers = array_filter($preSolverDiagnostics['skill_distribution'], fn($s) => ($s['risk_level'] ?? '') === 'high');
                            $mediumRiskOffers = array_filter($preSolverDiagnostics['skill_distribution'], fn($s) => ($s['risk_level'] ?? '') === 'medium');
                            ?>
                            <?php if (!empty($highRiskOffers) || !empty($mediumRiskOffers)): ?>
                                <div class="crud-warn">
                                    <strong>Répartition des compétences</strong>
                                    <?php if (!empty($highRiskOffers)): ?>
                                        <p class="mb-2 mt-2">Offres à haut risque (ratio &gt; 0.8)</p>
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
                                        <p class="mb-0">Offres à risque moyen (ratio &gt; 0.5)</p>
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
                </section>

                <?php if (!empty($excludedOffers)): ?>
                    <section class="crud-section">
                        <h2 class="crud-section-title">Offres exclues (sans agents compétents)</h2>
                        <p>Les offres suivantes ont été exclues car aucun agent n'a la compétence requise :</p>
                        <ul>
                            <?php foreach ($excludedOffers as $excludedOffer): ?>
                                <li><strong><?= h($excludedOffer) ?></strong></li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>

                <?php if (!empty($preSolverDiagnostics['agents_at_risk'] ?? [])): ?>
                    <section class="crud-section">
                        <h2 class="crud-section-title cursor-pointer"
                            data-bs-toggle="collapse"
                            data-bs-target="#report-agents-at-risk"
                            aria-expanded="false"
                            aria-controls="report-agents-at-risk">
                            Agents à risque (<?= count($preSolverDiagnostics['agents_at_risk']) ?> agents)
                        </h2>
                        <div id="report-agents-at-risk" class="collapse">
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
                                                    <?= h($issueLabel) ?>
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
                                                        <br><small>Offres manquantes : <?= implode(', ', array_map('h', $agent['missing_base_offers'])) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small><?= h($agent['recommendation'] ?? '') ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (!empty($preSolverDiagnostics['fixed_activity_conflicts'] ?? [])): ?>
                    <section class="crud-section">
                        <h2 class="crud-section-title cursor-pointer"
                            data-bs-toggle="collapse"
                            data-bs-target="#report-fixed-conflicts"
                            aria-expanded="false"
                            aria-controls="report-fixed-conflicts">
                            Conflits d'activités fixes (<?= count($preSolverDiagnostics['fixed_activity_conflicts']) ?> site(s))
                        </h2>
                        <div id="report-fixed-conflicts" class="collapse">
                            <p class="text-muted mb-3">
                                Plusieurs activités fixes sur le même site nécessitent plus d'agents que disponibles, ou se chevauchent dans le temps.
                            </p>
                            <?php foreach ($preSolverDiagnostics['fixed_activity_conflicts'] as $conflict): ?>
                                <div class="crud-warn">
                                    <h3 class="crud-subsection-title mt-0">Site « <?= h($conflict['site']) ?> »</h3>
                                    <ul class="mb-2">
                                        <li>Nombre d'activités fixes : <?= count($conflict['activities']) ?></li>
                                        <li>Besoin total : <?= $conflict['total_needed_agents'] ?> agent(s)</li>
                                        <li>Agents compétents pour toutes les activités : <?= count($conflict['agents_competent_for_all']) ?> agent(s)</li>
                                        <?php if ($conflict['has_temporal_overlap']): ?>
                                            <li>Chevauchement temporel détecté</li>
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
                                    <p class="mb-0 mt-2"><strong>Recommandation :</strong> <?= h($conflict['recommendation'] ?? '') ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
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
                    <section class="crud-section">
                        <h2 class="crud-section-title cursor-pointer"
                            data-bs-toggle="collapse"
                            data-bs-target="#report-problems"
                            aria-expanded="false"
                            aria-controls="report-problems">
                            Problèmes détectés
                        </h2>
                        <div id="report-problems" class="collapse">
                            <?php if (!empty($preSolverDiagnostics['fixed_activities_no_competent_agents'] ?? [])): ?>
                                <div class="crud-warn">
                                    <h3 class="crud-subsection-title mt-0">Activités fixes sans agents compétents</h3>
                                    <ul class="mb-0">
                                        <?php foreach ($preSolverDiagnostics['fixed_activities_no_competent_agents'] as $issue): ?>
                                            <li>
                                                <strong><?= h($issue['activity']) ?></strong> sur le site "<?= h($issue['site']) ?>"
                                                : Aucun agent sur ce site n'a la compétence "<?= h($issue['base_offer']) ?>"
                                                <br><small>→ <?= h($issue['recommendation'] ?? '') ?></small>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($preSolverDiagnostics['fixed_activities_outside_work_hours'] ?? [])): ?>
                                <div class="crud-warn">
                                    <h3 class="crud-subsection-title mt-0">Activités fixes en dehors des heures de travail</h3>
                                    <ul class="mb-0">
                                        <?php foreach ($preSolverDiagnostics['fixed_activities_outside_work_hours'] as $issue): ?>
                                            <li>
                                                <strong><?= h($issue['activity']) ?></strong>
                                                : Plage horaire (<?= h($issue['time_range']['start']) ?> - <?= h($issue['time_range']['end']) ?>)
                                                dépasse les heures de travail (<?= h($issue['work_hours']['start']) ?> - <?= h($issue['work_hours']['end']) ?>)
                                                <br><small>→ <?= h($issue['recommendation'] ?? '') ?></small>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($preSolverDiagnostics['site_competency_issues'] ?? [])): ?>
                                <div class="crud-warn">
                                    <h3 class="crud-subsection-title mt-0">Problèmes de compétences par site</h3>
                                    <ul class="mb-0">
                                        <?php foreach ($preSolverDiagnostics['site_competency_issues'] as $issue): ?>
                                            <li>
                                                <strong>Site "<?= h($issue['site']) ?>"</strong>
                                                : <?= $issue['agents_on_site'] ?> agent(s) sur le site, mais aucun n'a les compétences requises
                                                (<?= implode(', ', array_map('h', $issue['required_skills'])) ?>)
                                                <br><small>→ <?= h($issue['recommendation'] ?? '') ?></small>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($preSolverDiagnostics['excluded_agents_potentially_useful'] ?? [])): ?>
                                <div class="crud-warn">
                                    <h3 class="crud-subsection-title mt-0">Agents exclus qui auraient pu être utiles</h3>
                                    <ul class="mb-0">
                                        <?php foreach ($preSolverDiagnostics['excluded_agents_potentially_useful'] as $agent): ?>
                                            <li>
                                                <strong>Agent #<?= h($agent['agent_id']) ?></strong>
                                                (exclu : <?= h($agent['exclusion_reason']) ?>)
                                                : Aurait pu couvrir <?= implode(', ', array_map('h', $agent['could_cover'])) ?>
                                                <?php if (!empty($agent['if_condition'])): ?>
                                                    <br><small class="text-muted"><?= h($agent['if_condition']) ?></small>
                                                <?php endif; ?>
                                                <br><small>→ <?= h($agent['recommendation'] ?? '') ?></small>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($preSolverDiagnostics['fixed_activities_remote_work_incompatibilities'] ?? [])): ?>
                                <div class="crud-warn">
                                    <h3 class="crud-subsection-title mt-0">Activités fixes incompatibles avec le télétravail</h3>
                                    <p class="text-muted mb-2">
                                        Certains créneaux demandent une activité marquée incompatible télétravail, mais trop d'agents compétents sont en télétravail.
                                    </p>
                                    <ul class="mb-0">
                                        <?php foreach (array_slice($preSolverDiagnostics['fixed_activities_remote_work_incompatibilities'], 0, 50) as $issue): ?>
                                            <li>
                                                <strong><?= h($issue['activity'] ?? '?') ?></strong> à <?= h($issue['time_slot'] ?? '?') ?>
                                                : requis <?= (int)($issue['required'] ?? 0) ?>, éligibles (hors TT) <?= (int)($issue['eligible_non_remote'] ?? 0) ?>,
                                                agents en TT <?= (int)($issue['remote_work_agents'] ?? 0) ?>
                                                <br><small>→ <?= h($issue['recommendation'] ?? '') ?></small>
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
                    </section>
                <?php endif; ?>

                <?php if (!empty($excludedAgents)): ?>
                    <section class="crud-section">
                        <h2 class="crud-section-title cursor-pointer"
                            data-bs-toggle="collapse"
                            data-bs-target="#report-excluded-agents"
                            aria-expanded="false"
                            aria-controls="report-excluded-agents">
                            Agents exclus avant l'envoi au solveur (<?= count($excludedAgents) ?> agents)
                        </h2>
                        <div id="report-excluded-agents" class="collapse">
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
                                                <td><?= h($excludedAgent['reason'] ?? 'Raison inconnue') ?></td>
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
                    </section>
                <?php endif; ?>

                <?php if (!empty($agentsSentToSolver)): ?>
                    <section class="crud-section">
                        <h2 class="crud-section-title">Agents envoyés au solveur</h2>
                        <p class="mb-0"><?= $agentsSentToSolver ?> agent(s) ont été envoyés au solveur avec des compétences et une disponibilité valides.</p>
                    </section>
                <?php endif; ?>

                <?php if (!empty($fixedActivities)): ?>
                    <section class="crud-section">
                        <h2 class="crud-section-title">Activités fixes (Passe 1)</h2>
                        <p class="text-muted mb-3">
                            Ces activités ont été assignées lors de la Passe 1 (activités fixes).
                            <?php if ($fixedActivityAssignmentsCount > 0): ?>
                                <?= $fixedActivityAssignmentsCount ?> assignation(s) ont été effectuée(s).
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
                                            <td class="text-end"><?= number_format($assignmentsCount) ?></td>
                                            <td class="text-end"><?= number_format($shortfall) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (!empty($offersNeeds)): ?>
                    <section class="crud-section">
                        <h2 class="crud-section-title">Besoins par offre (Passe 2 - Forecast)</h2>
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
                                            <td class="text-end"><?= number_format($needs['covered']) ?></td>
                                            <td class="text-end"><?= number_format($needs['shortage']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (!empty($diagnostics) && isset($diagnostics['agents']) && is_array($diagnostics['agents'])): ?>
                    <section class="crud-section">
                        <h2 class="crud-section-title cursor-pointer"
                            data-bs-toggle="collapse"
                            data-bs-target="#report-solver-diagnostics"
                            aria-expanded="false"
                            aria-controls="report-solver-diagnostics">
                            Diagnostics du solveur
                        </h2>
                        <div id="report-solver-diagnostics" class="collapse">
                            <?php if (!empty($diagnostics['conflicts'] ?? [])): ?>
                                <div class="crud-warn">
                                    <h3 class="crud-subsection-title mt-0">Conflits entre activités fixes détectés par le solveur</h3>
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
                                <div class="crud-warn">
                                    <h3 class="crud-subsection-title mt-0">Agents avec compétences limitées</h3>
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
                                <p class="text-muted mb-2"><?= count($agentDiagnosticsList) ?> agent(s) analysé(s) par le solveur.</p>
                            <?php endif; ?>
                        </div>
                    </section>
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
                    <section class="crud-section">
                        <h2 class="crud-section-title">Solutions possibles</h2>
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
                    </section>
                <?php endif; ?>

                <div class="crud-actions-bar">
                    <?php if ($isSuccess): ?>
                        <?php
                        $dateFormatted = is_string($date) ? date('d/m/Y', strtotime($date)) : $date->i18nFormat('dd/MM/yyyy');
                        ?>
                        <?= $this->Html->link(
                            'Voir le planning',
                            [
                                'controller' => 'Grids',
                                'action' => 'index',
                                '?' => ['date_start' => $dateFormatted],
                            ],
                            ['class' => 'btn btn-primary']
                        ) ?>
                        <?= $this->Html->link(
                            'Régénérer',
                            ['action' => 'generate'],
                            ['class' => 'btn btn-outline-secondary']
                        ) ?>
                    <?php else: ?>
                        <?= $this->Html->link(
                            'Réessayer la génération',
                            ['action' => 'generate'],
                            ['class' => 'btn btn-primary']
                        ) ?>
                    <?php endif; ?>
                </div>
            </div>
</div>

