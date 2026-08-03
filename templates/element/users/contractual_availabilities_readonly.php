<?php
/**
 * Element: Disponibilités contractuelles (lecture seule)
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 */
?>

<?php
$days = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];
$byDay = [];
foreach (($user->user_availabilities ?? []) as $availability) {
    $byDay[(int)$availability->day_of_week] = $availability;
}

$formatTime = function ($value): string {
    if ($value === null || $value === '') {
        return '—';
    }
    if ($value instanceof \DateTimeInterface) {
        return $value->format('H:i');
    }
    if (is_string($value)) {
        return substr($value, 0, 5);
    }
    return '—';
};
?>

<div class="card border-info mb-4">
    <div class="card-header bg-info text-white">
        <i class="bi bi-calendar-week"></i> Disponibilités Contractuelles
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th style="width: 25%;">Jour</th>
                    <th style="width: 25%;">Disponible de</th>
                    <th style="width: 25%;">Disponible à</th>
                    <th style="width: 25%;">Fin la plus tôt</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($days as $dayNum => $dayName): ?>
                    <?php $a = $byDay[(int)$dayNum] ?? null; ?>
                    <tr>
                        <td class="fw-bold"><i class="bi bi-calendar"></i> <?= h($dayName) ?></td>
                        <td><?= h($formatTime($a->availability_start_time ?? null)) ?></td>
                        <td><?= h($formatTime($a->availability_end_time ?? null)) ?></td>
                        <td><?= h($formatTime($a->earliest_end_time ?? null)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>








