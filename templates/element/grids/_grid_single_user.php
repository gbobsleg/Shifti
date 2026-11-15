<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User[]|\Cake\Collection\CollectionInterface $users_ranges
 * @var array $day_ranges
 * @var array $offers_name
 * @var int $gridStartHour
 * @var int $gridEndHour
 */

// Configurer les heures de la grille dans le helper
if (isset($gridStartHour, $gridEndHour)) {
    $this->Grids->setGridHours($gridStartHour, $gridEndHour);
}

// Récupération de l'utilisateur unique
$user = is_array($users_ranges) ? $users_ranges[0] : $users_ranges->first();
$rangesProperty = $rangesProperty ?? 'ranges';
?>

<table class="quarter grid-single-user">
    <thead>
        <tr>
            <th scope="col" colspan="50" class="th_title">
                Planning de <?= h($user->full_name) ?>
                (Site: <?= h($user['site']['name']) ?>)
                du <?= $day_ranges['begin']->i18nFormat('dd/MM/yyyy') ?>
                au <?= $day_ranges['end']->i18nFormat('dd/MM/yyyy') ?>
            </th>
        </tr>
        <tr>
            <th scope="col" class="th_col2">Date</th>
            <?php 
            $frameDays = array_map('intval', $frameDays ?? [1, 2, 3, 4, 5]);
            $closedByDate = $closedByDate ?? [];
            // Premier jour de la trame pour l'en-tête des heures (un férié reste affiché)
            $firstDay = clone $day_ranges['begin'];
            while (!in_array((int)$firstDay->format('N'), $frameDays, true)) {
                $firstDay = $firstDay->addDays(1);
            }
            echo $this->Grids->writeThTime('hours', $firstDay); 
            ?>
        </tr>
        <tr>
            <th scope="col" class="th_col2">&nbsp;</th>
            <?php echo $this->Grids->writeThTime('minutes', $firstDay); ?>
        </tr>
    </thead>
    <tbody>
    <?php
    $days = $day_ranges['begin']->diffInDays($day_ranges['end']);
    $currentDay = $day_ranges['begin'];
    
    for ($i = 0; $i <= $days; $i++):
        if (!in_array((int)$currentDay->format('N'), $frameDays, true)) {
            $currentDay = $currentDay->addDays(1);
            continue;
        }
        $rowYmdEarly = $currentDay->format('Y-m-d');
        $closedLabel = $closedByDate[$rowYmdEarly] ?? null;
        
        // Vérifier si agent en télétravail ce jour
        $remoteWorkInfo = $this->Grids->isUserInRemoteWork($user, $currentDay);
        $bg = '';
        $icon = '';
        $homework = '';
        $remoteStyle = '';
        
        if ($remoteWorkInfo['is_remote']) {
            $bg = 'is-remote';
            $icon = '<i class="bi-house-door-fill pe-1"></i>';
            $homework = $remoteWorkInfo['info'];
            $remoteColor = (string)($remoteWorkInfo['color'] ?? '');
            if ($remoteColor !== '' && preg_match('/^#[0-9A-Fa-f]{3,8}$/', $remoteColor)) {
                $remoteStyle = ' style="background:' . $remoteColor . '"';
            }
        }
    ?>
        <?php $rowYmd = $rowYmdEarly; ?>
        <tr id="grid-day-<?= h($rowYmd) ?>" class="tr_quarter<?= $remoteWorkInfo['is_remote'] ? ' is-remote-row' : '' ?><?= $closedLabel !== null ? ' grid-day-closed' : '' ?>" data_user="<?= $user->id ?>"<?php if ($closedLabel !== null): ?> data-closed-label="<?= h($closedLabel) ?>"<?php endif; ?>>
            <th scope="row" class="th_row <?= $bg ?>"<?= $remoteStyle ?>
                data-bs-toggle="tooltip"
                data-placement="right"
                title="<?= h($user['site']['number']) ?> - <?= h($user->user_code) ?><?= $homework ?><?= $closedLabel !== null ? ' — ' . h($closedLabel) : '' ?>">
                <?= $icon ?><?= $currentDay->i18nFormat('EEEE dd/MM') ?><?php if ($closedLabel !== null): ?> <span class="grid-day-closed-label"><?= h($closedLabel) ?></span><?php endif; ?>
            </th>
            <?php echo $this->Grids->writeTimeSlots($user, $currentDay, (string)$rangesProperty); ?>
        </tr>
    <?php 
        $currentDay = $currentDay->addDays(1);
    endfor; 
    ?>
    </tbody>
</table>

