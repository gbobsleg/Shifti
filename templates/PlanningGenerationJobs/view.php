<?php
/**
 * Workspace unique : Planning / Qualité / Technique.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\PlanningGenerationJob $job
 * @var string $workspaceTab
 * @var string $workspaceSection
 * @var \Cake\I18n\FrozenTime|null $firstDayProcessedAt
 */
$workspaceTab = $workspaceTab ?? 'planning';
$workspaceSection = $workspaceSection ?? '';
?>
<?php $this->assign('title', 'Génération planning #' . (int)$job->id); ?>
<?php $this->extend('/layout/TwitterBootstrap/dashtron_fullwidth'); ?>
<?php $this->Html->script('planning-generation-workspace', ['block' => true]); ?>

<style>
.timeline-bar {
    height: 8px;
    border-radius: 4px;
}
.realtime-indicator {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background-color: #318f9b;
    animation: pulse 2s infinite;
    margin-right: 0.5rem;
}
.realtime-indicator.inactive {
    background-color: #6c757d;
    animation: none;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
.badge-count {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}
.pass-badge {
    font-size: 0.65rem;
    padding: 0.2rem 0.4rem;
    margin-left: 0.25rem;
}
.equity-report-table { font-size: 0.8rem; white-space: nowrap; }
.equity-report-table th,
.equity-report-table td { padding: 0.3rem 0.5rem; }
.equity-report-table th { cursor: pointer; user-select: none; }
.equity-report-table th.sort-asc::after { content: ' ▲'; font-size: 0.7em; }
.equity-report-table th.sort-desc::after { content: ' ▼'; font-size: 0.7em; }
.equity-report-table .pct-dispo { font-weight: 600; }
.equity-report-table th.equity-col { max-width: 10rem; white-space: normal; }
.equity-table-responsive { max-height: 70vh; overflow-y: auto; }
.equity-report-table thead th {
    position: sticky;
    top: 0;
    z-index: 10;
    background: #fff;
    box-shadow: inset 0 -1px 0 var(--crud-border, #dee2e6);
}
#equity-cols-menu { font-size: 0.85rem; }
#equity-cols-menu .form-check { margin-bottom: 0.1rem; }
#equity-cols-menu .form-check-label { cursor: pointer; white-space: normal; }
.cursor-pointer { cursor: pointer; }
.workspace-collapse-toggle .bi-chevron-down {
    transition: transform 0.2s ease;
    float: right;
    margin-top: 0.15rem;
}
.workspace-collapse-toggle[aria-expanded="true"] .bi-chevron-down {
    transform: rotate(180deg);
}
.excluded-reason-chip,
.excluded-filter-chip {
    white-space: normal;
    text-align: left;
    max-width: 100%;
}
.excluded-reason-chip.active,
.excluded-filter-chip.active {
    box-shadow: 0 0 0 2px rgba(49, 143, 155, 0.35);
}
.excluded-agent-toggle .bi-chevron-down {
    transition: transform 0.2s ease;
}
.excluded-agent-toggle[aria-expanded="true"] .bi-chevron-down {
    transform: rotate(180deg);
}
#excluded-agents-table tbody tr.excluded-agent-detail-row > td {
    background: transparent;
}
#compliance-panel .compliance-filter-radio {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}
#compliance-panel #cff-all:checked ~ .compliance-fixed-filters label[for="cff-all"],
#compliance-panel #crf-all:checked ~ .compliance-rotation-filters label[for="crf-all"] {
    color: #fff;
    background-color: #6c757d;
    border-color: #6c757d;
}
#compliance-panel #cff-ko:checked ~ .compliance-fixed-filters label[for="cff-ko"],
#compliance-panel #crf-ko:checked ~ .compliance-rotation-filters label[for="crf-ko"] {
    color: #fff;
    background-color: #dc3545;
    border-color: #dc3545;
}
#compliance-panel #cff-ko:checked ~ .compliance-fixed-groups tr[data-compliance-status="ok"],
#compliance-panel #crf-ko:checked ~ .table-responsive tr[data-compliance-status="ok"] {
    display: none !important;
}
#compliance-panel #cff-ko:checked ~ .compliance-fixed-groups .compliance-fixed-date-group[data-date-ko="0"] {
    display: none !important;
}
</style>

<div class="crud-app crud-app-wide planning-generation-jobs view content"
     id="planning-generation-workspace"
     data-workspace-section="<?= h($workspaceSection) ?>"
     data-csrf-token="<?= h((string)$this->request->getAttribute('csrfToken')) ?>">
    <?= $this->element('PlanningGenerationJobs/workspace/_header') ?>
    <?= $this->element('PlanningGenerationJobs/workspace/_nav') ?>

    <?php if ($workspaceTab === 'qualite'): ?>
        <?= $this->element('PlanningGenerationJobs/workspace/_tab_quality') ?>
    <?php elseif ($workspaceTab === 'equite'): ?>
        <?= $this->element('PlanningGenerationJobs/workspace/_tab_equity') ?>
    <?php elseif ($workspaceTab === 'technique'): ?>
        <?= $this->element('PlanningGenerationJobs/workspace/_tab_tech') ?>
    <?php elseif ($workspaceTab === 'conflits'): ?>
        <?= $this->element('PlanningGenerationJobs/workspace/_tab_absence_conflicts') ?>
    <?php else: ?>
        <?= $this->element('PlanningGenerationJobs/workspace/_tab_planning') ?>
    <?php endif; ?>
</div>
