<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Entity\Offer;
use App\Model\Table\OfferColorPresetsTable;
use App\Model\Table\OffersTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\OfferColorPresetsTable Test Case
 */
class OfferColorPresetsTableTest extends TestCase
{
    /**
     * @var \App\Model\Table\OfferColorPresetsTable
     */
    protected OfferColorPresetsTable $OfferColorPresets;

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
        $this->OfferColorPresets = $this->getTableLocator()->get('OfferColorPresets');
        $this->Offers = $this->getTableLocator()->get('Offers');
    }

    protected function tearDown(): void
    {
        $items = $this->getTableLocator()->get('OfferColorPresetItems');
        $presetIds = $this->OfferColorPresets->find()
            ->select(['id'])
            ->where(['name LIKE' => 'OCP_%'])
            ->all()
            ->extract('id')
            ->toList();
        if ($presetIds !== []) {
            $items->deleteAll(['preset_id IN' => $presetIds]);
            $this->OfferColorPresets->deleteAll(['id IN' => $presetIds]);
        }
        $this->Offers->deleteAll(['name LIKE' => 'OCP_Test_%']);

        parent::tearDown();
        TableRegistry::getTableLocator()->clear();
    }

    public function testRestoreRewritesMembersAndPlacesLaterOfferAfter(): void
    {
        $kept = $this->createOffer('OCP_Test_Kept', '#112233', 4);
        $changed = $this->createOffer('OCP_Test_Changed', '#445566', 5);

        $preset = $this->OfferColorPresets->capture('OCP_Avant');
        $this->assertNotEmpty($preset->id);

        $changed->color = '#abcdef';
        $changed->display_order = 90;
        $this->Offers->saveOrFail($changed);

        $later = $this->createOffer('OCP_Test_Later', '#778899', 6);

        $this->OfferColorPresets->restore((int)$preset->id);

        $keptAfter = $this->Offers->get($kept->id);
        $changedAfter = $this->Offers->get($changed->id);
        $laterAfter = $this->Offers->get($later->id);
        $itemCount = $this->getTableLocator()->get('OfferColorPresetItems')->find()
            ->where(['preset_id' => $preset->id])
            ->count();

        $this->assertSame('#112233', $keptAfter->color);
        $this->assertSame('#445566', $changedAfter->color);
        $this->assertSame('#778899', $laterAfter->color);
        $this->assertLessThan((int)$changedAfter->display_order, (int)$keptAfter->display_order);
        $this->assertLessThan((int)$laterAfter->display_order, (int)$changedAfter->display_order);
        $this->assertSame($itemCount, (int)$laterAfter->display_order);
    }

    public function testCaptureRejectsSameNameIgnoringCase(): void
    {
        $this->createOffer('OCP_Test_One', '#010101', 1);

        $first = $this->OfferColorPresets->capture('OCP_Été');
        $this->assertNotEmpty($first->id);

        $second = $this->OfferColorPresets->capture('OCP_été');
        $this->assertEmpty($second->id);
        $this->assertNotEmpty($second->getError('name'));
        $this->assertSame(1, $this->OfferColorPresets->find()->where(['name LIKE' => 'OCP_Été'])->count());
    }

    public function testCaptureReadsColorsFromDatabase(): void
    {
        $offer = $this->createOffer('OCP_Test_Source', '#abc123', 12);

        $preset = $this->OfferColorPresets->capture('OCP_Source');
        $this->assertNotEmpty($preset->id);

        $item = $this->getTableLocator()->get('OfferColorPresetItems')->find()
            ->where(['preset_id' => $preset->id, 'offer_id' => $offer->id])
            ->firstOrFail();

        $this->assertSame('#abc123', $item->color);
        $this->assertSame(12, $item->display_order);
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
