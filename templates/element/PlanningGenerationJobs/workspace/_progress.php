<?php
/**
 * Barre de progression live du job.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\PlanningGenerationJob $job
 * @var \Cake\I18n\FrozenTime|null $firstDayProcessedAt
 */
$status = (string)$job->status;
$badge = 'secondary';
$icon = 'clock';
$progressBarClass = 'bg-secondary';
$isRunning = false;

if ($status === 'finished' || $status === 'finished_with_errors') {
    $badge = 'success';
    $icon = 'check-circle';
    $progressBarClass = 'bg-success';
} elseif ($status === 'running') {
    $badge = 'info';
    $icon = 'arrow-repeat';
    $progressBarClass = 'bg-info';
    $isRunning = true;
} elseif ($status === 'queued') {
    $badge = 'warning';
    $icon = 'hourglass-split';
    $progressBarClass = 'bg-warning';
} elseif ($status === 'error' || $status === 'infeasible') {
    $badge = 'danger';
    $icon = $status === 'error' ? 'x-circle' : 'exclamation-triangle';
    $progressBarClass = 'bg-danger';
}

$processedDays = (int)$job->processed_days;
$totalDays = (int)$job->total_days;
$progress = $totalDays > 0 ? round(($processedDays / $totalDays) * 100) : 0;

$startedAt = $job->started_at ?? $job->created ?? null;
$startedAtTs = null;
if ($startedAt instanceof \DateTimeInterface) {
    $startedAtTs = $startedAt->getTimestamp();
}

$finishedAt = $job->finished_at ?? null;
$finishedAtTs = null;
if ($finishedAt instanceof \DateTimeInterface) {
    $finishedAtTs = $finishedAt->getTimestamp();
}

$firstDayProcessedAtTs = null;
if (!empty($firstDayProcessedAt) && $firstDayProcessedAt instanceof \DateTimeInterface) {
    $firstDayProcessedAtTs = $firstDayProcessedAt->getTimestamp();
}

$options = [];
if (!empty($job->options_json)) {
    $decoded = json_decode((string)$job->options_json, true);
    if (is_array($decoded)) {
        $options = $decoded;
    }
}
?>
<div class="card shadow mb-3" id="workspace-progress"
     data-job-id="<?= (int)$job->id ?>"
     data-status-url="<?= h($this->Url->build(['action' => 'status', (int)$job->id, '_ext' => 'json'])) ?>"
     data-started-at="<?= $startedAtTs !== null ? (int)$startedAtTs : '' ?>"
     data-finished-at="<?= $finishedAtTs !== null ? (int)$finishedAtTs : '' ?>"
     data-first-day-processed-at="<?= $firstDayProcessedAtTs !== null ? (int)$firstDayProcessedAtTs : '' ?>"
     data-ignore-fixed="<?= !empty($options['ignore_fixed_activities']) ? '1' : '0' ?>"
     data-ignore-rotation="<?= !empty($options['ignore_rotation']) ? '1' : '0' ?>"
     data-ignore-forecast="<?= !empty($options['ignore_forecast_solver']) ? '1' : '0' ?>"
     data-initial-status="<?= h($status) ?>">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <span class="realtime-indicator <?= $isRunning ? '' : 'inactive' ?>" id="realtimeIndicator"></span>
            <i class="bi bi-activity"></i> Progression
        </h5>
        <span id="jobStatusBadge" class="badge badge-<?= $badge ?>">
            <i class="bi bi-<?= $icon ?>"></i> <?= h($status) ?>
        </span>
    </div>
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span><strong id="jobProgressText"><?= $processedDays ?> / <?= $totalDays ?></strong> jours</span>
            <span class="text-muted" id="jobProgressPercent"><?= $progress ?>%</span>
        </div>
        <div class="progress mb-3" style="height: 24px;">
            <div id="jobProgressBar"
                 class="progress-bar <?= $isRunning ? 'progress-bar-striped progress-bar-animated' : '' ?> <?= $progressBarClass ?>"
                 role="progressbar"
                 style="width: <?= $progress ?>%"
                 aria-valuenow="<?= $progress ?>"
                 aria-valuemin="0"
                 aria-valuemax="100">
                <span id="jobProgressBarLabel"><?= $progress ?>%</span>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4 mb-2">
                <label class="text-muted small mb-1 d-block">Jour en cours</label>
                <div class="h6 mb-0" id="jobCurrentDate"><?= h((string)($job->current_day ?? '—')) ?></div>
            </div>
            <div class="col-md-4 mb-2">
                <label class="text-muted small mb-1 d-block">Étape</label>
                <div class="mb-0 small" id="jobCurrentStep"><?= h((string)($job->current_step ?? '—')) ?></div>
            </div>
            <div class="col-md-4 mb-2">
                <label class="text-muted small mb-1 d-block">Temps restant estimé</label>
                <div><strong id="jobEta">—</strong></div>
            </div>
        </div>
    </div>
</div>
