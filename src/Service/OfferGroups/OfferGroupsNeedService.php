<?php
declare(strict_types=1);

namespace App\Service\OfferGroups;

use App\Model\Entity\OfferGroup;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Applique les groupes d'offres sur need_curve + produit le payload solveur.
 */
class OfferGroupsNeedService
{
    use LocatorAwareTrait;

    private LargestRemainderAllocator $allocator;

    public function __construct(?LargestRemainderAllocator $allocator = null)
    {
        $this->allocator = $allocator ?? new LargestRemainderAllocator();
    }

    /**
     * @param array<string, array<string, int>> $needCurve
     * @return array{
     *   need_curve: array<string, array<string, int>>,
     *   offer_groups: list<array{name:string,mixed:string,members:list<string>,prefer_mixed:bool}>,
     *   offer_to_bucket: array<string, string>,
     *   groups_meta: list<array{name:string,mixed:string,members:list<string>}>
     * }
     */
    public function applyToNeedCurve(array $needCurve): array
    {
        $groups = $this->loadGroups();
        if ($groups === []) {
            return [
                'need_curve' => $needCurve,
                'offer_groups' => [],
                'offer_to_bucket' => [],
                'groups_meta' => [],
            ];
        }

        $offerGroupsPayload = [];
        $offerToBucket = [];
        $groupsMeta = [];

        foreach ($groups as $group) {
            $mixedOffer = $group->mixed_offer;
            $mixedName = is_object($mixedOffer) ? (string)$mixedOffer->name : '';
            if ($mixedName === '') {
                continue;
            }

            $members = [];
            $memberSpecs = [];
            foreach ($group->offer_group_members ?? [] as $member) {
                $offer = $member->offer ?? null;
                $memberName = is_object($offer) ? (string)$offer->name : '';
                if ($memberName === '') {
                    continue;
                }
                $members[] = $memberName;
                $memberSpecs[] = [
                    'offer_id' => (int)$member->offer_id,
                    'display_order' => (int)$member->display_order,
                    'ratio_percent' => (int)($member->split_ratio_percent ?? 0),
                    'key' => $memberName,
                ];
                $offerToBucket[$memberName] = (string)$group->name;
            }
            $offerToBucket[$mixedName] = (string)$group->name;

            if (count($members) < 2) {
                continue;
            }

            $groupsMeta[] = [
                'name' => (string)$group->name,
                'mixed' => $mixedName,
                'members' => $members,
            ];

            $offerGroupsPayload[] = [
                'name' => (string)$group->name,
                'mixed' => $mixedName,
                'members' => $members,
                'prefer_mixed' => (bool)$group->prefer_mixed,
            ];

            if ($group->forecast_source === OfferGroup::FORECAST_SOURCE_GROUP) {
                $needCurve = $this->applyGroupMode($needCurve, $mixedName, $memberSpecs);
            } else {
                $needCurve = $this->applyMembersMode($needCurve, $mixedName, $members);
            }
        }

        return [
            'need_curve' => $needCurve,
            'offer_groups' => $offerGroupsPayload,
            'offer_to_bucket' => $offerToBucket,
            'groups_meta' => $groupsMeta,
        ];
    }

    /**
     * Éligibilité : skill ∈ need_curve OU skill = mixte d'un groupe dont un membre est dans need_curve.
     *
     * @param list<string> $skills
     * @param array<string, array<string, int>> $needCurve
     * @param list<array{name:string,mixed:string,members:list<string>}> $groupsMeta
     */
    public function agentHasRelevantSkill(array $skills, array $needCurve, array $groupsMeta): bool
    {
        foreach ($skills as $skill) {
            if (isset($needCurve[$skill])) {
                return true;
            }
        }

        foreach ($groupsMeta as $group) {
            $mixed = $group['mixed'];
            if (!in_array($mixed, $skills, true)) {
                continue;
            }
            foreach ($group['members'] as $member) {
                if (isset($needCurve[$member])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<\App\Model\Entity\OfferGroup>
     */
    public function loadGroups(): array
    {
        $OfferGroups = $this->fetchTable('OfferGroups');

        return $OfferGroups->find()
            ->contain([
                'MixedOffers',
                'OfferGroupMembers' => ['Offers'],
            ])
            ->orderBy(['OfferGroups.name' => 'ASC'])
            ->all()
            ->toList();
    }

    /**
     * Mode group : split LRM de la courbe mixte → membres ; mixte → 0.
     *
     * @param array<string, array<string, int>> $needCurve
     * @param list<array{offer_id:int,display_order:int,ratio_percent:int,key:string}> $memberSpecs
     * @return array<string, array<string, int>>
     */
    private function applyGroupMode(array $needCurve, string $mixedName, array $memberSpecs): array
    {
        $mixedCurve = $needCurve[$mixedName] ?? [];
        $timeKeys = array_keys($mixedCurve);
        if ($timeKeys === []) {
            // Pas de courbe mixte : assurer des courbes membres vides + mixte 0
            foreach ($memberSpecs as $spec) {
                $needCurve[$spec['key']] = $needCurve[$spec['key']] ?? [];
            }
            $needCurve[$mixedName] = [];

            return $needCurve;
        }

        foreach ($memberSpecs as $spec) {
            $needCurve[$spec['key']] = $needCurve[$spec['key']] ?? [];
        }

        foreach ($timeKeys as $timeKey) {
            $total = (int)($mixedCurve[$timeKey] ?? 0);
            $parts = $this->allocator->allocate($total, $memberSpecs);
            foreach ($memberSpecs as $spec) {
                $needCurve[$spec['key']][$timeKey] = (int)($parts[$spec['key']] ?? 0);
            }
            $needCurve[$mixedName][$timeKey] = 0;
        }

        return $needCurve;
    }

    /**
     * Mode members : conserver needs membres ; injecter mixte à 0 sur l'union des créneaux.
     *
     * @param array<string, array<string, int>> $needCurve
     * @param list<string> $members
     * @return array<string, array<string, int>>
     */
    private function applyMembersMode(array $needCurve, string $mixedName, array $members): array
    {
        $timeKeys = [];
        foreach ($members as $member) {
            if (!isset($needCurve[$member]) || !is_array($needCurve[$member])) {
                $needCurve[$member] = [];
                continue;
            }
            foreach (array_keys($needCurve[$member]) as $tk) {
                $timeKeys[$tk] = true;
            }
        }

        $mixedCurve = [];
        foreach (array_keys($timeKeys) as $tk) {
            $mixedCurve[$tk] = 0;
        }
        // Préserver d'éventuels créneaux déjà présents sur le mixte en les forçant à 0
        if (isset($needCurve[$mixedName]) && is_array($needCurve[$mixedName])) {
            foreach (array_keys($needCurve[$mixedName]) as $tk) {
                $mixedCurve[$tk] = 0;
            }
        }
        $needCurve[$mixedName] = $mixedCurve;

        return $needCurve;
    }
}
