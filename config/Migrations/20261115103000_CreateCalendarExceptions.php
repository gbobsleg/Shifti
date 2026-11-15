<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateCalendarExceptions extends BaseMigration
{
    public function change(): void
    {
        if ($this->hasTable('calendar_exceptions')) {
            return;
        }

        $table = $this->table('calendar_exceptions');
        $table
            ->addColumn('date', 'date', [
                'null' => false,
            ])
            ->addColumn('label', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('is_open', 'boolean', [
                'default' => false,
                'null' => false,
            ])
            ->addColumn('source', 'string', [
                'limit' => 20,
                'null' => false,
                'comment' => 'legal_fr ou manual',
            ])
            ->addTimestamps('created', 'modified')
            ->addIndex(['date'], [
                'unique' => true,
                'name' => 'UQ_CALENDAR_EXCEPTIONS_DATE',
            ])
            ->create();
    }
}
