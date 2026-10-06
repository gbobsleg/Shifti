<?php
declare(strict_types=1);

use App\Service\RangeSource;
use Migrations\BaseMigration;

class AddSourceToRanges extends BaseMigration
{
    public function up(): void
    {
        if (!$this->hasTable('ranges')) {
            return;
        }

        $table = $this->table('ranges');
        if (!$table->hasColumn('source')) {
            $table->addColumn('source', 'enum', [
                'values' => RangeSource::values(),
                'default' => RangeSource::MANUAL,
                'null' => false,
                'after' => 'comment',
            ]);
            $table->update();
        }

        $rows = $this->fetchAll('SELECT id, comment FROM ranges');
        $idsBySource = [
            RangeSource::IMPORT => [],
            RangeSource::AUTO_TAD => [],
            RangeSource::PLANNING => [],
        ];
        foreach ($rows as $row) {
            $comment = isset($row['comment']) ? (string)$row['comment'] : null;
            $source = RangeSource::fromLegacyComment($comment);
            if ($source === RangeSource::MANUAL) {
                continue;
            }
            $idsBySource[$source][] = (int)$row['id'];
        }

        foreach ($idsBySource as $source => $ids) {
            foreach (array_chunk($ids, 500) as $chunk) {
                if ($chunk === []) {
                    continue;
                }
                $idList = implode(',', $chunk);
                $this->execute("UPDATE ranges SET source = '{$source}' WHERE id IN ({$idList})");
            }
        }
    }

    public function down(): void
    {
        if (!$this->hasTable('ranges')) {
            return;
        }

        $table = $this->table('ranges');
        if ($table->hasColumn('source')) {
            $table->removeColumn('source')->update();
        }
    }
}
