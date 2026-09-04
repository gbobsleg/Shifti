<?php
/**
 * Onglet Conflits d'absences : segments brouillons ignorés à la publication.
 *
 * @var \App\View\AppView $this
 * @var array $skippedDetails  [{agent, user_id, date, heure_debut, heure_fin, offre, offer_id}, ...]
 * @var int $skippedCount
 */
?>
<section class="crud-section">
    <h2 class="crud-section-title">
        Segments ignorés lors de la publication
        <span class="badge bg-danger badge-count ms-1"><?= $skippedCount ?></span>
    </h2>
    <p class="text-muted mb-3">
        Ces segments du brouillon chevauchaient une absence déclarée et ont été écartés.
    </p>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
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
                            <td><?= h($item['heure_debut']) ?></td>
                            <td><?= h($item['heure_fin']) ?></td>
                            <td><?= h($item['offre']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
