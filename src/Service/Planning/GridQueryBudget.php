<?php
declare(strict_types=1);

namespace App\Service\Planning;

use App\Service\OperatingCalendarService;
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
    private int $maxWeeks;

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
        $this->maxWeeks = max(1, (int)($cfg['max_weeks'] ?? 5));
    }

    /**
     * Du lundi au dimanche de la 5e semaine : 34 jours d'écart.
     */
    public function maxSpanDays(): int
    {
        return $this->maxWeeks * 7 - 1;
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
            'max_weeks' => $this->maxWeeks,
            'max_span_days' => $this->maxSpanDays(),
        ];
    }

    /**
     * @param list<int>|null $frameDays 1=lundi … 7=dimanche. Null = lundi–vendredi.
     */
    public function countWorkingDays(DateTimeInterface $begin, DateTimeInterface $end, ?array $frameDays = null): int
    {
        $cursor = FrozenTime::parse($begin->format('Y-m-d'))->startOfDay();
        $last = FrozenTime::parse($end->format('Y-m-d'))->startOfDay();
        if ($last < $cursor) {
            return 0;
        }

        $count = 0;
        while ($cursor <= $last) {
            if ($this->isFrameDay($cursor, $frameDays)) {
                $count++;
            }
            $cursor = $cursor->addDays(1);
        }

        return $count;
    }

    /**
     * @param list<int>|null $frameDays
     * @return list<\Cake\I18n\FrozenTime>
     */
    public function workingDays(DateTimeInterface $begin, DateTimeInterface $end, ?array $frameDays = null): array
    {
        $cursor = FrozenTime::parse($begin->format('Y-m-d'))->startOfDay();
        $last = FrozenTime::parse($end->format('Y-m-d'))->startOfDay();
        $days = [];
        if ($last < $cursor) {
            return $days;
        }
        while ($cursor <= $last) {
            if ($this->isFrameDay($cursor, $frameDays)) {
                $days[] = $cursor;
            }
            $cursor = $cursor->addDays(1);
        }

        return $days;
    }

    /**
     * @param list<int>|null $frameDays
     */
    private function isFrameDay(DateTimeInterface $day, ?array $frameDays): bool
    {
        $frame = $frameDays ?? OperatingCalendarService::DEFAULT_FRAME;

        return in_array((int)$day->format('N'), array_map('intval', $frame), true);
    }

    /**
     * @param mixed $raw
     * @return list<int>
     */
    public static function normalizeOfferIds(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('intval', $raw)));
        }

        $id = (int)$raw;

        return $id > 0 ? [$id] : [];
    }

    /**
     * @param list<int>|array<mixed> $offerIds
     * @return array{
     *   allowed: bool,
     *   view: string,
     *   auto_view: string,
     *   gantt_allowed: bool,
     *   working_days: int,
     *   code: string,
     *   message: string
     * }
     */
    public function evaluate(
        DateTimeInterface $begin,
        DateTimeInterface $end,
        int $siteId,
        int $userId,
        array $offerIds = [],
        ?array $frameDays = null,
        ?string $requestedView = null
    ): array {
        $workingDays = $this->countWorkingDays($begin, $end, $frameDays);
        $offerIds = array_values(array_filter(array_map('intval', $offerIds)));
        $hasOffers = $offerIds !== [];
        $hasDimension = $siteId > 0 || $userId > 0 || $hasOffers;

        $beginDay = FrozenTime::parse($begin->format('Y-m-d'))->startOfDay();
        $endDay = FrozenTime::parse($end->format('Y-m-d'))->startOfDay();
        $spanDays = $endDay < $beginDay
            ? 0
            : (int)round(($endDay->getTimestamp() - $beginDay->getTimestamp()) / 86400);

        if ($spanDays > $this->maxSpanDays()) {
            return $this->deny($workingDays, 'span', 'La période ne peut pas dépasser '
                . $this->maxWeeks . ' semaines. Choisis une plage plus courte.');
        }

        if ($workingDays > $this->needSiteOrUserAfter && !$hasDimension) {
            return $this->deny(
                $workingDays,
                'need_dimension',
                'Plus de ' . $this->needSiteOrUserAfter
                . ' jours ouvrés : choisis un site, un agent ou au moins une offre.'
            );
        }

        $ganttAllowed = $endDay <= $beginDay->addMonths($this->maxCalendarMonths)
            && $workingDays <= $this->maxWorkingDays;
        $offersOnly = $hasOffers && $siteId <= 0 && $userId <= 0;
        $autoMonth = ($userId <= 0 && $workingDays > $this->monthViewAfter)
            || ($offersOnly && $workingDays > $this->needSiteOrUserAfter);
        $autoView = $autoMonth ? self::VIEW_MONTH : self::VIEW_GANTT;
        $forceMonth = $requestedView === self::VIEW_MONTH;
        $view = (!$ganttAllowed || $autoMonth || $forceMonth) ? self::VIEW_MONTH : self::VIEW_GANTT;

        return [
            'allowed' => true,
            'view' => $view,
            'auto_view' => $autoView,
            'gantt_allowed' => $ganttAllowed,
            'working_days' => $workingDays,
            'code' => 'ok',
            'message' => '',
        ];
    }

    /**
     * @return array{
     *   allowed: bool,
     *   view: string,
     *   auto_view: string,
     *   gantt_allowed: bool,
     *   working_days: int,
     *   code: string,
     *   message: string
     * }
     */
    private function deny(int $workingDays, string $code, string $message): array
    {
        return [
            'allowed' => false,
            'view' => self::VIEW_GANTT,
            'auto_view' => self::VIEW_GANTT,
            'gantt_allowed' => false,
            'working_days' => $workingDays,
            'code' => $code,
            'message' => $message,
        ];
    }
}
