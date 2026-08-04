<?php
declare(strict_types=1);

namespace App\Service\OfferGroups;

/**
 * Migration idempotente de equity_state_json vers des buckets de groupe (v2).
 *
 * - Si equity_buckets_version >= 2 : no-op
 * - Sinon : pour chaque groupe, bucket[groupName][agent] = Σ minutes des clés legacy
 *   (membres ∪ mixte), suppression des clés legacy, pose version=2
 */
final class EquityBucketsMigrator
{
    public const VERSION_KEY = 'equity_buckets_version';
    public const VERSION = 2;

    /**
     * @param array<string, mixed> $equityState
     * @param list<array{
     *   name: string,
     *   mixed: string,
     *   members: list<string>
     * }> $groups
     * @return array{state: array<string, mixed>, migrated: bool}
     */
    public function migrateState(array $equityState, array $groups): array
    {
        $version = (int)($equityState[self::VERSION_KEY] ?? 0);
        if ($version >= self::VERSION) {
            return ['state' => $equityState, 'migrated' => false];
        }

        $forecastables = [];
        if (isset($equityState['forecastables']) && is_array($equityState['forecastables'])) {
            $forecastables = $equityState['forecastables'];
        }

        foreach ($groups as $group) {
            $bucket = (string)$group['name'];
            $legacyKeys = array_values(array_unique(array_merge(
                [(string)$group['mixed']],
                array_map('strval', $group['members'] ?? [])
            )));

            $bucketScores = [];
            if (isset($forecastables[$bucket]) && is_array($forecastables[$bucket])) {
                foreach ($forecastables[$bucket] as $agentId => $minutes) {
                    $bucketScores[(string)$agentId] = (int)$minutes;
                }
            }

            foreach ($legacyKeys as $legacyKey) {
                if ($legacyKey === $bucket) {
                    continue;
                }
                if (!isset($forecastables[$legacyKey]) || !is_array($forecastables[$legacyKey])) {
                    continue;
                }
                foreach ($forecastables[$legacyKey] as $agentId => $minutes) {
                    $aid = (string)$agentId;
                    $bucketScores[$aid] = (int)($bucketScores[$aid] ?? 0) + (int)$minutes;
                }
                unset($forecastables[$legacyKey]);
            }

            if ($bucketScores !== []) {
                $forecastables[$bucket] = [];
                foreach ($bucketScores as $aid => $minutes) {
                    $forecastables[$bucket][(int)$aid] = (int)$minutes;
                }
            } elseif (isset($forecastables[$bucket])) {
                // bucket vide explicite éventuel : conserver structure si déjà présent
                if (!is_array($forecastables[$bucket])) {
                    $forecastables[$bucket] = [];
                }
            }
        }

        $equityState['forecastables'] = $forecastables;
        $equityState[self::VERSION_KEY] = self::VERSION;

        return ['state' => $equityState, 'migrated' => true];
    }
}
