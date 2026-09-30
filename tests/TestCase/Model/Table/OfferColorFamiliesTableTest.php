<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Entity\Offer;
use App\Model\Table\OfferColorFamiliesTable;
use App\Model\Table\OffersTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\OfferColorFamiliesTable Test Case
 */
class OfferColorFamiliesTableTest extends TestCase
{
    /**
     * @var \App\Model\Table\OfferColorFamiliesTable
     */
    protected OfferColorFamiliesTable $OfferColorFamilies;

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
        $this->OfferColorFamilies = $this->getTableLocator()->get('OfferColorFamilies');
        $this->Offers = $this->getTableLocator()->get('Offers');
    }

    protected function tearDown(): void
    {
        $this->OfferColorFamilies->deleteAll(['id IS NOT' => null]);
        $this->Offers->deleteAll(['name LIKE' => 'OCF_Test_%']);

        parent::tearDown();
        TableRegistry::getTableLocator()->clear();
    }

    public function testReplaceArrangementStoresPositionsAndLeavesDisplayOrderUntouched(): void
    {
        $first = $this->createOffer('OCF_Test_First', '#111111', 3);
        $second = $this->createOffer('OCF_Test_Second', '#222222', 8);
        $leftOut = $this->createOffer('OCF_Test_Left', '#333333', 5);

        $error = $this->OfferColorFamilies->replaceArrangement([
            [
                'name' => 'OCF_Accueil',
                'position' => '1',
                'offer_ids' => [(string)$second->id, (string)$first->id],
            ],
            [
                'name' => 'OCF_Téléphonie',
                'position' => '0',
                'offer_ids' => [],
            ],
        ]);
        $this->assertNull($error);

        $families = $this->OfferColorFamilies->find()
            ->contain(['OfferColorFamilyOffers'])
            ->orderBy(['OfferColorFamilies.position' => 'ASC'])
            ->all()
            ->toList();
        $this->assertCount(2, $families);
        $this->assertSame('OCF_Téléphonie', $families[0]->name);
        $this->assertSame(0, $families[0]->position);
        $this->assertCount(0, $families[0]->offer_color_family_offers);
        $this->assertSame('OCF_Accueil', $families[1]->name);
        $this->assertSame(1, $families[1]->position);
        $this->assertSame((int)$second->id, $families[1]->offer_color_family_offers[0]->offer_id);
        $this->assertSame(0, $families[1]->offer_color_family_offers[0]->position);
        $this->assertSame((int)$first->id, $families[1]->offer_color_family_offers[1]->offer_id);
        $this->assertSame(1, $families[1]->offer_color_family_offers[1]->position);

        $members = $this->getTableLocator()->get('OfferColorFamilyOffers');
        $this->assertSame(0, $members->find()->where(['offer_id' => $leftOut->id])->count());

        $reloaded = $this->Offers->get($first->id);
        $this->assertSame(3, (int)$reloaded->display_order);
        $this->assertSame('#111111', $reloaded->color);
    }

    public function testDuplicateOfferIdWritesNothing(): void
    {
        $offer = $this->createOffer('OCF_Test_Dup', '#444444', 2);
        $kept = $this->createOffer('OCF_Test_Kept', '#555555', 6);

        $this->assertNull($this->OfferColorFamilies->replaceArrangement([
            [
                'name' => 'OCF_Stable',
                'position' => 0,
                'offer_ids' => [$kept->id],
            ],
        ]));

        $error = $this->OfferColorFamilies->replaceArrangement([
            [
                'name' => 'OCF_A',
                'position' => 0,
                'offer_ids' => [$offer->id],
            ],
            [
                'name' => 'OCF_B',
                'position' => 1,
                'offer_ids' => [(string)$offer->id],
            ],
        ]);
        $this->assertSame('Une offre est rangée dans plusieurs familles.', $error);

        $families = $this->OfferColorFamilies->find()->all()->toList();
        $this->assertCount(1, $families);
        $this->assertSame('OCF_Stable', $families[0]->name);
        $this->assertSame(6, (int)$this->Offers->get($kept->id)->display_order);
    }

    public function testReplaceKeepsAutomaticHueNullAndDoesNotPublish(): void
    {
        $offer = $this->createOffer('OCF_Test_Hue', '#abcdef', 4);

        $error = $this->OfferColorFamilies->replaceArrangement([
            [
                'name' => 'OCF_Auto',
                'position' => 0,
                'hue' => '',
                'offer_ids' => [$offer->id],
            ],
        ]);
        $this->assertNull($error);

        $family = $this->OfferColorFamilies->find()->firstOrFail();
        $this->assertNull($family->hue);
        $reloaded = $this->Offers->get($offer->id);
        $this->assertSame(4, (int)$reloaded->display_order);
        $this->assertSame('#abcdef', $reloaded->color);
    }

    public function testInvalidHueIsRejected(): void
    {
        $offer = $this->createOffer('OCF_Test_BadHue', '#111111', 1);
        $error = $this->OfferColorFamilies->replaceArrangement([
            [
                'name' => 'OCF_BadHue',
                'position' => 0,
                'hue' => 15,
                'offer_ids' => [$offer->id],
            ],
        ]);
        $this->assertSame('La teinte d\'une famille est invalide.', $error);
        $this->assertSame(0, $this->OfferColorFamilies->find()->count());
    }

    public function testPublishWritesOrderAndLeavesUnassignedColor(): void
    {
        $first = $this->createOffer('OCF_Test_First', '#111111', 9);
        $second = $this->createOffer('OCF_Test_Second', '#222222', 1);
        $early = $this->createOffer('OCF_Test_Early', '#333333', 2);
        $late = $this->createOffer('OCF_Test_Late', '#444444', 2);

        $error = $this->OfferColorFamilies->publishArrangement([
            [
                'name' => 'OCF_Accueil',
                'position' => 1,
                'hue' => 145,
                'offer_ids' => [(string)$second->id],
                'colors' => ['#12ab34'],
            ],
            [
                'name' => 'OCF_Téléphonie',
                'position' => 0,
                'hue' => '',
                'offer_ids' => [(string)$first->id],
                'colors' => ['#445566'],
            ],
        ]);
        $this->assertNull($error);

        $families = $this->OfferColorFamilies->find()
            ->contain(['OfferColorFamilyOffers'])
            ->orderBy(['OfferColorFamilies.position' => 'ASC'])
            ->all()
            ->toList();
        $this->assertSame(210, (int)$families[0]->hue);
        $this->assertSame(145, (int)$families[1]->hue);

        $reloadedFirst = $this->Offers->get($first->id);
        $reloadedSecond = $this->Offers->get($second->id);
        $reloadedEarly = $this->Offers->get($early->id);
        $reloadedLate = $this->Offers->get($late->id);

        $this->assertSame(0, (int)$reloadedFirst->display_order);
        $this->assertSame(1, (int)$reloadedSecond->display_order);
        $this->assertSame(2, (int)$reloadedEarly->display_order);
        $this->assertSame(3, (int)$reloadedLate->display_order);
        $this->assertSame('#445566', $reloadedFirst->color);
        $this->assertSame('#12ab34', $reloadedSecond->color);
        $this->assertSame('#333333', $reloadedEarly->color);
        $this->assertSame('#444444', $reloadedLate->color);
    }

    public function testPublishRejectsMissingColorsWithoutWriting(): void
    {
        $offer = $this->createOffer('OCF_Test_NoColor', '#111111', 4);
        $error = $this->OfferColorFamilies->publishArrangement([
            [
                'name' => 'OCF_Bare',
                'position' => 0,
                'hue' => 210,
                'offer_ids' => [$offer->id],
            ],
        ]);
        $this->assertSame('Les couleurs affichées sont incomplètes.', $error);
        $this->assertSame(0, $this->OfferColorFamilies->find()->count());
        $reloaded = $this->Offers->get($offer->id);
        $this->assertSame(4, (int)$reloaded->display_order);
        $this->assertSame('#111111', $reloaded->color);
    }

    public function testNonNumericOfferIdIsRejected(): void
    {
        $error = $this->OfferColorFamilies->replaceArrangement([
            [
                'name' => 'OCF_Bad',
                'position' => 0,
                'offer_ids' => ['12abc'],
            ],
        ]);
        $this->assertSame('Une offre du rangement est invalide.', $error);
        $this->assertSame(0, $this->OfferColorFamilies->find()->count());
    }

    private function createOffer(string $name, string $color, int $displayOrder): Offer
    {
        $offer = $this->Offers->newEntity([
            'name' => $name,
            'color' => $color,
            'offer_type' => 'normal',
            'display_order' => $displayOrder,
            'is_displayed_in_grid' => true,
            'is_forecastable' => false,
            'default_forecast_method' => 'historical',
            'equity_enabled' => false,
            'is_remote_work_compatible' => true,
        ]);
        $saved = $this->Offers->saveOrFail($offer);
        $this->assertInstanceOf(Offer::class, $saved);

        return $saved;
    }
}
