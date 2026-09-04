<?php
declare(strict_types=1);

namespace App\Service\Planning;

use Cake\Core\Configure;
use Cake\I18n\FrozenTime;
use DateTimeInterface;

/**
 * Décide si une requête de grille est chargeable, et quelle vue utiliser.
 * Les seuils viennent de Configure Grids.budget (pas de magie dans le service).
 */
class GridQueryBudget
{
    public const VIEW_GANTT = 'gantt';
    public const VIEW_MONTH = 'month';

    private int $freeDays;
    private int $needSiteOrUserAfter;
    private int $monthViewAfter;
    private int $maxWorkingDays;
    private int $maxCalendarMonths;

    /**
     * @param array<string,mixed>|null $config
     */
    public function __construct(?array $config = null)
    {
        $cfg = $config ?? (array)Configure::read('Grids.budget', []);
        $this->freeDays = (int)($cfg['free_days'] ?? 5);
        $this->needSiteOrUserAfter = (int)($cfg['need_site_or_user_after'] ?? 5);
        $this->monthViewAfter = (int)($cfg['month_view_after'] ?? 10);
        $this->maxWorkingDays = (int)($cfg['max_working_days'] ?? 23);
        $this->maxCalendarMonths = (int)($cfg['max_calendar_months'] ?? 1);
    }

    /**
     * @return array<string,int>
     */
    public function thresholds(): array
    {
        return [
            'free_days' => $this->freeDays,
            'need_site_or_user_after' => $this->needSiteOrUserAfter,
            'month_view_after' => $this->monthViewAfter,
            'max_working_days' => $this->maxWorkingDays,
            'max_calendar_months' => $this->maxCalendarMonths,
        ];
    }

    public function countWorkingDays(DateTimeInterface $begin, DateTimeInterface $end): int
    {
        $cursor = FrozenTime::parse($begin->format('Y-m-d'))->startOfDay();
        $last = FrozenTime::parse($end->format('Y-m-d'))->startOfDay();
        if ($last < $cursor) {
            return 0;
        }

        $count = 0;
        while ($cursor <= $last) {
            if (!$cursor->isWeekend()) {
                $count++;
            }
            $cursor = $cursor->addDays(1);
        }

        return $count;
    }

    /**
     * @return list<\Cake\I18n\FrozenTime>
     */
    public function workingDays(DateTimeInterface $begin, DateTimeInterface $end): array
    {
        $cursor = FrozenTime::parse($begin->format('Y-m-d'))->startOfDay();
        $last = FrozenTime::parse($end->format('Y-m-d'))->startOfDay();
        $days = [];
        if ($last < $cursor) {
            return $days;
        }
        while ($cursor <= $last) {
            if (!$cursor->isWeekend()) {
                $days[] = $cursor;
            }
            $cursor = $cursor->addDays(1);
        }

        return $days;
    }

    /**
     * @return array{
     *   allowed: bool,
     *   view: string,
     *   working_days: int,
     *   code: string,
     *   message: string
     * }
     */
    public function evaluate(
        DateTimeInterface $begin,
        DateTimeInterface $end,
        int $siteId,
        int $userId
    ): array {
        $workingDays = $this->countWorkingDays($begin, $end);
        $hasDimension = $siteId > 0 || $userId > 0;

        $beginDay = FrozenTime::parse($begin->format('Y-m-d'))->startOfDay();
        $endDay = FrozenTime::parse($end->format('Y-m-d'))->startOfDay();
        $maxEnd = $beginDay->addMonths($this->maxCalendarMonths);

        if ($endDay > $maxEnd) {
            return $this->deny($workingDays, 'span', 'La période ne peut pas dépasser '
                . $this->maxCalendarMonths . ' mois. Choisis une plage plus courte.');
        }

        if ($workingDays > $this->maxWorkingDays) {
            return $this->deny($workingDays, 'working_days', 'Trop de jours ouvrés ('
                . $workingDays . ', max ' . $this->maxWorkingDays . '). Restreins les dates.');
        }

        if ($workingDays > $this->needSiteOrUserAfter && !$hasDimension) {
            return $this->deny(
                $workingDays,
                'need_dimension',
                'Plus de ' . $this->needSiteOrUserAfter
                . ' jours ouvrés : choisis un site ou un agent.'
            );
        }

        $view = $workingDays > $this->monthViewAfter ? self::VIEW_MONTH : self::VIEW_GANTT;

        return [
            'allowed' => true,
            'view' => $view,
            'working_days' => $workingDays,
            'code' => 'ok',
            'message' => '',
        ];
    }

    /**
     * @return array{allowed: bool, view: string, working_days: int, code: string, message: string}
     */
    private function deny(int $workingDays, string $code, string $message): array
    {
        return [
            'allowed' => false,
            'view' => self::VIEW_GANTT,
            'working_days' => $workingDays,
            'code' => $code,
            'message' => $message,
        ];
    }
}
