<?php
declare(strict_types=1);

namespace App\View\Grid;

/**
 * Décore une ligne de créneaux 15 min (runs visuels) et émet les <td>.
 * La source de vérité reste data-offer-id / data-start / data-end.
 */
final class BarRenderer
{
    public const KIND_EMPTY = 'empty';
    public const KIND_UNAVAILABLE = 'unavailable';
    public const KIND_PAUSE = 'pause';
    public const KIND_LUNCH = 'lunch';
    public const KIND_REMOTE = 'remote';
    public const KIND_PROD = 'prod';

    /**
     * @param list<array<string,mixed>> $slots
     * @return list<array<string,mixed>>
     */
    public function decorate(array $slots): array
    {
        $n = count($slots);
        for ($i = 0; $i < $n; $i++) {
            $slots[$i]['kind'] = $this->kindOf($slots[$i]);
            $slots[$i]['hour_end'] = (($i + 1) % 4) === 0;
            $slots[$i]['bar_pos'] = '';
            $slots[$i]['show_label'] = false;
        }

        $i = 0;
        while ($i < $n) {
            $kind = (string)$slots[$i]['kind'];
            if ($kind === self::KIND_EMPTY || $kind === self::KIND_UNAVAILABLE || $kind === self::KIND_REMOTE) {
                $i++;
                continue;
            }
            $offerId = (string)($slots[$i]['offer_id'] ?? '0');
            $j = $i + 1;
            while ($j < $n
                && (string)$slots[$j]['kind'] === $kind
                && (string)($slots[$j]['offer_id'] ?? '0') === $offerId
            ) {
                $j++;
            }
            $len = $j - $i;
            for ($k = $i; $k < $j; $k++) {
                if ($len === 1) {
                    $slots[$k]['bar_pos'] = 'bar-single';
                } elseif ($k === $i) {
                    $slots[$k]['bar_pos'] = 'bar-start';
                } elseif ($k === $j - 1) {
                    $slots[$k]['bar_pos'] = 'bar-end';
                } else {
                    $slots[$k]['bar_pos'] = 'bar-mid';
                }
            }
            $label = trim((string)($slots[$i]['label'] ?? ''));
            if ($label !== '' && $len >= 2) {
                $slots[$i]['show_label'] = true;
            }
            $i = $j;
        }

        // Marquer le premier et dernier créneau occupé de la journée
        $first = null;
        $last  = null;
        for ($k = 0; $k < $n; $k++) {
            $kind = (string)$slots[$k]['kind'];
            if ($kind === self::KIND_EMPTY || $kind === self::KIND_UNAVAILABLE || $kind === self::KIND_REMOTE) {
                continue;
            }
            if ($first === null) {
                $first = $k;
            }
            $last = $k;
        }
        for ($k = 0; $k < $n; $k++) {
            $slots[$k]['day_start'] = ($first !== null && $k === $first);
            $slots[$k]['day_end']   = ($last  !== null && $k === $last);
        }

        return $slots;
    }

    /**
     * @param list<array<string,mixed>> $slots
     */
    public function renderHtml(array $slots, int $userId): string
    {
        $html = '';
        foreach ($this->decorate($slots) as $slot) {
            $html .= $this->renderTd($slot, $userId);
        }

        return $html;
    }

    /**
     * @param array<string,mixed> $slot
     */
    public function kindOf(array $slot): string
    {
        if (!empty($slot['unavailable'])) {
            return self::KIND_UNAVAILABLE;
        }
        $offerId = (string)($slot['offer_id'] ?? '0');
        if ($offerId === '' || $offerId === '0') {
            return self::KIND_EMPTY;
        }
        $type = (string)($slot['offer_type'] ?? 'normal');
        if ($type === 'pause') {
            return self::KIND_PAUSE;
        }
        if ($type === 'lunch') {
            return self::KIND_LUNCH;
        }
        if ($type === 'remote_work') {
            return self::KIND_REMOTE;
        }

        return self::KIND_PROD;
    }

    /**
     * @param array<string,mixed> $slot
     */
    private function renderTd(array $slot, int $userId): string
    {
        $kind = (string)$slot['kind'];
        $classes = ['td_quarter'];
        if ($kind === self::KIND_UNAVAILABLE) {
            $classes[] = 'td_unavailable';
        }
        if ($kind === self::KIND_PAUSE) {
            $classes[] = 'bar-pause';
        }
        if ($kind === self::KIND_LUNCH) {
            $classes[] = 'bar-lunch';
        }
        $barPos = (string)($slot['bar_pos'] ?? '');
        if ($barPos !== '') {
            $classes[] = $barPos;
        }
        if (!empty($slot['day_start'])) {
            $classes[] = 'bar-day-start';
        }
        if (!empty($slot['day_end'])) {
            $classes[] = 'bar-day-end';
        }
        if (!empty($slot['hour_end'])) {
            $classes[] = 'hour-end';
        }

        $offerId = (string)($slot['offer_id'] ?? '0');
        $offerType = (string)($slot['offer_type'] ?? '');
        $rangeId = (string)($slot['range_id'] ?? '');
        $start = (string)($slot['start'] ?? '');
        $end = (string)($slot['end'] ?? '');
        $color = (string)($slot['color'] ?? '');
        $title = (string)($slot['title'] ?? '');

        $style = '';
        if (($kind === self::KIND_PROD || $kind === self::KIND_REMOTE) && $color !== '') {
            $style = ' style="background-color:' . $this->esc($color) . '"';
        }

        $titleAttr = '';
        if ($title !== '') {
            $titleAttr = ' data-bs-toggle="tooltip" data-placement="top" title="' . $this->esc($title) . '"';
        }

        $labelHtml = '';
        if (!empty($slot['show_label'])) {
            $labelHtml = '<span class="bar-label">' . $this->esc((string)$slot['label']) . '</span>';
        }

        return '<td class="' . implode(' ', $classes) . '"' . $style . $titleAttr
            . ' data-user-id="' . (int)$userId . '"'
            . ' data-start="' . $this->esc($start) . '"'
            . ' data-end="' . $this->esc($end) . '"'
            . ' data-offer-id="' . $this->esc($offerId) . '"'
            . ' data-offer-type="' . $this->esc($offerType) . '"'
            . ' data-offer-label="' . $this->esc((string)($slot['label'] ?? '')) . '"'
            . ' data-range-id="' . $this->esc($rangeId) . '"'
            . '>' . $labelHtml . '</td>';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
