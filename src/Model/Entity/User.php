<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * User Entity
 *
 * @property int $id
 * @property int $role_id
 * @property string $user_code
 * @property string $last_name
 * @property string $first_name
 * @property int $site_id
 * @property string $email
 * @property string $password
 * @property \Cake\I18n\FrozenTime|null $created
 * @property \Cake\I18n\FrozenTime|null $modified
 *
 * @property \App\Model\Entity\Role $role
 * @property \App\Model\Entity\Site $site
 * @property \App\Model\Entity\Range[] $ranges
 */
class User extends Entity
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
        'role_id' => true,
        'user_code' => true,
        'last_name' => true,
        'first_name' => true,
        'site_id' => true,
        'email' => true,
        'password' => true,
        'created' => true,
        'modified' => true,
        'role' => true,
        'site' => true,
        'ranges' => true,
        'skills' => true,
        'offers' => true,
        'user_availabilities' => true,
    ];

    /**
     * Fields that are excluded from JSON versions of the entity.
     *
     * @var array<string>
     */
    protected array $_hidden = [
        'password',
    ];

    /**
     * Getter virtuel pour le nom complet
     *
     * @return string
     */
    protected function _getFullName(): string
    {
        return $this->last_name . ' ' . $this->first_name;
    }
}
