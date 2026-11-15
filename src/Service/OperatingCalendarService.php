<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\WfmSetting;
use Cake\Chronos\ChronosDate;
use Cake\ORM\Locator\LocatorAwareTrait;
use DateTimeInterface;

/**
 * Seul maître « jour ouvert / fermé ».
 * Trame hebdo = worked_days_json. Exceptions = calendar_exceptions.
 *
 * @phpstan-type MemoryRow array{is_open: bool, label: string}
 */
class OperatingCalendarService
{
    use LocatorAwareTrait;

    /** @var array<int, int> */
    public const DEFAULT_FRAME = [1, 2, 3, 4, 5];

    /**
     * Carte mémoire Y-m-d => is_open. null = lire la base.
     *
     * @var array<string, bool>|null
     */
    private ?array $memoryOpen = null;

    /**
     * @var array<string, string>
     */
    private array $memoryLabels = [];

    /**
     * @var array<string, bool|null>
     */
    private array $cache = [];

    /**
     * @param array<string, bool>|null $memoryOpen
     * @param array<string, string> $memoryLabels
     */
    public function __construct(?array $memoryOpen = null, array $memoryLabels = [])
    {
        $this->memoryOpen = $memoryOpen;
        $this->memoryLabels = $memoryLabels;
    }

    /**
     * @return list<int>
     */
    public function frameDays(?WfmSetting $settings): array
    {
        $raw = $settings?->worked_days_json ?? null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($raw) || $raw === []) {
            return self::DEFAULT_FRAME;
        }
        $days = [];
        foreach ($raw as $day) {
            $n = (int)$day;
            if ($n >= 1 && $n <= 7) {
                $days[$n] = $n;
            }
        }
        if ($days === []) {
            return self::DEFAULT_FRAME;
        }
        $list = array_values($days);
        sort($list);

        return $list;
    }

    public function isFrameDay(ChronosDate|DateTimeInterface $date, ?WfmSetting $settings): bool
    {
        return in_array((int)$date->format('N'), $this->frameDays($settings), true);
    }

    /**
     * Jour de trame sans exception fermante. Une exception is_open=true rouvre le jour.
     */
    public function isOpen(ChronosDate|DateTimeInterface $date, ?WfmSetting $settings): bool
    {
        if (!$this->isFrameDay($date, $settings)) {
            return false;
        }
        $flag = $this->exceptionIsOpen($this->asDate($date));
        if ($flag === null) {
            return true;
        }

        return $flag;
    }

    public function label(ChronosDate|DateTimeInterface $date): ?string
    {
        $key = $this->asDate($date)->format('Y-m-d');
        if ($this->memoryOpen !== null) {
            return $this->memoryLabels[$key] ?? null;
        }
        $row = $this->fetchTable('CalendarExceptions')->find()
            ->where(['date' => $key])
            ->first();
        if ($row === null) {
            return null;
        }

        return (string)$row->label;
    }

    /**
     * @return list<\Cake\Chronos\ChronosDate>
     */
    public function openDates(ChronosDate|DateTimeInterface $start, ChronosDate|DateTimeInterface $end, ?WfmSetting $settings): array
    {
        $cursor = $this->asDate($start);
        $last = $this->asDate($end);
        $out = [];
        if ($last < $cursor) {
            return $out;
        }
        while ($cursor <= $last) {
            if ($this->isOpen($cursor, $settings)) {
                $out[] = $cursor;
            }
            $cursor = $cursor->addDays(1);
        }

        return $out;
    }

    /**
     * Jours de trame fermés, pour la grille (affichés mais non générés).
     *
     * @return array<string, string> Y-m-d => libellé
     */
    public function closedFrameLabels(ChronosDate|DateTimeInterface $start, ChronosDate|DateTimeInterface $end, ?WfmSetting $settings): array
    {
        $cursor = $this->asDate($start);
        $last = $this->asDate($end);
        $out = [];
        if ($last < $cursor) {
            return $out;
        }
        while ($cursor <= $last) {
            if ($this->isFrameDay($cursor, $settings) && !$this->isOpen($cursor, $settings)) {
                $out[$cursor->format('Y-m-d')] = $this->label($cursor) ?? 'Fermé';
            }
            $cursor = $cursor->addDays(1);
        }

        return $out;
    }

    /**
     * Toutes les dates fermées (is_open = false), pour Prophet et l'historique.
     *
     * @return list<string>
     */
    public function closedDateStrings(?ChronosDate $start = null, ?ChronosDate $end = null): array
    {
        if ($this->memoryOpen !== null) {
            $out = [];
            foreach ($this->memoryOpen as $ymd => $open) {
                if ($open) {
                    continue;
                }
                if ($start !== null && $ymd < $start->format('Y-m-d')) {
                    continue;
                }
                if ($end !== null && $ymd > $end->format('Y-m-d')) {
                    continue;
                }
                $out[] = $ymd;
            }
            sort($out);

            return $out;
        }

        $query = $this->fetchTable('CalendarExceptions')->find()
            ->select(['date'])
            ->where(['is_open' => false])
            ->orderAsc('date');
        if ($start !== null) {
            $query->where(['date >=' => $start->format('Y-m-d')]);
        }
        if ($end !== null) {
            $query->where(['date <=' => $end->format('Y-m-d')]);
        }
        $out = [];
        foreach ($query->all() as $row) {
            $out[] = $this->ymd($row->date);
        }

        return $out;
    }

    /**
     * Indices ISO 0=lundi … 6=dimanche, dans la semaine, le job, et ouverts.
     *
     * @return list<int>
     */
    public function openDayIndices(
        ChronosDate|DateTimeInterface $weekMonday,
        ?WfmSetting $settings,
        ChronosDate|DateTimeInterface|null $jobStart = null,
        ChronosDate|DateTimeInterface|null $jobEnd = null
    ): array {
        $monday = $this->asDate($weekMonday);
        $indices = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $monday->addDays($i);
            if ($jobStart !== null && $day < $this->asDate($jobStart)) {
                continue;
            }
            if ($jobEnd !== null && $day > $this->asDate($jobEnd)) {
                continue;
            }
            if ($this->isOpen($day, $settings)) {
                $indices[] = $i;
            }
        }

        return $indices;
    }

    /**
     * Insère les fériés légaux absents. Ne touche pas une ligne déjà présente.
     *
     * @return array{inserted: int, skipped: int}
     */
    public function seedLegalRange(int $fromYear, int $toYear): array
    {
        if ($toYear < $fromYear) {
            return ['inserted' => 0, 'skipped' => 0];
        }
        $table = $this->fetchTable('CalendarExceptions');
        $inserted = 0;
        $skipped = 0;
        for ($year = $fromYear; $year <= $toYear; $year++) {
            foreach (FrenchPublicHolidays::forYear($year) as $ymd => $label) {
                $existing = $table->find()->where(['date' => $ymd])->first();
                if ($existing !== null) {
                    $skipped++;
                    continue;
                }
                $entity = $table->newEntity([
                    'date' => $ymd,
                    'label' => $label,
                    'is_open' => false,
                    'source' => 'legal_fr',
                ]);
                $table->saveOrFail($entity);
                $inserted++;
            }
        }

        return ['inserted' => $inserted, 'skipped' => $skipped];
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    public function attachToOptunaSnapshot(array $snapshot, ?WfmSetting $settings): array
    {
        $snapshot['worked_days'] = $this->frameDays($settings);
        $snapshot['closed_dates'] = $this->closedDateStrings();

        return $snapshot;
    }

    /**
     * Cake\I18n\Date n'est pas un DateTimeInterface : son cast donne 25/12/2026.
     * Prophet compare des chaînes ISO.
     */
    private function ymd(mixed $value): string
    {
        if ($value instanceof ChronosDate || $value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string)$value;
    }

    private function exceptionIsOpen(ChronosDate $date): ?bool
    {
        $key = $date->format('Y-m-d');
        if ($this->memoryOpen !== null) {
            if (!array_key_exists($key, $this->memoryOpen)) {
                return null;
            }

            return (bool)$this->memoryOpen[$key];
        }
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        $row = $this->fetchTable('CalendarExceptions')->find()
            ->where(['date' => $key])
            ->first();
        $this->cache[$key] = $row === null ? null : (bool)$row->is_open;

        return $this->cache[$key];
    }

    private function asDate(ChronosDate|DateTimeInterface $date): ChronosDate
    {
        if ($date instanceof ChronosDate) {
            return $date;
        }

        return new ChronosDate($date->format('Y-m-d'));
    }
}
