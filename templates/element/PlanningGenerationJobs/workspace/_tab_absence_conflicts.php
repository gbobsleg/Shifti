<?php
/**
 * Onglet Conflits d'absences : segments brouillons ignorés à la publication.
 *
 * @var \App\View\AppView $this
 * @var array $skippedDetails  [{agent, user_id, date, heure_debut, heure_fin, offre, offer_id}, ...]
 * @var int $skippedCount
 */
?>
<div class="card shadow">
    <div class="card-header bg-light">
        <h5 class="mb-0">
            <i class="bi bi-shield-x text-danger"></i>
            Segments ignorés lors de la publication
            <span class="badge badge-danger ml-2"><?= $skippedCount ?></span>
        </h5>
        <small class="text-muted">
            Ces segments du brouillon chevauchaient une absence déclarée et ont été écartés.
        </small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Agent</th>
                        <th>Date</th>
                        <th>Début</th>
                        <th>Fin</th>
                        <th>Offre</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($skippedDetails)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-3">
                                Aucun conflit d'absence détecté.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($skippedDetails as $item): ?>
                            <tr>
                                <td><strong><?= h($item['agent']) ?></strong></td>
                                <td><?= h($item['date']) ?></td>
                                <td><span class="badge badge-light"><?= h($item['heure_debut']) ?></span></td>
                                <td><span class="badge badge-light"><?= h($item['heure_fin']) ?></span></td>
                                <td><?= h($item['offre']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
