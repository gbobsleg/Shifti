<?php
/**
 * Element: Disponibilités contractuelles (lecture seule)
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 */
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

<section class="crud-section">
    <h2 class="crud-section-title">Disponibilités contractuelles</h2>
    <div class="table-responsive">
        <table class="table table-hover table-sm crud-table">
            <thead>
            <tr>
                <th scope="col">Jour</th>
                <th scope="col">Disponible de</th>
                <th scope="col">Disponible à</th>
                <th scope="col">Fin la plus tôt</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($days as $dayNum => $dayName): ?>
                <?php $a = $byDay[(int)$dayNum] ?? null; ?>
                <tr>
                    <td><?= h($dayName) ?></td>
                    <td><?= h($formatTime($a->availability_start_time ?? null)) ?></td>
                    <td><?= h($formatTime($a->availability_end_time ?? null)) ?></td>
                    <td><?= h($formatTime($a->earliest_end_time ?? null)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
