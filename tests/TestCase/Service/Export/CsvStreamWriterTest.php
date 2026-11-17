<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\Export;

use App\Service\Export\CsvStreamWriter;
use Cake\TestSuite\TestCase;

class CsvStreamWriterTest extends TestCase
{
    public function testBomAndQuotedSemicolon(): void
    {
        $writer = CsvStreamWriter::open();
        $writer->write(['Date', 'Offre']);
        $writer->write(['2026-03-02', 'Nord; Sud']);
        $handle = $writer->detach();

        rewind($handle);
        $bom = fread($handle, 3);
        $this->assertSame("\xEF\xBB\xBF", $bom);
        $header = fgetcsv($handle, 0, ';', '"', '');
        $row = fgetcsv($handle, 0, ';', '"', '');

        $this->assertSame(['Date', 'Offre'], $header);
        $this->assertSame(['2026-03-02', 'Nord; Sud'], $row);
    }
}
