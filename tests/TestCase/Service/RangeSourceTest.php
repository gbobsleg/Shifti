<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\RangeSource;
use Cake\TestSuite\TestCase;

class RangeSourceTest extends TestCase
{
    public function testFromLegacyComment(): void
    {
        $this->assertSame(RangeSource::MANUAL, RangeSource::fromLegacyComment(null));
        $this->assertSame(RangeSource::MANUAL, RangeSource::fromLegacyComment(''));
        $this->assertSame(RangeSource::MANUAL, RangeSource::fromLegacyComment('Congé posé'));
        $this->assertSame(RangeSource::AUTO_TAD, RangeSource::fromLegacyComment('[AUTO-TAD] 2026-01-01 00:00:00'));
        $this->assertSame(RangeSource::IMPORT, RangeSource::fromLegacyComment('Congé - GroomRH'));
        $this->assertSame(RangeSource::PLANNING, RangeSource::fromLegacyComment('Généré par WFM'));
        $this->assertSame(RangeSource::PLANNING, RangeSource::fromLegacyComment('Publié depuis brouillon (job #4)'));
        $this->assertSame(RangeSource::MANUAL, RangeSource::fromLegacyComment('GroomRH en début de note'));
    }
}
