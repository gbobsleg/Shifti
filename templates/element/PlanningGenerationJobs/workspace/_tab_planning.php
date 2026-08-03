<?php
/**
 * Onglet Planning : progression live ou iframe brouillon.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\PlanningGenerationJob $job
 * @var \Cake\I18n\FrozenTime|null $firstDayProcessedAt
 */
$status = (string)$job->status;
$isLive = in_array($status, ['queued', 'running'], true);

$draftQuery = ['embed' => '1'];
$dateStart = $this->request->getQuery('date_start');
$dateEnd = $this->request->getQuery('date_end');
if (!empty($dateStart)) {
    $draftQuery['date_start'] = $dateStart;
}
if (!empty($dateEnd)) {
    $draftQuery['date_end'] = $dateEnd;
}
$draftUrl = $this->Url->build(['action' => 'draft', (int)$job->id, '?' => $draftQuery]);
?>
<?php if ($isLive): ?>
    <?= $this->element('PlanningGenerationJobs/workspace/_progress') ?>
    <div class="alert alert-info mb-0">
        <i class="bi bi-info-circle"></i>
        Génération en cours. Le brouillon sera disponible ici dès que le job sera terminé.
    </div>
<?php else: ?>
    <div class="d-flex justify-content-end align-items-center mb-2">
        <?= $this->Html->link(
            '<i class="bi bi-box-arrow-up-right"></i> Ouvrir en plein écran',
            ['action' => 'draft', (int)$job->id, '?' => $draftQuery],
            ['class' => 'btn btn-sm btn-outline-secondary', 'escape' => false, 'target' => '_blank']
        ) ?>
    </div>
    <iframe
        id="draft-embed-frame"
        src="<?= h($draftUrl) ?>"
        title="Brouillon job #<?= (int)$job->id ?>"
        style="width: 100%; height: 75vh; border: 1px solid #dee2e6; border-radius: 0.25rem; background: #fff;"
        loading="lazy"
    ></iframe>
<?php endif; ?>
