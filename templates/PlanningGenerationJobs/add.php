<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\PlanningGenerationJob $job
 * @var array $wfmSettingsList
 * @var array $scenariosList
 * @var iterable<\App\Model\Entity\Site> $sites
 * @var array<int,int>|null $selectedAgentIds
 * @var bool|null $ignoreFixedActivities
 * @var bool|null $ignoreRotation
 * @var bool|null $ignoreForecastSolver
 * @var bool|null $debugSolvers
 */
$isEdit = !$job->isNew();
$selectedAgentIds = $selectedAgentIds ?? [];
?>
<?php $this->assign('title', $isEdit ? 'Modifier la génération #' . $job->id : 'Nouvelle génération'); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>

<div class="crud-app planning-generation-jobs form crud-app-wide content">
    <div class="crud-header">
        <h1>
            <i class="bi bi-cpu"></i>
            <?= $isEdit ? 'Modifier la génération #' . (int)$job->id : 'Générer un planning (multi-jours)' ?>
        </h1>
        <div class="crud-header-actions">
            <?= $this->Html->link(
                '<i class="bi bi-list me-1"></i> Liste',
                ['action' => 'index'],
                ['class' => 'btn btn-outline-secondary', 'escape' => false]
            ) ?>
        </div>
    </div>

    <?php if ($isEdit): ?>
        <div class="crud-notice">
            <div>
                Modifier ces paramètres réinitialise entièrement la génération (statuts des jours, brouillon, rapport) et la remet en file d'attente.
            </div>
        </div>
    <?php endif; ?>

    <?= $this->Form->create($job) ?>

    <p class="text-muted">
        Le planning est généré en brouillon (non visible dans la grille principale). À la fin, tu peux le consulter,
        le modifier, puis le publier (tout ou plage de dates).
    </p>

    <section class="crud-section">
        <h2 class="crud-section-title">Paramètres</h2>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Date de début</label>
                <?= $this->Form->control('start_date', [
                    'label' => false,
                    'type' => 'date',
                    'class' => 'form-control',
                    'required' => true,
                    'value' => $isEdit
                        ? $job->start_date->format('Y-m-d')
                        : (new \DateTime('tomorrow'))->format('Y-m-d'),
                ]) ?>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Date de fin</label>
                <?= $this->Form->control('end_date', [
                    'label' => false,
                    'type' => 'date',
                    'class' => 'form-control',
                    'required' => true,
                    'value' => $isEdit
                        ? $job->end_date->format('Y-m-d')
                        : (new \DateTime('tomorrow'))->modify('+7 days')->format('Y-m-d'),
                ]) ?>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Profil de paramètres à utiliser</label>
            <?= $this->Form->control('wfm_setting_id', [
                'label' => false,
                'options' => $wfmSettingsList,
                'class' => 'form-control',
                'required' => true,
            ]) ?>
        </div>
        <div class="mb-3">
            <label class="form-label">Scénario (optionnel)</label>
            <?= $this->Form->control('scenario_id', [
                'label' => false,
                'options' => $scenariosList,
                'empty' => '— Calculer en live —',
                'class' => 'form-control',
            ]) ?>
        </div>
    </section>

    <section class="crud-section">
        <h2 class="crud-section-title">Options</h2>
        <div class="form-check form-switch">
            <?= $this->Form->control('ignore_fixed_activities', [
                'type' => 'checkbox',
                'label' => 'Ignorer les activités fixes (Passe 1)',
                'class' => 'form-check-input',
                'checked' => $ignoreFixedActivities ?? false,
                'templates' => [
                    'inputContainer' => '<div class="form-check">{{content}}</div>',
                    'checkboxFormGroup' => '{{input}}<label class="form-check-label" for="{{name}}">{{label}}</label>',
                ],
            ]) ?>
        </div>
        <div class="form-check form-switch mt-2">
            <?= $this->Form->control('ignore_rotation', [
                'type' => 'checkbox',
                'label' => 'Ignorer les activités avec rotations (Passe 1.5)',
                'class' => 'form-check-input',
                'checked' => $ignoreRotation ?? false,
                'templates' => [
                    'inputContainer' => '<div class="form-check">{{content}}</div>',
                    'checkboxFormGroup' => '{{input}}<label class="form-check-label" for="{{name}}">{{label}}</label>',
                ],
            ]) ?>
        </div>
        <div class="form-check form-switch mt-2">
            <?= $this->Form->control('ignore_forecast_solver', [
                'type' => 'checkbox',
                'label' => 'Ignorer les activités avec prévisions (Passe 2)',
                'class' => 'form-check-input',
                'checked' => $ignoreForecastSolver ?? false,
                'templates' => [
                    'inputContainer' => '<div class="form-check">{{content}}</div>',
                    'checkboxFormGroup' => '{{input}}<label class="form-check-label" for="{{name}}">{{label}}</label>',
                ],
            ]) ?>
        </div>
        <div class="form-check form-switch mt-2">
            <?= $this->Form->control('debug_solvers', [
                'type' => 'checkbox',
                'label' => 'Mode diagnostic (explication d\'infaisabilité Passe 2 + logs structurés)',
                'class' => 'form-check-input',
                'checked' => $debugSolvers ?? false,
                'templates' => [
                    'inputContainer' => '<div class="form-check">{{content}}</div>',
                    'checkboxFormGroup' => '{{input}}<label class="form-check-label" for="{{name}}">{{label}}</label>',
                ],
            ]) ?>
        </div>
        <small class="text-muted d-block mt-2">
            Les weekends sont ignorés (cohérent avec l’affichage de la grille).
        </small>
    </section>

    <section class="crud-section">
        <h2 class="crud-section-title">Agents à planifier</h2>
        <div class="form-check form-switch">
            <?= $this->Form->control('restrict_agents', [
                'type' => 'checkbox',
                'label' => "Restreindre à une sélection d'agents",
                'class' => 'form-check-input',
                'id' => 'restrict-agents-switch',
                'data-bs-toggle' => 'collapse',
                'data-bs-target' => '#agentSelectionCollapse',
                'checked' => !empty($selectedAgentIds),
                'templates' => [
                    'inputContainer' => '<div class="form-check">{{content}}</div>',
                    'checkboxFormGroup' => '{{input}}<label class="form-check-label" for="{{name}}">{{label}}</label>',
                ],
            ]) ?>
        </div>

        <div class="collapse mt-3<?= !empty($selectedAgentIds) ? ' show' : '' ?>" id="agentSelectionCollapse">
            <p class="text-muted">
                Sélectionner un sous-ensemble d'agents ne réduit pas le besoin prévisionnel (need_curve).
                Des écarts de couverture sont à prévoir. À réserver au débogage ou à des générations ciblées.
            </p>

            <div id="agentsAccordion">
                <?php foreach ($sites as $site): ?>
                    <?php
                        $siteUsers = $site->users ?? [];
                        $siteAgentIds = array_map(fn ($u) => (int)$u->id, $siteUsers);
                        $allSiteAgentsSelected = !empty($siteAgentIds) && empty(array_diff($siteAgentIds, $selectedAgentIds));
                    ?>
                    <div class="mb-3 pb-2" style="border-bottom: 1px solid var(--crud-border, #e2e8ea);">
                        <div class="d-flex align-items-center justify-content-between py-2">
                            <div class="form-check mb-0">
                                <input type="checkbox" class="form-check-input site-select-all" id="siteCheckAll<?= (int)$site->id ?>" data-site-id="<?= (int)$site->id ?>" <?= $allSiteAgentsSelected ? 'checked' : '' ?> disabled>
                                <label class="form-check-label fw-bold" for="siteCheckAll<?= (int)$site->id ?>">
                                    <?= h($site->name) ?> <span class="text-muted">(<?= count($siteUsers) ?> agent<?= count($siteUsers) > 1 ? 's' : '' ?>)</span>
                                </label>
                            </div>
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#siteCollapse<?= (int)$site->id ?>">
                                <i class="bi bi-chevron-down"></i>
                            </button>
                        </div>
                        <div id="siteCollapse<?= (int)$site->id ?>" class="collapse">
                            <?php if (empty($siteUsers)): ?>
                                <span class="text-muted">Aucun agent rattaché à ce site.</span>
                            <?php else: ?>
                                <?php foreach ($siteUsers as $agent): ?>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input agent-checkbox" name="agent_ids[]" value="<?= (int)$agent->id ?>" data-site-id="<?= (int)$site->id ?>" id="agent<?= (int)$agent->id ?>" <?= in_array((int)$agent->id, $selectedAgentIds, true) ? 'checked' : '' ?> disabled>
                                        <label class="form-check-label" for="agent<?= (int)$agent->id ?>">
                                            <?= h(trim((string)$agent->last_name . ' ' . (string)$agent->first_name)) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <div class="crud-actions-bar">
        <?= $this->Form->button(
            $isEdit
                ? '<i class="bi bi-check-circle"></i> Enregistrer les modifications'
                : '<i class="bi bi-play-circle"></i> Créer la génération',
            [
                'class' => 'btn btn-primary',
                'escapeTitle' => false,
            ]
        ) ?>
        <?= $this->Html->link(
            '<i class="bi bi-x-circle me-2"></i> Annuler',
            ['action' => 'index'],
            ['class' => 'btn btn-outline-secondary', 'escape' => false]
        ) ?>
    </div>
    <?= $this->Form->end() ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var restrictSwitch = document.getElementById('restrict-agents-switch');
    var agentCheckboxes = document.querySelectorAll('.agent-checkbox');
    var siteCheckboxes = document.querySelectorAll('.site-select-all');

    function setAgentInputsDisabled(disabled) {
        agentCheckboxes.forEach(function (cb) { cb.disabled = disabled; });
        siteCheckboxes.forEach(function (cb) { cb.disabled = disabled; });
    }

    if (restrictSwitch) {
        setAgentInputsDisabled(!restrictSwitch.checked);
        restrictSwitch.addEventListener('change', function () {
            setAgentInputsDisabled(!restrictSwitch.checked);
        });
    }

    siteCheckboxes.forEach(function (siteCb) {
        siteCb.addEventListener('change', function () {
            var siteId = siteCb.getAttribute('data-site-id');
            document.querySelectorAll('.agent-checkbox[data-site-id="' + siteId + '"]').forEach(function (agentCb) {
                agentCb.checked = siteCb.checked;
            });
        });
    });

    agentCheckboxes.forEach(function (agentCb) {
        agentCb.addEventListener('change', function () {
            var siteId = agentCb.getAttribute('data-site-id');
            var siblings = document.querySelectorAll('.agent-checkbox[data-site-id="' + siteId + '"]');
            var allChecked = Array.prototype.every.call(siblings, function (cb) { return cb.checked; });
            var siteCb = document.querySelector('.site-select-all[data-site-id="' + siteId + '"]');
            if (siteCb) {
                siteCb.checked = allChecked;
            }
        });
    });
});
</script>
