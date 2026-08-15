<?php
/**
 * Navigation par onglets du workspace.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\PlanningGenerationJob $job
 * @var string $workspaceTab
 * @var array|null $stats
 */
$stats = $stats ?? null;
$currentTab = $workspaceTab ?? 'planning';
$skippedCountNav = (int)($skippedCount ?? 0);

$queryBase = $this->request->getQueryParams();
unset($queryBase['tab'], $queryBase['section']);

$tabUrl = function (string $tab) use ($job, $queryBase): array {
    $q = $queryBase;
    $q['tab'] = $tab;
    return ['action' => 'view', (int)$job->id, '?' => $q];
};

$qualityBadge = 0;
$techBadge = 0;
if (is_array($stats)) {
    $qualityBadge = (int)($stats['days_infeasible'] ?? 0) + (int)($stats['days_error'] ?? 0);
    $excludedSummaryNav = $excludedSummary ?? null;
    $actionableExcluded = is_array($excludedSummaryNav)
        ? (int)($excludedSummaryNav['agents_actionable'] ?? 0)
        : 0;
    $techBadge = $actionableExcluded + (int)($stats['total_warnings'] ?? 0);
}
$complianceSummaryNav = $complianceSummary ?? null;
if (is_array($complianceSummaryNav)) {
    $qualityBadge += (int)($complianceSummaryNav['ko_total'] ?? 0);
}

$tabs = [
    'planning' => ['label' => 'Planning', 'icon' => 'calendar3'],
    'qualite' => ['label' => 'Qualité', 'icon' => 'clipboard-check', 'badge' => $qualityBadge, 'badgeClass' => 'danger'],
    'equite' => ['label' => 'Équité', 'icon' => 'scale'],
    'technique' => ['label' => 'Technique', 'icon' => 'bug', 'badge' => $techBadge, 'badgeClass' => 'warning'],
    'conflits' => [
        'label' => "Conflits d'absences",
        'icon' => 'shield-x',
        'badge' => $skippedCountNav,
        'badgeClass' => 'danger',
        'hideWhenEmpty' => true,
    ],
];
?>
<ul class="nav nav-tabs mb-3" id="workspace-nav" role="tablist">
    <?php foreach ($tabs as $key => $meta):
        if (!empty($meta['hideWhenEmpty']) && empty($meta['badge'])) continue;
    ?>
        <li class="nav-item" role="presentation">
            <?= $this->Html->link(
                '<i class="bi bi-' . h($meta['icon']) . '"></i> ' . h($meta['label'])
                . (!empty($meta['badge'])
                    ? ' <span class="badge badge-' . h($meta['badgeClass'] ?? 'secondary') . ' badge-count ml-1">' . (int)$meta['badge'] . '</span>'
                    : ''),
                $tabUrl($key),
                [
                    'class' => 'nav-link' . ($currentTab === $key ? ' active' : ''),
                    'escape' => false,
                    'role' => 'tab',
                    'aria-selected' => $currentTab === $key ? 'true' : 'false',
                ]
            ) ?>
        </li>
    <?php endforeach; ?>
</ul>
