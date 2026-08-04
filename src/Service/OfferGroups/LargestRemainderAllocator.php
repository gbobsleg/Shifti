<?php
declare(strict_types=1);

namespace App\Service\OfferGroups;

/**
 * Répartition entière d'un total N selon des ratios (%) via la méthode du plus grand reste
 * (Largest Remainder / Hare–Niemeyer).
 *
 * Formule :
 * 1. q_m = N * r_m / 100
 * 2. a_m = floor(q_m)
 * 3. R = N - Σ a_m
 * 4. Trier par reste (q_m - a_m) décroissant, puis display_order croissant, puis offer_id croissant
 * 5. +1 aux R premiers
 */
final class LargestRemainderAllocator
{
    /**
     * @param list<array{
     *   offer_id:int,
     *   display_order:int,
     *   ratio_percent:int,
     *   key?:string
     * }> $members
     * @return array<string, int> map clé (key|offer_id) → part entière allouée ; Σ = $total
     */
    public function allocate(int $total, array $members): array
    {
        if ($total < 0) {
            throw new \InvalidArgumentException('total must be >= 0');
        }
        if ($members === []) {
            return [];
        }
        if ($total === 0) {
            $zeros = [];
            foreach ($members as $m) {
                $zeros[$this->memberKey($m)] = 0;
            }

            return $zeros;
        }

        $ratioSum = 0;
        foreach ($members as $m) {
            $ratioSum += (int)$m['ratio_percent'];
        }
        if ($ratioSum !== 100) {
            throw new \InvalidArgumentException(
                sprintf('sum of ratio_percent must be 100 (got %d)', $ratioSum)
            );
        }

        $rows = [];
        foreach ($members as $index => $m) {
            $ratio = (int)$m['ratio_percent'];
            $exact = ($total * $ratio) / 100.0;
            $floor = (int)floor($exact);
            $rows[] = [
                'key' => $this->memberKey($m),
                'offer_id' => (int)$m['offer_id'],
                'display_order' => (int)$m['display_order'],
                'index' => $index,
                'floor' => $floor,
                'remainder' => $exact - $floor,
            ];
        }

        $sumFloor = 0;
        foreach ($rows as $row) {
            $sumFloor += $row['floor'];
        }
        $R = $total - $sumFloor;

        usort($rows, static function (array $a, array $b): int {
            // reste décroissant
            if ($a['remainder'] !== $b['remainder']) {
                return $a['remainder'] < $b['remainder'] ? 1 : -1;
            }
            // display_order croissant
            if ($a['display_order'] !== $b['display_order']) {
                return $a['display_order'] <=> $b['display_order'];
            }
            // offer_id croissant
            if ($a['offer_id'] !== $b['offer_id']) {
                return $a['offer_id'] <=> $b['offer_id'];
            }

            return $a['index'] <=> $b['index'];
        });

        $result = [];
        foreach ($rows as $i => $row) {
            $result[$row['key']] = $row['floor'] + ($i < $R ? 1 : 0);
        }

        return $result;
    }

    /**
     * @param array{offer_id:int, key?:string} $member
     */
    private function memberKey(array $member): string
    {
        if (isset($member['key']) && is_string($member['key']) && $member['key'] !== '') {
            return $member['key'];
        }

        return (string)(int)$member['offer_id'];
    }
}
