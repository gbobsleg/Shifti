<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Exception de calendrier : fermeture (férié, pont) ou ouverture forcée.
 *
 * @property int $id
 * @property \Cake\I18n\FrozenDate $date
 * @property string $label
 * @property bool $is_open
 * @property string $source
 * @property \Cake\I18n\FrozenTime $created
 * @property \Cake\I18n\FrozenTime $modified
 */
class CalendarException extends Entity
{
    public const SOURCE_LEGAL = 'legal_fr';
    public const SOURCE_MANUAL = 'manual';

    protected array $_accessible = [
        'date' => true,
        'label' => true,
        'is_open' => true,
        'source' => true,
    ];
}
