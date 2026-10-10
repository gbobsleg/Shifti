<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddCodeToRolesAndPlanificateur extends BaseMigration
{
    public function up(): void
    {
        $table = $this->table('roles');
        if (!$table->hasColumn('code')) {
            $table
                ->addColumn('code', 'string', [
                    'limit' => 32,
                    'null' => true,
                    'default' => null,
                ])
                ->addIndex(['code'], [
                    'unique' => true,
                    'name' => 'uniq_roles_code',
                ])
                ->update();
        }

        $this->execute("UPDATE roles SET code = 'admin', priority = 10 WHERE id = 1");
        $this->execute("UPDATE roles SET code = 'manager', priority = 30 WHERE id = 2");
        $this->execute("UPDATE roles SET code = 'agent', priority = 40 WHERE id = 3");

        $existing = $this->fetchAll("SELECT id FROM roles WHERE code = 'planificateur'");
        if ($existing === []) {
            $now = date('Y-m-d H:i:s');
            $this->table('roles')->insert([
                [
                    'name' => 'Planificateur',
                    'code' => 'planificateur',
                    'priority' => 20,
                    'created' => $now,
                    'modified' => $now,
                ],
            ])->saveData();
        } else {
            $this->execute("UPDATE roles SET priority = 20 WHERE code = 'planificateur'");
        }
    }

    public function down(): void
    {
        $this->execute("DELETE FROM roles WHERE code = 'planificateur'");
        $this->execute('UPDATE roles SET priority = 1 WHERE id = 1');
        $this->execute('UPDATE roles SET priority = 2 WHERE id = 2');
        $this->execute('UPDATE roles SET priority = 3 WHERE id = 3');

        $table = $this->table('roles');
        if ($table->hasColumn('code')) {
            if ($table->hasIndexByName('uniq_roles_code')) {
                $table->removeIndexByName('uniq_roles_code');
            }
            $table->removeColumn('code')->update();
        }
    }
}
