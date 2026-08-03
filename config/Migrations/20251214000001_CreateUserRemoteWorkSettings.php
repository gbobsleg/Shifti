<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateUserRemoteWorkSettings extends AbstractMigration
{
    public function change(): void
    {
        if (!$this->hasTable('user_remote_work_settings')) {
            $table = $this->table('user_remote_work_settings');
            
            $table->addColumn('user_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
                'comment' => 'Référence vers l\'utilisateur',
            ]);
            
            $table->addColumn('remote_work_type', 'string', [
                'default' => 'none',
                'limit' => 20,
                'null' => false,
                'comment' => 'Type: none, fixed_days, flexible',
            ]);
            
            $table->addColumn('fixed_days_json', 'text', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'comment' => 'Config JSON pour jours fixes: {"days": [1,3], "time_ranges": [{"start": "09:00", "end": "17:00"}]}',
            ]);
            
            $table->addColumn('flexible_days_per_week', 'integer', [
                'default' => 0,
                'limit' => null,
                'null' => false,
                'comment' => 'Nombre de jours flexibles par semaine',
            ]);
            
            $table->addColumn('notes', 'text', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'comment' => 'Notes libres sur la configuration',
            ]);
            
            $table->addTimestamps('created', 'modified');
            
            // Clé étrangère vers users
            $table->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ]);
            
            // Index unique sur user_id (un seul config par user)
            $table->addIndex(['user_id'], [
                'unique' => true,
                'name' => 'unique_user_remote_work',
            ]);
            
            $table->create();
        }
    }
}
