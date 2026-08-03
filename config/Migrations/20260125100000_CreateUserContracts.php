<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateUserContracts extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('user_contracts');
        $table->addColumn('user_id', 'integer', ['null' => false]);
        $table->addColumn('start_date', 'date', ['null' => false]);
        $table->addColumn('end_date', 'date', ['null' => true, 'default' => null]);
        $table->addTimestamps('created', 'modified');
        
        // Index composite pour optimiser les requetes d'intersection
        $table->addIndex(['user_id', 'start_date', 'end_date'], [
            'name' => 'IDX_USER_CONTRACTS_PERIOD'
        ]);
        
        $table->addForeignKey('user_id', 'users', 'id', [
            'delete' => 'CASCADE',
            'update' => 'CASCADE'
        ]);
        
        $table->create();
    }
}
