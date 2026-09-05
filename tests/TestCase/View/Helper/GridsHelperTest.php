<?php
declare(strict_types=1);

namespace App\Test\TestCase\View\Helper;

use App\View\Helper\GridsHelper;
use Cake\I18n\FrozenTime;
use Cake\TestSuite\TestCase;
use Cake\View\View;

class GridsHelperTest extends TestCase
{
    public function testWriteLoadSlotsMatchesAgentSlotCount(): void
    {
        $helper = new GridsHelper(new View());
        $helper->setGridHours(9, 17);
        $day = new FrozenTime('2026-09-07 00:00:00');

        $user = (object)[
            'id' => 1,
            'ranges' => [],
            'user_availabilities' => [],
        ];

        $agentHtml = $helper->writeTimeSlots($user, $day);
        $loadHtml = $helper->writeLoadSlots($day);

        preg_match_all('/<td\b/', $agentHtml, $agentCells);
        preg_match_all('/<td\b/', $loadHtml, $loadCells);
        $this->assertSame(count($agentCells[0]), count($loadCells[0]));

        preg_match_all('/class="grids-load-cell"/', $loadHtml, $slots);
        preg_match_all('/class="td_quarter/', $agentHtml, $quarters);
        $this->assertSame(count($quarters[0]), count($slots[0]));
        $this->assertSame(32, count($slots[0]));

        preg_match_all('/class="grids-gutter"/', $loadHtml, $gutters);
        $this->assertSame(2, count($gutters[0]));
        $this->assertStringContainsString('data-slot="09:00"', $loadHtml);
        $this->assertStringContainsString('data-slot="16:45"', $loadHtml);
        $this->assertStringNotContainsString('data-slot="17:00"', $loadHtml);
    }

    public function testWriteLoadRowHasNeedAndPlannedLabels(): void
    {
        $helper = new GridsHelper(new View());
        $helper->setGridHours(9, 17);
        $day = new FrozenTime('2026-09-07 00:00:00');

        $need = $helper->writeLoadRow('need', $day);
        $planned = $helper->writeLoadRow('planned', $day);

        $this->assertStringContainsString('grids-load-row--need', $need);
        $this->assertStringContainsString('Besoin', $need);
        $this->assertStringContainsString(' hidden>', $need);
        $this->assertStringContainsString('grids-load-row--planned', $planned);
        $this->assertStringContainsString('Réel', $planned);
        $this->assertStringNotContainsString('td_quarter', $need);
    }
}
