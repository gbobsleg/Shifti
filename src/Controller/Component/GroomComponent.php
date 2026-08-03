<?php
declare(strict_types=1);

namespace App\Controller\Component;

use Cake\Controller\Component;
use Cake\I18n\FrozenTime;

class GroomComponent extends Component
{
    /**
     * @return array{begin:\Cake\I18n\FrozenTime,end:\Cake\I18n\FrozenTime}
     */
    public function findBeginEndDay(object $date_start, ?object $date_end = null, bool $exceptWeekEnd = false): array
    {
        if (empty($date_start)) {
            return [];
        }

        if ($date_start->isWeekend()) {
            $date_start = $date_start->next(FrozenTime::MONDAY);
        }

        $beginOfDayStart = $date_start->startOfDay();
        $endOfDay = $beginOfDayStart->endOfDay();

        if (!empty($date_end)) {
            $beginOfDayEnd = $date_end->startOfDay();
            $endOfDay = $beginOfDayEnd->endOfDay();
        }

        return ['begin' => $beginOfDayStart, 'end' => $endOfDay];
    }

    /**
     * @return array<int>
     */
    public function findRangesToDelete(array $ranges): array
    {
        $rangesToDelete = [];
        foreach ($ranges as $range) {
            if (empty($range['offer_id']) && !empty($range['id'])) {
                $rangesToDelete[] = $range['id'];
            }
        }

        return array_unique($rangesToDelete);
    }

    /**
     * @return array<int,array{date_start:string,date_end:string}>
     */
    public function findDayDates(array $days, array $ranges): array
    {
        $start = new FrozenTime($ranges['date_start']);
        $end = new FrozenTime($ranges['date_end']);

        $startTimestamp = $start->getTimestamp();
        $endTimestamp = $end->getTimestamp();

        foreach ($days as $day) {
            if ($day != '0') {
                for ($i = $startTimestamp; $i <= $endTimestamp; $i = strtotime('+1 day', $i)) {
                    if (date('N', $i) == $day) {
                        $dates[] = date('Y-m-d', $i);
                    }
                }
            }
        }

        if (empty($dates)) {
            return [];
        }

        $datesCount = count($dates);
        for ($i = 0; $i < $datesCount; $i++) {
            $datesRanges[$i]['date_start'] = (new FrozenTime($dates[$i] . ' ' . $start->i18nFormat('HH:mm')))
                ->i18nFormat('yyyy-MM-dd HH:mm:ss');
            $datesRanges[$i]['date_end'] = (new FrozenTime($dates[$i] . ' ' . $end->i18nFormat('HH:mm')))
                ->i18nFormat('yyyy-MM-dd HH:mm:ss');
        }

        return $datesRanges;
    }
}
