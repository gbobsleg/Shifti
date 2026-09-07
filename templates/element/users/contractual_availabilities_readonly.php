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
    if (is_object($value) && method_exists($value, 'format')) {
        return $value->format('H:i');
    }
    if (is_string($value) && preg_match('/^(\d{2}):(\d{2})/', $value, $m)) {
        return $m[1] . ':' . $m[2];
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
                <th scope="col">Heure de début</th>
                <th scope="col">Heure de fin</th>
                <th scope="col">Fin la plus tôt</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($days as $dayNum => $dayName): ?>
                <?php
                $a = $byDay[(int)$dayNum] ?? null;
                $start = $formatTime($a->availability_start_time ?? null);
                $end = $formatTime($a->availability_end_time ?? null);
                $off = $start === '00:00' && $end === '00:00';
                ?>
                <tr>
                    <td><?= h($dayName) ?></td>
                    <?php if ($off || $a === null): ?>
                        <td colspan="3" class="text-muted">Non travaillé</td>
                    <?php else: ?>
                        <td><?= h($start) ?></td>
                        <td><?= h($end) ?></td>
                        <td><?= h($formatTime($a->earliest_end_time ?? null)) ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
