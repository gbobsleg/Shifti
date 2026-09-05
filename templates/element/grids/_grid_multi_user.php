<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User[]|\Cake\Collection\CollectionInterface $users_ranges
 * @var array $day_ranges
 * @var array $offers_name
 * @var int $gridStartHour
 * @var int $gridEndHour
 * @var array $offers_list
 * @var array $publishedByDate
 * @var bool $canLoadSeries
 * @var bool $showCharts
 */

// Configurer les heures de la grille dans le helper
if (isset($gridStartHour, $gridEndHour)) {
    $this->Grids->setGridHours($gridStartHour, $gridEndHour);
}
$publishedByDate = $publishedByDate ?? [];
$offers_list = $offers_list ?? [];

$days = $day_ranges['begin']->diffInDays($day_ranges['end']);
$dayRangesBegin = $day_ranges['begin'];
for ($i = 0; $i <= $days; $i++):
    // exclusion des weekends
    if ($dayRangesBegin->isWeekend()) {
        $dayRangesBegin = $dayRangesBegin->addDays(1);
        continue;
    }
?>
    <?php
    $dayYmd = $dayRangesBegin->format('Y-m-d');
    $showLoadRows = !empty($showCharts) && !empty($canLoadSeries) && !empty($offers_list);
    ?>
    <table class="quarter" id="grid-day-<?= h($dayYmd) ?>"<?php if ($showLoadRows): ?> data-scenario-id="<?= isset($publishedByDate[$dayYmd]) ? (int)$publishedByDate[$dayYmd] : '' ?>"<?php endif; ?>>
        <thead>
            <tr>
                <th scope="col" colspan="50" class="th_title">
                    <span class="grids-day-nav">
                        <button type="button" class="grids-day-nav-btn" data-grid-day="<?= h($dayYmd) ?>" data-grid-day-step="-1" title="Jour précédent" aria-label="Jour précédent">
                            <i class="bi bi-chevron-left" aria-hidden="true"></i>
                        </button>
                        <span class="grids-day-nav-label">Planning du <?= $dayRangesBegin->i18nFormat('EEEE dd MMMM yyyy'); ?></span>
                        <button type="button" class="grids-day-nav-btn" data-grid-day="<?= h($dayYmd) ?>" data-grid-day-step="1" title="Jour suivant" aria-label="Jour suivant">
                            <i class="bi bi-chevron-right" aria-hidden="true"></i>
                        </button>
                    </span>
                </th>
            </tr>
            <tr class="grids-th-hours">
                <th scope="col" class="th_col2 site-column">Site</th>
                <th scope="col" class="th_col2">Agent</th>
                <?php echo $this->Grids->writeThTime('hours', $dayRangesBegin); ?>
            </tr>
            <tr class="grids-th-minutes">
                <th scope="col" class="th_col2 site-column">&nbsp;</th>
                <th scope="col" class="th_col2">&nbsp;</th>
                <?php echo $this->Grids->writeThTime('minutes', $dayRangesBegin); ?>
            </tr>
            <?php if ($showLoadRows): ?>
                <?= $this->Grids->writeLoadRow('need', $dayRangesBegin) ?>
                <?= $this->Grids->writeLoadRow('planned', $dayRangesBegin) ?>
            <?php endif; ?>
        </thead>
        <tbody>
        <?php
        $rangesProperty = $rangesProperty ?? 'ranges';
        
        // Normaliser la date du jour en string Y-m-d pour comparaison fiable
        $currentDayStr = $dayRangesBegin->format('Y-m-d');
        
        foreach ($users_ranges as $user): 
            // Logique de filtrage robuste pour l'affichage conditionnel selon le contrat
            $shouldDisplay = true; // Par défaut, on affiche (rétro-compatibilité pour users sans contrat)
            
            if (!empty($user->user_contracts)) {
                $shouldDisplay = false; // Si des contrats existent, on devient restrictif
                
                foreach ($user->user_contracts as $contract) {
                    // Extraire les dates du contrat et les convertir en string Y-m-d
                    // Les objets Cake\I18n\Date ont une méthode format() qui fonctionne
                    $contractStartStr = null;
                    $contractEndStr = null;
                    
                    if ($contract->start_date && is_object($contract->start_date) && method_exists($contract->start_date, 'format')) {
                        $contractStartStr = $contract->start_date->format('Y-m-d');
                    }
                    
                    if ($contract->end_date && is_object($contract->end_date) && method_exists($contract->end_date, 'format')) {
                        $contractEndStr = $contract->end_date->format('Y-m-d');
                    }
                    
                    // Vérification du chevauchement avec la journée en cours
                    // Un contrat est actif si :
                    // - Date début <= Jour affiché
                    // - Date fin est NULL (CDI) OU Date fin >= Jour affiché
                    
                    // Comparaison string Y-m-d (infaillible)
                    $startMatch = $contractStartStr && $contractStartStr <= $currentDayStr;
                    $endMatch = $contractEndStr === null || $contractEndStr >= $currentDayStr;
                    
                    if ($startMatch && $endMatch) {
                        $shouldDisplay = true;
                        break; // Un contrat valide trouvé, on affiche
                    }
                }
            }
            
            if (!$shouldDisplay) {
                continue;
            }

            // Filtre dynamique jour par jour si une ou plusieurs offres sont sélectionnées
            // On masque l'agent s'il n'a aucune des offres CE JOUR-LÀ (même s'il les a un autre jour de la période)
            $offerIdParam = $this->request->getQuery('offer_id');
            $filterOfferIds = is_array($offerIdParam)
                ? array_values(array_filter(array_map('intval', (array)$offerIdParam)))
                : ((int)$offerIdParam > 0 ? [(int)$offerIdParam] : []);
            if (!empty($filterOfferIds)) {
                $hasOfferToday = false;
                $userRanges = $user->{$rangesProperty} ?? [];
                
                // Bornes de la journée affichée (timestamps pour comparaison rapide)
                $dayStartTs = $dayRangesBegin->startOfDay()->getTimestamp();
                $dayEndTs = $dayRangesBegin->endOfDay()->getTimestamp();

                foreach ($userRanges as $range) {
                    if (!in_array((int)$range->offer_id, $filterOfferIds, true)) {
                        continue;
                    }

                    // Vérification chevauchement temporel
                    $rStart = $range->date_start instanceof \DateTimeInterface ? $range->date_start : new \Cake\I18n\FrozenTime($range->date_start);
                    $rEnd = $range->date_end instanceof \DateTimeInterface ? $range->date_end : new \Cake\I18n\FrozenTime($range->date_end);
                    
                    // Chevauchement : start < dayEnd && end > dayStart
                    if ($rStart->getTimestamp() < $dayEndTs && $rEnd->getTimestamp() > $dayStartTs) {
                        $hasOfferToday = true;
                        break;
                    }
                }

                if (!$hasOfferToday) {
                    continue;
                }
            }
        ?>
            <tr class="tr_quarter<?= !empty($this->Grids->isUserInRemoteWork($user, $dayRangesBegin)['is_remote']) ? ' is-remote-row' : '' ?>" data_user="<?= $user->id ?>">
                <?php echo $this->Grids->writeTrUser($user, $offers_name, $dayRangesBegin, (string)$rangesProperty);?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

<?php
$dayRangesBegin = $dayRangesBegin->addDays(1);
endfor;
?>
