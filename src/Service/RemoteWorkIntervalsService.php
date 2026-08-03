<?php
declare(strict_types=1);

namespace App\Service;

use Cake\I18n\FrozenTime;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Construit les intervalles de télétravail (offer_type=remote_work) par agent pour une journée.
 */
class RemoteWorkIntervalsService
{
    use LocatorAwareTrait;

    /**
     * @param array<int,array<string,mixed>> $agentsForJson
     * @return array<int,array<int,array{start:string,end:string}>> [agent_id] => [[start,end], ...]
     */
    public function getIntervalsForAgents(FrozenTime $date, array $agentsForJson): array
    {
        Log::write('debug', '[RemoteWorkIntervals] === DÉBUT getIntervalsForAgents ===');
        Log::write('debug', '[RemoteWorkIntervals] Date reçue: ' . $date->format('Y-m-d H:i:s'));

        $agentIds = array_map(fn($a) => (int)($a['id'] ?? 0), $agentsForJson);
        $agentIds = array_values(array_filter($agentIds, fn($id) => $id > 0));
        Log::write('debug', '[RemoteWorkIntervals] Nombre d\'agents: ' . count($agentIds));
        Log::write('debug', '[RemoteWorkIntervals] IDs agents: ' . implode(', ', array_slice($agentIds, 0, 10)) . (count($agentIds) > 10 ? '...' : ''));

        if (empty($agentIds)) {
            Log::write('debug', '[RemoteWorkIntervals] Aucun agent valide, retour vide');
            return [];
        }

        $Offers = $this->fetchTable('Offers');
        $Ranges = $this->fetchTable('Ranges');

        $remoteWorkOfferIds = $Offers->find('ByType', ['type' => 'remote_work'])
            ->all()
            ->extract('id')
            ->map(fn($id) => (int)$id)
            ->toList();

        Log::write('debug', '[RemoteWorkIntervals] Nombre d\'offres remote_work trouvées: ' . count($remoteWorkOfferIds));
        Log::write('debug', '[RemoteWorkIntervals] IDs offres remote_work: ' . implode(', ', $remoteWorkOfferIds));

        if (empty($remoteWorkOfferIds)) {
            Log::write('debug', '[RemoteWorkIntervals] Aucune offre remote_work, retour vide');
            return [];
        }

        $dayStart = (clone $date)->setTime(0, 0, 0);
        $dayEnd = (clone $date)->setTime(23, 59, 59);

        Log::write('debug', '[RemoteWorkIntervals] Journée recherchée:');
        Log::write('debug', '[RemoteWorkIntervals]   - dayStart: ' . $dayStart->format('Y-m-d H:i:s'));
        Log::write('debug', '[RemoteWorkIntervals]   - dayEnd: ' . $dayEnd->format('Y-m-d H:i:s'));

        $rwRanges = $Ranges->find()
            ->where([
                'user_id IN' => $agentIds,
                'offer_id IN' => $remoteWorkOfferIds,
                'date_start <=' => $dayEnd,
                'date_end >=' => $dayStart,
            ])
            ->all();

        $rangesCount = $rwRanges->count();
        Log::write('debug', '[RemoteWorkIntervals] Nombre de Ranges trouvés par la requête: ' . $rangesCount);

        if ($rangesCount > 0) {
            Log::write('debug', '[RemoteWorkIntervals] Détails des premiers Ranges trouvés:');
            $idx = 0;
            foreach ($rwRanges as $rw) {
                if ($idx >= 5) {
                    Log::write('debug', '[RemoteWorkIntervals]   ... (' . ($rangesCount - 5) . ' autres)');
                    break;
                }
                Log::write('debug', '[RemoteWorkIntervals]   Range #' . ($idx + 1) . ': user_id=' . $rw->user_id . ', offer_id=' . $rw->offer_id . ', date_start=' . ($rw->date_start instanceof \DateTimeInterface ? $rw->date_start->format('Y-m-d H:i:s') : $rw->date_start) . ', date_end=' . ($rw->date_end instanceof \DateTimeInterface ? $rw->date_end->format('Y-m-d H:i:s') : $rw->date_end));
                $idx++;
            }
        }

        $intervalsByAgent = [];
        foreach ($rwRanges as $rw) {
            $uid = (int)$rw->user_id;
            if ($uid <= 0) {
                continue;
            }
            $normalizedStart = $this->normalizeTime($rw->date_start);
            $normalizedEnd = $this->normalizeTime($rw->date_end);
            $intervalsByAgent[$uid][] = [
                'start' => $normalizedStart,
                'end' => $normalizedEnd,
            ];
        }

        Log::write('debug', '[RemoteWorkIntervals] Intervalles construits par agent:');
        foreach ($intervalsByAgent as $uid => $intervals) {
            Log::write('debug', '[RemoteWorkIntervals]   Agent #' . $uid . ': ' . count($intervals) . ' intervalle(s)');
            foreach ($intervals as $int) {
                Log::write('debug', '[RemoteWorkIntervals]     - ' . $int['start'] . ' -> ' . $int['end']);
            }
        }

        Log::write('debug', '[RemoteWorkIntervals] === FIN getIntervalsForAgents (retourne ' . count($intervalsByAgent) . ' agents avec intervalles) ===');

        return $intervalsByAgent;
    }

    private function normalizeTime(mixed $t, string $default = '00:00:00'): string
    {
        if ($t instanceof \DateTimeInterface) {
            return $t->format('H:i:s');
        }
        if (!$t || !is_string($t)) {
            return $default;
        }
        if (preg_match('/^\d{2}:\d{2}$/', $t)) {
            return $t . ':00';
        }
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $t)) {
            return $t;
        }
        return $default;
    }
}


