<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Model\Table\RangesTable;
use App\Service\ExcelRangeImportClassifier;
use Cake\Collection\Collection;
use Cake\I18n\FrozenTime;
use Cake\ORM\Query\SelectQuery;
use Cake\TestSuite\TestCase;

class ExcelRangeImportClassifierTest extends TestCase
{
    private function classifierWithRows(array $existingRows): ExcelRangeImportClassifier
    {
        $query = $this->createMock(SelectQuery::class);
        $query->method('select')->willReturnSelf();
        $query->method('where')->willReturnSelf();
        $query->method('disableHydration')->willReturnSelf();
        $query->method('all')->willReturn(new Collection($existingRows));

        $table = $this->createMock(RangesTable::class);
        $table->method('find')->willReturn($query);

        $classifier = new ExcelRangeImportClassifier();
        $this->rangesTable = $table;

        return $classifier;
    }

    private RangesTable $rangesTable;

    private function excelRange(int $userId, int $offerId, string $start, string $end): array
    {
        return [
            'user_id' => $userId,
            'offer_id' => $offerId,
            'date_start' => FrozenTime::parse($start),
            'date_end' => FrozenTime::parse($end),
            'comment' => 'Télétravail - GroomRH',
        ];
    }

    private function existing(int $id, int $userId, int $offerId, string $start, string $end, string $comment): array
    {
        return [
            'id' => $id,
            'user_id' => $userId,
            'offer_id' => $offerId,
            'date_start' => $start,
            'date_end' => $end,
            'comment' => $comment,
        ];
    }

    public function testExactGroomrhIsSkippedNotReplaced(): void
    {
        $classifier = $this->classifierWithRows([
            $this->existing(1, 10, 3, '2026-09-08 08:00:00', '2026-09-08 18:00:00', 'Télétravail - GroomRH'),
        ]);

        $decisions = $classifier->classify([
            $this->excelRange(10, 3, '2026-09-08 08:00:00', '2026-09-08 18:00:00'),
        ], $this->rangesTable);

        $this->assertSame(ExcelRangeImportClassifier::STATUS_SKIP_IDENTICAL, $decisions[0]['status']);
        $this->assertSame([], $decisions[0]['replace_ids']);
    }

    public function testAutoTadOverlapIsSkipped(): void
    {
        $classifier = $this->classifierWithRows([
            $this->existing(2, 10, 3, '2026-09-08 09:00:00', '2026-09-08 17:00:00', '[AUTO-TAD] 2026-01-01 00:00:00'),
        ]);

        $decisions = $classifier->classify([
            $this->excelRange(10, 3, '2026-09-08 08:00:00', '2026-09-10 18:00:00'),
        ], $this->rangesTable);

        $this->assertSame(ExcelRangeImportClassifier::STATUS_SKIP_AUTO_TAD, $decisions[0]['status']);
    }

    public function testManualOverlapIsSkipped(): void
    {
        $classifier = $this->classifierWithRows([
            $this->existing(3, 10, 5, '2026-09-08 10:00:00', '2026-09-08 12:00:00', 'Réunion équipe'),
        ]);

        $decisions = $classifier->classify([
            $this->excelRange(10, 5, '2026-09-08 11:00:00', '2026-09-08 13:00:00'),
        ], $this->rangesTable);

        $this->assertSame(ExcelRangeImportClassifier::STATUS_SKIP_MANUAL, $decisions[0]['status']);
    }

    public function testGroomrhFullyCoveredIsReplaced(): void
    {
        $classifier = $this->classifierWithRows([
            $this->existing(4, 10, 5, '2026-09-09 08:00:00', '2026-09-09 18:00:00', 'Congé - GroomRH'),
        ]);

        $decisions = $classifier->classify([
            $this->excelRange(10, 5, '2026-09-08 08:00:00', '2026-09-12 18:00:00'),
        ], $this->rangesTable);

        $this->assertSame(ExcelRangeImportClassifier::STATUS_REPLACE_GROOMRH, $decisions[0]['status']);
        $this->assertSame([4], $decisions[0]['replace_ids']);
    }

    public function testGroomrhLargerThanExcelIsSkipped(): void
    {
        $classifier = $this->classifierWithRows([
            $this->existing(5, 10, 5, '2026-09-08 08:00:00', '2026-09-12 18:00:00', 'Congé - GroomRH'),
        ]);

        $decisions = $classifier->classify([
            $this->excelRange(10, 5, '2026-09-10 08:00:00', '2026-09-10 18:00:00'),
        ], $this->rangesTable);

        $this->assertSame(ExcelRangeImportClassifier::STATUS_SKIP_GROOMRH_PARTIAL, $decisions[0]['status']);
        $this->assertSame([], $decisions[0]['replace_ids']);
    }

    public function testIntraExcelSecondLineIsSkipped(): void
    {
        $classifier = $this->classifierWithRows([]);

        $decisions = $classifier->classify([
            $this->excelRange(10, 5, '2026-09-08 08:00:00', '2026-09-08 12:00:00'),
            $this->excelRange(10, 5, '2026-09-08 11:00:00', '2026-09-08 13:00:00'),
        ], $this->rangesTable);

        $this->assertSame(ExcelRangeImportClassifier::STATUS_NEW, $decisions[0]['status']);
        $this->assertSame(ExcelRangeImportClassifier::STATUS_SKIP_INTRA_EXCEL, $decisions[1]['status']);
    }

    public function testNewWhenNoOverlap(): void
    {
        $classifier = $this->classifierWithRows([
            $this->existing(6, 10, 5, '2026-09-01 08:00:00', '2026-09-01 18:00:00', 'Congé - GroomRH'),
        ]);

        $decisions = $classifier->classify([
            $this->excelRange(10, 5, '2026-09-08 08:00:00', '2026-09-08 18:00:00'),
        ], $this->rangesTable);

        $this->assertSame(ExcelRangeImportClassifier::STATUS_NEW, $decisions[0]['status']);
    }
}
