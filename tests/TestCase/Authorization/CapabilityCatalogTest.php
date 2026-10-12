<?php
declare(strict_types=1);

namespace App\Test\TestCase\Authorization;

use App\Authorization\Capability;
use App\Authorization\CapabilityCatalog;
use Cake\TestSuite\TestCase;

class CapabilityCatalogTest extends TestCase
{
    /**
     * Chaque capacité a un libellé unique, et le catalogue n'en invente pas.
     *
     * @return void
     */
    public function testCatalogListsEveryCapabilityOnceWithALabel(): void
    {
        $listed = [];
        foreach (CapabilityCatalog::groups() as $groupName => $capabilities) {
            $this->assertNotSame('', trim($groupName));
            $this->assertNotEmpty($capabilities);
            foreach ($capabilities as $capability => $label) {
                $this->assertNotSame('', trim($label), "Libellé vide pour {$capability}");
                $listed[] = $capability;
            }
        }
        $this->assertSame(count($listed), count(array_unique($listed)), 'Capacité en double dans le catalogue');
        $expected = Capability::all();
        sort($expected);
        sort($listed);
        $this->assertSame($expected, $listed);
    }
}
