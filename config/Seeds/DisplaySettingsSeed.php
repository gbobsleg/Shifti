<?php
declare(strict_types=1);

use Migrations\BaseSeed;

/**
 * DisplaySettings seed.
 */
class DisplaySettingsSeed extends BaseSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeds is available here:
     * https://book.cakephp.org/migrations/4/en/seeding.html
     *
     * @return void
     */
    public function run(): void
    {
        $data = [
            [
                'key' => 'grid_start_hour',
                'value' => '8',
                'description' => 'Heure de début d\'affichage de la grille de planning (0-23)',
                'type' => 'int',
            ],
            [
                'key' => 'grid_end_hour',
                'value' => '18',
                'description' => 'Heure de fin d\'affichage de la grille de planning (0-23)',
                'type' => 'int',
            ],
        ];

        $table = $this->table('display_settings');
        $table->insert($data)->save();
    }
}
