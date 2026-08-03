<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddDatesToUserRemoteWorkSettings extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('user_remote_work_settings');
        
        if (!$table->hasColumn('start_date')) {
            $table->addColumn('start_date', 'date', [
                'default' => null,
                'null' => true,
                'comment' => 'Date de début de validité du télétravail',
                'after' => 'remote_work_type',
            ]);
        }
        
        if (!$table->hasColumn('end_date')) {
            $table->addColumn('end_date', 'date', [
                'default' => null,
                'null' => true,
                'comment' => 'Date de fin de validité du télétravail',
                'after' => 'start_date',
            ]);
        }
        
        $table->update();
    }
}
