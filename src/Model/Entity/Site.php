<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Site Entity
 *
 * @property int $id
 * @property string $name
 * @property int $number
 * @property int $region_id
 *
 * @property \App\Model\Entity\Region $region
 * @property \App\Model\Entity\User[] $users
 */
class Site extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'name' => true,
        'number' => true,
        'region_id' => true,
        'region' => true,
        'users' => true,
    ];
}
