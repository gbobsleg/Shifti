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
.kpi-card {
    transition: transform 0.2s, box-shadow 0.2s;
    border-left: 4px solid;
}
.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
}
.kpi-value {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 0.35rem;
}
.kpi-label {
    font-size: 0.85rem;
    color: #6c757d;
}
.timeline-bar {
    height: 8px;
    border-radius: 4px;
}
.realtime-indicator {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background-color: #28a745;
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
.nav-tabs .nav-link {
    border: none;
    border-bottom: 3px solid transparent;
    color: #6c757d;
    font-weight: 500;
}
.nav-tabs .nav-link:hover {
    border-bottom-color: #dee2e6;
    color: #495057;
}
.nav-tabs .nav-link.active {
    border-bottom-color: #0d6efd;
    color: #0d6efd;
    background: transparent;
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
.equity-report-table { font-size: 0.85rem; }
.equity-report-table .pct-dispo { font-weight: 600; }
.equity-report-table th.equity-col { max-width: 10rem; }
.cursor-pointer { cursor: pointer; }
.card-header .bi-chevron-down {
    transition: transform 0.2s ease;
}
.card-header[aria-expanded="true"] .bi-chevron-down {
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
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.35);
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
#compliance-panel .badge.p-2 {
    font-size: 0.9rem;
    font-weight: 500;
}
/* Filtres conformité (radio + CSS, sans JS) */
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

<div class="row">
    <div class="col-12"
         id="planning-generation-workspace"
         data-workspace-section="<?= h($workspaceSection) ?>"
         data-csrf-token="<?= h((string)$this->request->getAttribute('csrfToken')) ?>">
        <?= $this->element('PlanningGenerationJobs/workspace/_header') ?>
        <?= $this->element('PlanningGenerationJobs/workspace/_nav') ?>

        <?php if ($workspaceTab === 'qualite'): ?>
            <?= $this->element('PlanningGenerationJobs/workspace/_tab_quality') ?>
        <?php elseif ($workspaceTab === 'technique'): ?>
            <?= $this->element('PlanningGenerationJobs/workspace/_tab_tech') ?>
        <?php elseif ($workspaceTab === 'conflits'): ?>
            <?= $this->element('PlanningGenerationJobs/workspace/_tab_absence_conflicts') ?>
        <?php else: ?>
            <?= $this->element('PlanningGenerationJobs/workspace/_tab_planning') ?>
        <?php endif; ?>
    </div>
</div>
