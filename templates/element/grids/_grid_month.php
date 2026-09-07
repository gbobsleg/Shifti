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
$showSiteColumn = (int)$this->request->getQuery('site_id') <= 0;
$sitePalette = [
    '#1565c0', '#7b1fa2', '#2e7d32', '#e65100', '#c2185b',
    '#00695c', '#f57f17', '#558b2f', '#6a1b9a', '#d84315',
];

$weekGroups = [];
foreach ($days as $day) {
    $weekGroups[$day->format('o-W')][] = $day;
}

$weekLabel = function (array $group): string {
    $first = $group[0];
    $last = $group[array_key_last($group)];
    $weekNum = (int)$first->format('W');
    if ($first->format('Y-m-d') === $last->format('Y-m-d')) {
        $span = $first->i18nFormat('d MMM');
    } elseif ($first->format('Y-m') === $last->format('Y-m')) {
        $span = $first->i18nFormat('d') . '–' . $last->i18nFormat('d MMMM');
    } else {
        $span = $first->i18nFormat('d MMM') . ' – ' . $last->i18nFormat('d MMM');
    }

    return 'S' . $weekNum . ' · ' . $span;
};

$isWeekStart = function (int $index) use ($days): bool {
    if ($index <= 0) {
        return false;
    }

    return $days[$index]->format('o-W') !== $days[$index - 1]->format('o-W');
};
?>
<table class="grids-month">
    <thead>
        <tr class="grids-month-weeks">
            <?php if ($showSiteColumn): ?>
                <th rowspan="2" class="grids-month-site site-column">Site</th>
            <?php endif; ?>
            <th rowspan="2" class="grids-month-agent">Agent</th>
            <?php foreach (array_values($weekGroups) as $weekIndex => $group): ?>
                <?php $label = $weekLabel($group); ?>
                <th colspan="<?= count($group) ?>"
                    class="grids-month-week<?= $weekIndex > 0 ? ' is-week-start' : '' ?>"
                    title="<?= h($label) ?>">
                    <?= h($label) ?>
                </th>
            <?php endforeach; ?>
        </tr>
        <tr class="grids-month-days">
            <?php foreach ($days as $index => $day): ?>
                <th class="<?= $isWeekStart($index) ? 'is-week-start' : '' ?>"><?= h($day->i18nFormat('dd/MM')) ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($users_ranges as $user): ?>
        <tr>
            <?php if ($showSiteColumn):
                $siteId = (int)($user->site->id ?? 0);
                $siteColor = $siteId > 0
                    ? $sitePalette[($siteId - 1) % count($sitePalette)]
                    : '#64748b';
                ?>
                <th class="grids-month-site site-column" style="border-left: 2px solid <?= h($siteColor) ?>; color: <?= h($siteColor) ?>;"><?= h($user->site->name ?? '') ?></th>
            <?php endif; ?>
            <th class="grids-month-agent"><?= h($user->full_name) ?></th>
            <?php foreach ($days as $index => $day):
                $dayStart = $day->startOfDay()->getTimestamp();
                $dayEnd = $day->endOfDay()->getTimestamp();
                $offersById = [];
                $userRanges = $user->{$rangesProperty} ?? [];
                foreach ($userRanges as $range) {
                    $offerId = (int)$range->offer_id;
                    if (!empty($filterOfferIds) && !in_array($offerId, $filterOfferIds, true)) {
                        continue;
                    }
                    $rStart = $range->date_start instanceof \DateTimeInterface ? $range->date_start : new FrozenTime($range->date_start);
                    $rEnd = $range->date_end instanceof \DateTimeInterface ? $range->date_end : new FrozenTime($range->date_end);
                    if ($rStart->getTimestamp() >= $dayEnd || $rEnd->getTimestamp() <= $dayStart) {
                        continue;
                    }
                    $offerType = (string)($range->offer->offer_type ?? '');
                    if ($offerType === 'pause' || $offerType === 'lunch') {
                        continue;
                    }
                    if (isset($offersById[$offerId])) {
                        continue;
                    }
                    $name = trim((string)($range->offer->name ?? ''));
                    $offersById[$offerId] = [
                        'name' => $name !== '' ? $name : 'Offre',
                        'color' => (string)($range->offer->color ?? '#94a3b8'),
                    ];
                }
                $dayOffers = array_values($offersById);
                $offerCount = count($dayOffers);
                $tooltip = implode("\n", array_column($dayOffers, 'name'));
                $next = $q;
                $next['date_start'] = $day->format('d/m/Y');
                $next['date_end'] = $day->format('d/m/Y');
                $dayLink = ['action' => $action, '?' => $next];
                if ($action === 'draft' && !empty($pass)) {
                    $dayLink = ['action' => 'draft', $pass[0], '?' => $next + ['embed' => '1']];
                }
                $linkOptions = ['escape' => false];
                if ($tooltip !== '') {
                    $linkOptions['title'] = $tooltip;
                }
                $label = '';
                if ($offerCount === 1) {
                    $label = '<span class="grids-month-dot" style="background:' . h($dayOffers[0]['color']) . '"></span>';
                } elseif ($offerCount > 1) {
                    $label = '<span class="grids-month-mix">' . $offerCount . '</span>';
                }
                ?>
                <td class="grids-month-cell<?= $isWeekStart($index) ? ' is-week-start' : '' ?>">
                    <?= $this->Html->link($label, $dayLink, $linkOptions) ?>
                </td>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
