<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Entity\Offer;
use App\Model\Entity\OfferGroup;
use App\Model\Table\OfferGroupsTable;
use App\Model\Table\OffersTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\OfferGroupsTable Test Case
 *
 * Couvre la sauvegarde des associations membres + validation des ratios.
 */
class OfferGroupsTableTest extends TestCase
{
    /**
     * @var \App\Model\Table\OfferGroupsTable
     */
    protected OfferGroupsTable $OfferGroups;

    /**
     * @var \App\Model\Table\OffersTable
     */
    protected OffersTable $Offers;

    /**
     * Pas de fixtures : schéma via Migrator, données créées dans chaque test.
     *
     * @var array<string>
     */
    protected array $fixtures = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->OfferGroups = $this->getTableLocator()->get('OfferGroups');
        $this->Offers = $this->getTableLocator()->get('Offers');
    }

    protected function tearDown(): void
    {
        // Nettoyage pour isoler les tests (contraintes unique name / offer_id).
        $members = $this->getTableLocator()->get('OfferGroupMembers');
        $members->deleteAll(['id IS NOT' => null]);
        $this->OfferGroups->deleteAll(['id IS NOT' => null]);
        $this->Offers->deleteAll(['name LIKE' => 'OG_Test_%']);

        parent::tearDown();
        TableRegistry::getTableLocator()->clear();
    }

    /**
     * Mode group + ratios 40/50 → échec (somme ≠ 100).
     */
    public function testSaveGroupModeFailsWhenRatiosSumIsNot100(): void
    {
        [$ti, $ae, $mixed] = $this->createOffers(['OG_Test_TI', 'OG_Test_AE', 'OG_Test_TI_AE']);

        $entity = $this->OfferGroups->newEntity([
            'name' => 'Groupe TI-AE ratios invalides',
            'mixed_offer_id' => $mixed->id,
            'forecast_source' => OfferGroup::FORECAST_SOURCE_GROUP,
            'prefer_mixed' => true,
            'offer_group_members' => [
                [
                    'offer_id' => $ti->id,
                    'display_order' => 0,
                    'split_ratio_percent' => 40,
                ],
                [
                    'offer_id' => $ae->id,
                    'display_order' => 1,
                    'split_ratio_percent' => 50,
                ],
            ],
        ], [
            'associated' => ['OfferGroupMembers'],
        ]);

        $result = $this->OfferGroups->save($entity, [
            'associated' => ['OfferGroupMembers'],
        ]);

        $this->assertFalse($result);
        $this->assertNotEmpty($entity->getError('offer_group_members'));
        $this->assertSame(0, $this->OfferGroups->find()->where(['name' => 'Groupe TI-AE ratios invalides'])->count());
    }

    /**
     * Mode group + ratios 50/50 → succès.
     */
    public function testSaveGroupModeSucceedsWithRatios50_50(): void
    {
        [$a, $b, $mixed] = $this->createOffers(['OG_Test_A', 'OG_Test_B', 'OG_Test_Mix']);

        $entity = $this->OfferGroups->newEntity([
            'name' => 'Groupe 50-50',
            'mixed_offer_id' => $mixed->id,
            'forecast_source' => OfferGroup::FORECAST_SOURCE_GROUP,
            'prefer_mixed' => true,
            'offer_group_members' => [
                [
                    'offer_id' => $a->id,
                    'display_order' => 0,
                    'split_ratio_percent' => 50,
                ],
                [
                    'offer_id' => $b->id,
                    'display_order' => 1,
                    'split_ratio_percent' => 50,
                ],
            ],
        ], [
            'associated' => ['OfferGroupMembers'],
        ]);

        $result = $this->OfferGroups->save($entity, [
            'associated' => ['OfferGroupMembers'],
        ]);

        $this->assertNotFalse($result);
        $this->assertNotEmpty($result->id);

        $saved = $this->OfferGroups->get($result->id, contain: ['OfferGroupMembers']);
        $this->assertCount(2, $saved->offer_group_members);
        $ratios = array_map(
            static fn ($m) => (int)$m->split_ratio_percent,
            $saved->offer_group_members
        );
        sort($ratios);
        $this->assertSame([50, 50], $ratios);
    }

    /**
     * Mode group + ratios 33/33/34 → succès.
     */
    public function testSaveGroupModeSucceedsWithRatios33_33_34(): void
    {
        [$m1, $m2, $m3, $mixed] = $this->createOffers([
            'OG_Test_M1',
            'OG_Test_M2',
            'OG_Test_M3',
            'OG_Test_Mix3',
        ]);

        $entity = $this->OfferGroups->newEntity([
            'name' => 'Groupe 33-33-34',
            'mixed_offer_id' => $mixed->id,
            'forecast_source' => OfferGroup::FORECAST_SOURCE_GROUP,
            'prefer_mixed' => false,
            'offer_group_members' => [
                [
                    'offer_id' => $m1->id,
                    'display_order' => 0,
                    'split_ratio_percent' => 33,
                ],
                [
                    'offer_id' => $m2->id,
                    'display_order' => 1,
                    'split_ratio_percent' => 33,
                ],
                [
                    'offer_id' => $m3->id,
                    'display_order' => 2,
                    'split_ratio_percent' => 34,
                ],
            ],
        ], [
            'associated' => ['OfferGroupMembers'],
        ]);

        $result = $this->OfferGroups->save($entity, [
            'associated' => ['OfferGroupMembers'],
        ]);

        $this->assertNotFalse($result);

        $saved = $this->OfferGroups->get($result->id, contain: ['OfferGroupMembers']);
        $this->assertCount(3, $saved->offer_group_members);
        $sum = array_sum(array_map(
            static fn ($m) => (int)$m->split_ratio_percent,
            $saved->offer_group_members
        ));
        $this->assertSame(100, $sum);
    }

    /**
     * Mode members : ratios fournis sont nullifiés, sauvegarde OK.
     */
    public function testSaveMembersModeSucceedsAndNullifiesRatios(): void
    {
        [$a, $b, $mixed] = $this->createOffers([
            'OG_Test_Mem_A',
            'OG_Test_Mem_B',
            'OG_Test_Mem_Mix',
        ]);

        $entity = $this->OfferGroups->newEntity([
            'name' => 'Groupe mode members',
            'mixed_offer_id' => $mixed->id,
            'forecast_source' => OfferGroup::FORECAST_SOURCE_MEMBERS,
            'prefer_mixed' => true,
            'offer_group_members' => [
                [
                    'offer_id' => $a->id,
                    'display_order' => 0,
                    'split_ratio_percent' => 60,
                ],
                [
                    'offer_id' => $b->id,
                    'display_order' => 1,
                    'split_ratio_percent' => 40,
                ],
            ],
        ], [
            'associated' => ['OfferGroupMembers'],
        ]);

        // beforeMarshal doit déjà avoir nullifié
        foreach ($entity->offer_group_members as $member) {
            $this->assertNull($member->split_ratio_percent);
        }

        $result = $this->OfferGroups->save($entity, [
            'associated' => ['OfferGroupMembers'],
        ]);

        $this->assertNotFalse($result);

        $saved = $this->OfferGroups->get($result->id, contain: ['OfferGroupMembers']);
        $this->assertSame(OfferGroup::FORECAST_SOURCE_MEMBERS, $saved->forecast_source);
        $this->assertCount(2, $saved->offer_group_members);
        foreach ($saved->offer_group_members as $member) {
            $this->assertNull($member->split_ratio_percent);
        }
    }

    /**
     * @param list<string> $names
     * @return list<\App\Model\Entity\Offer>
     */
    private function createOffers(array $names): array
    {
        $offers = [];
        $order = 1000;
        foreach ($names as $name) {
            $offer = $this->Offers->newEntity([
                'name' => $name,
                'color' => '#336699',
                'offer_type' => 'normal',
                'display_order' => $order++,
                'is_displayed_in_grid' => true,
                'is_forecastable' => false,
                'default_forecast_method' => 'historical',
                'equity_enabled' => false,
                'is_remote_work_compatible' => true,
            ]);
            $saved = $this->Offers->saveOrFail($offer);
            $this->assertInstanceOf(Offer::class, $saved);
            $offers[] = $saved;
        }

        return $offers;
    }
}
