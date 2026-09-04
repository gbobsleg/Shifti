<?php
declare(strict_types=1);

namespace App\Test\TestCase\View\Grid;

use App\View\Grid\BarRenderer;
use Cake\TestSuite\TestCase;

class BarRendererTest extends TestCase
{
    public function testMergesConsecutiveSameOffer(): void
    {
        $renderer = new BarRenderer();
        $slots = [
            $this->prod('1', 'AE'),
            $this->prod('1', 'AE'),
            $this->prod('1', 'AE'),
            $this->empty(),
        ];
        $decorated = $renderer->decorate($slots);
        $this->assertSame('bar-start', $decorated[0]['bar_pos']);
        $this->assertSame('bar-mid', $decorated[1]['bar_pos']);
        $this->assertSame('bar-end', $decorated[2]['bar_pos']);
        $this->assertSame('', $decorated[3]['bar_pos']);
        $this->assertTrue($decorated[0]['show_label']);
    }

    public function testPauseIsOwnKind(): void
    {
        $renderer = new BarRenderer();
        $slots = [
            $this->prod('1', 'AE'),
            ['offer_id' => '9', 'offer_type' => 'pause', 'unavailable' => false, 'label' => 'Pause'],
            $this->prod('1', 'AE'),
        ];
        $decorated = $renderer->decorate($slots);
        $this->assertSame(BarRenderer::KIND_PAUSE, $decorated[1]['kind']);
        $this->assertSame('bar-single', $decorated[1]['bar_pos']);
        $this->assertSame('bar-single', $decorated[0]['bar_pos']);
    }

    public function testRemoteWorkKeepsColorWithoutBar(): void
    {
        $renderer = new BarRenderer();
        $slots = [
            [
                'offer_id' => '8',
                'offer_type' => 'remote_work',
                'unavailable' => false,
                'label' => 'Télétravail',
                'color' => '#ACCCF3',
                'start' => '2026-09-07 09:00:00',
                'end' => '2026-09-07 09:15:00',
                'range_id' => '1',
                'title' => '',
            ],
            $this->prod('1', 'TI'),
        ];
        $decorated = $renderer->decorate($slots);
        $this->assertSame(BarRenderer::KIND_REMOTE, $decorated[0]['kind']);
        $this->assertSame('', $decorated[0]['bar_pos']);
        $this->assertSame(BarRenderer::KIND_PROD, $decorated[1]['kind']);

        $html = $renderer->renderHtml($slots, 12);
        $this->assertStringContainsString('background-color:#ACCCF3', $html);
        $this->assertStringContainsString('data-offer-type="remote_work"', $html);
    }

    /**
     * @return array<string,mixed>
     */
    private function prod(string $id, string $label): array
    {
        return [
            'offer_id' => $id,
            'offer_type' => 'normal',
            'unavailable' => false,
            'label' => $label,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function empty(): array
    {
        return [
            'offer_id' => '0',
            'offer_type' => '',
            'unavailable' => false,
            'label' => '',
        ];
    }
}
