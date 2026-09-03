<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\I18n\Date;
use Cake\ORM\Entity;
use DateTimeInterface;

/**
 * Skill Entity
 *
 * @property int $id
 * @property int $user_id
 * @property int $offer_id
 * @property \Cake\I18n\Date|null $validity_start
 * @property \Cake\I18n\Date|null $validity_end
 * @property \Cake\I18n\FrozenTime $created
 * @property \Cake\I18n\FrozenTime $modified
 *
 * @property \App\Model\Entity\User $user
 * @property \App\Model\Entity\Offer $offer
 */
class Skill extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'user_id' => true,
        'offer_id' => true,
        'validity_start' => true,
        'validity_end' => true,
        'created' => true,
        'modified' => true,
        'user' => true,
        'offer' => true,
    ];

    /**
     * Vérifie si cette compétence est valide pour une date donnée.
     *
     * CakePHP 5 : Cake\I18n\Date / FrozenDate n’implémentent pas DateTimeInterface.
     *
     * @param \DateTimeInterface|\Cake\I18n\Date $date La date à vérifier
     * @return bool True si la compétence est valide pour cette date
     */
    public function isValidForDate(DateTimeInterface|Date $date): bool
    {
        $dateOnly = $date->format('Y-m-d');
        
        // Si validity_start est défini, la date doit être >= validity_start
        if ($this->validity_start !== null) {
            $validityStart = $this->validity_start->format('Y-m-d');
            if ($dateOnly < $validityStart) {
                return false;
            }
        }
        
        // Si validity_end est défini, la date doit être <= validity_end
        if ($this->validity_end !== null) {
            $validityEnd = $this->validity_end->format('Y-m-d');
            if ($dateOnly > $validityEnd) {
                return false;
            }
        }
        
        return true;
    }
}

