<?php
/**
 * @var \App\View\AppView $this
 * @var iterable $users_ranges
 * @var array $day_ranges
 * @var array $offers_name
 */
use App\Service\Planning\GridQueryBudget;
use Cake\I18n\FrozenTime;

$budget = new GridQueryBudget();
$days = $budget->workingDays($day_ranges['begin'], $day_ranges['end']);
$offerIdParam = $this->request->getQuery('offer_id');
$filterOfferIds = is_array($offerIdParam)
    ? array_values(array_filter(array_map('intval', (array)$offerIdParam)))
    : ((int)$offerIdParam > 0 ? [(int)$offerIdParam] : []);
$rangesProperty = $rangesProperty ?? 'ranges';
$q = $this->request->getQueryParams();
$action = $this->request->getParam('action');
$pass = $this->request->getParam('pass');
?>
<table class="grids-month">
    <thead>
        <tr>
            <th class="grids-month-agent">Agent</th>
            <?php foreach ($days as $day): ?>
                <th><?= h($day->i18nFormat('dd/MM')) ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($users_ranges as $user): ?>
        <tr>
            <th class="grids-month-agent"><?= h($user->full_name) ?></th>
            <?php foreach ($days as $day):
                $dayStart = $day->startOfDay()->getTimestamp();
                $dayEnd = $day->endOfDay()->getTimestamp();
                $colors = [];
                $userRanges = $user->{$rangesProperty} ?? [];
                foreach ($userRanges as $range) {
                    if (!empty($filterOfferIds) && !in_array((int)$range->offer_id, $filterOfferIds, true)) {
                        continue;
                    }
                    $rStart = $range->date_start instanceof \DateTimeInterface ? $range->date_start : new FrozenTime($range->date_start);
                    $rEnd = $range->date_end instanceof \DateTimeInterface ? $range->date_end : new FrozenTime($range->date_end);
                    if ($rStart->getTimestamp() < $dayEnd && $rEnd->getTimestamp() > $dayStart) {
                        $color = (string)($range->offer->color ?? '#94a3b8');
                        $colors[$color] = true;
                    }
                }
                $colorList = array_keys($colors);
                ?>
                <?php
                $next = $q;
                $next['date_start'] = $day->format('d/m/Y');
                $next['date_end'] = $day->format('d/m/Y');
                $dayLink = ['action' => $action, '?' => $next];
                if ($action === 'draft' && !empty($pass)) {
                    $dayLink = ['action' => 'draft', $pass[0], '?' => $next + ['embed' => '1']];
                }
                ?>
                <td class="grids-month-cell">
                    <?= $this->Html->link(
                        count($colorList) === 0
                            ? ''
                            : (count($colorList) === 1
                                ? '<span class="grids-month-dot" style="background:' . h($colorList[0]) . '"></span>'
                                : '<span class="grids-month-mix">' . count($colorList) . '</span>'),
                        $dayLink,
                        ['escape' => false, 'title' => $day->i18nFormat('EEEE dd MMMM')]
                    ) ?>
                </td>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
